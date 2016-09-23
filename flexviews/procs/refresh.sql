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

DROP PROCEDURE IF EXISTS flexviews.refresh ;;
/****f* UTIL_API/refresh
 * NAME
 *   flexviews.refresh - Applies changes made to the database since the materialized view was created.
 * SYNOPSIS
 *   flexviews.refresh(v_mview_id, 
                       v_mode,
                       v_uow_id) 
 * FUNCTION
 *   This function initiates the refresh process for the given view.  The process varies depending on
 *   the type of view.  
 * INPUTS
 *   This function takes a combination of input parameters:
 *   * v_mview_id - The materialized view identifier
 *   * v_mode     - COMPLETE|COMPUTE|APPLY|BOTH
 *   * v_uow_id   - (MAY BE NULL)
 *		  When a uow_id (transaction id) is provided the mode selected above will operate only on records
 *		  created at or before the given uow_id.  If a NULL value is provided, then the most recent committed 
 *		  transaction id will be used.
 * NOTES
 * v_mode:
 * |html <table border=1 align="center">
 * |html <tr><th bgcolor=#cccccc>v_mode<th bgcolor=#cccccc>explanation</th></tr><tr>             
 * |html </tr><tr><td>COMPLETE</td><td>COMPLETE is used only for COMPLETE refresh materialized view.  The view is refreshed from scratch using a combination of CREATE TABLE, INSERT INTO and RENAME TABLE
 * |html </tr><tr><td>COMPUTE</td><td>COMPUTE is used for INCREMENTAL tables.  It computes the changes since the last refresh but it does not apply them.  Low cost frequent computations can be made while maintaining the transactional consistency of the view at the last refresh point in time.
 * |html </tr><tr><td>APPLY</td><td>APPLY is used to apply any un-applied changes from previous COMPUTE runs
 * |html </tr><tr><td>BOTH</td><td>BOTH executes a COMPUTE followed by an APPLY
 * |html </tr></table>
 * SEE ALSO
 *   flexviews.enable, flexviews.add_table, flexviews.add_expr
 * EXAMPLE
 *   mysql>
 *     set @mv_id = flexviews.get_id('test', 'mv_example');
 *     call flexviews.refresh(@mv_id, 'BOTH', NULL);

 * NOTES
 *   The external binary log consumer MUST BE RUNNING in order to COMPUTE changes to views!
******
*/
CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.refresh(
  IN v_mview_id INT,
  IN v_mode ENUM('AUTO','COMPLETE','FULL','BOTH','COMPUTE','APPLY'),
  IN v_uow_id BIGINT 
)
  MODIFIES SQL DATA
  COMMENT 'Apply changes since MVIEW creation'
BEGIN
DECLARE v_mview_refresh_type TEXT CHARACTER SET UTF8;

  -- TODO: If COMPLETE is used on a INCREMENTAL view, then rebuild it from
  -- scratch and swap, as if it was a COMPLETE view.  This is to fix 
  -- broken or updated INCREMENTAL views
  -- Implement this along with the APPEND mode

  DECLARE v_incremental_hwm BIGINT;
  DECLARE v_refreshed_to_uow_id BIGINT;
  DECLARE v_current_uow_id BIGINT;
  DECLARE v_child_mview_id INT DEFAULT NULL;
  DECLARE v_signal_id BIGINT DEFAULT NULL;
  DECLARE v_mview_schema TEXT CHARACTER SET UTF8;
  DECLARE v_mview_name TEXT CHARACTER SET UTF8;
  DECLARE v_using_clause TEXT CHARACTER SET UTF8 DEFAULT '';

  SET v_mode = UPPER(v_mode);
  SET max_sp_recursion_depth=255;

  IF NOT flexviews.is_enabled(v_mview_id) = 1 THEN
    CALL flexviews.signal('MV_NOT_ENABLED');
  END IF;

  IF v_mode IS NULL OR v_mode = 'AUTO' THEN
    SELECT IF(mview_refresh_type = 'COMPLETE', 'COMPLETE', 'BOTH')
      INTO v_mode
      FROM flexviews.mview 
      WHERE mview_id = v_mview_id;
  END IF;

  IF v_mode != 'COMPLETE' AND v_mode != 'FULL' AND v_mode != "BOTH" and v_mode != "COMPUTE" and v_mode != "APPLY" THEN
    CALL flexviews.signal('INVALID_REFRESH_MODE');
  END IF;

  IF v_mode = 'FULL' THEN SET v_mode = 'BOTH'; END IF;

  CALL flexviews.update_refresh_step_info(v_mview_id, concat('REFRESH_START: ',v_mode));

  -- get the table name and schema of the given mview_id
  SELECT
      mview_refresh_type,
      incremental_hwm,
      refreshed_to_uow_id,
      mview_schema, 
      mview_name,
      created_at_signal_id
    INTO
      v_mview_refresh_type,
      v_incremental_hwm,
      v_refreshed_to_uow_id,
      v_mview_schema, 
      v_mview_name,
      v_signal_id
    FROM flexviews.mview
    WHERE mview_id = v_mview_id;

  SET @min_uow_id := NULL;
  
  #CUT
  IF v_signal_id IS NOT NULL AND v_refreshed_to_uow_id IS NULL THEN
    START TRANSACTION;

    SELECT MAX(uow_id)
      INTO v_refreshed_to_uow_id
      FROM flexviews.mvlog_3b0cef8fb9788ab03163cf02b19918d1 as flexviews_mview_signal
      WHERE signal_id = v_signal_id 
        AND `fv$server_id` = @@server_id 
        AND dml_type = 1;

    IF v_refreshed_to_uow_id IS NULL THEN
      CALL flexviews.signal('ERROR: SIGNAL ID NOT FOUND (FlexCDC consumer is likely behind)');
    END IF;

    UPDATE flexviews.mview mv
      JOIN flexviews.mview_uow uow
      ON uow.uow_id = v_refreshed_to_uow_id
        AND mv.mview_id = v_mview_id
      SET refreshed_to_uow_id = uow.uow_id,
          incremental_hwm = uow.uow_id,
          mview_last_refresh = uow.commit_time; 

    COMMIT;

    -- refresh these variables as they may have been changed by our UPDATE statement
    SELECT 
        incremental_hwm,
        refreshed_to_uow_id
      INTO 
        v_incremental_hwm,
        v_refreshed_to_uow_id
      FROM flexviews.mview
      WHERE mview_id = v_mview_id;

  END IF;

  -- EXIT the refresh process if the consumer has not caught up to the point
  -- where the view is possible to be refreshed

  IF v_refreshed_to_uow_id IS NULL AND v_mview_refresh_type = 'INCREMENTAL' THEN
    CALL flexviews.signal(concat('ERROR: SIGNAL_ID not found(FlexCDC is not running or is behind) OR the specified mview:', v_mview_id, ' does not exist.'));
  END IF;

  SELECT mview_id
    INTO v_child_mview_id
    FROM mview
    WHERE parent_mview_id = v_mview_id;

  SET @v_start_time = NOW();
 
  IF v_mview_refresh_type = 'COMPLETE' THEN
    CALL flexviews.mview_refresh_complete(v_mview_id);

    UPDATE flexviews.mview
      SET mview_last_refresh=@v_start_time
      WHERE mview_id = v_mview_id;
  ELSEIF v_mview_refresh_type = 'INCREMENTAL' THEN
    SET v_current_uow_id = v_uow_id;

    -- IF v_uow_id is null, then that means refresh to NOW.
    -- You can't refresh backward in time (YET!) so refresh to NOW
    -- if an older/invalid uow_id is given 
    IF v_current_uow_id IS NULL OR v_current_uow_id < v_incremental_hwm THEN 
      -- By default we refresh to the latest available unit of work
      SELECT max(uow_id)
        INTO v_current_uow_id
        FROM flexviews.mview_uow;
    END IF;

    -- this will recursively populate the materialized view delta table
    IF v_mode = 'BOTH' OR v_mode = 'COMPUTE' THEN
      IF v_child_mview_id IS NOT NULL THEN
        BEGIN
          DECLARE v_incremental_hwm BIGINT;

          -- The incremental high water mark of the dependent table may be different from 
          -- the parent table, so explicity fetch it to make sure we don't push the wrong
          -- values into the mview
          SELECT incremental_hwm, created_at_signal_id, refreshed_to_uow_id
            INTO v_incremental_hwm, v_signal_id, v_refreshed_to_uow_id
            FROM mview
            WHERE mview_id = v_child_mview_id;
          IF v_signal_id IS NOT NULL AND v_refreshed_to_uow_id IS NULL THEN
            START TRANSACTION;

            SELECT MAX(uow_id)
              INTO v_refreshed_to_uow_id
              FROM flexviews.mvlog_3b0cef8fb9788ab03163cf02b19918d1 as flexviews_mview_signal
              WHERE signal_id = v_signal_id 
                AND `fv$server_id` = @@server_id 
                AND dml_type = 1;

            IF v_refreshed_to_uow_id IS NULL THEN
              CALL flexviews.signal('ERROR: SIGNAL ID NOT FOUND, FlexCDC may not be caught up.');
            END IF;

            UPDATE flexviews.mview mv
              JOIN flexviews.mview_uow uow
              ON uow.uow_id = v_refreshed_to_uow_id
	        AND mv.mview_id = v_child_mview_id
              SET refreshed_to_uow_id = uow.uow_id,
                incremental_hwm = uow.uow_id,
                mview_last_refresh = uow.commit_time; 
            COMMIT;

            -- refresh these variables as they may have been changed by our UPDATE statement
            SELECT incremental_hwm
              INTO v_incremental_hwm
              FROM flexviews.mview
              WHERE mview_id = v_child_mview_id;
          END IF;

          SET @now := UNIX_TIMESTAMP(NOW());
          if @flashback != 1 then
          CALL flexviews.execute_refresh(v_child_mview_id, v_incremental_hwm, v_current_uow_id, 1);
          end if;
/*
          IF @flashback = 1 THEN
            SELECT CONCAT('`',mview_schema,'`.`',mview_name,'_delta`')
              INTO @child_view_delta
              FROM flexviews.mview
             WHERE mview_id = v_child_mview_id;

            set @update_sql := CONCAT('UPDATE ', @child_view_delta, ' SET dml_type = dml_type * -1, cnt=cnt*-1');
            select @update_sql;
            PREPARE stmt from @update_sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
          END IF;
*/
          set @flashback := 0;

          SET @compute_time = UNIX_TIMESTAMP(NOW()) - @now;

          UPDATE flexviews.mview_compute_schedule
            SET last_computed_at = now(),
              last_compute_elapsed_seconds = @compute_time
            WHERE mview_id = v_child_mview_id;
        END;
      END IF;
  
      SET @now := UNIX_TIMESTAMP(NOW());
      CALL flexviews.execute_refresh(v_mview_id, v_incremental_hwm, v_current_uow_id, 1);    
      SET @compute_time = UNIX_TIMESTAMP(NOW()) - @now;
      UPDATE flexviews.mview_compute_schedule
        SET last_computed_at = now(),
          last_compute_elapsed_seconds = @compute_time
        WHERE mview_id = v_mview_id;
    END IF;

    IF v_mode = 'BOTH' OR v_mode = 'APPLY' THEN
      -- this will apply unapplied deltas up to v_current_uow_id

      BEGIN
        DECLARE v_child_mview_name TEXT CHARACTER SET UTF8;
        DECLARE v_agg_set TEXT CHARACTER SET UTF8;

        SET @now := UNIX_TIMESTAMP(NOW());
        CALL flexviews.apply_delta(v_mview_id, v_current_uow_id);
        SET @compute_time = UNIX_TIMESTAMP(NOW()) - @now;

        IF v_child_mview_id IS NOT NULL THEN
          if @flashback != 1 then
          CALL flexviews.apply_delta(v_child_mview_id, v_current_uow_id);
          end if;

          UPDATE flexviews.mview
            SET mview_last_refresh = (select commit_time from flexviews.mview_uow where uow_id = v_current_uow_id)
            WHERE mview_id = v_child_mview_id;

          UPDATE flexviews.mview_apply_schedule
            SET last_applied_at = now(),
                last_apply_elapsed_seconds = @compute_time
            WHERE mview_id = v_mview_id;

	        SELECT CONCAT(mview_schema, '.', mview_name)
            INTO v_child_mview_name
            FROM flexviews.mview
            WHERE mview_id = v_child_mview_id;

          SELECT group_concat(concat('`' , v_mview_name, '`.`',mview_alias,'` = `x_alias`.`',mview_alias, '`'),'\n')
            INTO v_agg_set
            FROM flexviews.mview_expression 
            WHERE mview_id = v_mview_id
              AND mview_expr_type in('MIN','MAX','COUNT_DISTINCT', 'STDDEV_POP','STDDEV_SAMP','VAR_SAMP','VAR_POP','BIT_AND','BIT_OR','BIT_XOR','GROUP_CONCAT','PERCENTILE');

          SET @debug=v_agg_set;

          SET v_using_clause := flexviews.get_delta_aliases(v_mview_id, '', true); 

          IF (RIGHT(v_agg_set, 1) = ',') THEN
            SET v_agg_set = LEFT(v_agg_set, LENGTH(v_agg_set)-1);
          END IF;

          SET @v_sql = CONCAT('UPDATE ', v_mview_schema, '.', v_mview_name, '\n',
                           '  JOIN (\n', 
                           'SELECT ', get_child_select(v_mview_id, 'cv'), '\n',
                           '  FROM ', v_child_mview_name, ' as cv\n',
                   --        '  JOIN ', v_mview_schema, '.', v_mview_name, '_delta as pv \n '); 
                   --      '  JOIN ', v_mview_schema, '.', v_mview_name, ' as pv \n '
                   ''); 


	  IF v_using_clause != '' THEN 
            SET @v_sql = CONCAT(@v_sql, ' USING (', v_using_clause, ')\n',  
                               ' GROUP BY ', get_delta_aliases(v_mview_id, 'cv', true)); 
	  END IF;

          SET @v_sql = CONCAT(@v_sql, ') x_alias \n');

          IF v_using_clause != '' THEN
            SET @v_sql = CONCAT(@v_sql, ' USING (', get_delta_aliases(v_mview_id, '', true), ')\n');
          END IF;

          SET @v_sql = CONCAT(@v_sql,' SET ', v_agg_set , '\n');

          PREPARE update_stmt from @v_sql;
          EXECUTE update_stmt;   
          DEALLOCATE PREPARE update_stmt;
        END IF;

        UPDATE flexviews.mview
          SET mview_last_refresh = (select commit_time from flexviews.mview_uow where uow_id = v_current_uow_id)
          WHERE mview_id = v_mview_id;

        UPDATE flexviews.mview_apply_schedule
          SET last_applied_at = now(),
              last_apply_elapsed_seconds = @compute_time
          WHERE mview_id = v_mview_id;
      END;
    END IF;
  ELSE
    CALL flexviews.update_refresh_step_info(v_mview_id, concat('UNSUPPORTED REFRESH METHOD:',v_mode));
    CALL flexviews.signal(' XYZ UNSUPPORTED REFRESH METHOD'); 
  END IF;
  set @flashback := NULL;
  CALL flexviews.update_refresh_step_info(v_mview_id, concat('REFRESH_END: ',v_mode));
END ;;

DELIMITER ;
