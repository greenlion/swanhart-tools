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

DROP PROCEDURE IF EXISTS `flexviews`.`show_warnings`;;

/****f* SQL_API/show_warnings
 * NAME
 *   flexviews.show_warnings - shows last error/warning.
 * SYNOPSIS
 *   flexviews.show_warnings()
 * FUNCTION
 *   If the server does not support SIGNAL, and at least 1 condition
 *   occurred, this procedure shows the last condition.
 * RESULT
 *   Same columns as SHOW WARNINGS.
 *   Only 0 or 1 row can be returned; see fv_raise().
 * EXAMPLE
 *   mysql>
 *    CALL flexviews.show_warnings();
******
*/
CREATE DEFINER=`flexviews`@`localhost` PROCEDURE `flexviews`.`show_warnings`()
	CONTAINS SQL
	COMMENT 'If SIGNAL is not supported, returns Flexviews errors'
BEGIN
	SELECT @fv_level AS `Level`, @fv_errno AS `Code`, @fv_error AS `Message` FROM DUAL;
END;
;;

DELIMITER ;
