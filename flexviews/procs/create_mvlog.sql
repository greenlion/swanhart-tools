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

DROP PROCEDURE IF EXISTS flexviews.`create_mvlog` ;;
/****f* CDC_API/create_mvlog
 * NAME
 *   flexviews.create_mvlog - Create a table changelog for a MySQL table
 * SYNOPSIS
 *   flexviews.create_mvlog(v_schema, v_mview_name)
 * FUNCTION
 *   This function creates a table change log (aka materialized view log)
 *   on a particular table in the database. Any data changes made to the table
 *   will be recorded in the table change log by the Flexviews binary log consumer (FlexCDC).
 *
 *   You do not need to use this function if you use the auto-changelog mode of FlexCDC.
 *   When this mode is used, change logs are made automatically when a change for a table is 
 *   detected and the log has not yet been created.
 *
 *   If you use temporary tables, or you have only a small number of tables to log, then you might
 *   consider not using auto-changelogging and instead only log certain tables.  Use this function
 *   to add a table to the list of tables to log.  Once added a table may not be removed.
 * INPUTS
 *   * v_schema       - The schema (aka database) in which the table resides
 *   * v_table_name   - The name of the table you want to log changes on
 * RESULT
 *   An error will be generated in the MySQL client if the changelog could not be created.
 * EXAMPLE
 *     mysql>
 *       call flexviews.create_mvlog('test', 'my_table');
******
*/
CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`create_mvlog`(
   IN v_schema_name VARCHAR(100),
   IN v_table_name VARCHAR(50) 
)
BEGIN
  DECLARE v_done BOOLEAN DEFAULT FALSE;
  
  DECLARE v_column_name VARCHAR(100);
  DECLARE v_data_type VARCHAR(1024);
  DECLARE v_delim CHAR(5);
  DECLARE v_mview_type TINYINT(4) DEFAULT -1;
  DECLARE v_trig_extension CHAR(3);
  DECLARE v_sql TEXT;
  DECLARE v_mvlog_name TEXT;
  DECLARE cur_columns CURSOR
  FOR SELECT COLUMN_NAME, 
             IF(COLUMN_TYPE='TIMESTAMP', 'TIMESTAMP', COLUMN_TYPE) COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS 
       WHERE TABLE_NAME=v_table_name 
         AND TABLE_SCHEMA = v_schema_name;
  
  DECLARE CONTINUE HANDLER FOR 
  SQLSTATE '02000'
    SET v_done = TRUE;

  SET v_mvlog_name := CONCAT(v_schema_name, '_', v_table_name);
  
  SET v_sql = CONCAT('DROP TABLE IF EXISTS ', flexviews.get_setting('mvlog_db'), '.', v_mvlog_name);
  SET @v_sql = v_sql;
  PREPARE drop_stmt from @v_sql;
  EXECUTE drop_stmt;
  DEALLOCATE PREPARE drop_stmt;

  
  OPEN cur_columns;
  
  SET v_sql = '';
  
  columnLoop: LOOP
    IF v_sql != '' THEN
      SET v_sql = CONCAT(v_sql, ', ');
    END IF;
    FETCH cur_columns INTO 
      v_column_name,
      v_data_type;
  
    IF v_done THEN
      CLOSE cur_columns;
      LEAVE columnLoop;
    END IF;
    SET v_sql = CONCAT(v_sql, v_column_name, ' ', v_data_type);
  END LOOP; 
  -- TODO the Flexviews names should have a fv$mvlog_id$ prefix on them in order to prevent conflicts.  
  -- This will allow weird constructs like mvlogs on mvlogs, though I can't think of a good reason for
  -- such constructs.  It isn't possible to put mvlogs on almost any internal Flexviews table because
  -- almost all the tables contain uow_id.  mview_signal is an exception.

  IF TRIM(v_sql) = "" THEN
    CALL flexviews.signal('TABLE NOT EXISTS OR ACCESS DENIED');
  END IF; 
  SET v_sql = CONCAT('CREATE TABLE ', flexviews.get_setting('mvlog_db'), '.', v_mvlog_name, 
                 '( dml_type INT DEFAULT 0, uow_id BIGINT, `fv$server_id` INT UNSIGNED, fv$gsn bigint, ', v_sql, 'KEY(uow_id, dml_type) ) ENGINE=INNODB');
   
  SET @v_sql = v_sql;
  PREPARE create_stmt from @v_sql;
  EXECUTE create_stmt;
  DEALLOCATE PREPARE create_stmt; 

  REPLACE INTO flexviews.mvlogs (table_schema, table_name, mvlog_name) values (v_schema_name, v_table_name, v_mvlog_name);

END ;;

DELIMITER ;
