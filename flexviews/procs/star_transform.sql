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

DROP PROCEDURE IF EXISTS star_transform;


CREATE DEFINER=flexviews@localhost PROCEDURE  `star_transform`(
  IN v_mview_id INT
)
body: BEGIN
  DECLARE v_has_star tinyint;
  DECLARE v_column TEXT;
  DECLARE v_table_alias TEXT;
  DECLARE v_cnt smallint;
  DECLARE v_done BOOLEAN DEFAULT FALSE;
  DECLARE v_expr TEXT;
  DECLARE v_alias TEXT;

  DECLARE cur_columns CURSOR
  FOR
  SELECT columns.column_name,
         mt.mview_table_alias,
         sq.cnt
    FROM flexviews.mview_table mt
    JOIN information_schema.columns
      ON table_schema = mview_table_schema
     AND table_name = mview_table_name
     AND mview_id = v_mview_id
    JOIN ( select column_name, IFNULL(count(*),0)  cnt
             from information_schema.columns
             join flexviews.mview_table
               on table_name = mview_table_name
              and table_schema = mview_table_schema
            where mview_id = v_mview_id
            group by column_name
         ) sq
   USING (column_name)
   ORDER BY table_name, ordinal_position;
   
   DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET v_done=TRUE;

   SET v_done = false;

   SELECT IFNULL(count(*),0) has_star 
     INTO v_has_star
     FROM flexviews.mview_expression
    WHERE mview_id = v_mview_id
      AND mview_expr_type IN ('COLUMN','GROUP') 
      AND trim(mview_expression) = '*';

   IF v_has_star = 0 THEN
     LEAVE body;
   END IF;

   IF flexviews.has_aggregates(v_mview_id) THEN
     call flexviews.signal('AGGREGATE TABLE MAY NOT HAVE SELECT *');
   ELSE
     -- Just in case someone does add_expr(@mvid, 'GROUP', '*', 'star')
     UPDATE flexviews.mview_expression set mview_expr_type = 'COLUMN' where mview_expr_type = 'GROUP';
   END IF;
   DELETE from flexviews.mview_expression where mview_id = v_mview_id and mview_expr_type = 'COLUMN' and trim(mview_expression) != '*';
        
   OPEN cur_columns;
   columnLoop: LOOP
      FETCH cur_columns
       INTO v_column,
            v_table_alias,
            v_cnt;
 
      IF v_done THEN
        CLOSE cur_columns;
        LEAVE columnLoop;
      END IF;
      IF v_cnt > 1 THEN
         set v_expr = CONCAT('`',v_table_alias, '`.`', v_column, '`');
         set v_alias = CONCAT(v_table_alias, '$', v_column);
      ELSE
         set v_expr = v_column;
         set v_alias = v_column;
      END IF;

      CALL flexviews.add_expr(v_mview_id, 'COLUMN', v_expr, v_alias);
   END LOOP;
       
END ;;

DELIMITER ;
