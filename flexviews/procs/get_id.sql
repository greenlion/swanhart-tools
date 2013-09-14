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

DROP FUNCTION IF EXISTS flexviews.get_id;;
/****f* UTIL_API/get_id
 * NAME
 *   flexviews.get_id - Get the materialized view id for the given view in the given schema
 * SYNOPSIS
 *   flexviews.get_id(v_mview_schema, v_mview_name)
 * FUNCTION
 *   This function returns the materialied view id for the given materialized view.
 * INPUTS
 *   * v_mview_schema - The schema which contains the materialized view
 *   * v_mview_name - The name of the table which contains the rows for the view
 * RESULT
 *   NULL will be returned if the view does not exist.
 * EXAMPLE
 *  mysql> 
 *    set @mv_id := flexviews.get_id('test','mv_example');
******
*/


CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_id (
  v_mview_schema TEXT,
  v_mview_name TEXT
)
RETURNS INT
READS SQL DATA
BEGIN
DECLARE v_mview_id INT;
 SELECT mview_id 
   INTO v_mview_id
   FROM flexviews.mview
  WHERE mview_name = v_mview_name
    AND mview_schema = v_mview_schema;

 RETURN v_mview_id;
END;
;;

DELIMITER ;
