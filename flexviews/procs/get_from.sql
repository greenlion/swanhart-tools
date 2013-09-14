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

DROP FUNCTION IF EXISTS flexviews.get_from ;;

CREATE DEFINER=`flexviews`@`localhost`FUNCTION flexviews.`get_from`(
  v_mview_id INT,
  v_join_type TEXT,
  v_table_suffix TEXT
) RETURNS TEXT CHARSET latin1
    READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_table_name TEXT;
DECLARE v_mview_table_alias TEXT;
DECLARE v_mview_table_schema TEXT;
DECLARE v_mview_join_condition TEXT;
DECLARE v_from_clause TEXT default NULL;  
DECLARE cur_from CURSOR 
FOR  
SELECT mview_table_name,
       mview_table_schema,
       mview_table_alias,
       mview_join_condition
  FROM flexviews.mview_table t
 WHERE t.mview_id = v_mview_id
 ORDER BY IF(mview_join_condition IS NULL, 0, 1);

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

SET v_from_clause = '';

OPEN cur_from;  
fromLoop: LOOP    
  FETCH cur_from 
   INTO v_mview_table_name,
        v_mview_table_schema,
        v_mview_table_alias,
        v_mview_join_condition;
  
  IF v_done THEN      
    CLOSE cur_from;      
    LEAVE fromLoop;    
  END IF;    

  IF v_table_suffix != '' THEN
    SET v_mview_table_name = CONCAT(v_mview_table_name, v_table_suffix);
  END IF;

  SET v_from_clause = CONCAT(v_from_clause, ' ',
                             IF(v_mview_join_condition IS NULL AND v_from_clause = '' , '', v_join_type), ' ',
                             v_mview_table_schema, '.', v_mview_table_name, ' as ',
                             v_mview_table_alias, ' ',
                             IFNULL(v_mview_join_condition, '') );
END LOOP;

RETURN CONCAT('FROM', v_from_clause);
END ;;

DELIMITER ;
