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

DROP FUNCTION IF EXISTS flexviews.get_where;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.get_where(v_mview_id INT)
RETURNS TEXT character set utf8
READS SQL DATA
BEGIN
DECLARE v_done BOOLEAN DEFAULT FALSE;
DECLARE v_where_clause TEXT character set utf8 DEFAULT '';
DECLARE v_mview_expression TEXT character set utf8;
DECLARE cur_expr CURSOR
FOR
SELECT mview_expression
  FROM flexviews.mview_expression
 WHERE mview_id = v_mview_id
   AND mview_expr_type = 'WHERE';

DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET v_done = 1;

SET v_done = false;
open cur_expr;
exprLoop: LOOP
  FETCH cur_expr
   INTO v_mview_expression;
 
  IF v_done THEN
    CLOSE cur_expr;
    LEAVE exprLoop;
  END IF;

  IF v_where_clause != '' THEN
    SET v_where_clause = CONCAT(v_where_clause, ' AND ');
  END IF; 

  SET v_where_clause = CONCAT(v_where_clause, v_mview_expression);
END LOOP;

IF v_where_clause != '' THEN
  SET v_where_clause := CONCAT(' WHERE ', v_where_clause);
END IF;

RETURN v_where_clause;

END;;

DELIMITER ;
