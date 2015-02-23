DELIMITER ;;
drop procedure if exists row_len_stats;;
create procedure row_len_stats(v_table_schema varchar(64), v_table_name varchar(64)) 
BEGIN
  DECLARE v_done BOOLEAN DEFAULT FALSE;
 
  select group_concat(concat("`",column_name,"`") order by ordinal_position)
    into @cols
      FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=v_table_schema
       AND TABLE_NAME=v_table_name;

  SET @expr = CONCAT('LENGTH(concat(', @cols ,'))');
  select "Using Expression:",@expr;

  SELECT concat("SELECT MIN(",@expr,") min_len,MAX(",@expr,") max_len, AVG(",  @expr, ") avg_len,COUNT(*) FROM `",
                 v_table_schema, 
                 "`.`", 
                 v_table_name, 
                 "`"
         ) sql_statement
    INTO @v_sql  
      FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=v_table_schema
       AND TABLE_NAME=v_table_name
     GROUP BY TABLE_NAME;

   PREPARE stmt FROM @v_sql;
   EXECUTE stmt;
   DEALLOCATE PREPARE stmt;   
END;;
DELIMITER ;
