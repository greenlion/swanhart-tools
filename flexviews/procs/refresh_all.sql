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

DROP PROCEDURE IF EXISTS flexviews.refresh_all ;;
/****f* UTIL_API/refresh_all
 * NAME
 *   flexviews.refresh_all - Computes or applies changes or completely refreshes every enabled materialized view, serially.
 * SYNOPSIS
 *   flexviews.refresh(v_mview_id, 
                       v_mode,
                       v_uow_id) 
 * FUNCTION
 *   This is a convenience function...
 *   This function initiates the refresh process for all enabled views.  It operates only on one type of  
 *   view at a time.  That is, if you specify 'COMPLETE', only 'COMPLETE' views will be refreshed. 
 *   Conversely, complete refresh views won't be refreshed if 'COMPUTE' or 'APPLY' is specified. 
 *   
 * INPUTS
 *   This function takes a combination of input parameters:
 *   * v_mview_id - The materialized view identifier
 *   * v_mode     - COMPLETE|COMPUTE|APPLY|BOTH
 *   * v_uow_id   - (MAY BE NULL)
 *		  When a uow_id (transaction id) is provided the mode selected above will operate only on records
 *		  created at or before the given uow_id.  If a NULL value is provided, then the most recent committed 
 *		  transaction id will be used.
 * NOTES
 * v_mode:
 * |html <table border=1 align="center">
 * |html <tr><th bgcolor=#cccccc>v_mode<th bgcolor=#cccccc>explanation</th></tr><tr>             
 * |html </tr><tr><td>COMPLETE</td><td>COMPLETE is used only for COMPLETE refresh materialized view.  The view is refreshed from scratch using a combination of CREATE TABLE, INSERT INTO and RENAME TABLE
 * |html </tr><tr><td>COMPUTE</td><td>COMPUTE is used for INCREMENTAL tables.  It computes the changes since the last refresh but it does not apply them.  Low cost frequent computations can be made while maintaining the transactional consistency of the view at the last refresh point in time.
 * |html </tr><tr><td>APPLY</td><td>APPLY is used to apply any un-applied changes from previous COMPUTE runs
 * |html </tr><tr><td>BOTH</td><td>BOTH executes a COMPUTE followed by an APPLY
 * |html </tr></table>
 * SEE ALSO
 *   flexviews.enable, flexviews.add_table, flexviews.add_expr
 * EXAMPLE
 *   mysql>
 *     set @mv_id = flexviews.get_id('test', 'mv_example');
 *     call flexviews.refresh(@mv_id, 'BOTH', NULL);
 * NOTES
 *   FlexCDC MUST BE RUNNING in order to COMPUTE changes to views!
******
*/
CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.refresh_all(
  IN v_mode ENUM('COMPLETE', 'COMPUTE', 'APPLY', 'BOTH'),
  IN v_uow_id BIGINT UNSIGNED
)
BEGIN
DECLARE v_done BOOLEAN DEFAULT FALSE;
DECLARE v_mview_id SMALLINT UNSIGNED;


DECLARE cur_views CURSOR
FOR 
SELECT mview_id
  FROM flexviews.mview
 WHERE mview_refresh_type = IF(v_mode = 'COMPLETE', 'COMPLETE', 'INCREMENTAL')
   AND mview_enabled = TRUE;

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'
    SET v_done = TRUE;

SET max_sp_recursion_depth=255;
IF v_mode IS NULL THEN SET v_mode := 'BOTH'; END IF;
OPEN cur_views;

viewLoop: LOOP
  FETCH cur_views
   INTO v_mview_id;

  IF v_done THEN
    CLOSE cur_views;
    LEAVE viewLoop;
  END IF;

  CALL flexviews.refresh(v_mview_id, v_mode, v_uow_id);
END LOOP;

END ;;

DELIMITER ;
