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

DROP FUNCTION IF EXISTS flexviews.is_enabled;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.is_enabled(v_mview_id INT) 
RETURNS BOOLEAN
READS SQL DATA
BEGIN
  DECLARE v_return BOOLEAN DEFAULT NULL;

  SELECT mview_enabled
    INTO v_return
    FROM flexviews.mview
   WHERE mview_id = v_mview_id;
 
  RETURN v_return;
END;;

DELIMITER ;
