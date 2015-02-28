<?php
require_once('rewriteengine.php'); 
 
function extract_partition_info($json_explain) {
	$x = json_decode($json_explain, true);
	$retval=array();
	if(!empty($x['query_block']['nested_loop'])) {
		foreach($x['query_block']['nested_loop'] as $t) {
			$t = $t['table'];
			if(!empty($t['materialized_from_subquery'])) continue;
			if(!empty($t['partitions'])) 
				$retval[$t['table_name']] = $t['partitions']; 
			else 
				$retval[$t['table_name']] = array();
		}
	} else {
		return false;
	}
 
	return $retval;
}
 
function EXEC_RULES($s, $t, $p, $set, $r) { 
  return RewriteBaseRule::_ENTRY($s, $t, $p, $set, $r); 
} 
 
function GET_INDEX($ary, $idx) { 
  return($ary[$idx]); 
}; 

function GET_GLOBAL($var) {
	if(!isset($GLOBALS[$var])) return false;
	return $GLOBALS[$var];
}

function SET_GLOBAL($var,$val) {
	if(!$var) return false;
	$GLOBALS[$var] = $val;
	return true;
}
?>
