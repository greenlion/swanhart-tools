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

DROP PROCEDURE IF EXISTS flexviews.`drop_mvlog` ;;
/****f* CDC_API/drop_mvlog
 * NAME
 *   flexviews.drop_mvlog - Drop a table changelog for a MySQL table
 * SYNOPSIS
 *   flexviews.drop_mvlog(v_schema, v_mview_name)
 * FUNCTION
 *   This function drops a table change log from a particular table in the database.
 * INPUTS
 *   * v_schema       - The schema (aka database) in which the table resides
 *   * v_table_name   - The name of the table you want to log changes on
 * RESULT
 *   An error will be generated in the MySQL client if the changelog could not be created.
 * EXAMPLE
 *     mysql>
 *       call flexviews.drop_mvlog('test', 'my_table');
******
*/
CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`drop_mvlog`(
   IN v_schema_name VARCHAR(100),
   IN v_table_name VARCHAR(50) 
)
BEGIN
  DECLARE v_mvlog_name TEXT;

  START TRANSACTION;
  SET v_mvlog_name := CONCAT(v_schema_name, '_', v_table_name);
  DELETE FROM flexviews.mvlogs where table_schema = v_schema_name and table_name = v_table_name;
  
  SET @v_sql = CONCAT('DROP TABLE IF EXISTS ', flexviews.get_setting('mvlog_db'), '.', v_mvlog_name);
  PREPARE drop_stmt from @v_sql;
  EXECUTE drop_stmt;
  DEALLOCATE PREPARE drop_stmt;


END ;;

DELIMITER ;
