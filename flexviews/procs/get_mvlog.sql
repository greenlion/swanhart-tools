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

DROP FUNCTION IF EXISTS flexviews.get_mvlog;;
/****f* CDC_API/get_mvlog
 * NAME
 *   flexviews.get_mvlog - Get the materialized view log name for the given table in the given schema
 * SYNOPSIS
 *   flexviews.get_mvlog(v_mview_schema, v_mview_name)
 * FUNCTION
 *   This function returns the materialied view log name for the table.
 * INPUTS
 *   * v_table_schema - The schema which contains the materialized view
 *   * v_table_name   - The name of the table which contains the rows for the view
 * RESULT
 *   * NULL will be returned if the table does not exist or is not logged
 *   * EMPTY STRING is returned if logging on the table is disabled
 *   * The name of the table will be returned otherwise
 * EXAMPLE
 *  mysql>
 *    select flexviews.get_mvlog('test','mv_example');
******
*/


CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_mvlog (
  v_table_schema TEXT,
  v_table_name TEXT
)
RETURNS TEXT
READS SQL DATA
BEGIN
DECLARE v_mvlog_name TEXT;
DECLARE v_active_flag BOOLEAN;
  SELECT mvlog_name, 
         active_flag
    INTO v_mvlog_name,
         v_active_flag
    FROM flexviews.mvlogs
   WHERE table_name = v_table_name
     AND table_schema = v_table_schema;
  IF v_active_flag IS NOT NULL AND v_active_flag != TRUE THEN 
    SET v_mvlog_name := "";
  END IF;
  RETURN v_mvlog_name;
END;
;;

DELIMITER ;
