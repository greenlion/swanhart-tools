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

DROP FUNCTION IF EXISTS flexviews.get_delta_groupby;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_delta_groupby(
  v_mview_id INT
) RETURNS TEXT CHARSET latin1
    READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_expr_type varchar(100);  
DECLARE v_mview_expression varchar(100); 
DECLARE v_mview_alias varchar(100);  
DECLARE v_group_list TEXT default '';  
DECLARE v_mview_alias_prefixed varchar(150);
DECLARE cur_select CURSOR 
FOR  
SELECT mview_expr_type, 
       mview_expression, 
       mview_alias
  FROM flexviews.mview_expression m
 WHERE m.mview_id = v_mview_id
   AND m.mview_expr_type  = 'GROUP'
 ORDER BY mview_expr_order;  

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  
OPEN cur_select;  
selectLoop: LOOP    
  FETCH cur_select 
   INTO v_mview_expr_type,
        v_mview_expression,
        v_mview_alias;
  
  IF v_done THEN      
    CLOSE cur_select;      
    LEAVE selectLoop;    
  END IF;    

  IF v_group_list != '' THEN
    SET v_group_list = CONCAT(v_group_list, ', ');
  END IF;

  SET v_group_list = CONCAT(v_group_list, '(', v_mview_expression,')');
END LOOP;

RETURN CONCAT(v_group_list);

END ;;

DELIMITER ;
