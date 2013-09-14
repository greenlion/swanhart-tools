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

DROP FUNCTION IF EXISTS flexviews.`get_keys` ;;

CREATE DEFINER=`flexviews`@`localhost` FUNCTION flexviews.`get_keys`(v_mview_id INT) RETURNS TEXT CHARSET latin1
READS SQL DATA
BEGIN
  DECLARE v_done boolean DEFAULT FALSE;
  DECLARE v_mview_expr_type VARCHAR(50);
  DECLARE v_mview_expression TEXT default NULL;
  DECLARE v_mview_alias TEXT;
  DECLARE v_key_list TEXT default '';
  DECLARE v_mview_refresh_type TEXT;
  DECLARE v_parent_mview_id INT;

  DECLARE cur_expr CURSOR FOR
  SELECT mview_expression, 
         mview_alias, 
         parent_mview_id
    FROM flexviews.mview_expression 
    JOIN flexviews.mview USING (mview_id)
   WHERE mview_expr_type = v_mview_expr_type 
     AND mview_id = v_mview_id
   ORDER BY mview_expr_order;
  
  DECLARE CONTINUE HANDLER FOR
  SQLSTATE '02000'
    SET v_done = TRUE;

  -- Is an explicit PRIMARY key defined?
  SET v_mview_expr_type='PRIMARY';
  OPEN cur_expr;
  exprLoop: LOOP
    FETCH cur_expr INTO 
      v_mview_expression,
      v_mview_alias,
      v_parent_mview_id;

    IF v_done THEN
      CLOSE cur_expr;
      LEAVE exprLoop;
    END IF;
  END LOOP;

  SET v_mview_expr_type=NULL;

  SET v_done=FALSE; 
    -- a mview can't have both COLUMN expressions and GROUP BY expressions....
    -- so figure out which one this one uses.
  SELECT MIN(mview_expr_type)
    INTO v_mview_expr_type
    FROM flexviews.mview_expression
   WHERE mview_expr_type = 'GROUP';

   OPEN cur_expr;

   exprLoop: LOOP
      FETCH cur_expr INTO
        v_mview_expression,
        v_mview_alias,
      v_parent_mview_id;

      IF v_done THEN 
         CLOSE cur_expr;
         LEAVE exprLoop;
      END IF;

      IF v_key_list != '' THEN
        SET v_key_list = CONCAT(v_key_list, ','); 
      END IF;
      SET v_key_list = CONCAT(v_key_list, v_mview_alias);
    END LOOP;

    IF v_key_list != '' THEN
       	SET v_key_list = CONCAT('UNIQUE (', v_key_list, ')');
    END IF;

  SET v_mview_expr_type = 'KEY';
  SET v_done=FALSE;
  OPEN cur_expr;

  exprLoop: LOOP
    FETCH cur_expr INTO
      v_mview_expression,
      v_mview_alias,
      v_parent_mview_id;
    
    IF v_done THEN
       CLOSE cur_expr;
       LEAVE exprLoop;
    END IF;

    IF v_key_list != '' THEN
      SET v_key_list = CONCAT(v_key_list, ',');
    END IF;
    SET v_key_list = CONCAT(v_key_list, 'KEY ', v_mview_alias, '(', v_mview_expression, ')');
  END LOOP;

  SET v_mview_expr_type = 'UNIQUE';
  SET v_done=FALSE;
  OPEN cur_expr;

  exprLoop: LOOP
    FETCH cur_expr INTO
      v_mview_expression,
      v_mview_alias,
      v_parent_mview_id;
    
    IF v_done THEN
       CLOSE cur_expr;
       LEAVE exprLoop;
    END IF;

    IF v_key_list != '' THEN
      SET v_key_list = CONCAT(v_key_list, ',');
    END IF;
    SET v_key_list = CONCAT(v_key_list, 'UNIQUE ', v_mview_alias, '(', v_mview_expression, ')');
  END LOOP;

  -- IF v_key_list = '' THEN
  --  SET v_key_list = CONCAT('mview$pk bigint auto_increment primary key');
  -- END IF;

  RETURN v_key_list;
END ;;

DELIMITER ;
