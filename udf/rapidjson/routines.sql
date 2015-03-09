delimiter ;;
drop function if exists jsum;
create function jsum(v_values longtext)
returns decimal(65,5)
deterministic
begin
  declare v_cur int;
  declare v_end int;
  declare v_sum decimal(65,5);
  declare v_char char(1);
  declare v_str longtext default '';
  set v_cur := 1; 
  set v_end := LENGTH(v_values);
  set v_sum := 0;
  theloop:LOOP
    set v_char := substr(v_values, v_cur, 1);
    if(v_char = '\n') 
    then
      set v_sum := v_sum + cast(v_str as decimal(65,5));
      set v_str := "";
    else 
      set v_str := concat(v_str, v_char);
    end if;
    set v_cur := v_cur + 1;
    if v_cur > v_end 
    then 
      leave theloop;
    end if;
  end loop;
  return v_sum;
end;;

drop function if exists jmin;
create function jmin(v_values longtext)
returns decimal(65,5)
deterministic
begin
  declare v_cur int;
  declare v_end int;
  declare v_min decimal(65,5);
  declare v_char char(1);
  declare v_str longtext default '';
  set v_cur := 1; 
  set v_end := LENGTH(v_values);
  set v_min := 999999999999999;
  theloop:LOOP
    set v_char := substr(v_values, v_cur, 1);
    if(v_char = '\n') 
    then
      if(cast(v_str as decimal(65,5)) < v_min) 
      then
        set v_min := cast(v_str as decimal(65,5));
        set v_str := "";
      end if;
    else 
      set v_str = concat(v_str, v_char);
    end if;
    set v_cur := v_cur + 1;
    if v_cur > v_end 
    then 
      leave theloop;
    end if;
  end loop;
  return v_min;
end;;

drop function if exists jmax;
create function jmax(v_values longtext)
returns decimal(65,5)
deterministic
begin
  declare v_cur int;
  declare v_end int;
  declare v_max decimal(65,5);
  declare v_char char(1);
  declare v_str longtext default '';
  set v_cur := 1; 
  set v_end := LENGTH(v_values);
  set v_max := -999999999999999;
  theloop:LOOP
    set v_char := substr(v_values, v_cur, 1);
    if(v_char = '\n') 
    then
      if(cast(v_str as decimal(65,5)) > v_max) 
      then
        set v_max := cast(v_str as decimal(65,5));
        set v_str := "";
      end if;
    else 
      set v_str = concat(v_str, v_char);
    end if;
    set v_cur := v_cur + 1;
    if v_cur > v_end 
    then 
      leave theloop;
    end if;
  end loop;
  return v_max;
end;;

drop function if exists jcount;
create function jcount(v_values longtext)
returns bigint
deterministic
begin
  declare v_cur int;
  declare v_end int;
  declare v_cnt bigint;
  declare v_char char(1);
  declare v_str longtext default '';
  set v_cur := 1; 
  set v_end := LENGTH(v_values);
  set v_cnt := 0;
  theloop:LOOP
    set v_char := substr(v_values, v_cur, 1);
    if(v_char = '\n') 
    then
      set v_cnt := v_cnt + 1; 
      set v_str := "";
    else 
      set v_str = concat(v_str, v_char);
    end if;
    set v_cur := v_cur + 1;
    if v_cur > v_end 
    then 
      leave theloop;
    end if;
  end loop;
  return v_cnt;
end;;

drop function if exists javg;
create function javg(v_values longtext)
returns decimal(65,5)
deterministic
begin
  declare v_cur int;
  declare v_end int;
  declare v_sum decimal(65,5);
  declare v_cnt bigint default 0;
  declare v_char char(1);
  declare v_str longtext default '';
  set v_cur := 1; 
  set v_end := LENGTH(v_values);
  set v_sum := 0;
  theloop:LOOP
    set v_char := substr(v_values, v_cur, 1);
    if(v_char = '\n') 
    then
      set v_cnt := v_cnt + 1;
      set v_sum := v_sum + cast(v_str as decimal(65,5));
      set v_str := "";
    else 
      set v_str := concat(v_str, v_char);
    end if;
    set v_cur := v_cur + 1;
    if v_cur > v_end 
    then 
      leave theloop;
    end if;
  end loop;
  return v_sum/v_cnt;
end;;
delimiter ;
