#a vim: set ts=2: set expandtab:
DELIMITER ;;
/*  Pro Parallel (async) (part of the Swanhart Toolkit)
    Copyright 2015 Justin Swanhart

    async is free software: you can redistribute it and/or modify
    it under the terms of the Lesser GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    async is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FlexViews in the file COPYING, and the Lesser extension to
    the GPL (the LGPL) in COPYING.LESSER.
    If not, see <http://www.gnu.org/licenses/>.
*/
SET NAMES UTF8;

select 'Dropping and creating database' as message;

DROP DATABASE IF EXISTS async;

CREATE DATABASE IF NOT EXISTS async;

USE async;

CREATE DEFINER=root@localhost PROCEDURE async.check_reqs()
READS SQL DATA
SQL SECURITY DEFINER
BEGIN
  IF(VERSION() LIKE '5.1%' OR VERSION() LIKE '5.5%') THEN
		SIGNAL SQLSTATE '99999'
		SET MESSAGE_TEXT = 'MySQL version 5.6+ is needed to use this tool';
  END IF;

  IF(@@performance_schema != 1) THEN
    SIGNAL SQLSTATE '99999'   
    SET MESSAGE_TEXT = 'The performance schema must be enabled to use this tool';
  END IF;
/*
  SELECT COUNT(*)
		INTO @v_tmp
 		FROM performance_schema.threads 
   WHERE PROCESSLIST_USER=CURRENT_USER();

	IF (@v_tmp = 0) THEN
    SET @errmsg := CONCAT('The performance_schema.setup_actors table is not recording threads for the ', CURRENT_USER(), ' user');
    SIGNAL SQLSTATE '99999'   
    SET MESSAGE_TEXT = @errmsg;
  END IF;
*/
END;;

CALL check_reqs();

select 'Creating setup procedure' as message;

DROP PROCEDURE IF EXISTS setup;;

CREATE DEFINER=root@localhost PROCEDURE async.setup()
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
	CALL check_reqs();

  -- LIFO queue of queries
  CREATE TABLE async.q (
    q_id bigint auto_increment primary key,
    sql_text longtext not null,
    created_on timestamp,
    started_on datetime default null,
    completed_on datetime default null,
    parent bigint default null, 
    completed boolean default FALSE,
    state enum ('CHECKING','COMPLETED','WAITING','RUNNING','ERROR') NOT NULL DEFAULT 'WAITING',
    errno VARCHAR(10) DEFAULT NULL,
    errmsg TEXT DEFAULT NULL
  ) ;
  
  -- list of running worker threads
  -- note this is a slightly unusual innodb table:
  -- the auto_inc is not the PK
  CREATE TABLE threads (
    worker_id bigint auto_increment,
    thread_id bigint not null,
    start_time timestamp,
    exec_count bigint not null default 0,
    thread_num integer,
    KEY(worker_id),
    PRIMARY KEY(start_time, thread_id)
  );

  CREATE TABLE settings(
    variable varchar(64) primary key, 
    value varchar(64) DEFAULT 'thread_count'
  ) CHARSET=UTF8 COLLATE=UTF8_GENERAL_CI engine = InnoDB;
  
  INSERT INTO async.settings VALUES ('thread_count', '8');
      
END;;

CREATE DEFINER=root@localhost PROCEDURE async.count_running_threads(OUT v_count BIGINT)
READS SQL DATA
SQL SECURITY DEFINER
BEGIN
  DECLARE v_version TEXT DEFAULT '5.6';
  DECLARE v_has_ps BOOLEAN DEFAULT FALSE;
  DECLARE v_uptime BIGINT DEFAULT 0;
  DECLARE v_tmp BIGINT DEFAULT 0;

  CALL check_reqs();

  -- remove threads from table that are expired
  START TRANSACTION;
  SELECT variable_value
    INTO v_uptime
    FROM performance_schema.global_status
   WHERE variable_name='uptime';
  
  DELETE 
    FROM threads
   WHERE start_time <= SYSDATE()-v_uptime;

  COMMIT;
  -- end cleanup
  
  SELECT count(*)
    INTO v_count
    FROM threads
    JOIN performance_schema.threads 
   USING (thread_id);

END;;

DROP PROCEDURE IF EXISTS async.run_worker;;

CREATE DEFINER=root@localhost PROCEDURE async.worker()
MODIFIES SQL DATA
SQL SECURITY DEFINER
worker:BEGIN
  DECLARE v_thread_count BIGINT DEFAULT 8;
  DECLARE v_running_threads BIGINT DEFAULT 0;
  DECLARE v_next_thread BIGINT DEFAULT 1;
  DECLARE v_got_lock BOOLEAN DEFAULT FALSE;
  DECLARE v_q_id BIGINT DEFAULT 0;
  DECLARE v_sql_text LONGTEXT DEFAULT NULL;

  -- FIXME: 
  -- These control the backoff in the loop waiting
  -- for queries to be placed in the q table.  You
  -- can change the values here but they should be
  -- sufficient for most setups. I'll convert them
  -- to configuration variables later.
  DECLARE v_wait FLOAT DEFAULT 0;
  DECLARE v_min_wait FLOAT DEFAULT 0;
  DECLARE v_inc_wait FLOAT DEFAULT 0.01;
  DECLARE v_max_wait FLOAT DEFAULT 0.25;

  SELECT `value`
    INTO v_thread_count
    FROM async.settings
   WHERE variable = 'thread_count';

  IF(v_thread_count IS NULL) THEN
    SIGNAL SQLSTATE '99999'
       SET MESSAGE_TEXT = 'assertion: missing thread_count variable';
  END IF;

  -- v_running_threads is an OUT param
  CALL async.count_running_threads(v_running_threads);

  -- leave procedure if enough threads are already running
  IF(v_running_threads >= v_thread_count) THEN
    LEAVE  worker;
  END IF;

	SET v_next_thread := v_running_threads;
	
  start_thread:LOOP
    SET v_next_thread := v_next_thread + 1;
    IF(v_next_thread >= v_thread_count) THEN
			DO sleep(v_max_wait);  
      LEAVE worker;
    END IF;
  
    SELECT GET_LOCK(CONCAT('thread#', v_next_thread),0) INTO v_got_lock;  
    IF(v_got_lock IS NULL OR v_got_lock = 0) THEN
			ITERATE start_thread;
    END IF;

    IF (v_got_lock = 1) THEN
      -- if the old thread exited on error for some reason, clean it up
      DELETE 
        FROM threads 
       WHERE thread_num = v_next_thread;

      -- record details of the new thread
      INSERT into threads(thread_id, start_time, exec_count, thread_num)
      VALUES (connection_id(), sysdate(), 0, v_next_thread);

      COMMIT;
       
      LEAVE start_thread;
    END IF;

  END LOOP;

  -- already enough threads running so exit the stored routine
  IF(v_next_thread > v_thread_count) THEN
    LEAVE worker;
  END IF;

  -- The execution loop to grab queries from the queue and run them
  run_block:BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
      GET DIAGNOSTICS CONDITION 1
      @errno = RETURNED_SQLSTATE, @errmsg = MESSAGE_TEXT;
      SET v_wait := v_min_wait;
    END;
 
    run_loop:LOOP
      set @errno = NULL;
      set @errmsg = NULL;
      START TRANSACTION;

      -- get the next SQL to run.  This SQL makes the q
      -- table a LIFO queue because the last unexecuted
      -- statement is the next to be run.  This ensures
      -- that the time taken to lock the queue is small
      SELECT q_id,
             sql_text
        INTO v_q_id,
             v_sql_text
        FROM q
       WHERE completed = FALSE
         AND state = 'WAITING'
       ORDER BY q_id DESC
      LIMIT 1 
 			FOR UPDATE;

      -- Increase the wait if there was no SQL found to
      -- execute.  
      SET @errno := NULL;

      IF(v_q_id = 0 OR v_q_id IS NULL) THEN

        SET v_wait := v_wait + v_inc_wait;
        IF(v_wait > v_max_wait) THEN
          SET v_wait := v_max_wait;
        END IF;

        -- let go of the lock on the q!
        ROLLBACK; 

      ELSE 

        -- mark it as running
        UPDATE q 
           SET started_on = NOW(), 
                 state='RUNNING'
         WHERE q_id = v_q_id;

        -- unlock the record (don't block the q)
        COMMIT;

        START TRANSACTION;

        -- the output of the SELECT statement must go into a table
        -- other statements like INSERT or CALL can not return a 
        -- resultset
        IF(SUBSTR(TRIM(LOWER(v_sql_text)),1,6) = 'select') THEN
          SET @v_sql := CONCAT('CREATE TABLE async.rs_', v_q_id, ' ENGINE=MYISAM AS ', v_sql_text);
        END IF;

        PREPARE stmt FROM @v_sql;
        IF(@errno IS NULL) THEN
					DO GET_LOCK(CONCAT('AS#run_', v_q_id),0);
        	EXECUTE stmt;
					DEALLOCATE PREPARE stmt;
				END IF;
				DO RELEASE_LOCK(CONCAT('AS#run_', v_q_id));

        UPDATE q
           SET state = IF(@errno IS NULL, 'COMPLETED', 'ERROR'),
               errno = @errno,
               errmsg = @errmsg,
               completed = TRUE,
               completed_on = SYSDATE()
         WHERE q_id = v_q_id;

        COMMIT;

        -- reset the wait time to min because something
        -- was executed
        SET v_wait := v_min_wait;
      END IF;

			SET v_q_id := 0;

      -- wait a bit for the next SQL so that we aren't
      -- spamming MySQL with queries to execute an
      -- empty queue
      DO SLEEP(v_wait);

    END LOOP;  

  END;
  
END;;

CREATE DEFINER=root@localhost PROCEDURE async.check(IN v_q_id BIGINT)
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
	SELECT * from q where q_id = v_q_id;	
END;;

CREATE DEFINER=root@localhost PROCEDURE async.wait(IN v_q_id BIGINT)
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
	DECLARE v_errmsg TEXT;
  DECLARE v_errno TEXT;
  DECLARE v_status TEXT;
	-- this will block when the query is running
	SELECT state, errmsg, errno INTO v_status,v_errmsg, v_errno from q where q_id = v_q_id ;
	IF (v_status IS NULL) THEN
    SIGNAL SQLSTATE '99998'
       SET MESSAGE_TEXT='Invalid QUERY_NUMBER';
  END IF;
	IF(v_status = 'WAITING') THEN
		wait_loop:LOOP
			DO SLEEP(.05);
			SELECT state, errmsg, errno INTO v_status, v_errmsg, v_errno from q where q_id = v_q_id ;
			IF (v_status !='WAITING') THEN
				LEAVE wait_loop;
			END IF;
		END LOOP;
	END IF;

	IF(v_errno IS NOT NULL) THEN
    SIGNAL SQLSTATE '99990'
       SET MESSAGE_TEXT = 'CALL asynch.check(QUERY_NUMBER) to get the detailed error information';
  END IF;
	
  DO GET_LOCK(CONCAT('AS#run_', v_q_id),86400*7);
	DO RELEASE_LOCK(CONCAT('AS#run_', v_q_id));

  SET @v_sql := CONCAT('SELECT * from rs_', v_q_id);
  PREPARE stmt from @v_sql;
  EXECUTE stmt;
	DEALLOCATE PREPARE stmt;

	SET @v_sql := CONCAT('DROP TABLE rs_', v_q_id);
  PREPARE stmt from @v_sql;
  EXECUTE stmt;
	DEALLOCATE PREPARE stmt;

END;;

/*

CREATE DEFINER=root@localhost PROCEDURE async.setup()
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    DECLARE v_done BOOLEAN DEFAULT FALSE;
    DECLARE v_table VARCHAR(64); 
    DECLARE v_sql TEXT;
    DECLARE table_cur CURSOR 
    FOR 
    SELECT CONCAT('CREATE TABLE async.', TABLE_NAME, '( ', GROUP_CONCAT(CONCAT(COLUMN_NAME, ' ', IF(COLUMN_TYPE = 'timestamp','datetime',COLUMN_TYPE), IF(CHARACTER_SET_NAME IS NOT NULL,CONCAT(' CHARACTER SET ', CHARACTER_SET_NAME),''),if(COLLATION_NAME IS NOT NULL,CONCAT(' COLLATE ', COLLATION_NAME),'')) ORDER BY ORDINAL_POSITION SEPARATOR ',\n') , ',server_id int unsigned,\nts datetime(5),KEY(ts) ) CHARSET=UTF8 COLLATE=UTF8_GENERAL_CI PARTITION BY KEY(ts) PARTITIONS 7') as create_tbl,
           TABLE_NAME 
      FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA='performance_schema' 
     GROUP BY TABLE_NAME;

    DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET v_done=TRUE;

    SET group_concat_max_len := @@max_allowed_packet;

    SET v_done = FALSE;
    OPEN table_cur;
    tableLoop: LOOP

      FETCH table_cur
        INTO v_sql,
             v_table;
          
      IF v_done THEN
        CLOSE table_cur;
        LEAVE tableLoop;
      END IF;

      SET @v_sql := CONCAT('DROP TABLE IF EXISTS async.', v_table,'');
      PREPARE drop_stmt FROM @v_sql;
      EXECUTE drop_stmt;
      DEALLOCATE PREPARE drop_stmt;

      SET @v_sql := v_sql;
      -- print out the CREATE statements so the user knows what is happening
      SELECT @v_sql;
      PREPARE create_stmt FROM @v_sql;
      EXECUTE create_stmt;
      DEALLOCATE PREPARE create_stmt;

    END LOOP;

    -- These are async specific tables.  There are triggers defined on settings below.
    CREATE TABLE async.settings(variable varchar(64), key(variable), value varchar(64)) CHARSET=UTF8 COLLATE=UTF8_GENERAL_CI engine = InnoDB;
    INSERT INTO async.settings VALUES ('interval', '30');
    INSERT INTO async.settings VALUES ('retention_period', '1 WEEK');
    CREATE TABLE async.last_refresh(last_refreshed_at DATETIME(6) NOT NULL) engine=InnoDB CHARSET=UTF8 COLLATE=UTF8_GENERAL_CI;

END;;

DROP PROCEDURE IF EXISTS truncate_tables;;

CREATE DEFINER=root@localhost PROCEDURE async.truncate_tables()
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    DECLARE v_done BOOLEAN DEFAULT FALSE;
    DECLARE v_sql TEXT;

    DECLARE table_cur CURSOR FOR
    SELECT CONCAT('TRUNCATE TABLE async.', table_name) 
      FROM INFORMATION_SCHEMA.TABLES
     WHERE table_schema = 'async'
       AND table_name NOT LIKE 'psh%';

    DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET v_done=TRUE;

    SET v_done = FALSE;
    OPEN table_cur;
    tableLoop: LOOP

        FETCH table_cur
         INTO v_sql;

        IF v_done THEN
            CLOSE table_cur;
            LEAVE tableLoop;
        END IF; 

        SET @v_sql := v_sql;
        PREPARE truncate_stmt FROM @v_sql;
        EXECUTE truncate_stmt;
        DEALLOCATE PREPARE truncate_stmt;

    END LOOP;

END;;

DROP PROCEDURE IF EXISTS cleanup_history;;

CREATE DEFINER=root@localhost PROCEDURE async.cleanup_history(v_interval VARCHAR(64))
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    DECLARE v_done BOOLEAN DEFAULT FALSE;
    DECLARE v_sql TEXT;

    DECLARE table_cur CURSOR FOR
    SELECT CONCAT('DELETE FROM async.', table_name, ' WHERE ts < NOW() - INTERVAL ', v_interval) 
      FROM INFORMATION_SCHEMA.TABLES
     WHERE table_schema = 'async'
       AND table_name NOT LIKE 'psh%';

    DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET v_done=TRUE;

    SET v_done = FALSE;
    OPEN table_cur;
    tableLoop: LOOP

        FETCH table_cur
         INTO v_sql;

        IF v_done THEN
            CLOSE table_cur;
            LEAVE tableLoop;
        END IF; 

        SET @v_sql := v_sql;
        PREPARE truncate_stmt FROM @v_sql;
        EXECUTE truncate_stmt;
        DEALLOCATE PREPARE truncate_stmt;

    END LOOP;

END;;

DROP PROCEDURE IF EXISTS async.auto_cleanup_history;

CREATE DEFINER=root@localhost PROCEDURE async.auto_cleanup_history()
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    DECLARE v_retention_period VARCHAR(64);

    -- get the retention period
    SELECT value
      INTO v_retention_period
      FROM async.settings
     WHERE variable = 'retention_period';

    CALL async.cleanup_history(v_retention_period); 

END;;

-- Added to fix consistency with documentation.  
-- Fix issue #2
DROP PROCEDURE IF EXISTS async.set_collection_interval;
CREATE DEFINER=root@localhost PROCEDURE async.set_collection_interval(v_interval INT)
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
  CALL async.set_collect_interval(v_interval);
END;

DROP PROCEDURE IF EXISTS async.set_collect_interval;

CREATE DEFINER=root@localhost PROCEDURE async.set_collect_interval(v_interval INT)
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    START TRANSACTION;
    UPDATE async.settings SET value = v_interval WHERE variable = 'interval';
    SELECT 'Updated interval setting' as message;
    COMMIT;
END;;

DROP PROCEDURE IF EXISTS async.set_retention_period;

CREATE DEFINER=root@localhost PROCEDURE async.set_retention_period(v_retention_period VARCHAR(64))
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    START TRANSACTION;
    CALL async.test_retention_period(v_retention_period);
    UPDATE async.settings SET value = v_retention_period WHERE variable = 'retention_period';
    SELECT 'Updated retention period setting' as message;
    COMMIT;
END;;

DROP PROCEDURE IF EXISTS collect;;

CREATE DEFINER=root@localhost PROCEDURE async.collect()
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    DECLARE v_done BOOLEAN DEFAULT FALSE;
    DECLARE v_sql TEXT;
    DECLARE v_count INT;
    DECLARE v_created_table BOOLEAN DEFAULT FALSE;
    DECLARE v_i INTEGER DEFAULT 0;
    DECLARE v_col INTEGER DEFAULT 0;
    DECLARE v_table VARCHAR(64); 
    DECLARE v_collist TEXT;
    DECLARE v_max INT DEFAULT 0;

    DECLARE table_cur CURSOR FOR
    SELECT table_name,
           COUNT(*) cnt
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_schema = 'performance_schema'
     GROUP BY table_name
     ORDER BY count(*) DESC;

    DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET v_done=TRUE;

    SET group_concat_max_len := @@max_allowed_packet;

    -- moved from end of procedure to start of procedure to fix issue #3
    DELETE FROM async.last_refresh;
    INSERT INTO async.last_refresh VALUES (now());


    SELECT GET_LOCK('ps_snapshot_lock',0) INTO @have_lock;
    IF @have_lock = 1 THEN

        SET v_done = FALSE;
        OPEN table_cur;
        tableLoop: LOOP

            FETCH table_cur
             INTO v_table, 
                  v_count;
          
            IF v_done THEN
                CLOSE table_cur;
                LEAVE tableLoop;
            END IF;

            -- Create a temporary table to store the snapshot in, but only create it on first loop iteration
            IF NOT v_created_table THEN
                SET v_max := v_count; -- sort is descending so first table has the most columns
                SET v_sql = '';
                SET v_created_table = TRUE;
                SET v_col = 1;
          SET v_i := v_count;
                WHILE(v_i >= 1) DO
                    IF v_sql != '' THEN
                        SET v_sql := CONCAT(v_sql, ',\n');
                    END IF;

                    SET v_sql = CONCAT(v_sql,'col',v_col,' TEXT');
                    SET v_i := v_i - 1; 
                    SET v_col := v_col + 1;
                END WHILE;

                SET v_sql = CONCAT('CREATE TEMPORARY TABLE async.snapshot(table_name varchar(64), server_id INT UNSIGNED, ts DATETIME(6), KEY(table_name),',v_sql,')');

                SET @v_sql := v_sql;
                PREPARE create_stmt FROM @v_sql;
                EXECUTE create_stmt;
                DEALLOCATE PREPARE create_stmt;
                SET v_sql = '';
            END IF;

            IF v_sql != '' THEN
                SET v_sql := CONCAT(v_sql, ' UNION ALL ');
            END IF;

            -- Get the list of columns from the table
            SELECT GROUP_CONCAT(column_name ORDER BY ORDINAL_POSITION SEPARATOR ', ')
              INTO v_collist
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE table_schema = 'performance_schema'
               AND table_name = v_table
             GROUP BY table_name;

            -- PAD the SELECT with NULL values so that the column count is right for insertion into the temp table
            IF v_count < v_max THEN
                SET v_collist := CONCAT(v_collist, REPEAT(",NULL", v_max - v_count));
            END IF;

            SET v_sql := CONCAT(v_sql, '(SELECT \'',v_table,'\',@@server_id,NOW(6),', v_collist,' FROM performance_schema.', v_table, ')');

        END LOOP;

        -- Get the data into the temporary snapshot table
        SET @v_sql := CONCAT('INSERT INTO async.snapshot\n', v_sql);
        PREPARE insert_stmt FROM @v_sql;
        EXECUTE insert_stmt;
        DEALLOCATE PREPARE insert_stmt;

        -- Need to re-open the cursor and take data from snapshot table into async tables 
        SET v_done = FALSE;
        OPEN table_cur;
        tableLoop2: LOOP

            FETCH table_cur
              INTO v_table, 
                   v_count;
          
            IF v_done THEN
                CLOSE table_cur;
                LEAVE tableLoop2;
            END IF;

            SET v_i := 1;
            SET v_sql = '';
            WHILE(v_i <= v_count) DO
                IF v_sql != '' THEN
                    SET v_sql := CONCAT(v_sql, ', ');  
                END IF;
                SET v_sql := CONCAT(v_sql, 'col', v_i);
                SET v_i := v_i + 1;
            END WHILE;

            SET @v_sql = CONCAT('INSERT INTO async.', v_table, ' SELECT ', v_sql, ',server_id, ts FROM async.snapshot where table_name = \'', v_table, '\'');
            PREPARE insert_stmt FROM @v_sql;
            EXECUTE insert_stmt;
            DEALLOCATE PREPARE insert_stmt;

        END LOOP;

        DROP TABLE async.snapshot;

        CALL async.auto_cleanup_history();

    END IF;

END;;

DROP PROCEDURE IF EXISTS async.collect_at_interval;

CREATE DEFINER=root@localhost PROCEDURE async.collect_at_interval()
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    DECLARE v_last_refresh DATETIME DEFAULT NULL;
    DECLARE v_seconds INT DEFAULT 0; 
    DECLARE v_refresh_interval INT DEFAULT 30;

    -- get the refresh interval
    SELECT value
      INTO v_refresh_interval
      FROM async.settings
     WHERE variable = 'interval';

    -- Set the last refresh the right amount of time in the past if the data has never been refreshed.
    -- Otherwise use the actual last refresh time
    SELECT IF(max(last_refreshed_at) IS NULL, NOW() - INTERVAL v_refresh_interval SECOND,max(last_refreshed_at))  
      INTO v_last_refresh  
      FROM async.last_refresh ;

    -- Figure out how long ago (in seconds) the data was collected
    SET v_seconds = TO_SECONDS(NOW()) - TO_SECONDS(v_last_refresh);

    IF v_seconds >= v_refresh_interval THEN
       CALL async.collect(); 
    END IF;

END;;

DROP PROCEDURE IF EXISTS async.test_interval;;

CREATE DEFINER=root@localhost PROCEDURE async.test_retention_period(v_interval VARCHAR(64))
MODIFIES SQL DATA
SQL SECURITY DEFINER
BEGIN
    DECLARE v_bad BOOLEAN DEFAULT FALSE;
    DECLARE CONTINUE HANDLER FOR SQLSTATE '42000' SET v_bad=TRUE;
    SET @v_sql = CONCAT('SELECT NOW() - INTERVAL ', v_interval, ' INTO @discard FROM DUAL');
    PREPARE test_stmt FROM @v_sql;
    IF v_bad THEN
        SIGNAL SQLSTATE '99999'   
        SET MESSAGE_TEXT = 'Invalid retention period.  Should be 1 DAY, 1 WEEK, 7200 SECOND, etc';
    END IF;
    EXECUTE test_stmt;
    DEALLOCATE PREPARE test_stmt;
END;;


DROP EVENT IF EXISTS async.snapshot_performance_schema;;

CREATE DEFINER=root@localhost EVENT async.snapshot_performance_schema
ON SCHEDULE
EVERY 1 SECOND
ON COMPLETION PRESERVE
ENABLE
COMMENT 'Collect global performance_schema information'
DO
CALL async.collect_at_interval()
;;
*/

SELECT 'Creating async tables' as message;;
call async.setup();;

 
-- The triggers can't be created in stored routines, so they 
-- have to be created here.  They aren't vital to behavior so
-- it is not so bad if they go missing
CREATE TRIGGER trg_before_delete before DELETE ON async.settings
FOR EACH ROW 
BEGIN  
    SIGNAL SQLSTATE '99999'   
    SET MESSAGE_TEXT = 'You may not delete rows in this table';
END;;

CREATE TRIGGER trg_before_insert before INSERT ON async.settings
FOR EACH ROW 
BEGIN  
    SIGNAL SQLSTATE '99999'   
    SET MESSAGE_TEXT = 'You may not insert rows in this table';
END;;

CREATE TRIGGER trg_before_update before UPDATE ON async.settings
FOR EACH ROW 
BEGIN  
    IF new.variable != 'thread_count' THEN 
        SIGNAL SQLSTATE '99999'   
        SET MESSAGE_TEXT = 'Only the thread_count variable is supported at this time';
    END IF;
    IF new.variable = 'thread_count' AND CAST(new.value AS SIGNED) < 1 THEN 
        SIGNAL SQLSTATE '99999'   
        SET MESSAGE_TEXT = 'Thread count must be greater than or equal to 1';
    END IF;
END;;

SELECT 'Installation complete' as message;;

SELECT IF(@@event_scheduler=1,'The event scheduler is enabled.  The default of 8 running background threads of execution is currently being used.  Execute CALL async.set_concurrency(X) to set the number of threads manually.',
                              'You must enable the event scheduler ( SET GLOBAL event_scheduler=1 ) to enable background parallel execution threads.') 
as message;;


DELIMITER ;

