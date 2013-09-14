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
DROP PROCEDURE IF EXISTS flexviews.`add_table` ;;

/****f* SQL_API/add_table
 * NAME
 *   flexviews.add_table - Add a table to the FROM clause of the materialized view.
 * SYNOPSIS
 *   flexviews.add_table(v_mview_id, v_table_schema, v_table_name, v_table_alias, v_join_clause);
 * FUNCTION
 * This function adds a table to the FROM clause of the materialized view.  
 * INPUTS
 *   * v_mview_id     - The materialized view id (see flexviews.get_id)
 *   * v_table_schema - The schema which contains the table to add
 *   * v_table_name   - The name of the table to add
 *   * v_table_alias  - The table alias to use in the view.  All tables MUST have an alias.
 *   * v_join_clause  - Every table after the first must have a NOT-NULL join clause
 * NOTES
 *   * For views with a single table, or for the first table on a view with joins, the last paramter of the function will be NULL.
 *   * Additional tables MUST provide a valid ON or USING clause.  CROSS JOIN/cartesian products ARE NOT SUPPORTED.  
 *   * All expressions used in an ON clause must be prefixed with a valid table alias!  
 * RESULT
 * An error will be generated in the MySQL client if:
 * * The table does not exist
 * * There is no materialized view log on the table 
 * SEE ALSO
 *   flexviews.disable, flexviews.get_id
 * EXAMPLE
 *    mysql>
 *     set @mv_id = flexviews.get_id('test', 'mv_example');
 *     call flexviews.add_table(@mv_id, 'schema', 'table', 'an_alias', NULL);
 *     call flexviews.add_table(@mv_id, 'schema', 'table2', 'a2', 'ON an_alias.c1 = a2.c1');
******
*/
CREATE DEFINER=`flexviews`@`localhost` PROCEDURE `flexviews`.`add_table`(
  IN v_mview_id INT,
  IN v_mview_table_schema TEXT,
  IN v_mview_table_name TEXT, 
  IN v_mview_table_alias TEXT,
  IN v_mview_join_condition TEXT
)
BEGIN

  IF v_mview_id IS NULL OR v_mview_id = 0 THEN
    CALL flexviews.signal('INVALID_MVIEW_ID');
  END IF;

  IF flexviews.is_enabled(v_mview_id) = 1 THEN
    CALL flexviews.signal('MAY_NOT_MODIFY_ENABLED_MVIEW');
  END IF;

  SET @v_exists = false;

  SELECT true
    INTO @v_exists
    FROM information_schema.tables
   WHERE table_name = v_mview_table_name
     AND table_schema = v_mview_table_schema
   LIMIT 1;

  if @v_exists != true then
    call flexviews.signal('NO_SUCH_TABLE'); 
  end if;

  SET @v_exists = false;

  SELECT true
    INTO @v_exists
    FROM flexviews.mvlogs
   WHERE table_name = v_mview_table_name
     AND table_schema = v_mview_table_schema
     AND active_flag = true
   LIMIT 1;

  if @v_exists != true  then
    call flexviews.signal('NO_CHANGELOG_ON_TABLE'); 
  end if;

  INSERT INTO flexviews.mview_table
  (  mview_id,
     mview_table_name,
     mview_table_schema,
     mview_table_alias, 
     mview_join_condition )
  VALUES
  (  v_mview_id,
     v_mview_table_name,
     v_mview_table_schema, 
     v_mview_table_alias, 
     v_mview_join_condition );

END ;;

DELIMITER ;
