<?php
require_once('rewriter.php');
require_once('parallel.php');

$sql = "select a, count(*) from some_table st join table2 t2 using(c1) where c2 = 'open' group by a /* having count(*) > 0*/;";
$table_info = array(
	't2' => array(),
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
