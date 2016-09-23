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

DROP PROCEDURE IF EXISTS flexviews.copy;;

CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.copy(
  IN v_mview_id INT,
  IN v_schema VARCHAR(64) CHARACTER SET UTF8, 
  IN v_table VARCHAR(64) CHARACTER SET UTF8
)
  MODIFIES SQL DATA
  COMMENT 'Copies a materialized view into the new view'
BEGIN
/****f* UTIL_API/copy
 * NAME
 *   flexviews.asof - Copy a materialized view from one name to another
 * SYNOPSIS
 *   flexviews.asof(v_mview_id, 
                       v_when);
 * FUNCTION
 *   This function copies a view definition (and the contents, if enabled)
 *   of a given materialized view (by id) into the new given name
 * INPUTS
 *   * v_mview_id - The materialized view identifier
 *   * v_schema - schema into which new view is placed
 *   * v_table - new view name
 * SEE ALSO
 *   flexviews.enable
 * EXAMPLE
 *   mysql>
 *     set @mv_id = flexviews.get_id('test', 'mv_example');
 *     call flexviews.copy(@mv_id, 'test', 'new_mv_name');
******
*/

  start transaction;

  INSERT INTO flexviews.mview
  SELECT null,
         v_table, 
         v_schema ,  
         @is_enabled := mview_enabled, 
         mview_last_refresh , 
         mview_refresh_period , 
         mview_refresh_type , 
         mview_engine , 
         mview_definition , 
         incremental_hwm , 
         refreshed_to_uow_id , 
         null , 
         @signal_id, 
         'INVALID' 
   FROM flexviews.mview 
  WHERE mview_id = v_mview_id; 

  -- get the id of the new materialized view
  SET @new_id := LAST_INSERT_ID();

  INSERT INTO flexviews.mview_expression
  select null,
         @new_id,
         mview_expr_type , 
         mview_expression , 
         mview_alias , 
         mview_expr_order , 
         percentile
    from flexviews.mview_expression
  where mview_id = v_mview_id;

  INSERT INTO flexviews.mview_table
   select null,
          @new_id,
          mview_table_name , 
          mview_table_schema , 
          mview_table_alias , 
          mview_join_condition , 
          mview_join_order
     from flexviews.mview_table
  where mview_id = v_mview_id;

  if @is_enabled = 1 THEN
    select concat('`',mview_schema, '`.`',mview_name,'`')
      into @old_name
      from flexviews.mview 
     where mview_id = v_mview_id;

    set @new_name := CONCAT('`',v_schema,'`.`',v_table,'`');
    set @new_delta := CONCAT('`',v_schema,'`.`',v_table,'_delta`');

    set @v_sql := CONCAT('CREATE TABLE ', @new_name, ' like ', @old_name ) ;
    prepare stmt from @v_sql;
    execute stmt;
    deallocate prepare stmt;

    set @v_sql := CONCAT('CREATE TABLE ', @new_delta,' like ', @old_name ) ;
    prepare stmt from @v_sql;
    execute stmt;
    deallocate prepare stmt;

    -- fill the new table
    set @v_sql := CONCAT('INSERT INTO ' ,@new_name, ' SELECT * FROM ', @old_name );
    prepare stmt from @v_sql;
    execute stmt;
    deallocate prepare stmt;
  end if;

  COMMIT;
  
END ;;

DELIMITER ;
