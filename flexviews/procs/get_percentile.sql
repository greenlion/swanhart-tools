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


    This particular function is based on the example provided by Roland Bouman:
    http://rpbouman.blogspot.com/2008/07/calculating-nth-percentile-in-mysql.html

    I've modified it to support data sets with repeat values using DISTINCT.
*/

DROP FUNCTION IF EXISTS flexviews.`get_percentile`;;

CREATE DEFINER=flexviews@localhost FUNCTION flexviews.`get_percentile`( v_mview_expression TEXT, v_percentile INT) RETURNS TEXT CHARSET latin1
    READS SQL DATA
BEGIN  

set group_concat_max_len = 1024 * 1024 * 1024;

return CONCAT(
'SUBSTRING_INDEX(
            SUBSTRING_INDEX(
		SUBSTR(
                    GROUP_CONCAT(DISTINCT IFNULL(',
                        v_mview_expression,
                        ', "") ORDER BY ',v_mview_expression,'
                    ),
                    MAX(',v_mview_expression,' IS NULL) + 1
                )
            ,   ","                           
            ,   ', v_percentile, '/100 * COUNT(distinct ', v_mview_expression, ') + 1)
        ,   ","                               
        ,   -1
        )'
);


END ;;

DELIMITER ;

