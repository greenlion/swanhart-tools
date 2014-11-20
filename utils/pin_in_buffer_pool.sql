CREATE DATABASE IF NOT EXISTS `percona`;

use percona;

CREATE TABLE IF NOT EXISTS `pin_buffer_pool_config` (
  `schema_name` varchar(50) DEFAULT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `index_name` varchar(255) DEFAULT 'PRIMARY' comment 'null for all indexes',
  `where_clause` varchar(255) DEFAULT NULL comment 'do not include the WHERE keyword'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

delimiter ;;

DROP PROCEDURE IF EXISTS pin_buffer_pool;

-- load all the columns for the given index
-- into the buffer pool
CREATE PROCEDURE pin_buffer_pool()
BEGIN
DECLARE v_done boolean default false;
DECLARE v_stmt TEXT default null;
DECLARE v_got_lock tinyint default 0;
DECLARE v_cursor CURSOR FOR
SELECT CONCAT('SELECT COUNT(CONCAT(', 
              GROUP_CONCAT(column_name ORDER BY seq_in_index), ')) INTO @discard 
              FROM `', s.table_schema, '`.`', s.table_name,
              '` FORCE INDEX(`', s.index_name, '`)', 
              IF(where_clause IS NOT NULL, CONCAT(' WHERE ', where_clause), '')
         ) AS stmt 
   FROM information_schema.statistics s 
   JOIN percona.pin_buffer_pool_config pbpc
     ON pbpc.table_name = s.table_name 
    AND pbpc.schema_name = s.table_schema 
    -- when the index_name is null, it means warm all indexes for the table
    AND s.index_name = ifnull(pbpc.index_name, s.index_name) 
  GROUP BY s.index_name;

  DECLARE CONTINUE HANDLER FOR
  SQLSTATE '02000'
    SET v_done = TRUE;

  -- DON'T HOLD A LONG TRANSACTION, START A NEW SNAPSHOT FOR EACH READ
  SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

  -- abort if the last pin is still running
  SELECT GET_LOCK('BUFFER_POOL_PIN', 0) INTO v_got_lock;
  IF v_got_lock = 1 THEN

    OPEN v_cursor;
    cursorLoop: LOOP
      FETCH v_cursor INTO v_stmt;

      IF v_done THEN
        CLOSE v_cursor;
        LEAVE cursorLoop;
      END IF;

      set @v_stmt = v_stmt;
      prepare v_bp_pin from @v_stmt;
      execute v_bp_pin;
      deallocate prepare v_bp_pin;
      
    END LOOP;

    SELECT RELEASE_LOCK('BUFFER_POOL_PIN') INTO @discard;
 
  END IF;

END;;

DROP PROCEDURE IF EXISTS pin_buffer_pool_loop;;

CREATE PROCEDURE pin_buffer_pool_loop()
BEGIN
  -- This procedure can be scheduled to start
  -- every second with no harm done.  It will
  -- simply exit if more than one copy tries
  -- to run.  This means that an event can
  -- be used to ensure the warming function is
  -- always on and that it is looping faster
  -- than innodb_old_blocks_time (default 1000 in 5.6)
  SELECT GET_LOCK('BUFFER_POOL_PIN_LOOP_LOCK', 0) INTO @got_lock;
  IF @got_lock = 1 THEN
    LOOP
      CALL pin_buffer_pool();
      select sleep(.25) into @discard;
    END LOOP;
    SELECT RELEASE_LOCK('BUFFER_POOL_PIN_LOOP_LOCK') INTO @discard;
  END IF;
END;;

delimiter ;

