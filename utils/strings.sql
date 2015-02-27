delimiter ;;
DROP FUNCTION IF EXISTS substr_count;;
CREATE FUNCTION substr_count(x text, delim varchar(12)) RETURNS INT
  NO SQL
  DETERMINISTIC
RETURN (LENGTH(x)-LENGTH(REPLACE(x, delim, '')))/LENGTH(delim);;

DROP FUNCTION IF EXISTS substr_at;;
CREATE FUNCTION substr_at(x VARCHAR(255), delim VARCHAR(12), pos int) RETURNS VARCHAR(255)
  NO SQL
  DETERMINISTIC
RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos), LENGTH(SUBSTRING_INDEX(x, delim, pos - 1)) + 1), delim, '');;
-- end external --

DROP FUNCTION IF EXISTS is_numeric;;
CREATE FUNCTION is_numeric(x text) RETURNS BOOLEAN
  NO SQL
  DETERMINISTIC
  RETURN x REGEXP('(^[0-9.]+$)');;

DROP FUNCTION IF EXISTS is_guid;;
CREATE FUNCTION is_guid(x text) RETURNS BOOLEAN
  NO SQL
  DETERMINISTIC
  RETURN x REGEXP '[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}';

DROP FUNCTION IF EXISTS filename;;

CREATE FUNCTION filename (
  v_path TINYTEXT
)
  RETURNS VARCHAR(255)
  NO SQL
  DETERMINISTIC
  COMMENT 'Return the filename portion of a path'
BEGIN
   RETURN substr(v_path, -1*(locate('/',reverse(v_path))-1));
END;
;;

DROP FUNCTION IF EXISTS pathname;;

CREATE FUNCTION pathname (
  v_path TINYTEXT
)
  RETURNS VARCHAR(255)
  NO SQL
  DETERMINISTIC
  COMMENT 'Return the path portion of a path which includes a filename'
BEGIN
  DECLARE v_pos INTEGER;
  SET v_pos := (locate('/',reverse(v_path))-1);
  RETURN substr(v_path, 1, length(v_path) - v_pos);
END;
;;
delimiter ;
