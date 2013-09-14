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

DROP PROCEDURE IF EXISTS flexviews.`remove_expr`;;
/****f* SQL_API/remove_expr
 * NAME
 *   flexviews.remove_expr - Remove an expression or indexes from a materialized view.
 * SYNOPSIS
 *   flexviews.remove_expr(v_mview_id, v_alias) 
 *
 * FUNCTION
 *   This function removes the expression with the given alias from the materialized view.
 * INPUTS
 *   * v_mview_id - The materialized view id
 *   * v_alias - The alias that was specified for the expression when you added it to the view
 * NOTES
 *   * You can not modify an enabled view.
 *   * This function is most often used to correct a mistake or typo made when creating the view.
 * SEE ALSO
 *   flexviews.enable, flexviews.add_table, flexviews.add_expr
 * EXAMPLE
 *   mysql>
 *     set @mv_id = flexviews.get_id('test', 'mv_example');
 *     call flexviews.add_table(@mv_id, 'schema', 'table', 'an_alias', NULL);
 *     call flexviews.add_expr(@mv_id, 'PRIMARY', 'c1*oops*', 'pk'); # ADD AN EXPRESSION WITH A PROBLEM
 *     call flexviews.remove_expr(@mv_id, 'pk'); # REMOVE THE EXPRESSION
******
*/

CREATE DEFINER=`flexviews`@`localhost`PROCEDURE flexviews.`remove_expr`(
  IN v_mview_id INT,
  IN v_mview_alias TEXT
)
BEGIN
  IF flexviews.is_enabled(v_mview_id) = 1 THEN
    CALL flexviews.signal('MAY_NOT_MODIFY_ENABLED_MVIEW');
  END IF;

  DELETE 
    FROM flexviews.mview_expression
   WHERE mview_id = v_mview_id
     AND mview_alias = v_mview_alias;
END ;;

DELIMITER ;
