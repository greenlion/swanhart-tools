delimiter ;;
DROP FUNCTION IF EXISTS digest_url;;
CREATE FUNCTION digest_url(
  v_path TINYTEXT
)
  RETURNS TEXT
  NO SQL
  DETERMINISTIC
  COMMENT 'Returns a digested URL path'
BEGIN
  DECLARE v_pos INTEGER DEFAULT 1;
  DECLARE v_last_pos INTEGER DEFAULT 2;
  DECLARE v_token VARCHAR(255) DEFAULT '';
  DECLARE v_retval TEXT DEFAULT '';
  DECLARE v_token_count INTEGER DEFAULT 0;
  DECLARE v_current_token INTEGER DEFAULT 1;
  DECLARE v_sub_tok_pos INTEGER DEFAULT 0;
  DECLARE v_next_is_var BOOLEAN DEFAULT FALSE;

  SET v_token_count = substr_count(v_path,'/')+1;

  while_loop: WHILE v_current_token <= v_token_count DO

    SET v_token = substr_at(v_path, '/', v_current_token);
    SET v_current_token = v_current_token + 1;

    IF( v_retval != '' ) THEN
      SET v_retval := CONCAT(v_retval, '/');
    END IF;

    IF v_next_is_var THEN
      SET v_retval := CONCAT(v_retval, '?'); 
      SET v_next_is_var = FALSE;
      ITERATE while_loop;
    END IF;

    IF(LOCATE('@', v_token)) THEN
      SET v_retval := CONCAT(v_retval, '?'); 
      SET v_next_is_var = TRUE;
      ITERATE while_loop;
    END IF;

    set v_next_is_var := (v_token IN ('views', 'agents', 'classes', 'metrics', 'users'));

    -- if the IN above was true, then just use the current token
    -- and iterate, the next token will be skipped
    IF(v_next_is_var = TRUE) THEN
      SET v_retval := CONCAT(v_retval, v_token); 
      ITERATE while_loop;
    ELSE 
      IF is_numeric(v_token) THEN
        SET v_token := '?'; 
      ELSE 
        IF(is_guid(v_token)) THEN
          SET v_token := '?';
        END IF;
      END IF;
    END IF;

    SET v_retval := CONCAT(v_retval, v_token); 

  END WHILE;

  RETURN CONCAT('/', v_retval);

END;
;;

delimiter ;

