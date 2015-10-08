<?php
require_once('phar://rewriteengine.phar/parallel.php');


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

echo "SQL: $sql\n";
print_r(RewriteBaseRule::_ENTRY($sql, $table_info, $process_info,$SETTINGS));
print_r(RewriteBaseRule::_ENTRY($sql, $table_info, $process_info,$SETTINGS,'RewriteParallelRule'));
echo "\nShould be same plan (diff table names though) as before.  It has actually been through two filters\n";
print_r(RewriteBaseRule::_ENTRY($sql, $table_info, $process_info,$SETTINGS,'RewriteBaseRule,RewriteParallelRule'));
