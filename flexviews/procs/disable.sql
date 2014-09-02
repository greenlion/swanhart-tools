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

DROP PROCEDURE IF EXISTS flexviews.disable;;
/****f* SQL_API/disable
 * NAME
 *   flexviews.disable - Drop the materialized view table.  
 * SYNOPSIS
 *   flexviews.disable(v_mview_id);
 * FUNCTION
 *   This function drops the table holding the rows for the materialized
 *   view.  There is no warning and the table is dropped as soon as this command is issued.
 * INPUTS
 *   v_mview_id - The materialized view id 
 * RESULT
 *   An error will be generated in the MySQL client if the view can not be disabled.
 * NOTES
 *   The dictionary information is not removed, instead the metadata is updated to reflect the disabled status.
 * SEE ALSO
 *   SQL_API/create, SQL_API/enable, SQL_API/get_id 
 * EXAMPLE
 *  mysql>
 *    call flexviews.disable(flexviews.get_id('test','mv_example'))
 
******
*/

CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`disable`(
  IN v_mview_id INT UNSIGNED
)
  MODIFIES SQL DATA
  COMMENT 'Disable a materialized view'
BEGIN
  -- DECLARE v_mview_enabled tinyint(1);
  DECLARE v_mview_name TEXT character set utf8;
  DECLARE v_mview_schema TEXT character set utf8;
  DECLARE v_mview_refresh_type TEXT character set utf8;
  DECLARE v_mview_enabled INT;

  DECLARE v_child_mview_id INT;

  -- backup SESSION max_sp_recursion_depth
  DECLARE bkp_max_sp_recursion_depth INT UNSIGNED DEFAULT @@session.max_sp_recursion_depth;

  -- suppress DROP IF EXISTS warnings
  DECLARE CONTINUE HANDLER FOR 1051
  BEGIN END;
  call flexviews.update_refresh_step_info(v_mview_id,'DISABLE_BEGIN');

  SET max_sp_recursion_depth := 255;

  START TRANSACTION WITH CONSISTENT SNAPSHOT;

  SELECT mview_name, 
         mview_schema,
	 mview_enabled, 
         mview_refresh_type
    INTO v_mview_name, 
         v_mview_schema,
         v_mview_enabled,
         v_mview_refresh_type
    FROM flexviews.mview
   WHERE flexviews.mview.mview_id = v_mview_id;

   IF v_mview_refresh_type != 'COMPLETE' THEN 
     SELECT 'This procedure is deprecated.  Please use flexviews.DROP() to remove a view or flexviews.INVALIDATE() to mark it as invalid' as `WARNING` from dual;

     IF v_mview_id IS NULL THEN
       CALL flexviews.signal('The specified materialized view does not exist (NOTHING WAS DROPPED)');
     END IF;

     IF v_mview_enabled = FALSE OR v_mview_enabled is null THEN
       CALL flexviews.signal('This materialized view is already disabled (NOTHING WAS DROPPED)');
     END IF;

     SELECT mview_id
       INTO v_child_mview_id
       FROM flexviews.mview
      WHERE flexviews.mview.parent_mview_id = v_mview_id;

     IF v_child_mview_id IS NOT NULL THEN
       CALL flexviews.disable(v_child_mview_id);
     END IF;

     -- This will be committed by the DROP
     UPDATE flexviews.mview
        SET mview_last_refresh = NULL,
            mview_enabled = FALSE
      WHERE flexviews.mview.mview_id = v_mview_id;

     SET @v_sql = CONCAT('DROP TABLE IF EXISTS ', v_mview_schema, '.', v_mview_name);
     PREPARE drop_stmt FROM @v_sql; 
     EXECUTE drop_stmt;

     SELECT 'The materialized view was dropped sucessfully' as `MESSAGE` from dual;

     SET @v_sql = CONCAT('DROP TABLE IF EXISTS ', v_mview_schema, '.', v_mview_name, '_delta');
     PREPARE drop_stmt FROM @v_sql; 
     EXECUTE drop_stmt;

     SET @v_sql := NULL;
     DEALLOCATE PREPARE drop_stmt;
     SELECT 'The materialized view delta table was dropped sucessfully' as `MESSAGE` from dual;

     SELECT 'View is now disabled.' as `MESSAGE` from dual;
   END IF;
   -- restore SESSION max_sp_recursion_depth
   SET max_sp_recursion_depth := bkp_max_sp_recursion_depth;
   call flexviews.update_refresh_step_info(v_mview_id,'DISABLE_END');
END ;;

DELIMITER ;
