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

/*
    This file contains functions which check the existence of an SQL object.
    They are used to signal user errors, for example when one tries to build
    a materialized view but mistypes the table's name.
*/


DROP FUNCTION IF EXISTS `flexviews`.`schema_exists`;;

/****f* SQL_API/schema_exists
 * NAME
 *   flexviews.schema_exists - Returns wether specified schema exists
 * SYNOPSIS
 *   flexviews.schema_exists(v_schema)
 * FUNCTION
 *   This function returns TRUE if the given schema exists, otherwise returns FALSE.
 * INPUTS
 *   * v_schema       - Schema name
 * RESULT
 *   Pseudo-boolean value.
 * EXAMPLE
 *   mysql>
 *     call flexviews.schema_exists('test');
******
*/
CREATE DEFINER=`flexviews`@`localhost` FUNCTION `flexviews`.`schema_exists`(v_schema VARCHAR(64) CHARACTER SET UTF8)
    RETURNS BOOLEAN
    NOT DETERMINISTIC
    READS SQL DATA
    COMMENT 'Returns wether specified schema exists'
BEGIN
    RETURN EXISTS (SELECT 1 FROM `information_schema`.`SCHEMATA` WHERE `SCHEMA_NAME` = v_schema);
END ;;


DROP FUNCTION IF EXISTS `flexviews`.`table_exists`;;

/****f* SQL_API/table_exists
 * NAME
 *   flexviews.table_exists - Returns wether specified table (or view) exists
 * SYNOPSIS
 *   flexviews.schema_exists(v_schema, v_table)
 * FUNCTION
 *   This function returns TRUE if the specified table (or view) exists in the given schema, otherwise returns FALSE.
 * INPUTS
 *   * v_schema       - Schema name
 *   * v_table        - Table/view name
 * RESULT
 *   Pseudo-boolean value.
 * EXAMPLE
 *   mysql>
 *     call flexviews.schema_exists('information_schema', 'SCHEMATA');
******
*/
CREATE DEFINER=`flexviews`@`localhost` FUNCTION `flexviews`.`table_exists`(v_schema VARCHAR(64) CHARACTER SET UTF8, v_table VARCHAR(64) CHARACTER SET UTF8)
    RETURNS BOOLEAN
    NOT DETERMINISTIC
    READS SQL DATA
    COMMENT 'Returns wether specified table/view exists'
BEGIN
    RETURN EXISTS (
        SELECT 1 FROM `information_schema`.`TABLES` WHERE `TABLE_NAME` = v_table AND `TABLE_SCHEMA` = v_schema AND `TABLE_TYPE` LIKE 'BASE TABLE'
      );
END ;;

DELIMITER ;
