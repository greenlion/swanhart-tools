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

/****f* SQL_API/is_enabled
 * NAME
 *   flexviews.is_enabled - Returns wether a materialized view is enabled.
 * SYNOPSIS
 *   flexviews.is_enabled(v_mview_id)
 * FUNCTION
 *   This function accepts a materialized view id and returns TRUE if the mview is enabled,
 *   FALSE if it is not enabled, NULL if it does not exist.
 * INPUTS
 *   * v_mview_id     - Materialized view id.
 * RESULT
 *   Boolean value.
 * EXAMPLE
 *   mysql>
 *     call flexviews.is_enabled(flexviews.get_id('test', 'mv_example'));
******
*/

DROP FUNCTION IF EXISTS flexviews.is_enabled;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.is_enabled(v_mview_id INT) 
RETURNS BOOLEAN
NOT DETERMINISTIC
READS SQL DATA
COMMENT 'Wether specified MView is currently enabled'
BEGIN
    RETURN (
          SELECT mview_enabled
              FROM flexviews.mview
              WHERE mview_id = v_mview_id
      );
END;;

DELIMITER ;
