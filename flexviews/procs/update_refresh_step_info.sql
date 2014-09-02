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

DROP PROCEDURE IF EXISTS update_refresh_step_info;


CREATE DEFINER=flexviews@localhost PROCEDURE  `update_refresh_step_info`(
  IN v_mview_id INT,
  IN v_last_step VARCHAR(255)
)
BEGIN
  INSERT INTO flexviews.refresh_step_info (mview_id, last_step)
  VALUES (v_mview_id, v_last_step)
  ON DUPLICATE KEY UPDATE last_step = v_last_step, last_step_at=NOW();
END ;;

DELIMITER ;
