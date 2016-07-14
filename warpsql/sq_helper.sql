delimiter ;;
create database if not exists shard_query;;
use shard_query;;
drop procedure if exists sq_helper;;
drop procedure if exists sq_throw;;
create procedure sq_throw(v_err text) 
begin
  signal sqlstate '45000' set
    class_origin     = 'Fastbit_UDF',
    subclass_origin  = 'Fastbit_UDF',
    mysql_errno      = 45000,
    message_text     = v_err;
end;;
create procedure sq_helper(v_sql longtext, v_remote_schema varchar(64), v_into_schema varchar(64), v_into_table varchar(64), v_return_result tinyint, v_drop_table tinyint)
proc:begin
  -- query to send to SQ formatted as JSON
  declare v_args longtext;

  -- JSON returned from SQ
  declare v_json longtext;

  -- where to put the rows after decoding the JSON
  declare v_table    text;

  -- JSON string extracted from v_json which represents list of columns 
  -- in the resultset from SQ
  declare v_columns  text;

  -- the VALUES clause for the insert into v_table
  declare v_values   text;

  -- used for finding parts of the JSON
  declare v_pos       int;
  declare v_pos2      int;

  -- use the SQL default schema if no schema provided
  -- this is the only parameter that may be NULL and 
  -- since this makes it not null, it will pass the
  -- check below
  if v_remote_schema IS NULL
  then
    set v_remote_schema := ''; 
  end if;
  
  -- check args
  set v_table := concat('`',v_into_schema,'`.`',v_into_table,'`');
  if v_sql is null or v_table is null or v_remote_schema is null or v_into_schema is null or v_into_table is null or v_return_result is null or v_drop_table is null 
  then 
    call sq_throw('All arguments are NOT NULL');
  end if;

  set v_args := concat('{"sql":"', v_sql, '","schema_name":"',v_remote_schema,'"}');
  
  set v_json := gman_do('shard_query_worker', v_args);

  -- Extract the JSON arrays from the document
  set v_pos     := locate("[", v_json);
  set v_pos2    := locate("]", v_json);
  set v_columns := substr(v_json, v_pos+1, v_pos2-v_pos-1);

  -- clean up column list
  set v_columns := replace(v_columns, '\\"', '"');

  -- extract rows
  set v_pos     := locate('[[',v_json);
  set v_pos2    := locate(']]',v_json, v_pos+2);

  -- the column json has a very simple structure.  The easiest thing to do is simply 
  -- get rid of the extra JSON with REPLACE() and generate the CREATE TABLE with 
  -- the column names that remain
  set @create := concat('create table temporary',v_table, '(', replace(replace(replace(v_columns, '{"type":250,"name":"',''),'"}',''),',',' text,'),' text)');

  -- generate the INSERT statement
  set @insert:= concat('insert into ', v_table, ' values ', replace( replace( replace( replace( replace( substr(v_json,v_pos+1,v_pos2-v_pos),'[\\','[') ,'\\",\\"','","') ,'\\"]','"]') ,'[' ,'(') ,']',')'));

  if(@sqdebug is not null) then
    select @create, @insert;
  end if;
  prepare stmt from @create;
  execute stmt;
  deallocate prepare stmt;

  prepare stmt from @insert;
  execute stmt;
  deallocate prepare stmt;

  if v_return_result != 0 then
    set @sql := concat("select * from ", v_table);
    prepare stmt from @sql;
    execute stmt;
    deallocate prepare stmt;
  end if;

  if v_drop_table != 0 then
    set @sql := concat('drop table ', v_table);
    prepare stmt from @sql;
    execute stmt;
    deallocate prepare stmt;
  end if;

end;;

delimiter ;
