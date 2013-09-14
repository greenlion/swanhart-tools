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

DROP PROCEDURE IF EXISTS flexviews.get_trigger_body;;

CREATE DEFINER=`flexviews`@`localhost` PROCEDURE flexviews.`get_trigger_body`(
  IN v_table_name VARCHAR(50),
  IN v_schema_name VARCHAR(50),
  IN v_dml_type TINYINT(4),
  INOUT v_return VARCHAR(32000) 
)
BEGIN
    DECLARE v_done BOOLEAN DEFAULT FALSE;
    DECLARE v_delim CHAR(2) DEFAULT ';\n';
    DECLARE v_column_name VARCHAR(100);
   
    DECLARE cur_columns CURSOR
    FOR SELECT COLUMN_NAME
          FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_NAME = v_table_name 
           AND TABLE_SCHEMA = v_schema_name;
    DECLARE CONTINUE HANDLER FOR 
    SQLSTATE '02000'
      SET v_done = TRUE;
    
    OPEN cur_columns;
    
    SET v_return = CONCAT(IFNULL(v_return, ''), ' INSERT INTO ', flexviews.get_setting('mvlog_db'), '.', v_table_name, '_mvlog',
                          ' VALUES (', v_dml_type, ', @__uow_id');
     
    columnLoop: LOOP
      SET v_return = CONCAT(v_return);
      FETCH cur_columns 
       INTO v_column_name;
      
      IF v_done THEN
        CLOSE cur_columns;
        LEAVE columnLoop;
      END IF;
    
      IF v_dml_type = -1 THEN
        SET v_return = CONCAT(v_return, ',OLD.');
      END IF;
      IF v_dml_type = 1 THEN
        SET v_return = CONCAT(v_return , ',NEW.');
      END IF; 
      SET v_return = CONCAT(v_return, v_column_name);
    END LOOP;
    SET v_return = CONCAT(v_return, ')', v_delim);
END ;;

DELIMITER ;
