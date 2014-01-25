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
 *  If the materialized view cannot be created, an error is produced.
 *  If @fv_force is set to TRUE, the creation is forced and no error is issued, whenever possible.
 * SEE ALSO
 *  SQL_API/enable, SQL_API/add_table, SQL_API/add_expr
 * EXAMPLE
 *   mysql>
 *     call flexviews.create('test', 'mv_example', 'INCREMENTAL');
 *     call flexviews.create('test', 'another_example', 'COMPLETE');
******
*/

CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`create`(
  IN v_mview_schema TEXT CHARACTER SET UTF8,
  IN v_mview_name TEXT CHARACTER SET UTF8,
  IN v_mview_refresh_type ENUM('INCREMENTAL','COMPLETE')
)
  MODIFIES SQL DATA
  COMMENT 'Create a MVIEW skeleton'
BEGIN
  DECLARE EXIT HANDLER
    FOR 1062
  BEGIN
    SET @fv_force := NULL;
    CALL flexviews.fv_raise('ERROR', 31005,
        CONCAT_WS('', 'Materialized view already exists: ', v_mview_name, '.', v_mview_schema));
  END;
  
  -- validate input:
  -- ENUM is not enforced if SQL_MODE is not strict
  IF v_mview_refresh_type IS NULL OR v_mview_refresh_type NOT IN ('INCREMENTAL', 'COMPLETE') THEN
    SET @fv_force := NULL;
	CALL flexviews.fv_raise('ERROR', 31006, 'Invalid refresh type');
  END IF;
  -- schema MUST exist
  IF NOT @fv_force <=> TRUE AND NOT `flexviews`.`schema_exists`(v_mview_schema) THEN
    SET @fv_force := NULL;
    CALL flexviews.fv_raise('ERROR', 31007,
        CONCAT_WS('', 'No such schema: ', v_mview_schema));
  END IF;
  -- table MUST NOT exist
  IF NOT @fv_force <=> TRUE AND `flexviews`.`table_exists`(v_mview_schema, v_mview_name) THEN
    SET @fv_force := NULL;
    CALL flexviews.fv_raise('ERROR', 31008,
        CONCAT_WS('', 'Table exists: ', v_mview_name, '.', v_mview_schema));
  END IF;

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

  -- generic error
  IF NOT ROW_COUNT() = 1 THEN
	SET @fv_force := NULL;
	CALL flexviews.fv_raise('ERROR', 31009, CONCAT_WS('', 'Could not create materialized view: ', v_mview_name));
  END IF;

  SET @fv_force := NULL;
END ;;

DELIMITER ;
