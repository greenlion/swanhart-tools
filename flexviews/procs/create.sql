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

DROP PROCEDURE IF EXISTS flexviews.create;;
/****f* SQL_API/create
 * NAME
 *   flexviews.create - Create a materialized view id for the view.  This is the very first step when creating a new materialized view.
 * SYNOPSIS
 *   flexviews.create(v_schema, v_mview_name, v_refresh_type)
 * FUNCTION
 *   This function creates a materialized view id for a new materialized view.  
 *   The materialized view identifier is stored in LAST_INSERT_ID() and is also accessible
 *   using flexviews.get_id()
 * INPUTS
 *   * v_schema       - The schema (aka database) in which to create the new view
 *   * v_mview_name   - The name of the materialzied view to create
 *   * v_refresh_type - ENUM('INCREMENTAL','COMPLETE')
 * RESULT
 *   An error will be generated in the MySQL client if the skeleton can not be created.
 * NOTES
 *  Every materialized view has a unique identifier assigned to it by this function.  
 *  Almost every other Flexviews function takes a materialized view id (mview_id) as the first parameter.
 *  v_refresh_type:
 *  * COMPLETE - The view is completely replaced with CREATE TABLE .. AS SELECT.  Slow, but works with all SQL.
 *  * INCREMENTAL - Changelogs are used to update the view only based on the rows that changed.  Fast, but only works with a subset of SQL.
 *  Note that this command will not immediately create the table.  It will be created only when the view is ENABLED.
 * SEE ALSO
 *  SQL_API/enable, SQL_API/add_table, SQL_API/add_expr
 * EXAMPLE
 *   mysql>
 *     call flexviews.create('test', 'mv_example', 'INCREMENTAL');
 *     call flexviews.create('test', 'another_example', 'COMPLETE');
******
*/

CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`create`(
  IN v_mview_schema TEXT,
  IN v_mview_name TEXT,
  IN v_mview_refresh_type TEXT
)
BEGIN
  INSERT INTO flexviews.mview
  (  mview_name,
     mview_schema, 
     mview_refresh_type
  )
  VALUES
  (  v_mview_name,
     v_mview_schema,
     v_mview_refresh_type
  );

END ;;

DELIMITER ;
