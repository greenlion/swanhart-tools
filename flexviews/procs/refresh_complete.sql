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

DROP PROCEDURE IF EXISTS `mview_refresh_complete`;;

CREATE DEFINER=`flexviews`@`localhost` PROCEDURE `mview_refresh_complete`(
  IN v_mview_id INT
)
BEGIN
DECLARE v_sql TEXT;
DECLARE v_mview_name TEXT;
DECLARE v_mview_schema TEXT;
DECLARE v_mview_last_refresh DATETIME;
DECLARE v_mview_refresh_period INT;
DECLARE v_mview_definition TEXT;

SELECT mview_name, 
       mview_schema, 
       mview_last_refresh,
       mview_refresh_period, 
       mview_definition
  INTO v_mview_name,
       v_mview_schema,
       v_mview_last_refresh,
       v_mview_refresh_period,
       v_mview_definition 
  FROM flexviews.mview
 WHERE mview_id = v_mview_id;

 IF @v_start_time IS NULL THEN
   CALL flexviews.signal('This procedure is meant to be called from mview_refresh.  Refresh aborted.');
 END IF;

 SET v_sql = CONCAT('DROP TABLE IF EXISTS ', v_mview_schema, '.', v_mview_name, '_new');
 SET @v_sql = v_sql;
 PREPARE drop_stmt FROM @v_sql;
 EXECUTE drop_stmt;
 DEALLOCATE PREPARE drop_stmt;
 
 SET v_sql = CONCAT('CREATE TABLE ', v_mview_schema, '.', v_mview_name, '_new (mview$pk bigint auto_increment primary key');
 SET @v_keys = flexviews.get_keys(v_mview_id);

 IF @v_keys != "" THEN
   SET v_sql = CONCAT(v_sql, ',', @v_keys,'\n');
 END IF;

 SET v_sql = CONCAT(v_sql, ')');
 SET v_sql = CONCAT(v_sql, ' AS ');
 SET v_sql = CONCAT(v_sql, v_mview_definition);

 SET @v_sql = v_sql;
 PREPARE create_stmt FROM @v_sql;
 EXECUTE create_stmt;
 DEALLOCATE PREPARE create_stmt;
/*
 SET v_sql = CONCAT('ANALYZE TABLE ', v_mview_schema, '.', v_mview_name,'_new');
 SET @v_sql = v_sql;
 PREPARE analyze_stmt  FROM @v_sql;
 EXECUTE analyze_stmt;
 DEALLOCATE PREPARE analyze_stmt;
*/
 SET v_sql = CONCAT('RENAME TABLE ', v_mview_schema, '.', v_mview_name, ' to ', v_mview_schema, '.', v_mview_name, '_old,',
                    v_mview_schema, '.', v_mview_name, '_new to ', v_mview_schema, '.', v_mview_name);

 SET @v_sql = v_sql;
 PREPARE replace_stmt FROM @v_sql;
 EXECUTE replace_stmt;
 DEALLOCATE PREPARE replace_stmt;

 SET v_sql = CONCAT('DROP TABLE IF EXISTS ', v_mview_schema, '.', v_mview_name, '_old');
 SET @v_sql = v_sql;
 PREPARE drop_stmt FROM @v_sql;
 EXECUTE drop_stmt;
 DEALLOCATE PREPARE drop_stmt;

END ;;

DELIMITER ;
