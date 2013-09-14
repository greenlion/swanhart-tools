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

DROP PROCEDURE IF EXISTS mview_mvlog_autoclean;;

CREATE DEFINER=flexviews@localhost PROCEDURE mview_mvlog_autoclean() 
BEGIN
  DECLARE v_mview_tstamp DATETIME;
  DECLARE v_mvlog_name TEXT;
  DECLARE v_done BOOLEAN DEFAULT FALSE;
  DECLARE cur_mvlog CURSOR
  FOR
  SELECT CONCAT(table_schema, '.', table_name) mvlog_name, MIN(mview_last_refresh) mview_tstamp
    FROM flexviews.mview_table m
    JOIN information_schema.tables t
      ON t.table_name = CONCAT(m.mview_table_name, '_mvlog')
     AND t.table_schema = m.mview_table_schema
    JOIN flexviews.mview
   USING (mview_id)
  GROUP BY mvlog_name;

  DECLARE CONTINUE HANDLER 
  FOR SQLSTATE '02000'
  SET v_done = TRUE;

  OPEN cur_mvlog;

  mvlogLoop: LOOP
    FETCH cur_mvlog
     INTO v_mvlog_name,
          v_mview_tstamp;

    IF v_done THEN
      CLOSE cur_mvlog;
      LEAVE mvlogLoop;
    END IF;

    SET @v_sql = CONCAT('CREATE TEMPORARY TABLE mvlog_rows AS SELECT * FROM ',  v_mvlog_name, ' WHERE mview_tstamp >= ?');
    SET @v_mview_tstamp = v_mview_tstamp;
    PREPARE create_stmt FROM @v_sql;
    EXECUTE create_stmt USING @v_mview_tstamp;
    DEALLOCATE PREPARE create_stmt;


    SET @v_sql = CONCAT('TRUNCATE TABLE ', v_mvlog_name);
    PREPARE truncate_stmt FROM @v_sql;
    EXECUTE truncate_stmt;
    DEALLOCATE PREPARE truncate_stmt;

  
    SET @v_sql = CONCAT('INSERT INTO ', v_mvlog_name, ' SELECT * FROM mvlog_rows');
    PREPARE insert_stmt FROM @v_sql;
    DEALLOCATE PREPARE insert_stmt;

    DROP TEMPORARY TABLE IF EXISTS mvlog_rows;
  END LOOP; 
  SET v_done = TRUE;
END;
;;

DELIMITER ;
 
