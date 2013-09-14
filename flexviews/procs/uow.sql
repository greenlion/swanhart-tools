
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

DELIMITER ;;

DROP FUNCTION IF EXISTS flexviews.uow_from_dtime;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.uow_from_dtime(
v_dtime DATETIME
) RETURNS BIGINT
READS SQL DATA
BEGIN
DECLARE v_uow_id BIGINT;

SELECT uow_id 
  INTO v_uow_id
  FROM flexviews.mview_uow 
 WHERE commit_time <= v_dtime 
 ORDER BY uow_id DESC 
 LIMIT 1;

RETURN v_uow_id;

END;;

DROP PROCEDURE IF EXISTS flexviews.uow_start;;

CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.uow_start(OUT v_uow_id BIGINT)
BEGIN
DECLARE v_deadlocked BOOLEAN DEFAULT FALSE;

startUOW:WHILE (1) DO
  BEGIN
    DECLARE deadlock_detected CONDITION FOR 1213;
    DECLARE EXIT HANDLER 
    FOR deadlock_detected
      SET v_deadlocked = TRUE;
    
    SET v_deadlocked = FALSE;

    INSERT INTO flexviews.mview_uow
      (uow_id) VALUES (NULL);

    -- set our output value
    SET v_uow_id = LAST_INSERT_ID();
  END;
  IF v_deadlocked = FALSE THEN
    LEAVE startUOW;
  END IF;
END WHILE;

END ;;

DROP PROCEDURE IF EXISTS flexviews.uow_end;;

CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.uow_end(IN v_uow_id BIGINT)
BEGIN
  DECLARE do_nothing INT;
  SET do_nothing = 1;
END ;;

DROP PROCEDURE IF EXISTS flexviews.uow_state_change;;

CREATE PROCEDURE flexviews.uow_state_change()
BEGIN
CALL flexviews.uow_start(@__uow_id);
END;;

DROP PROCEDURE IF EXISTS flexviews.uow_execute;;

CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.uow_execute(IN v_sql TEXT, OUT v_uow_id BIGINT)
BEGIN
  DECLARE v_signal_id INT;
  START TRANSACTION;

  SET @v_sql = v_sql;
  PREPARE uow_stmt FROM @v_sql;
  
  EXECUTE uow_stmt;
  DEALLOCATE PREPARE uow_stmt;

  INSERT INTO flexviews.mview_signal VALUES ();

  SELECT LAST_INSERT_ID() 
    INTO v_signal_id;

  COMMIT;

  BEGIN
    SET v_uow_id := NULL;

    wait_for_uowid: LOOP
/*
     SELECT max(uow_id)
       INTO v_uow_id
       FROM (
          select uow_id
          FROM flexviews.flexviews_mview_signal
           WHERE signal_id = v_signal_id
             AND dml_type = 1
           UNION ALL
           select NULL) derived
      LIMIT 1;
*/
          select max(uow_id)
          into v_uow_id
          FROM flexviews.flexviews_mview_signal
           WHERE signal_id >= v_signal_id
             AND dml_type = 1;
    

      IF (v_uow_id IS NOT NULL) THEN
        LEAVE wait_for_uowid;
      END IF;

    set @nothing := SLEEP(.001);

    END LOOP wait_for_uowid;
  END;

--  CALL flexviews.uow_end(v_uow_id);

END;;

DELIMITER ;
