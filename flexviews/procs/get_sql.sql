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

DROP FUNCTION IF EXISTS flexviews.get_sql;;
/****f* UTIL_API/get_sql
 * NAME
 *   flexviews.get_sql - Retrieve the SELECT statement which would represent the materialized view in a regular view
 * SYNOPSIS
 *   flexviews.get_sql(v_mview_id)
 * FUNCTION
 *   This function returns the SELECT statement which would be used by a normal view for the given expressions, tables
 *   etc which have been created for the materialized view.
 * INPUTS
 *   v_mview_id - Id of the view
 * RESULT
 *   The SQL for the view.
 * EXAMPLE
 *   mysql>
 *     call flexviews.get_sql(1);
 *     call flexviews.get_sql(flexviews.get_id('test','test_mv'));
******
*/
CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_sql (
  v_mview_id INT
)
RETURNS TEXT
READS SQL DATA
BEGIN
  DECLARE v_sql TEXT default '';

  -- COMPLETE REFRESH views store the SQL in the 
  -- mview table
  SELECT mview_definition
    INTO v_sql
    FROM flexviews.mview
   WHERE mview_id = v_mview_id;

  IF (v_sql IS NOT NULL) THEN
    RETURN v_sql;
  END IF;
 
  SET v_sql = CONCAT(flexviews.get_select(v_mview_id, 'CREATE','\n'), char(10));
  SET v_sql = CONCAT(v_sql, flexviews.get_from(v_mview_id, '\nJOIN', ''));
  SET v_sql = CONCAT(v_sql, flexviews.get_where(v_mview_id));

  SELECT count(*) 
    INTO @has_group
    FROM mview_expression
   WHERE mview_id = v_mview_id
     AND mview_expr_type = 'GROUP';

  SET v_sql = CONCAT(v_sql, IF(flexviews.has_aggregates(v_mview_id) = true AND @has_group > 0, '\nGROUP BY ', ''), flexviews.get_delta_groupby(v_mview_id), ' ');

  RETURN v_sql;

END;
;;

DELIMITER ; 
