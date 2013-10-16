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


DROP FUNCTION IF EXISTS `flexviews`.`quote_name`;;

/****f* SQL_API/quote_name
 * NAME
 *   flexviews.quote_name - Returns a quoted identifier
 * SYNOPSIS
 *   flexviews.quote_name(v_name)
 * FUNCTION
 *   This function returns the given string, quoted with backticks and properly escaped.
 * INPUTS
 *   * v_name       - Identifier to quote.
 * EXAMPLE
 *   mysql>
 *     call flexviews.quote_name('this`that');
 *   > `this``that`
******
*/
CREATE DEFINER=`flexviews`@`localhost` FUNCTION `flexviews`.`quote_name`(v_name VARCHAR(64) CHARACTER SET UTF8)
    RETURNS VARCHAR(128) CHARACTER SET UTF8
    DETERMINISTIC
    CONTAINS SQL
    COMMENT 'Returns name quoted with backticks'
BEGIN
    RETURN CONCAT('`', REPLACE(v_name, '`', '``'), '`');
END ;;


DELIMITER ;
