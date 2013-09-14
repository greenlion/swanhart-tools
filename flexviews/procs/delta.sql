-- This file contains all the functions and procedures
-- related to inserting deltas into the mv delta table
DELIMITER ;;
/*  Flexviews for MySQL 
    Copyright 2008 Justin Swanhart

    FlexViews is free software: you can redistribute it and/or modify
    it under the terms of the Lesser GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    FlexViews is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FlexViews in the file COPYING, and the Lesser extension to
    the GPL (the LGPL) in COPYING.LESSER.
    If not, see <http://www.gnu.org/licenses/>.
*/


DROP PROCEDURE IF EXISTS flexviews.apply_delta;;
CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.apply_delta(
IN v_mview_id INT,
IN v_until_uow_id BIGINT
)
BEGIN
DECLARE v_incremental_hwm BIGINT; -- propagation high water mark (we can't refresh past here)
DECLARE v_refreshed_to_uow_id BIGINT; -- uow_id that the mview has been refreshed to
DECLARE v_sql TEXT;
DECLARE v_mview_schema TEXT;
DECLARE v_mview_name TEXT;
DECLARE v_delta_table TEXT;
DECLARE v_mview_refresh_type TEXT;
DECLARE v_cur_row INT;
DECLARE v_row_count INT;
DECLARE v_cnt_column TEXT;
DECLARE v_only_agg BOOLEAN DEFAULT FALSE;
DECLARE v_mview_fqn TEXT;

SELECT mview_name,
       mview_schema,
       CONCAT(mview_schema, '.', mview_name, '_delta'), 
       incremental_hwm,
       refreshed_to_uow_id,  
       mview_refresh_type,
       CONCAT(mview_schema, '.', mview_name) 
  INTO v_mview_name, 
       v_mview_schema,
       v_delta_table,
       v_incremental_hwm,
       v_refreshed_to_uow_id,
       v_mview_refresh_type,
       v_mview_fqn
  FROM flexviews.mview
 WHERE mview_id = v_mview_id;

IF v_mview_refresh_type != 'INCREMENTAL' THEN
  CALL flexviews.signal('NOT AN INCREMENTAL REFRESH MV');
END IF;

-- Refresh up until NOW() unless otherwise specified 
IF v_until_uow_id IS NULL OR v_until_uow_id = 0 THEN
  SET v_until_uow_id = flexviews.uow_from_dtime(now());
END IF;

IF NOT flexviews.has_aggregates(v_mview_id) THEN
  DROP TEMPORARY TABLE IF EXISTS apply_gsn;
  CREATE TEMPORARY TABLE apply_gsn(uow_id bigint, dml_type tinyint signed, gsn bigint, primary key(uow_id,gsn)) engine=innodb;
  set v_sql=concat('insert into apply_gsn select distinct uow_id, dml_type, fv$gsn gsn from ',
                    v_delta_table,' where uow_id > ', v_refreshed_to_uow_id, ' and uow_id <= ', v_until_uow_id);
  set @v_sql = v_sql;
  PREPARE insert_stmt from @v_sql;
  EXECUTE insert_stmt; 
  DEALLOCATE PREPARE insert_stmt;

  BEGIN
    DECLARE v_dml_type tinyint;
    DECLARE v_gsn bigint;
    DECLARE v_uow_id bigint;
    DECLARE v_done BOOLEAN DEFAULT FALSE;
    DECLARE gsn_cur cursor for select * from apply_gsn order by uow_id, gsn;
    DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET v_done=TRUE;
    SET v_done = false;
    OPEN gsn_cur;
    gsnLoop: LOOP

      FETCH gsn_cur
       INTO v_uow_id, 
            v_dml_type,
            v_gsn;
          
      IF v_done THEN
        CLOSE gsn_cur;
        LEAVE gsnLoop;
      END IF;

      IF v_dml_type = 1 THEN
        set v_sql = CONCAT('INSERT INTO ', v_mview_fqn, 
                           ' SELECT NULL,', flexviews.get_delta_aliases(v_mview_id,'',FALSE), 
                           '   FROM ', v_delta_table, 
                           '  WHERE uow_id=', v_uow_id,
                           '    AND fv$gsn=', v_gsn); 
      ELSE
        SET v_sql = CONCAT(' DELETE ', v_mview_fqn, '.*',
                           '   FROM ', v_mview_fqn,
                           '   NATURAL JOIN ', v_delta_table,
                           '   WHERE fv$gsn = ', v_gsn,
                           '     AND uow_id = ', v_uow_id);
      END IF;

      SET @v_sql = v_sql;
      PREPARE stmt from @v_sql;
      EXECUTE stmt; 
      DEALLOCATE PREPARE stmt;
    END LOOP;

  END;
  DROP TEMPORARY TABLE IF EXISTS apply_gsn;

ELSE -- this mview has aggregates
  SELECT mview_alias
    INTO v_cnt_column
    FROM flexviews.mview_expression
   WHERE mview_id = v_mview_id
     AND mview_expr_type = 'COUNT'
     AND mview_expression = '*'
   LIMIT 1; -- use limit just in case there is more than one count(*), though why that would be is beyond me...

  IF v_cnt_column IS NULL then 
    call flexviews.signal('NO COUNT(*) found on materialized view');
  END IF;


  IF flexviews.get_delta_aliases(v_mview_id, '', TRUE) = "" THEN

    SET v_sql = CONCAT('DELETE ', v_delta_table, '.*, ', v_mview_schema, '.', v_mview_name, '.* ',
                       '  FROM ',v_delta_table,
                       ' NATURAL JOIN ', v_mview_schema, '.', v_mview_name,
                       ' WHERE ', v_mview_name, '.', v_cnt_column, ' + ', v_delta_table, '.',v_cnt_column, '=0');
  	CALL flexviews.rlog(v_sql);
  	SET @v_sql = v_sql;
  	PREPARE delete_stmt FROM @v_sql;
  	EXECUTE delete_stmt;
  	DEALLOCATE PREPARE delete_stmt;

  END IF;

  IF flexviews.has_aggregates(v_mview_id) THEN
    SELECT COUNT(*) = 0 
      INTO v_only_agg
      from flexviews.mview_expression
     where mview_id = v_mview_id
       and (mview_expr_type = 'GROUP' or mview_expr_type = 'COLUMN');
   
  END IF;

  IF v_only_agg THEN
    SET v_sql = 'SELECT 1,';
  ELSE
    SET v_sql = 'SELECT ';
  END IF;


  SET v_sql = CONCAT(v_sql, get_delta_aliases(v_mview_id, '', FALSE), ' ',
                     '  FROM ', v_delta_table, ' as x_select_',
                     ' WHERE uow_id > ', v_refreshed_to_uow_id,
                     '   AND uow_id <= ', v_until_uow_id, 
                     '   AND dml_type IS NOT NULL ');

  SET v_sql = get_insert(v_mview_id, v_sql);

  CALL flexviews.rlog(v_sql);
  SET @v_sql = v_sql;
  PREPARE insert_stmt FROM @v_sql;
  EXECUTE insert_stmt;
  DEALLOCATE PREPARE insert_stmt;

END IF;

  DROP TEMPORARY TABLE IF EXISTS deletes;
  -- END PROCESSING DELETES --

IF v_cnt_column is not null THEN

  IF flexviews.get_delta_aliases(v_mview_id, '', TRUE) != '' THEN

     SET v_sql = CONCAT('DELETE ', v_mview_schema, '.', v_mview_name, '.*',
                         '  FROM ', v_mview_schema, '.', v_mview_name,
                         '  JOIN ( SELECT ', flexviews.get_delta_aliases(v_mview_id, 'mview', TRUE),
                                          ',SUM(', v_cnt_column,') _cnt ',
                                  '  FROM ', v_mview_schema, '.', v_mview_name, ' as mview ',
                                  '  NATURAL JOIN ( select distinct ', flexviews.get_delta_aliases(v_mview_id, '',true), 
                                            ' from ', v_mview_schema, '.', v_mview_name, '_delta ', 
                                  '       ) x_select ',
                         ' GROUP BY ', flexviews.get_delta_aliases(v_mview_id, 'mview', TRUE), 
                         ' HAVING _cnt <= 0) delta ',
                         ' USING ( ', flexviews.get_delta_aliases(v_mview_id, '', true), ')',
                         ' WHERE ', flexviews.get_delta_join(v_mview_id)
    );

    set @Z := v_sql;
    CALL flexviews.rlog(v_sql);
    SET @v_sql = v_sql;
    PREPARE delete_stmt FROM @v_sql;
    EXECUTE delete_stmt;
    DEALLOCATE PREPARE delete_stmt;
  END IF;

  set v_sql := CONCAT('DELETE from ', v_mview_schema, '.', v_mview_name, ' WHERE ', v_cnt_column, ' = 0');
  set @v_sql = v_sql;
  prepare delete_stmt from @v_sql;
  execute delete_stmt;
  deallocate prepare delete_stmt;

END IF;

/*
  -- CLEAN UP THE DELTA LOG
  SET v_sql = CONCAT('DELETE FROM ', v_delta_table, ' WHERE dml_type is null OR  uow_id <= ', v_until_uow_id);
  SET @v_sql = v_sql;
  PREPARE delete_stmt FROM @v_sql;
  EXECUTE delete_stmt;
  DEALLOCATE PREPARE delete_stmt;
*/

  
  -- Fix aggregate tables without group by attributes when they go to zero rows
  SET v_sql = CONCAT('SELECT COUNT(*) INTO @mv_count FROM (select 1 from  ', v_mview_schema, '.', v_mview_name, ' LIMIT 1) x_select ');
  SET @v_sql = v_sql;
  PREPARE count_stmt FROM @v_sql;
  EXECUTE count_stmt;
  DEALLOCATE PREPARE count_stmt;

  SELECT count(*)
  INTO @has_group
  FROM flexviews.mview_expression
 WHERE mview_id = v_mview_id
   AND mview_expr_type in ('GROUP');

  IF @has_group = 0 AND @mv_count = 0 AND IFNULL(v_cnt_column,'') != '' THEN
    SET v_sql = CONCAT('INSERT INTO ', v_mview_schema, '.', v_mview_name, ' (', v_cnt_column, ') VALUES (0)');
    SET @v_sql = v_sql;
    PREPARE fixup_count FROM @v_sql;
    EXECUTE fixup_count;
    DEALLOCATE PREPARE fixup_count;
  END IF;



  -- UPDATE TABLE TO INDICATE WE HAVE REFRESHED--
  UPDATE flexviews.mview
     SET refreshed_to_uow_id = IF(v_until_uow_id > incremental_hwm, incremental_hwm, v_until_uow_id)
   WHERE mview_id = v_mview_id;

END;;

DROP FUNCTION IF EXISTS flexviews.get_delta_where;;
CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_delta_where(
v_mview_id INT,
v_depth TINYINT
) RETURNS TEXT
READS SQL DATA
BEGIN
DECLARE v_where_clause TEXT DEFAULT '';

DECLARE v_uow_id_start BIGINT;
DECLARE v_uow_id_end BIGINT;
DECLARE v_mview_table_alias TEXT;
DECLARE v_mview_table_id INT;

DECLARE v_mview_expression TEXT;

DECLARE v_done BOOLEAN DEFAULT FALSE;

-- this is a cursor to get the uow_id range expressions
DECLARE cur_tables CURSOR
FOR
SELECT uow_id_start,
       uow_id_end,
       mview_table_alias
  FROM flexviews.table_list
  JOIN flexviews.mview_table USING (mview_table_id)
 WHERE depth = v_depth
   AND uow_id_end IS NOT NULL;

DECLARE CONTINUE HANDLER FOR SQLSTATE '02000'
SET v_done=TRUE;

SET v_where_clause = flexviews.get_where(v_mview_id);

SET v_done = false;
OPEN cur_tables;
tableLoop: LOOP
  FETCH cur_tables
   INTO v_uow_id_start,
        v_uow_id_end,
        v_mview_table_alias;

  IF v_done THEN
    CLOSE cur_tables;
    LEAVE tableLoop;
  END IF;

  IF v_where_clause != '' THEN
    SET v_where_clause = CONCAT(v_where_clause, ' AND ');
  END IF;  

  SET v_where_clause = CONCAT(v_where_clause, v_mview_table_alias,'.uow_id >', v_uow_id_start,
                              ' AND ', v_mview_table_alias, '.uow_id <=', v_uow_id_end); 
END LOOP;

RETURN v_where_clause;

END;;

DROP PROCEDURE IF EXISTS flexviews.execute_refresh_step;;
CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.execute_refresh_step (
IN v_mview_id INT, 
IN v_depth INT, 
IN v_method TINYINT,
IN v_mview_table_id INT,
OUT v_uow_id BIGINT
)
BEGIN
DECLARE v_select_clause TEXT;
DECLARE v_from_clause TEXT;
DECLARE v_where_clause TEXT;
DECLARE v_group_clause TEXT;

DECLARE v_sql TEXT default '';
DECLARE v_mview_table_alias TEXT;
DECLARE v_delta_table TEXT;

SELECT CONCAT(mview_schema, '.', mview_name, '_delta')
  INTO v_delta_table
  FROM flexviews.mview
 WHERE mview_id = v_mview_id;

SELECT mview_table_alias
  INTO v_mview_table_alias
  FROM flexviews.mview_table
 WHERE mview_table_id = v_mview_table_id;

SET v_where_clause = CONCAT('WHERE ', flexviews.get_delta_where(v_mview_id, v_depth));
SET @delta_where = v_where_clause;


SET v_group_clause = flexviews.get_delta_groupby(v_mview_id);
IF v_group_clause != '' THEN
  SET v_group_clause = CONCAT(' GROUP BY ', v_group_clause);
END IF;

SET v_select_clause = flexviews.get_delta_select(v_mview_id, v_method, v_mview_table_id);

SET @delta_select = v_select_clause;
SET @least_gsn = flexviews.get_delta_least_gsn(v_depth);
SET v_select_clause = CONCAT('SELECT (', v_mview_table_alias, '.dml_type * ', v_method, ') as dml_type,', flexviews.get_delta_least_uowid(v_depth), ' as uow_id,', @least_gsn ,' as fv$gsn ', IF(v_select_clause != '', concat(',', v_select_clause), ''));


SET v_from_clause = flexviews.get_delta_from(v_depth);

SET v_sql = CONCAT(v_select_clause, '\n', v_from_clause, '\n', v_where_clause); 


IF v_group_clause != "" THEN
  SET v_sql = CONCAT('INSERT INTO ', v_delta_table, ' ', v_sql, ' AND (', v_mview_table_alias, '.dml_type * ', v_method, ' IS NOT NULL) ', v_group_clause);
ELSE

  SET v_sql = CONCAT('INSERT INTO ', v_delta_table, ' ', v_sql);

END IF;

 

CALL flexviews.rlog(v_sql);

-- Execute the SQL, returning the UOW_ID into v_uow_id, which is an OUT parameter
call flexviews.uow_execute(v_sql, v_uow_id);

END;;

DROP PROCEDURE IF EXISTS flexviews.execute_refresh;;
CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.execute_refresh(
IN v_mview_id INT,
IN v_start_uow_id BIGINT, 
IN v_until_uow_id BIGINT,
IN v_method TINYINT
)
BEGIN

DECLARE v_table_count INT;
DECLARE v_base_table_num INT DEFAULT 0;
DECLARE v_consider_base_table_num INT DEFAULT 0;
DECLARE v_mview_table_id INT;

DECLARE v_uow_start BIGINT;
DECLARE v_uow_end BIGINT;
DECLARE v_exec_uow_id BIGINT;

DECLARE v_recurse TINYINT;

-- if we recurse, this will be the next depth 
DECLARE v_next_depth TINYINT;

-- when we recurse, v_start_uow_id IS NULL
IF v_start_uow_id IS NULL THEN
  SET @__compute_delta_depth = @__compute_delta_depth + 1;

  SELECT COUNT(*)
    INTO v_table_count
    FROM flexviews.mview_table
   WHERE mview_id = v_mview_id;

  SET @__ = 0;

ELSE
  -- else set up the data structures we need if this is first invocation

  DROP TEMPORARY TABLE IF EXISTS flexviews.table_list;
  DROP TEMPORARY TABLE IF EXISTS flexviews.table_list_old;

  SET @__ = 0; -- counter for "rownum"
  SET @__compute_delta_depth = 1;

 
  CREATE TEMPORARY TABLE flexviews.table_list (
    mview_table_id INT,
             depth TINYINT,
      uow_id_start BIGINT,
        uow_id_end BIGINT DEFAULT NULL,
               idx TINYINT,  
    KEY USING BTREE(depth, idx) 
  ) ENGINE=MEMORY;


  CREATE TEMPORARY TABLE flexviews.table_list_old 
    LIKE flexviews.table_list;
  
  INSERT INTO flexviews.table_list
  SELECT mview_table_id, 
         @__compute_delta_depth,
         v_start_uow_id, 
         NULL,
         @__:=@__+1 -- simulate oracle's rownum
    FROM flexviews.mview_table   
   WHERE mview_id = v_mview_id
   ORDER BY mview_join_condition, mview_join_order;   
/*
  example:
  37, 1, 15, NULL, 1
  38, 2, 15, NULL, 2
  It contains one row per table involved in the materialized view (v_mview_id).
  Depth is required because compute_delta is recursive and populates values 
  into the table.  The procedure always inserts into flexviews.table_list with 
  depth = current_depth + 1 and updates table_list with current_depth.
  The current_depth is tracked in a session variable called @__compute_delta_depth.  
  If a select is from a log table (delta table) then uow_id_end is NOT NULL.

*/
  SET v_table_count = @__;
  SET @__ = 0;
END IF;  -- setup complete

-- We update the values in table_list.
-- We need to restore the originals so save them.

DELETE FROM flexviews.table_list_old WHERE depth >= @__compute_delta_depth;
INSERT INTO flexviews.table_list_old 
SELECT * 
  FROM flexviews.table_list 
 WHERE depth = @__compute_delta_depth; 

-- execute one query for v_cur_base_table_num = 1 to v_table_count
-- recurse to execute compensation queries for each base table query
considerRelationLoop: LOOP
  SET v_consider_base_table_num = v_consider_base_table_num + 1;

  IF v_consider_base_table_num > v_table_count THEN
    LEAVE considerRelationLoop;
  END IF;

  IF v_consider_base_table_num > 1 THEN
    DELETE FROM flexviews.table_list where depth >= @__compute_delta_depth;
    INSERT INTO flexviews.table_list 
    SELECT * 
      FROM flexviews.table_list_old 
     WHERE depth = @__compute_delta_depth;
  END IF;

  -- only consider recursing into base tables (not DELTA tables)

  SELECT uow_id_start, 
         uow_id_end,
         mview_table_id
    INTO v_uow_start,
         v_uow_end,
         v_mview_table_id
    FROM flexviews.table_list
   WHERE depth = @__compute_delta_depth
     AND idx = v_consider_base_table_num;
 
  IF v_uow_end IS NULL THEN
    IF v_uow_start < v_until_uow_id THEN 
      SET v_base_table_num = 0;
      baseRelationLoop: LOOP
        SET v_base_table_num = v_base_table_num + 1;
        IF v_base_table_num > v_table_count THEN
          LEAVE baseRelationLoop;
        END IF;
        SELECT uow_id_start, 
               uow_id_end
          INTO v_uow_start, 
               v_uow_end 
          FROM flexviews.table_list
         WHERE depth = @__compute_delta_depth
           AND idx = v_base_table_num;

	IF v_uow_end IS NULL THEN
	  IF v_base_table_num < v_consider_base_table_num THEN
             	UPDATE flexviews.table_list
                   SET uow_id_start = v_uow_start,
                       uow_id_end = NULL
                 WHERE depth = @__compute_delta_depth
                   AND idx = v_base_table_num;

            
          ELSEIF v_base_table_num = v_consider_base_table_num THEN
 		UPDATE flexviews.table_list
                   SET uow_id_start = v_uow_start,
                       uow_id_end = v_until_uow_id
                 WHERE depth = @__compute_delta_depth
                   AND idx = v_base_table_num;


          ELSE
             	UPDATE flexviews.table_list
                   SET uow_id_start = v_until_uow_id,
                       uow_id_end = NULL
                 WHERE depth = @__compute_delta_depth
                   AND idx = v_base_table_num;

          END IF;
        END IF;
      END LOOP baseRelationLoop; 
      SET @EXEC=@EXEC + 1;
      
     
      -- execute the SQL statements necessary to populate the mview delta
      -- for this step of the refresh plan

      CALL flexviews.execute_refresh_step(v_mview_id, @__compute_delta_depth, v_method, v_mview_table_id,v_exec_uow_id);

      -- if any base tables remain in the SQL for the current depth
      SELECT IFNULL(COUNT(*),0) 
        INTO v_recurse
        FROM flexviews.table_list
       WHERE depth = @__compute_delta_depth
         AND uow_id_end IS NULL;
      
      IF v_recurse != 0  THEN
        CREATE TEMPORARY TABLE x as 
        (SELECT mview_table_id,
                depth+1, 
                uow_id_start, 
                uow_id_end, 
                idx
           FROM flexviews.table_list
          WHERE depth = @__compute_delta_depth);
        REPLACE INTO flexviews.table_list
        SELECT * FROM x;
        DROP TEMPORARY TABLE x;
        
        -- recurse to compensate, so multiply v_method * -1
        CALL flexviews.execute_refresh(v_mview_id, NULL, v_exec_uow_id, -v_method);
        
      END IF;
      
    END IF;
  ELSE 
    -- not a base table, don't consider
    ITERATE considerRelationLoop;
  END IF;

END LOOP considerRelationLoop;

SET @__compute_delta_depth = @__compute_delta_depth - 1;
IF @__compute_delta_depth IS NULL OR @__compute_delta_depth = 0 THEN
  DROP TEMPORARY TABLE IF EXISTS flexviews.table_list;
  DROP TEMPORARY TABLE IF EXISTS flexviews.table_list_old;

END IF;

UPDATE flexviews.mview 
   SET incremental_hwm = v_until_uow_id
 WHERE mview_id = v_mview_id;

END;;

DROP FUNCTION IF EXISTS `get_delta_from` ;;

CREATE DEFINER=flexviews@localhost FUNCTION get_delta_from (
  v_depth INT
) RETURNS TEXT CHARSET latin1
    READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_table_name TEXT;
DECLARE v_mview_table_alias TEXT;
DECLARE v_mview_table_schema TEXT;
DECLARE v_mview_join_condition TEXT;
DECLARE v_from_clause TEXT default NULL;  
DECLARE v_uow_id_start BIGINT;
DECLARE v_uow_id_end BIGINT;
DECLARE v_mvlog_name varchar(250);

DECLARE cur_from CURSOR 
FOR  
SELECT mview_table_name,
       mview_table_schema,
       mview_table_alias,
       mview_join_condition,
       uow_id_start, 
       uow_id_end,
       mvlog_name
  FROM flexviews.mview_table t
  JOIN flexviews.table_list USING (mview_table_id)
  JOIN flexviews.mvlogs logs on logs.table_name = mview_table_name
                            and logs.table_schema = mview_table_schema
 WHERE depth = v_depth
 ORDER BY idx;

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

SET v_from_clause = '';

OPEN cur_from;  
fromLoop: LOOP    
  FETCH cur_from 
   INTO v_mview_table_name,
        v_mview_table_schema,
        v_mview_table_alias,
        v_mview_join_condition,
        v_uow_id_start,
        v_uow_id_end,
        v_mvlog_name;
  
  IF v_done THEN      
    CLOSE cur_from;      
    LEAVE fromLoop;    
  END IF;    

  SET v_from_clause = CONCAT(v_from_clause, ' ',
                             IF(v_mview_join_condition IS NULL AND v_from_clause = '' , '', ' JOIN '), ' ',
                             IF(v_uow_id_end IS NOT NULL, flexviews.get_setting('mvlog_db'), v_mview_table_schema), 
                             '.', 
                             IF(v_uow_id_end IS NOT NULL, v_mvlog_name, v_mview_table_name), ' as ',
                             v_mview_table_alias, ' ',
                             IFNULL(v_mview_join_condition, '') );
END LOOP;

RETURN CONCAT('FROM', v_from_clause);
END ;;


DROP FUNCTION IF EXISTS `get_delta_least_uowid` ;;

CREATE DEFINER=flexviews@localhost FUNCTION get_delta_least_uowid (
  v_depth INT
) RETURNS TEXT CHARSET latin1
    READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_table_alias TEXT;
DECLARE v_list TEXT DEFAULT '';
DECLARE v_delta_cnt INT default 0;
DECLARE v_mview_id INT;
DECLARE cur_from CURSOR 
FOR  
SELECT mview_table_alias,
       mview_id
  FROM flexviews.mview_table t
  JOIN flexviews.table_list USING (mview_table_id)
 WHERE depth = v_depth
   AND uow_id_end IS NOT NULL;

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

OPEN cur_from;  
fromLoop: LOOP    
  FETCH cur_from 
   INTO v_mview_table_alias,
        v_mview_id;
  
  IF v_done THEN      
    CLOSE cur_from;      
    LEAVE fromLoop;    
  END IF;    

  IF v_list != '' THEN
    SET v_list = CONCAT(v_list, ',');
  END IF;

  SET v_list = CONCAT(v_list, v_mview_table_alias, '.uow_id');
  SET v_delta_cnt = v_delta_cnt + 1;

END LOOP;
IF v_delta_cnt > 1 THEN
  RETURN CONCAT('LEAST(', v_list,')');
ELSE
  RETURN v_list;
END IF;
END ;;

DROP FUNCTION IF EXISTS `get_delta_least_gsn` ;;

CREATE DEFINER=flexviews@localhost FUNCTION get_delta_least_gsn (
  v_depth INT
) RETURNS TEXT CHARSET latin1
    READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_table_alias TEXT;
DECLARE v_list TEXT DEFAULT '';
DECLARE v_delta_cnt INT default 0;
DECLARE v_mview_id INT;
DECLARE cur_from CURSOR 
FOR  
SELECT mview_table_alias,
       mview_id
  FROM flexviews.mview_table t
  JOIN flexviews.table_list USING (mview_table_id)
 WHERE depth = v_depth
   AND uow_id_end IS NOT NULL;

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

OPEN cur_from;  
fromLoop: LOOP    
  FETCH cur_from 
   INTO v_mview_table_alias,
        v_mview_id;
  
  IF v_done THEN      
    CLOSE cur_from;      
    LEAVE fromLoop;    
  END IF;    

  IF v_list != '' THEN
    SET v_list = CONCAT(v_list, ',');
  END IF;

  SET v_list = CONCAT(v_list, v_mview_table_alias, '.fv$gsn');
  SET v_delta_cnt = v_delta_cnt + 1;

END LOOP;
IF v_delta_cnt > 1 THEN
  RETURN CONCAT('LEAST(', v_list,')');
ELSE
  RETURN v_list;
END IF;
END ;;

DELIMITER ;;

DROP FUNCTION IF EXISTS `get_delta_aliases`;;

CREATE DEFINER=flexviews@localhost FUNCTION `get_delta_aliases`(  
v_mview_id INT, 
v_prefix TEXT, 
v_only_groupby BOOLEAN
)
RETURNS TEXT 
READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_expr_type TEXT;
DECLARE v_mview_expression TEXT;
DECLARE v_mview_alias TEXT;
DECLARE v_select_list TEXT default '';  
DECLARE cur_select CURSOR 
FOR  
SELECT IF(mview_expr_type='COLUMN' and v_only_groupby = true,'GROUP',mview_expr_type) ,
       mview_expression,
       mview_alias
  FROM flexviews.mview_expression m
 WHERE m.mview_id = v_mview_id
   AND m.mview_expr_type in ('AVG','COLUMN', 'GROUP','COUNT','SUM')
   AND NOT trim(mview_expr_type = 'COLUMN' and mview_expression = '*')
 ORDER BY mview_expr_order;  

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

IF v_prefix != '' AND v_prefix IS NOT NULL THEN
  SET v_prefix = CONCAT(v_prefix, '.');
END IF;

OPEN cur_select;  

selectLoop: LOOP    
  FETCH cur_select 
   INTO v_mview_expr_type,
        v_mview_expression,
        v_mview_alias;
  
  IF v_done THEN      
    CLOSE cur_select;      
    LEAVE selectLoop;    
  END IF;    
 
  IF v_only_groupby = TRUE AND v_mview_expr_type != 'GROUP' THEN
    ITERATE selectLoop;
  END IF;
 
  IF v_select_list != '' THEN      
    SET v_select_list = CONCAT(v_select_list, ', ');    
  END IF;    

  SET v_select_list = CONCAT(v_select_list, v_prefix, v_mview_alias);   

  IF v_mview_expr_type = 'AVG' THEN
    SET v_select_list = CONCAT(v_select_list,',', v_prefix, v_mview_alias, '_cnt');   
    SET v_select_list = CONCAT(v_select_list,',', v_prefix, v_mview_alias, '_sum');   
  END IF;

END LOOP;  
RETURN v_select_list;
END ;;

DROP FUNCTION IF EXISTS flexviews.get_delta_select;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_delta_select(  
v_mview_id INT,
v_method INT,
v_mview_table_id INT )
RETURNS TEXT 
READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_expr_type TEXT;
DECLARE v_mview_expression TEXT;
DECLARE v_mview_alias TEXT;
DECLARE v_select_list TEXT default '';  
DECLARE v_dml_type TEXT;
DECLARE v_mview_table_alias TEXT;
DECLARE cur_select CURSOR 
FOR  
SELECT mview_expr_type, 
       mview_expression, 
       mview_alias
  FROM flexviews.mview_expression m
 WHERE m.mview_id = v_mview_id
   AND m.mview_expr_type in ('COUNT','GROUP','SUM','COLUMN','AVG','MIN','MAX','COUNT_DISTINCT', 'STDDEV_POP','VAR_POP', 'STDDEV_SAMP','VAR_SAMP','GROUP_CONCAT','BIT_AND','BIT_OR','BIT_XOR','PERCENTILE')
   AND NOT (m.mview_expr_type = 'COLUMN' and trim(mview_expression) = '*')
 ORDER BY mview_expr_order;  

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

SELECT mview_table_alias
  INTO v_mview_table_alias
  FROM flexviews.mview_table
 WHERE mview_table_id = v_mview_table_id;

SET v_dml_type = CONCAT('(', v_mview_table_alias, '.dml_type * ', v_method, ')');

OPEN cur_select;  

selectLoop: LOOP    
  FETCH cur_select 
   INTO v_mview_expr_type,
        v_mview_expression,
        v_mview_alias;
  
  IF v_done THEN      
    CLOSE cur_select;      
    LEAVE selectLoop;    
  END IF;    
  
  IF v_select_list != '' THEN      
    SET v_select_list = CONCAT(v_select_list, ', ');    
  END IF;    

  IF v_mview_expr_type = 'GROUP' OR v_mview_expr_type = 'COLUMN' THEN
    SET v_mview_expression = CONCAT('(', v_mview_expression, ')');
  ELSEIF v_mview_expr_type = 'COUNT' THEN
    IF TRIM(v_mview_expression)  = '*' THEN
      SET v_mview_expression  = v_dml_type;
    ELSE
      SET v_mview_expression  = CONCAT('IF(',v_mview_expression,' IS NULL,0,', v_dml_type);
    END IF;
    SET v_mview_expression = CONCAT('IFNULL(SUM(', v_mview_expression, '),0)');
  ELSEIF v_mview_expr_type = 'SUM' THEN
    SET v_mview_expression = CONCAT('SUM(',v_dml_type, ' * ', v_mview_expression, ')');
  ELSE
    IF v_mview_expr_type != 'AVG' THEN
      SET v_mview_expression = 'COUNT(NULL)';
    END IF;
  END IF;

  IF v_mview_expr_type != 'AVG' THEN
    SET v_select_list = CONCAT(v_select_list,v_mview_expression, ' as `', v_mview_alias, '`');   
  ELSE
    SET v_select_list = CONCAT(v_select_list, 0, ' as `', v_mview_alias, '`,');   
    SET v_select_list = CONCAT(v_select_list, 'SUM(',v_dml_type, ' * cast(', v_mview_expression, ' as decimal(50,15))) as `', v_mview_alias, '_sum`,');
    SET v_select_list = CONCAT(v_select_list, 'SUM(IF(',v_mview_expression,' IS NULL,0,', v_dml_type, ')) as `', v_mview_alias, '_cnt`');
  END IF;
END LOOP;  

RETURN v_select_list;
END ;;

DROP FUNCTION IF EXISTS `has_aggregates`;;

CREATE DEFINER=flexviews@localhost FUNCTION  `has_aggregates`(  
v_mview_id INT
)
RETURNS BOOLEAN
READS SQL DATA
BEGIN  
DECLARE v_count INT;

SELECT COUNT(*)
  INTO v_count    
  FROM flexviews.mview_expression m
 WHERE m.mview_id = v_mview_id
   AND m.mview_expr_type in ('GROUP','SUM','COUNT' );

IF v_count > 0 THEN return TRUE; END IF;

RETURN FALSE;
END ;;

DROP FUNCTION IF EXISTS flexviews.get_delta_groupby;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_delta_groupby(
  v_mview_id INT
) RETURNS TEXT CHARSET latin1
    READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_expr_type varchar(100);  
DECLARE v_mview_expression varchar(100); 
DECLARE v_mview_alias varchar(100);  
DECLARE v_group_list TEXT default '';  
DECLARE v_mview_alias_prefixed varchar(150);
DECLARE cur_select CURSOR 
FOR  
SELECT mview_expr_type, 
       mview_expression, 
       mview_alias
  FROM flexviews.mview_expression m
 WHERE m.mview_id = v_mview_id
   AND m.mview_expr_type  = 'GROUP'
 ORDER BY mview_expr_order;  

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  
OPEN cur_select;  
selectLoop: LOOP    
  FETCH cur_select 
   INTO v_mview_expr_type,
        v_mview_expression,
        v_mview_alias;
  
  IF v_done THEN      
    CLOSE cur_select;      
    LEAVE selectLoop;    
  END IF;    

  IF v_group_list != '' THEN
    SET v_group_list = CONCAT(v_group_list, ', ');
  END IF;

  SET v_group_list = CONCAT(v_group_list, '(', v_mview_expression,')');
END LOOP;

RETURN CONCAT(v_group_list);

END ;;

DROP FUNCTION IF EXISTS get_insert;;

CREATE DEFINER=flexviews@localhost FUNCTION  get_insert (
  v_mview_id INT,
  v_select_stmt TEXT
) RETURNS TEXT
READS SQL DATA
BEGIN
DECLARE v_mview_name TEXT;
DECLARE v_mview_schema TEXT; 
DECLARE v_sql TEXT;
DECLARE v_mview_expr_type TEXT;
DECLARE v_mview_alias TEXT;
DECLARE v_mview_expression TEXT;
DECLARE v_set_clause TEXT DEFAULT '';
DECLARE v_done BOOLEAN DEFAULT FALSE;
DECLARE v_only_agg BOOLEAN DEFAULT FALSE;
DECLARE cur_expr CURSOR 
FOR
SELECT mview_alias, 
       mview_expr_type
  FROM flexviews.mview_expression
 WHERE mview_id = v_mview_id
   AND mview_expr_type = 'GROUP';

DECLARE cur_agg CURSOR 
FOR
SELECT mview_alias, 
       mview_expr_type,
       mview_expression
  FROM flexviews.mview_expression
 WHERE mview_id = v_mview_id
   AND mview_expr_type in ('AVG', 'SUM', 'COUNT');

DECLARE CONTINUE HANDLER
FOR SQLSTATE '02000'
SET v_done = TRUE;

SELECT mview_name,
       mview_schema
  INTO v_mview_name,
       v_mview_schema
  FROM flexviews.mview
 WHERE mview_id = v_mview_id; 
   
/*
SET v_sql = CONCAT('INSERT INTO ', v_mview_schema, '.', v_mview_name, '\n', 
'(',flexviews.get_delta_aliases(v_mview_id, '', FALSE),')\n',
'SELECT * FROM (', v_select_stmt ,') x_select_ \n',
"ON DUPLICATE KEY UPDATE\n");
*/

IF flexviews.has_aggregates(v_mview_id) THEN
  SET v_only_agg = FALSE;
  SELECT COUNT(*) = 0
    INTO v_only_agg
    from flexviews.mview_expression
   where mview_id = v_mview_id
     and (mview_expr_type = 'GROUP' or mview_expr_type = 'COLUMN');
END IF;

IF v_only_agg THEN
  SET v_sql = CONCAT('INSERT INTO ', v_mview_schema, '.', v_mview_name, '\n', 
'(mview$pk,',flexviews.get_delta_aliases(v_mview_id, '', FALSE),')\n(',
v_select_stmt,')\n',
"ON DUPLICATE KEY UPDATE\n");
ELSE 
  SET v_sql = CONCAT('INSERT INTO ', v_mview_schema, '.', v_mview_name, '\n', 
'(',flexviews.get_delta_aliases(v_mview_id, '', FALSE),')\n(',
v_select_stmt,')\n',
"ON DUPLICATE KEY UPDATE\n");

END IF;

SET @HERE=1;

OPEN cur_agg;
exprLoop: LOOP
  FETCH cur_agg 
   INTO v_mview_alias,
        v_mview_expr_type,
        v_mview_expression;

  IF v_done = TRUE THEN
    CLOSE cur_agg;
    LEAVE exprLoop;
  END IF; 

  IF v_set_clause != '' THEN
    SET v_set_clause = CONCAT(v_set_clause, ',');
  END IF;

  IF v_mview_expr_type != 'AVG' THEN
    SET v_set_clause = CONCAT(v_set_clause, '`', v_mview_alias, '` = ', v_mview_name, '.`',v_mview_alias, '` + x_select_.`',v_mview_alias, '`\n');
  ELSE
    SET v_set_clause = CONCAT(v_set_clause, '`', v_mview_alias, '_sum` = ', v_mview_name, '.`',v_mview_alias, '_sum` + x_select_.`',v_mview_alias, '_sum`,\n');
    SET v_set_clause = CONCAT(v_set_clause, '`', v_mview_alias, '_cnt` = ', v_mview_name, '.`',v_mview_alias, '_cnt` + x_select_.`',v_mview_alias, '_cnt`,\n');
    SET v_set_clause = CONCAT(v_set_clause, '`', v_mview_alias, '` = ', v_mview_name, '.`',v_mview_alias, '_sum` / ', v_mview_name, '.`', v_mview_alias, '_cnt`\n');
  END IF;
END LOOP;

set @get_insert := CONCAT(v_sql, v_set_clause);

RETURN CONCAT(v_sql, v_set_clause);
END;;

DROP PROCEDURE IF EXISTS flexviews.ensure_validity;;

CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.ensure_validity(
IN v_id INT
) 
BEGIN
DECLARE v_mview_id INT;
DECLARE v_mview_refresh_type TEXT;
DECLARE v_mview_definition TEXT;
DECLARE v_incremental_hwm BIGINT;
DECLARE v_mview_enabled BOOLEAN DEFAULT TRUE;
DECLARE v_refreshed_to_uow_id BIGINT;
DECLARE v_has_count_star BOOLEAN;
DECLARE v_message TEXT DEFAULT '';
DECLARE v_leave_loop BOOLEAN DEFAULT FALSE;
DECLARE v_sql TEXT;
DECLARE v_error BOOLEAN DEFAULT FALSE;

-- this 'loop' gives us an easy way to only do the necessary checks
theLoop:LOOP

SELECT mview_id,
       mview_enabled,
       mview_refresh_type
  INTO v_mview_id,
       v_mview_enabled,
       v_mview_refresh_type
  FROM flexviews.mview
 WHERE mview_id = v_id;
IF v_mview_id IS NULL THEN
  CALL flexviews.signal('Non-existent materialized view');
END IF;

IF v_mview_refresh_type = 'COMPLETE' THEN
  call flexviews.signal('THIS FUNCTION ONLY SUPPORTS INCREMENTAL REFRESH VIEWS');
END IF;
/*
IF v_mview_enabled = TRUE THEN
  -- already been validated since it is enabled
  -- LEAVE theLoop;
  set @noop=0;
END IF;
*/

IF flexviews.has_aggregates(v_mview_id) = 1 THEN
  
  SELECT IFNULL(COUNT(*), 0)
    INTO v_has_count_star 
    FROM flexviews.mview_expression
   WHERE mview_id = v_mview_id
     AND mview_expression = '*'
     AND mview_expr_type = 'COUNT';
   
   -- add COUNT(*) to aggregate tables if the user forgot
   -- we can't delete from the mview otherwise
   IF v_has_count_star = 0 THEN
     CALL flexviews.add_expr(v_mview_id, 'COUNT', '*', 'CNT');
   END IF;
   
END IF;
SET v_error=0;

-- put together the SELECT statement to make sure it works
SET v_sql = CONCAT(flexviews.get_select(v_mview_id, 'CREATE',''), char(10));
SET v_sql = CONCAT(v_sql, flexviews.get_from(v_mview_id, 'JOIN', ''));
IF flexviews.get_where(v_mview_id) != "" THEN
  SET v_sql = CONCAT(v_sql, ' WHERE ' , flexviews.get_where(v_mview_id));
END IF;

IF flexviews.get_delta_groupby(v_mview_id) != "" THEN
  SET v_sql = CONCAT(v_sql, ' GROUP BY ', flexviews.get_delta_groupby(v_mview_id), ' ');
END IF;

SET v_sql = CONCAT(v_sql, ' LIMIT 0');

SET @MV_DEBUG = v_sql;
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  SET v_error = 1;
  SET @v_sql = CONCAT('CREATE TEMPORARY TABLE flexviews.is_valid AS ', v_sql);


  PREPARE thesql from @v_sql;
  EXECUTE thesql;
  DEALLOCATE PREPARE thesql;

  DROP TEMPORARY TABLE flexviews.is_valid;
END;
IF v_error = 1 THEN
  CALL flexviews.signal('COULD NOT VALIDATE MATERIALIZED VIEW.  CHECK @MV_DEBUG.');
END IF;

LEAVE theLoop;
END LOOP;

END;;

DROP PROCEDURE IF EXISTS flexviews.rlog;;
CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.rlog(v_message TEXT)
BEGIN
DECLARE v_tstamp DATETIME;
  INSERT INTO flexviews.refresh_log VALUES (NOW(), NULL, v_message);
END;;
drop procedure if exists process_rlog;;

DROP FUNCTION IF EXISTS flexviews.get_child_select;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_child_select(  
v_mview_id INT, 
v_alias TEXT
)
RETURNS TEXT 
READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_expr_type TEXT;
DECLARE v_mview_expression TEXT;
DECLARE v_mview_alias TEXT;
DECLARE v_select_list TEXT default '';  
DECLARE v_percentile int default null;

DECLARE cur_select CURSOR 
FOR  
SELECT mview_expr_type, 
       mview_alias,
       percentile
  FROM flexviews.mview_expression m
 WHERE m.mview_id = v_mview_id
   AND m.mview_expr_type in ( 'COLUMN', 'GROUP', 'MIN','MAX','COUNT_DISTINCT', 'STDDEV_SAMP','STDDEV_POP','VAR_POP','VAR_SAMP','BIT_AND','BIT_OR','BIT_XOR','GROUP_CONCAT', 'PERCENTILE' )
   AND NOT (m.mview_expr_type = 'COLUMN' and trim(m.mview_expression) = '*')
 ORDER BY mview_expr_order;  

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

OPEN cur_select;  

selectLoop: LOOP    
  FETCH cur_select 
   INTO v_mview_expr_type,
        v_mview_alias,
        v_percentile;
  
  IF v_done THEN      
    CLOSE cur_select;      
    LEAVE selectLoop;    
  END IF;    

  SET v_mview_expression := CONCAT('(`', v_alias, '`.`', v_mview_alias, '`)'); 

  IF v_mview_expr_type != 'GROUP' AND v_mview_expr_type != 'COLUMN' THEN
    IF v_mview_expr_type = 'COUNT_DISTINCT' THEN
      SET v_mview_expression := CONCAT('COUNT(DISTINCT ', v_mview_expression,')');
    ELSEIF v_mview_expr_type = 'PERCENTILE' THEN
      SET v_mview_expression := flexviews.get_percentile(v_mview_expression, v_percentile);
    ELSE
      SET v_mview_expression := CONCAT(v_mview_expr_type, v_mview_expression);
    END IF;
  END IF;
  
  IF v_select_list != '' THEN      
    SET v_select_list := CONCAT(v_select_list, ', ');    
  END IF;    

  SET v_select_list := CONCAT(v_select_list, v_mview_expression, ' as `', v_mview_alias , '`' );

END LOOP;  

RETURN CONCAT('NULL as mview$pk,',v_select_list);
END ;;


DROP FUNCTION IF EXISTS `get_delta_join`;;

CREATE DEFINER=flexviews@localhost FUNCTION `get_delta_join`(  
v_mview_id INT 
)
RETURNS TEXT 
READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_expr_type TEXT;
DECLARE v_mview_expression TEXT;
DECLARE v_mview_alias TEXT;
DECLARE v_select_list TEXT default '';  
DECLARE v_mview_fqn TEXT DEFAULT '';
DECLARE cur_select CURSOR 
FOR  
SELECT mview_expr_type, 
       mview_expression, 
       mview_alias,
       concat (mview_schema, '.', mview_name) 
  FROM flexviews.mview_expression m
  JOIN flexviews.mview USING (mview_id)
 WHERE m.mview_id = v_mview_id
   AND (m.mview_expr_type = 'GROUP' OR m.mview_expr_type = 'COLUMN')
 ORDER BY mview_expr_order;  

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

OPEN cur_select;  

selectLoop: LOOP    
  FETCH cur_select 
   INTO v_mview_expr_type,
        v_mview_expression,
        v_mview_alias,
        v_mview_fqn;
  
  IF v_done THEN      
    CLOSE cur_select;      
    LEAVE selectLoop;    
  END IF;    
 
  IF v_select_list != '' THEN      
    SET v_select_list = CONCAT(v_select_list, ' AND ');    
  END IF;    

--  SET v_select_list = CONCAT(v_select_list, 'IFNULL(', v_mview_fqn ,'.`', v_mview_alias, '`, ":NULL:") = IFNULL(delta.`', v_mview_alias, '`, ":NULL:")' );   
  SET v_select_list = CONCAT(v_select_list,  v_mview_fqn ,'.`', v_mview_alias, '` <=> delta.`', v_mview_alias, '`' );   

END LOOP;  
RETURN v_select_list;
END ;;

DELIMITER ;


