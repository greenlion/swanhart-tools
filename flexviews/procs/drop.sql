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

DROP PROCEDURE IF EXISTS flexviews.drop;;
/****f* SQL_API/drop
 * NAME
 *   flexviews.drop - Drop the materialized view table.  
 * SYNOPSIS
 *   flexviews.drop(v_mview_id);
 * FUNCTION
 *   This function drops the table holding the rows for the materialized
 *   view and also the delta table with the view.  Any automatically maintained
 *   child views are also dropped.  
 * DANGER 
 *   The table is dropped as soon as this command is issued.
 * INPUTS
 *   v_mview_id - The materialized view id 
 * RESULT
 *   An error will be generated in the MySQL client if the view can not be dropped .
 * NOTES
 *   The dictionary information is not removed, instead the metadata is updated to reflect the disabled status.
 * SEE ALSO
 *   SQL_API/create, SQL_API/enable, SQL_API/get_id 
 * EXAMPLE
 *  mysql>
 *    call flexviews.drop(flexviews.get_id('test','mv_example'))
 
******
*/

CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`drop`(
  IN v_mview_id INT UNSIGNED
)
  MODIFIES SQL DATA
  COMMENT 'Physically remove the view and mark as disabled in metadata.'
`this_proc`:
BEGIN
  -- DECLARE v_mview_enabled tinyint(1);
  DECLARE v_mview_name TEXT character set utf8;
  DECLARE v_mview_schema TEXT character set utf8;
  DECLARE v_mview_enabled INT;
  DECLARE v_child_mview_id INT;
  DECLARE v_parent_mview_id INT DEFAULT NULL;
  DECLARE v_condition_level ENUM('WARNING', 'ERROR') DEFAULT IF(@fv_force = TRUE, 'WARNING', 'ERROR');
  DECLARE v_force BOOL DEFAULT (@fv_force = TRUE);

  -- backup SESSION max_sp_recursion_depth
  DECLARE bkp_max_sp_recursion_depth INT UNSIGNED DEFAULT @@session.max_sp_recursion_depth;

  -- suppress DROP IF EXISTS warnings
  DECLARE CONTINUE HANDLER FOR 1051
  BEGIN END;

  SET max_sp_recursion_depth := 255;

  START TRANSACTION WITH CONSISTENT SNAPSHOT;

  SELECT mv1.mview_name, 
         mv1.mview_schema,
	 mv1.mview_enabled,
	 mv1.parent_mview_id,
         mv2.mview_id
    INTO v_mview_name, 
         v_mview_schema,
         v_mview_enabled,
	 v_parent_mview_id,
         v_child_mview_id
    FROM flexviews.mview mv1
    LEFT JOIN flexviews.mview mv2
      ON mv1.mview_id = mv2.parent_mview_id
   WHERE mv1.mview_id = v_mview_id;

   IF v_mview_id IS NULL OR NOT EXISTS (SELECT TRUE FROM `flexviews`.`mview` WHERE `mview_id` = v_mview_id) THEN
     IF NOT flexviews.table_exists(v_mview_schema, v_mview_name) THEN
       CALL flexviews.fv_raise(v_condition_level, 31010,
         CONCAT_WS('', 'No such MVIEW: ', v_mview_id));
       LEAVE `this_proc`;
     ELSEIF v_force IS NULL OR v_force <> TRUE THEN
       CALL flexviews.fv_raise('ERROR', 31011, 'TABLE EXISTS. POSSIBLE METADATA SYNC ISSUE. DANGER: set @fv_force=true to actually DROP the objects if desired');
     END IF;
   END IF;

   IF NOT v_force <=> TRUE AND (v_mview_enabled = FALSE OR v_mview_enabled IS NULL) THEN
     IF NOT flexviews.table_exists(v_mview_schema, v_mview_name) THEN
       CALL flexviews.fv_raise(v_condition_level, 31012, CONCAT_WS('', 'MVIEW is already disabled: ', v_mview_id));
       LEAVE `this_proc`;
     ELSEIF v_force IS NULL OR v_force <> TRUE THEN
       CALL flexviews.fv_raise('ERROR', 31011, 'TABLE EXISTS. POSSIBLE METADATA SYNC ISSUE. DANGER: set @fv_force=true to actually DROP the objects if desired');
     END IF;
   END IF;

   -- start the transaction on the parent view.  the parent and child will both get marked
   -- disabled when the first child table is dropped, or if there is no child view, when
   -- the first parent table is dropped
   IF v_parent_mview_id IS NULL THEN
     START TRANSACTION;
   END IF;

   UPDATE flexviews.mview
      SET mview_last_refresh = NULL,
          mview_enabled = FALSE
    WHERE mview_id = v_mview_id;

   IF v_child_mview_id IS NOT NULL THEN
     CALL flexviews.drop(v_child_mview_id);
   END IF;

   SET @v_sql = CONCAT('DROP TABLE IF EXISTS ', v_mview_schema, '.', v_mview_name);
   PREPARE drop_stmt FROM @v_sql; 
   EXECUTE drop_stmt;

   SET @v_sql = CONCAT('DROP TABLE IF EXISTS ', v_mview_schema, '.', v_mview_name, '_delta');
   PREPARE drop_stmt FROM @v_sql; 
   EXECUTE drop_stmt;

   SET @v_sql := NULL;
   DEALLOCATE PREPARE drop_stmt;

   IF v_parent_mview_id IS NOT NULL THEN
     if @fv_silent != 1 THEN
       SELECT 'The view, the view delta and the child views (if any) have now been removed' as `MESSAGE` from dual;
     end if;
   END IF;
 
   -- restore SESSION max_sp_recursion_depth
   SET max_sp_recursion_depth := bkp_max_sp_recursion_depth;
   SET @fv_force := NULL;
   SET @fv_silent := NULL;
  
END ;;

DELIMITER ;
