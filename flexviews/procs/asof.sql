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

DROP PROCEDURE IF EXISTS flexviews.asof;;

CREATE DEFINER=flexviews@localhost PROCEDURE flexviews.asof(
  IN v_mview_id INT,
  IN v_when DATETIME 
)
  MODIFIES SQL DATA
  COMMENT 'Copies a materialized view into the new view refreshed to a specific point in time'
proc: BEGIN
/****f* UTIL_API/asof
 * NAME
 *   flexviews.asof - Computes and applies changes to a view to move it to a specified datetime
 * SYNOPSIS
 *   flexviews.asof(v_mview_id, 
                       v_when);
 * FUNCTION
 *   This function moves a given view to a specific point in time. It can move views both
 *   forward and backward in time.  The REFRESH function is not capable of this.
 * INPUTS
 *   This function takes a combination of input parameters:
 *   * v_mview_id - The materialized view identifier
 *   * v_when - A DATETIME value that indicates when the view will be refreshed to
 * NOTES
 *   The external binary log consumer MUST BE RUNNING!
 * SEE ALSO
 *   flexviews.refresh
 * EXAMPLE
 *   mysql>
 *     set @mv_id = flexviews.get_id('test', 'mv_example');
 *     call flexviews.asof(@mv_id, '2016-09-22 22:22:22');
******
*/

  SET max_sp_recursion_depth=2;
  SET @child_mview_id := NULL;
  SELECT mview_id
    INTO @child_mview_id
    FROM flexviews.mview
   WHERE parent_mview_id = v_mview_id;

  IF @child_mview_id IS NOT NULL THEN
    CALL flexviews.asof(@child_mview_id, v_when);
  END IF;

  -- where to roll the view to
  SET @to_uowid := flexviews.uow_from_dtime(v_when);
  IF @to_uowid IS NULL THEN
    call flexviews.signal('FlexCDC history is not available to answer this ASOF operation');
  END IF;

  -- get the delta table name for the view
  SELECT CONCAT('`',mview_schema,'`.`',mview_name,'_delta`')
    INTO @view_delta
    FROM flexviews.mview
   WHERE mview_id = v_mview_id;

  -- are there changes in the delta table that have been computed but not applied?
  set @v_sql := CONCAT('select 1 into @found FROM ', @view_delta, ' limit 1');
  prepare stmt from @v_sql;
  execute stmt;
  deallocate prepare stmt;
  
  -- if so, apply them
  if @found = 1 THEN
    -- apply any computed but not applied changes
    -- this will flush the delta table for the view
    CALL flexviews.refresh(v_mview_id, 'APPLY', NULL);
  end if;

  start transaction;

  -- Where is the view currently applied to?
  SELECT refreshed_to_uow_id, mview_enabled
    INTO @cur_uowid, @enabled
    FROM flexviews.mview
   WHERE mview_id = v_mview_id;

  IF @enabled != 1 THEN
    call flexviews.signal('The specified materialized view is not enabled.');
  END IF;

  IF @cur_uowid IS NULL THEN
    call flexviews.refresh(v_mview_id, 'BOTH', NULL);
    SELECT refreshed_to_uow_id
      INTO @cur_uowid
      FROM flexviews.mview
     WHERE mview_id = v_mview_id;
  END IF;

  IF(@cur_uowid = @to_uowid) THEN
    LEAVE proc;
  END IF;

  -- normal refresh is used to move views forward
  IF @cur_uowid <= @to_uowid THEN
    CALL flexviews.refresh(v_mview_id, 'BOTH', @to_uowid);
  ELSE
    -- update the metadata to flash backward
    UPDATE flexviews.mview 
       SET refreshed_to_uow_id = @to_uowid  ,
           incremental_hwm = @to_uowid
     WHERE mview_id = v_mview_id or parent_mview_id = v_mview_id;

    -- roll the view forward to the point it was refreshed to
    CALL flexviews.refresh(v_mview_id,'COMPUTE', @cur_uowid);

    -- flip the inserts to deletes to make the apply go "backwards"
    set @flashback := 0;
    IF flexviews.has_aggregates(v_mview_id) THEN
      set @flashback := 1;
      set @update_sql := CONCAT('UPDATE ', @view_delta, ' SET dml_type = dml_type * -1, cnt=cnt*-1');
      PREPARE stmt from @update_sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    ELSE
      set @cnt := 0;
      set @update_sql := CONCAT('UPDATE ', @view_delta, ' SET dml_type = dml_type * -1, `fv$gsn` = @cnt := @cnt + 1 ORDER BY `fv$gsn` desc');
      PREPARE stmt from @update_sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
    COMMIT;

    CALL flexviews.refresh(v_mview_id, 'APPLY', NULL);

    -- update the metadata one final time to indicate that the view is at the ASOF point in time
    UPDATE flexviews.mview 
       SET refreshed_to_uow_id = @to_uowid,
           incremental_hwm = @to_uowid,
           refresh_state = 'VALID'
     WHERE mview_id = v_mview_id;

    COMMIT;

  END IF;
  set @flashback := NULL;
  
END ;;

DELIMITER ;
