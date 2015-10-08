<?php
require_once('parallel.php');

$sql = "select a, count(*) from some_table st join c1 where c2 = 'open' group by a /* having count(*) > 0*/;";
$table_info = array(
	'c1' => array(),
	'st' => array('p1','p2','p3')
);

$process_info = array(
	'pid' => 303,
	'current_schema' => 'test'
);

$SETTINGS=array();

echo <<<EOF
EXPECTED OUTPUT:
SQL: select a, count(*) from some_table st join c1 where c2 = 'open' group by a /* having count(*) > 0*/;
Array
(
    [has_rewrites] => 0
    [queries] => Array
        (
            [0] => select a, count(*) from some_table st join c1 where c2 = 'open' group by a /* having count(*) > 0*/;
        )

    [has_errors] => 
    [errors] => Array
        (
        )

)
Array
(
    [has_rewrites] => 1
    [plan] => Array
        (
            [0] => CREATE TABLE IF NOT EXISTS p303_agg_16a836807550a78ff9793b7636ee4cbe (UNIQUE KEY gb_key (`expr_51179`)) ENGINE= AS SELECT a AS expr$0,COUNT(*) AS expr_463809566,a AS `expr_51179`
FROM some_table  PARTITION(p1)  AS `st`  JOIN table2  AS `t2` USING(c1) WHERE  c2 = 'open' AND 0=1  GROUP BY `expr_51179` 
            [1] => Array
                (
                    [0] => INSERT INTO p303_agg_16a836807550a78ff9793b7636ee4cbe SELECT a AS expr$0,COUNT(*) AS expr_463809566,a AS `expr_51179`
FROM some_table  PARTITION(p1)  AS `st`  JOIN table2  AS `t2` USING(c1) WHERE  c2 = 'open' AND 1=1  GROUP BY `expr_51179`
                    [1] => INSERT INTO p303_agg_16a836807550a78ff9793b7636ee4cbe SELECT a AS expr$0,COUNT(*) AS expr_463809566,a AS `expr_51179`
FROM some_table  PARTITION(p2)  AS `st`  JOIN table2  AS `t2` USING(c1) WHERE  c2 = 'open' AND 1=1  GROUP BY `expr_51179`
                    [2] => INSERT INTO p303_agg_16a836807550a78ff9793b7636ee4cbe SELECT a AS expr$0,COUNT(*) AS expr_463809566,a AS `expr_51179`
FROM some_table  PARTITION(p3)  AS `st`  JOIN table2  AS `t2` USING(c1) WHERE  c2 = 'open' AND 1=1  GROUP BY `expr_51179`
                )

            [2] => SELECT expr$0 AS `a`,SUM(expr_463809566) AS `count(*)`
FROM `p303_agg_16a836807550a78ff9793b7636ee4cbe`  GROUP BY a 
            [4] => DROP TABLE IF EXISTS p303_agg_16a836807550a78ff9793b7636ee4cbe
        )

    [has_errors] => 
    [errors] => Array
        (
        )

)
EOF
;
echo "\nACTUAL OUTPUT\n";
echo "SQL: $sql\n";
print_r(RewriteBaseRule::_ENTRY($sql, $table_info, $process_info,$SETTINGS));
print_r(RewriteBaseRule::_ENTRY($sql, $table_info, $process_info,$SETTINGS,'RewriteParallelRule'));
echo "\nShould be same plan (diff table names though) as before.  It has actually been through two filters\n";
print_r(RewriteBaseRule::_ENTRY($sql, $table_info, $process_info,$SETTINGS,'RewriteBaseRule,RewriteParallelRule'));
