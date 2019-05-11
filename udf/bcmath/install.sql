-- aggregate sum
create /*M!100300 or replace*/ aggregate function bcsum returns string soname 'udf_bcmath.so';

-- add two numbers
create /*M!100300 or replace*/ function bcadd returns string soname 'udf_bcmath.so';

-- subtract two numbers
create /*M!100300 or replace*/ function bcsub returns string soname 'udf_bcmath.so';

-- multiply two numbers
create /*M!100300 or replace*/ function bcmul returns string soname 'udf_bcmath.so';

-- divide two numbers
create /*M!100300 or replace*/ function bcdiv returns string soname 'udf_bcmath.so';

-- raise one number to another
create /*M!100300 or replace*/ function bcpow returns string soname 'udf_bcmath.so';

-- square root
create /*M!100300 or replace*/ function bcsqrt returns string soname 'udf_bcmath.so';

-- returns 0 if number is the same, neg if first is smaller than second
create /*M!100300 or replace*/ function bccomp returns int soname 'udf_bcmath.so';

DELIMITER ;;

CREATE OR REPLACE AGGREGATE FUNCTION bc_stddev(
  p_num LONGTEXT
)
RETURNS LONGTEXT
BEGIN
  -- partial result
  DECLARE v_sum LONGTEXT DEFAULT '0';
  DECLARE v_sumsq LONGTEXT DEFAULT '0';

  -- number of values
  DECLARE v_count BIGINT UNSIGNED DEFAULT 0;
  DECLARE v_retval LONGTEXT;

  DECLARE EXIT HANDLER
  FOR NOT FOUND
  BEGIN
    set @mean := bcdiv(@bc_scale, v_sum, cast(v_count as char));
    set @mean2 := bcpow(@bc_scale, @mean, '2');
    set v_retval := bcsqrt(@bc_scale, bcsub(@bc_scale, bcdiv(@bc_scale, v_sumsq, cast(v_count as char)), @mean2));
    set @mean := NULL;
    set @mean2 := NULL;
    return v_retval;
  END;

  IF @bc_scale IS NULL THEN
    SET @bc_scale := 10;
  END IF;

  agg_main:
  LOOP
    FETCH GROUP NEXT ROW;

    -- skip NULLs
    IF p_num IS NULL THEN
      ITERATE agg_main;
    END IF;

    -- update the running product and the values count
    SET v_sum := bcadd(@bc_scale,v_sum, p_num);
    SET v_sumsq := bcadd(@bc_scale,v_sumsq, bcpow(@bc_scale, p_num, '2'));
    SET v_count := v_count + 1;
  END LOOP;
END;;

CREATE OR REPLACE AGGREGATE FUNCTION bc_stddev_samp(
  p_num LONGTEXT
)
RETURNS LONGTEXT
BEGIN
  -- partial result
  DECLARE v_sum LONGTEXT DEFAULT '0';
  DECLARE v_sumsq LONGTEXT DEFAULT '0';

  -- number of values
  DECLARE v_count BIGINT UNSIGNED DEFAULT 0;

  DECLARE EXIT HANDLER
  FOR NOT FOUND
  BEGIN
    return bcsqrt(@bc_scale, bcdiv(@bc_scale, bcsub(@bc_scale,v_sumsq, bcdiv(@bc_scale, bcpow(@bc_scale,v_sum, '2'), cast(v_count as char))),bcsub(@bc_scale,cast(v_count as char),'1')));
  END;

  IF @bc_scale IS NULL THEN
    SET @bc_scale := 10;
  END IF;

  agg_main:
  LOOP
    FETCH GROUP NEXT ROW;

    -- skip NULLs
    IF p_num IS NULL THEN
      ITERATE agg_main;
    END IF;

    SET v_sum := bcadd(@bc_scale,v_sum, p_num);
    SET v_sumsq := bcadd(@bc_scale,v_sumsq, bcpow(@bc_scale, p_num, '2'));
    SET v_count := v_count + 1;
  END LOOP;
END;;


CREATE OR REPLACE AGGREGATE FUNCTION bc_variance(
  p_num LONGTEXT
)
RETURNS LONGTEXT
BEGIN
  -- partial result
  DECLARE v_sum LONGTEXT DEFAULT '0';
  DECLARE v_sumsq LONGTEXT DEFAULT '0';

  -- number of values
  DECLARE v_count BIGINT UNSIGNED DEFAULT 0;
  DECLARE v_retval LONGTEXT;

  DECLARE EXIT HANDLER
  FOR NOT FOUND
  BEGIN
    -- (SUM({$alias_sum2})/SUM({$alias_cnt}) - POW((SUM({$alias_sum})/SUM($alias_cnt)),2))
    set @tmp1 := bcdiv(@bc_scale, v_sumsq, cast(v_count as char));
    set @tmp2 := bcdiv(@bc_scale, v_sum, cast(v_count as char));
    set @tmp2 := bcpow(@bc_scale, @tmp2, '2');
    set v_retval := bcsub(@bc_scale, @tmp1, @tmp2);
    set @tmp1 := NULL;
    set @tmp2 := NULL;
    return v_retval;
  END;

  IF @bc_scale IS NULL THEN
    SET @bc_scale := 10;
  END IF;

  agg_main:
  LOOP
    FETCH GROUP NEXT ROW;

    -- skip NULLs
    IF p_num IS NULL THEN
      ITERATE agg_main;
    END IF;

    SET v_sum := bcadd(@bc_scale,v_sum, p_num);
    SET v_sumsq := bcadd(@bc_scale,v_sumsq, bcpow(@bc_scale, p_num, '2'));
    SET v_count := v_count + 1;
  END LOOP;
END;;


CREATE OR REPLACE AGGREGATE FUNCTION bc_variance_samp(
  p_num LONGTEXT
)
RETURNS LONGTEXT
BEGIN
  -- partial result
  DECLARE v_sum LONGTEXT DEFAULT '0';
  DECLARE v_sumsq LONGTEXT DEFAULT '0';

  -- number of values
  DECLARE v_count BIGINT UNSIGNED DEFAULT 0;
  DECLARE v_retval LONGTEXT;

  DECLARE EXIT HANDLER
  FOR NOT FOUND
  BEGIN
    -- return bcsqrt(@bc_scale, bcdiv(@bc_scale, bcsub(@bc_scale,v_sumsq,bcdiv(@bc_scale,bcpow(@bc_scale,v_sum,'2'), cast(v_count as char)))),bcsub(@bc_scale,cast(v_count as char),'1')); 
    set @tmp := bcdiv(@bc_scale, bcpow(@bc_scale, v_sum, '2'), cast(v_count as char));
    set @tmp := bcsub(@bc_scale, v_sumsq, @tmp);
    set @tmp := bcdiv(@bc_scale, @tmp, bcsub(@bc_scale, cast(v_count as char), '1'));
    -- set v_retval := bcsqrt(@bc_scale, @tmp);
    set v_retval := @tmp;
    set @tmp := NULL;
    return v_retval;
  END;

  IF @bc_scale IS NULL THEN
    SET @bc_scale := 10;
  END IF;

  agg_main:
  LOOP
    FETCH GROUP NEXT ROW;

    -- skip NULLs
    IF p_num IS NULL THEN
      ITERATE agg_main;
    END IF;

    SET v_sum := bcadd(@bc_scale,v_sum, p_num);
    SET v_sumsq := bcadd(@bc_scale,v_sumsq, bcpow(@bc_scale, p_num, '2'));
    SET v_count := v_count + 1;
  END LOOP;
END;;

CREATE OR REPLACE AGGREGATE FUNCTION bc_min(
  p_num LONGTEXT
)
RETURNS LONGTEXT
BEGIN
  -- partial result
  DECLARE v_min LONGTEXT DEFAULT NULL;

  DECLARE EXIT HANDLER
  FOR NOT FOUND
  BEGIN
    RETURN v_min;
  END;

  IF @bc_scale IS NULL THEN
    SET @bc_scale := 10;
  END IF;

  agg_main:
  LOOP
    FETCH GROUP NEXT ROW;

    -- skip NULLs
    IF p_num IS NULL THEN
      ITERATE agg_main;
    END IF;

    IF bccomp(@bc_scale, p_num, ifnull(v_min,p_num)) <= 0 THEN
        SET v_min := p_num;
    END IF;
  END LOOP;

  RETURN NULL;

END;;

CREATE OR REPLACE AGGREGATE FUNCTION bc_max(
  p_num LONGTEXT
)
RETURNS LONGTEXT
BEGIN
  -- partial result
  DECLARE v_max LONGTEXT DEFAULT NULL;

  DECLARE EXIT HANDLER
  FOR NOT FOUND
  BEGIN
    RETURN v_max;
  END;

  IF @bc_scale IS NULL THEN
    SET @bc_scale := 10;
  END IF;

  agg_main:
  LOOP
    FETCH GROUP NEXT ROW;

    -- skip NULLs
    IF p_num IS NULL THEN
      ITERATE agg_main;
    END IF;

IF bccomp(@bc_scale,ifnull(v_max,p_num), p_num) <= 0 THEN
        SET v_max := p_num;
    END IF;
  END LOOP;

  RETURN NULL;

END;;

DELIMITER ;
