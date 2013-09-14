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

DROP PROCEDURE IF EXISTS flexviews.`add_expr` ;;
/****f* SQL_API/add_expr
 * NAME
 *   flexviews.add_expr - Add an expression or indexes to a materialized view.
 * SYNOPSIS
 *   flexviews.add_expr(v_mview_id, v_expr_type, v_expression, v_alias) 
 * FUNCTION
 *   This function adds an expression or indexes to the materialized view 
 *   definition.  This function may normally be called only on disabled materialized views.
 *   The exception are KEY and UNIQUE index expressions, which may be added at any time.
 *
 *   The expression specified in v_expression is added to the materialized view.
 *   If your view will be using aggregate functions, then you should specify the 'GROUP' type for the  non-agg columns.
 *   If your view does not use aggregation, then each expression should be of type 'COLUMN'.
 *   In the expression, any references to table columns must be fully qualified with the TABLE_ALIAS that you
 *   specified in the flexviews.ADD_TABLE() call.  For example, if you added a table with alias 'T', then when you reference
 *   a column in that table, you must prefix the alias:
 *   call flexviews.add_expr(flexviews.get_id('test','mv1'),'GROUP','t.c1', 'group_on_t_c1');
 *
 *   WHERE expressions are added to the WHERE clause of the view.
 *
 *   The UNIQUE and KEY expressions represent indexes on the materialized view table. Note that UNIQUE and KEY expressions do not reference base table columns, but instead you must specify one or more expression aliases.  This is similar to the use of table aliases in expressions (see above).  See the examples below for more info.
 *
 * INPUTS
 *   * v_mview_id -- The materialized view id (see flexviews.get_id)
 *   * v_expr_type -- GROUP|BIT_AND|BIT_OR|BIT_XOR|COLUMN|SUM|COUNT|MIN|MAX|AVG|COUNT_DISTINCT|UNIQUE|KEY|PERCENTILE|STDDEV_POP|STDDEV_SAMP|VAR_SAMP|VAR_POP
 *   * v_expr -- The expression to add.  
 *      Any columns in the expression must be prefixed with a table alias created with flexviews.add_table.  
 *      When the UNIQUE or KEY expression types are used, the user must specify one more more aliases to
 *      index.  These are normally aliases to GROUP expressions.
 *   * v_alias -- Every expression must be given a unique alias in the view, which becomes the
 *      name of the column in the materialized view. For UNIQUE and KEY expressions, this will be
 *      the name of the index in the view.  You must NOT use any reserved words in this name. 
 *
 *  NOTES
 *  Possible values of v_expr_type (a string value):
 *   |html <table border=1 align=center><tr><th bgcolor=#cccccc >EXPR_TYPE</th><th bgcolor=#cccccc>Explanation</th></tr>
 *   |html <tr><td>GROUP</td><td>GROUP BY this expression.
 *   |html <tr><td>COLUMN</td><td>Simply project this expression.  Only works for views without aggregation.    
 *   |html </tr><tr><td>COUNT<td>Count rows or expressions
 *   |html </tr><tr><td>SUM<td>SUM adds the value of each expression.  SUM(distinct) is not yet supported.
 *   |html </tr><tr><td>MIN<td>MIN (uses auxilliary view)
 *   |html </tr><tr><td>MAX<td>MAX (uses auxilliary view)
 *   |html </tr><tr><td>AVG<td>AVG (adds SUM and COUNT expressions automatically)
 *   |html </tr><tr><td>COUNT_DISTINCT<td>COUNT(DISTINCT) (uses auxilliary view)
 *   |html </tr><tr><td>STDDEV_POP<td>Standard deviation population(uses auxilliary view)
 *   |html </tr><tr><td>STDDEV_SAMP<td>Standard deviation sample(uses auxilliary view)
 *   |html </tr><tr><td>VAR_POP<td>Variance population (uses auxilliary view)
 *   |html </tr><tr><td>VAR_SAMP<td>Variance sample (uses auxilliary view)
 *   |html </tr><tr><td>GROUP_CONCAT<td>Group concatenation - NOT YET SUPPORTED (uses auxilliary view)
 *   |html </tr><tr><td>BIT_AND<td>BIT_AND(uses auxilliary view)
 *   |html </tr><tr><td>BIT_OR<td>BIT_OR(uses auxilliary view)
 *   |html </tr><tr><td>BIT_XOR<td>BIT_XOR(uses auxilliary view)
 *   |html </tr><tr><td>KEY<td>Adds an index to the view.  Specify column aliases in v_expr.
 *   |html </tr><tr><td>PERCENTILE_##<td>Adds a percentile calculation to the view. 
 *   |html </tr></table>
 *   
 
 * SEE ALSO
 *   flexviews.enable, flexviews.add_table, flexviews.disable
 * EXAMPLE
 *   mysql>
 *     set @mv_id = flexviews.get_id('test', 'mv_example');
 *     call flexviews.add_table(@mv_id, 'schema', 'table', 'an_alias', NULL);
 *
 *     #add a GROUP BY 
 *     call flexviews.add_expr(@mv_id, 'GROUP', 'an_alias.c1', 'c1');
 *
 *     #add a SUM
 *     call flexviews.add_expr(@mv_id, 'SUM', 'an_alias.c2', 'sum_c2');
 *
 *     # add indexes 
 *     call flexviews.add_expr(@mv_id, 'KEY', 'c1,sum_c2', 'key2');
 *     call flexviews.add_expr(@mv_id, 'UNIQUE', 'alias1,alias2,alias3', 'index_name');
 *
 *     # calculate the 95th percentile of an expression
 *     call flexviews.add_expr(@mv_id, 'PERCENTILE_95', 'c1', 'pct_95');
******
*/

CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`add_expr`(
  IN v_mview_id INT,
  IN v_mview_expr_type varchar(50),
  IN v_mview_expression TEXT,
  IN v_mview_alias TEXT
)
BEGIN
  DECLARE v_error BOOLEAN default false;
  DECLARE v_mview_enabled BOOLEAN default NULL;
  DECLARE v_mview_refresh_type TEXT;
  DECLARE v_percentile INT;
  DECLARE v_mview_fqn TEXT;

  DECLARE v_mview_expr_order INT;
  DECLARE CONTINUE HANDLER FOR SQLSTATE '01000' SET v_error = true;
  SELECT IFNULL(mview_enabled,false),
         mview_refresh_type,
         concat(mview_schema, '.', mview_name)
    INTO v_mview_enabled,
         v_mview_refresh_type,
         v_mview_fqn
    FROM flexviews.mview
   WHERE mview_id = v_mview_id;

  SET max_sp_recursion_depth = 2;

  IF v_mview_enabled IS NULL THEN
    SELECT 'FAILURE: The specified materialized view does not exist.' as message;
  ELSEIF v_mview_enabled = 1 AND v_mview_refresh_type = 'INCREMENTAL' AND ( v_mview_expr_type != 'KEY' AND v_mview_expr_type != 'UNIQUE' )  THEN
    SELECT 'FAILURE: The specified materialized view is enabled.  INCREMENTAL refresh materialized views may not be modified after they have been enabled.' as message;
  ELSE

    SET v_percentile := NULL;

    IF v_mview_expr_type like 'PERCENTILE%' THEN
      SET v_percentile = RIGHT(v_mview_expr_type, 2);
      IF SUBSTR(v_percentile, 1, 1) = "_" THEN
        SET v_percentile := RIGHT(v_percentile, 1);
      END IF;

      SET v_mview_expr_type := 'PERCENTILE';

      select 'PERCENTILE:', v_percentile;

    END IF;

    SELECT IFNULL(max(mview_expr_order), 0)+1
      INTO v_mview_expr_order
      FROM flexviews.mview_expression
     WHERE mview_id=v_mview_id;

      INSERT INTO flexviews.mview_expression
      (  mview_id,
         mview_expr_type,
         mview_expression,
         mview_alias,
         mview_expr_order, 
         percentile )
      VALUES
      (  v_mview_id,
         v_mview_expr_type,
         v_mview_expression,
         v_mview_alias,
         v_mview_expr_order, 
         v_percentile );
     if (v_error != false) then
       select concat('Invalid expression type: ', v_mview_expr_type,'  Available expression types: ', column_type) as 'error'
         from information_schema.columns 
        where table_name='mview_expression'
          and table_schema='flexviews'
          and column_name='mview_expr_type';
     end if;
  END IF;

  IF v_mview_expr_type = 'GROUP' or  v_mview_expr_type = 'COUNT' THEN
    call flexviews.add_expr(v_mview_id, 'KEY', v_mview_alias, concat('key_', v_mview_alias)); 
  END IF;

  IF v_mview_enabled = 1 AND ( v_mview_expr_type = 'KEY' OR v_mview_expr_type = 'UNIQUE' )  THEN

    SET @v_sql = CONCAT('ALTER TABLE ', v_mview_fqn, ' ADD ', v_mview_expr_type, '(', v_mview_expression, ')'); 
    PREPARE alter_stmt from @v_sql;
    EXECUTE alter_stmt;
    DEALLOCATE PREPARE alter_stmt;

  END IF;
  

END ;;

DELIMITER ;
