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

DROP PROCEDURE IF EXISTS flexviews.`create_child_views` ;;

CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`create_child_views`(IN v_mview_id INT) 
BEGIN
  DECLARE v_new_mview_id INT;
  DECLARE v_needs_dependent_view BOOLEAN DEFAULT FALSE;


  SELECT count(*)
    INTO v_needs_dependent_view
    FROM flexviews.mview_expression
   WHERE mview_id = v_mview_id
     AND mview_expr_type in ('PERCENTILE','MIN','MAX','COUNT_DISTINCT', 'STDDEV_POP','VAR_POP', 'STDDEV_SAMP','VAR_SAMP','GROUP_CONCAT','BIT_AND','BIT_OR','BIT_XOR');

   -- Destroy any existing child materialization
   SET v_new_mview_id := flexviews.get_id(flexviews.get_setting('mvlog_db'), concat('mv$', v_mview_id));
   IF v_new_mview_id is not null and v_new_mview_id != 0 THEN
     CALL flexviews.disable(v_new_mview_id);
     DELETE from flexviews.mview_expression where mview_id = v_new_mview_id;
     DELETE from flexviews.mview_table where mview_id = v_new_mview_id;
     DELETE from flexviews.mview where mview_id = v_new_mview_id;
     SET v_new_mview_id := NULL;
   END IF;

  IF v_needs_dependent_view is not null and v_needs_dependent_view > 0 THEN

      -- Create the new view and set its parent to be the view we are enabling
      CALL flexviews.create(flexviews.get_setting('mvlog_db'), concat('mv$',v_mview_id),'INCREMENTAL');
      SET v_new_mview_id := LAST_INSERT_ID();
      UPDATE flexviews.mview set parent_mview_id = v_mview_id where mview_id = v_new_mview_id;


      -- Copy the tables into the new child mview
      REPLACE INTO flexviews.mview_table
      (mview_table_id, mview_id, mview_table_name, mview_table_schema, mview_table_alias, mview_join_condition, mview_join_order)
      SELECT NULL,
             v_new_mview_id,
             mview_table_name,
             mview_table_schema,
             mview_table_alias,
             mview_join_condition,
             mview_join_order
        FROM flexviews.mview_table
       WHERE mview_id = v_mview_id;


      -- Copy the GB projections and any selections (where clauses) to the new view 
      REPLACE INTO flexviews.mview_expression
      (mview_expression_id, mview_id, mview_expr_type, mview_expression, mview_alias, mview_expr_order)
      SELECT NULL,
	     v_new_mview_id,
             if(mview_expr_type not in ('PERCENTILE','MIN','MAX','COUNT_DISTINCT','STDDEV_POP','STDDEV_SAMP','VAR_POP','VAR_SAMP','BIT_AND','BIT_OR','BIT_XOR','GROUP_CONCAT'), mview_expr_type, 'GROUP'),
             mview_expression,
             mview_alias,         
             mview_expr_order
        FROM flexviews.mview_expression
       WHERE mview_id = v_mview_id
         AND mview_expr_type in ('GROUP','WHERE','PERCENTILE','MIN','MAX','COUNT_DISTINCT', 'STDDEV_POP','STDDEV_SAMP','VAR_SAMP','VAR_POP','BIT_AND','BIT_OR','BIT_XOR','GROUP_CONCAT');

       -- Add a COUNT(*) as `CNT` since it would be added automatically anyway
       CALL flexviews.add_expr(v_new_mview_id, 'COUNT', '*', 'CNT');

       -- CALL flexviews.add_expr(v_new_mview_id, 'UNIQUE', flexviews.get_delta_aliases(v_new_mview_id,'',TRUE), 'UK');

       -- Build the new view (this could be recursive...)
       SET max_sp_recursion_depth=999;
       SET unique_checks=off;
       CALL flexviews.enable(v_new_mview_id);
       SET unique_checks=on;
	
  END IF;
END ;;

DELIMITER ;
