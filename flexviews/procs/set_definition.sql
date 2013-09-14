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

DROP PROCEDURE IF EXISTS `set_definition`;;
/****f* SQL_API/set_definition
 * NAME
 *   flexviews.set_definition - sets the SQL SELECT statement to be used by the
 *   CREATE TABLE AS ... SELECT statement which is used for COMPLETE refresh
 *   materialized views.
 * SYNOPSIS
 *   flexviews.set_definition(v_mview_id, v_sql);
 * FUNCTION
 *   COMPLETE refresh materialized views are created and refreshed with
 *   CREATE TABLE ... AS SELECT.  The SELECT statement provided in v_sql is
 *   used to create the view.
 * INPUTS
 *   v_mview_id - The materialized view id (see flexviews.get_id)
 *   v_sql      - The SELECT statement to use for the view
 * RESULT
 *   If the UPDATE does not succeed then the error will passed to the MySQL client
 * SEE ALSO
 *   SQL_API/disable, UTIL_API/flexviews.get_id, SQL_API/flexviews.enable
 * EXAMPLE
 *   mysql>
 *     call flexviews.set_definition(flexviews.get_id('test','mv_example'), 'SELECT * from my_table where c1=1')
******
*/
CREATE DEFINER=`flexviews`@`localhost` PROCEDURE `set_definition`(
  IN v_mview_id INT,
  IN v_definition_sql TEXT
)
BEGIN

 UPDATE flexviews.mview
    SET mview_definition = v_definition_sql
  WHERE mview_id = v_mview_id;   
     
END ;;

DELIMITER ;
