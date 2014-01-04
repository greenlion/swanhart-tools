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

DROP PROCEDURE IF EXISTS `flexviews`.`fv_raise`;;

/****f* SQL_API/fv_raise
 * NAME
 *   flexviews.fv_raise - SIGNAL a warning or an error.
 * SYNOPSIS
 *   flexviews.fv_raise(in_level, in_mysql_errno, in_message_text)
 * FUNCTION
 *   On modern servers, this procedure SIGNALs a condition (error or warning).
 *   Warnings have a '01000' SQLSTATE (generic user-defined warning),
 *   Errors have a '45000' STASTATE (suggested value for custom exceptions).
 *   Error codes are SMALLINT UNSIGNED. Use high values if you want to avoid
 *   builtin codes.
 *
 *   On versions of the server not supporting SIGNAL, a 1146 ('42S02') error
 *   or a 1292 ('22007') warning is produced.
 *   On those versions, SQLSTATE, error number and error message can be read
 *   via @fv_sqlstate, @fv_errno, @fv_error.
 *   These variables are NOT set on modern servers, because this method is limited
 *   to 1 condition, while the diagnostics area can contain several conditions.
 * INPUTS
 *   * in_level         - 'ERROR' or 'WARNING'.
 *   * in_mysql_errno   - Error/warning number.
 *   * in_message_text  - Error/warning message.
 * RESULT
 *   An error is generated.
 * EXAMPLE
 *   mysql>
 *    CALL flexviews.fv_raise('warning', 100, 'Run SHOW WARNINGS to see this message');
******
*/
CREATE DEFINER=`flexviews`@`localhost` PROCEDURE `flexviews`.`fv_raise`( 
	IN `in_level` ENUM('WARNING', 'ERROR'),
	IN `in_mysql_errno` SMALLINT UNSIGNED,
	IN `in_message_text` VARCHAR(128) CHARACTER SET utf8
)
	CONTAINS SQL
	COMMENT 'SIGNALs a warning/error; for pre-5.5, see FV docs'
`whole_proc`:
BEGIN
	-- SQLSTATE cannot be set dynamically,
	-- so we duplicate code and only allow 2 values.
	-- '01000' means generic user-defined warning
	-- '45000' is suggested for custom exceptions
	IF `in_level` = 'WARNING' THEN
		/*!50404
			SIGNAL SQLSTATE '01000' SET
				CLASS_ORIGIN     = 'FlexViews',
				SUBCLASS_ORIGIN  = 'FlexViews',
				MYSQL_ERRNO      = `in_mysql_errno`,
				MESSAGE_TEXT     = `in_message_text`;
		*/
		LEAVE `whole_proc`;
	ELSE
		/*!50404
			SIGNAL SQLSTATE '45000' SET
				CLASS_ORIGIN     = 'FlexViews',
				SUBCLASS_ORIGIN  = 'FlexViews',
				MYSQL_ERRNO      = `in_mysql_errno`,
				MESSAGE_TEXT     = `in_message_text`;
		*/
		DO NULL;
	END IF;
	
	-- pre 5.4 server don't have SIGNAL.
	-- set error properties into user-defined vars and cause an error.
	SET @fv_sqlstate := IF(`in_level` = 'Warning', 'Warning', 'Error');
	SET @fv_errno := `in_mysql_errno`;
	SET @fv_error := `in_message_text`;
	IF `in_level` = 'WARNING' THEN
		DO -'Flexviews error:  for info: CALL flexviews.show_errors()';
	ELSE
		SELECT `error` FROM `_`.`Flexviews error:  for info: CALL flexviews.show_errors()`;
	END IF;
END;
;;

DELIMITER ;
