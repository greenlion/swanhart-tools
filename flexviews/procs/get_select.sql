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

DROP FUNCTION IF EXISTS flexviews.`get_select`;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.`get_select`(  v_mview_id INT, v_mode varchar(10), v_prefix varchar(50)) RETURNS TEXT CHARSET latin1
    READS SQL DATA
BEGIN  
DECLARE v_done boolean DEFAULT FALSE;  
DECLARE v_mview_expr_type varchar(100);  
DECLARE v_mview_expression TEXT; 
DECLARE v_mview_alias varchar(100);  
DECLARE v_select_list TEXT default NULL;  
DECLARE v_mview_alias_prefixed TEXT; 
DECLARE v_percentile INT;

DECLARE cur_select CURSOR 
FOR  
SELECT mview_expr_type, 
       mview_expression, 
       mview_alias,
       percentile
  FROM flexviews.mview_expression m
 WHERE m.mview_id = v_mview_id
   AND m.mview_expr_type in ('COLUMN','COUNT_DISTINCT','MIN','MAX', 'AVG', 'SUM', 'GROUP','COUNT','PERCENTILE','STDDEV_POP','STDDEV_SAMP','VAR_POP','VAR_SAMP','BIT_AND','BIT_OR','BIT_XOR','GROUP_CONCAT')
   AND NOT (m.mview_expr_type = 'COLUMN' and trim(mview_expression) = '*') 
 ORDER BY mview_expr_order;  

DECLARE CONTINUE HANDLER FOR  SQLSTATE '02000'    
    SET v_done = TRUE;  

OPEN cur_select;  

selectLoop: LOOP    
  FETCH cur_select 
   INTO v_mview_expr_type,
        v_mview_expression,
        v_mview_alias, 
        v_percentile;
  
  IF v_done THEN      
    CLOSE cur_select;      
    LEAVE selectLoop;    
  END IF;    
  
  IF v_select_list IS NOT NULL THEN      
    SET v_select_list = CONCAT(v_select_list, ', ');    
  ELSE      
    SET v_select_list = '';    
  END IF;    
/*
  IF SUBSTR(v_mview_alias,1,1) != '`' THEN
     SET v_mview_alias = CONCAT('`',v_mview_alias,'`');
  END IF;
*/

  IF v_prefix IS NOT NULL AND v_prefix != '' THEN
    SET v_mview_alias_prefixed = CONCAT(v_prefix, '.', v_mview_alias);
  END IF;

  SET v_mview_alias_prefixed = v_mview_alias;


--  IF v_mode = 'LOG' THEN
--    SET v_mview_expression = CONCAT('(IFNULL(', IF(v_mview_expression = '*', 1, v_mview_expression), ', 0))');
--  ELSE 
    SET v_mview_expression = CONCAT('(', v_mview_expression, ')');
--  END IF;

  IF v_mode = 'CREATE' THEN      

      IF v_mview_expr_type = 'GROUP' OR v_mview_expr_type = 'COLUMN' THEN
        SET v_mview_expr_type = '';
      ELSE 
        IF v_mview_expr_type = 'COUNT_DISTINCT' THEN
	  SET v_mview_expr_type = 'COUNT(DISTINCT ';
          SET v_mview_expression = CONCAT(v_mview_expression, ')');
        END IF;

        -- percentile calculation is complex, so its construction is in its own function
        IF v_mview_expr_type = 'PERCENTILE' THEN
          SET v_mview_expr_type := ''; 
          SET v_mview_expression := CONCAT( '(', flexviews.get_percentile(v_mview_expression, v_percentile), ')' );
        END IF;
      END IF;

      SET v_select_list = CONCAT(v_select_list, v_mview_expr_type, v_mview_expression, ' as ', v_mview_alias);      

      -- ADD sum and count for AVG expressions
      IF v_mview_expr_type = 'AVG' THEN        
        SET v_select_list = CONCAT(v_select_list, ',SUM', v_mview_expression, ' as ', v_mview_alias, '_sum' );        
        SET v_select_list = CONCAT(v_select_list, ',COUNT', v_mview_expression, ' as ', v_mview_alias, '_cnt' );      
      END IF;      
      ITERATE selectLoop;    
    ELSE
        IF v_mview_expr_type  = 'COUNT' THEN      
                IF v_mode = 'UNION' THEN        
                        SET v_select_list = CONCAT(v_select_list, 'SUM(', v_mview_alias, ') as ', v_mview_alias);        
                        ITERATE selectLoop;      
                END IF;       
                IF v_mode = 'LOG' THEN   
                        IF (TRIM(v_mview_expression)  = '(*)') THEN          
                                SET v_mview_expression  = 'mview_type';        
                        ELSE          
                                SET v_mview_expression  = CONCAT('IF(',v_mview_expression,' IS NULL,0,mview_type');        
                        END IF;        
                        SET v_select_list = CONCAT(v_select_list, 'SUM(', v_mview_expression, ') as ', v_mview_alias);        
                        ITERATE selectLoop;      
                END IF;      
                IF v_mode = 'VIEW' THEN        
                        SET v_select_list = CONCAT(v_select_list, v_mview_alias_prefixed); 
                        ITERATE selectLoop;      
                END IF;   
        END IF;    


        IF v_mview_expr_type   = 'GROUP' OR
           v_mview_expr_type   = 'COLUMN' OR
           v_mview_expr_type   = 'MIN' OR
           v_mview_expr_type   = 'MAX' OR 
           v_mview_expr_type   = 'SUM' OR
           v_mview_expr_type   = 'COUNT_DISTINCT' OR
	   v_mview_expr_type   = 'STDDEV_POP' OR
	   v_mview_expr_type   = 'STDDEV_SAMP' OR
           v_mview_expr_type   = 'VAR_POP' OR
           v_mview_expr_type   = 'VAR_SAMP' OR
           v_mview_expr_type   = 'BIT_AND' OR
           v_mview_expr_type   = 'BIT_OR' OR
           v_mview_expr_type   = 'BIT_XOR' OR
           v_mview_expr_type   = 'GROUP_CONCAT' THEN

                IF v_mview_expr_type = 'GROUP' OR v_mview_expr_type = 'COLUMN' THEN
                  SET v_mview_expr_type = '';
                END IF;
 
                IF v_mview_expr_type = 'PERCENTILE' THEN
                  SET v_mview_expr_type := '';
                  SET v_mview_expression := CONCAT( '(', flexviews.get_percentile(v_mview_expression, v_percentile), ')' );
                END IF;


                IF v_mode = 'UNION' THEN        
                        SET v_select_list = CONCAT(v_select_list, v_mview_expr_type, '(', v_mview_alias, ') as ', v_mview_alias);        
                        ITERATE selectLoop;      
                END IF;      
                IF v_mode = 'LOG' THEN        
                        IF v_mview_expr_type != '' THEN
                          IF v_mview_expr_type != 'MIN' AND
                             v_mview_expr_type != 'MAX' AND
                             v_mview_expr_type != 'STDDEV_POP' AND 
                             v_mview_expr_type != 'STDDEV_SAMP' AND
                             v_mview_expr_type != 'VAR_POP' AND 
                             v_mview_expr_type != 'VAR_SAMP' AND 
                             v_mview_expr_type != 'BIT_AND' AND 
                             v_mview_expr_type != 'BIT_OR' AND 
                             v_mview_expr_type != 'BIT_XOR' AND 
                             v_mview_expr_type != 'PERCENTILE' AND
                             v_mview_expr_type != 'GROUP_CONCAT' THEN
                            SET v_select_list = CONCAT(v_select_list, v_mview_expr_type, '(mview_type * ', v_mview_expression,') as ', v_mview_alias);         
                          ELSE
                            SET v_select_list = CONCAT(v_select_list, ' 0 as ', v_mview_alias);  
                          END IF;
                        ELSE
                          SET v_select_list = CONCAT(v_select_list, v_mview_expression, ' as ', v_mview_alias);
                        END IF;
                        ITERATE selectLoop;      
                END IF;      
                IF v_mode = 'VIEW' THEN        
                        IF v_prefix is NOT NULL AND v_prefix != '' THEN
                          SET v_mview_alias = CONCAT(v_prefix,'.',v_mview_alias);
                        END IF;
                        SET v_select_list = CONCAT(v_select_list, v_mview_alias);        
                        ITERATE selectLoop;      
                END IF;    
        END IF;    
        IF v_mview_expr_type = 'AVG' THEN      
                IF v_mode = 'UNION' THEN
                        SET v_select_list = CONCAT(v_select_list, '(SUM(', v_mview_alias, '_sum) / SUM(', v_mview_alias, '_cnt)) as ', v_mview_alias, ', SUM(', v_mview_alias, '_sum ) as ', v_mview_alias, '_sum, SUM(' , v_mview_alias, '_cnt) as ', v_mview_alias, '_cnt');
                        ITERATE selectLoop;
                END IF;
                IF v_mode = 'LOG' THEN
                        SET v_select_list = CONCAT(v_select_list, 'SUM(IF(mview_type<0,-1*', v_mview_expression, ',',  v_mview_expression,')) as ', v_mview_alias, '_sum', ',SUM(IF(mview_type<0,-1,1)) as ', v_mview_alias, '_cnt');   
                        ITERATE selectLoop;
                END IF;
                IF v_mode = 'VIEW' THEN        
                        SET v_select_list = CONCAT(v_select_list,   v_mview_alias_prefixed, '_sum, ', v_mview_prefixed, '_cnt');        
                        ITERATE selectLoop;      
                END IF;    
        END IF;    
    END IF;
    SET v_select_list = CONCAT(v_select_list, ' ');   
END LOOP;  

-- IF v_mode = 'CREATE' THEN
  RETURN CONCAT('SELECT ',if(@flex_option is NULL,'',@flex_option),' NULL as mview$pk, ',v_select_list);
-- else
--  RETURN CONCAT('SELECT , ',v_select_list);

-- end if;
END ;;

DELIMITER ;

