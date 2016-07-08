<?php
require_once 'worker_common.php';

if(function_exists('gc_enable')) gc_enable();

function map_colref($column_name, $colref_map) {

	foreach($colref_map as $real_column_name => $coord_column_name) {
		if($column_name == $real_column_name) {
			return $coord_column_name;
		}

	}

	return false;

}

class Net_Gearman_Job_custom_function extends Net_Gearman_Job_Common
{
		public function run($arg) {

		    static $created_table = array();

    		    $func_arg = $arg['function_info']['arg'];
    		    switch(strtolower($arg['function_info']['function'])) {

			case 'percentile':

    		    		if($func_arg[0]['expr_type'] != 'colref') {
    		    			return(return_error('PERCENTILE: Only column references are allowed as the first argument to this function', $arg['tmp_shard'], 'ERR_SQ_INVALID_FUNC_CALL'));
    		    		}
    		    		if($func_arg[1]['expr_type'] != 'const') {
    		    			return(return_error('PERCENTILE: Only constants are allowed as the second argument to this function', $arg['tmp_shard'], 'ERR_SQ_INVALID_FUNC_CALL'));
    		    		}

    		    		if(!empty($func_arg[2])) {
    		    			return(return_error('PERCENTILE: Wrong number of arguments to this function', $arg['tmp_shard'], 'ERR_SQ_INVALID_FUNC_CALL'));

    		    		}

    		    		$colname = $arg['function_info']['colref_map'][$func_arg['0']['base_expr']];

    		    		$conn = SimpleDAL::factory($arg['tmp_shard']);
    		    		if($conn->my_error()) {
    		            		return(return_error('Failed to connect to storage node',$arg['tmp_shard'],$conn->my_error()));
    		    		}		

				$conn->my_select_db($arg['tmp_shard']['db']);

				if(empty($created_table[$arg['func_call_id']])) {
    		    			$sql = "CREATE TABLE IF NOT EXISTS`" . $arg['func_call_id'] ."` (gb_hash char(40) primary key, retval double) ";
    		    			$result = $conn->my_query($sql);
    		    			if(!$result || $conn->my_error()) return(return_error('SQL error:', $arg['tmp_shard'], $conn->my_error()));
					$created_table[$arg['func_call_id']] = 1;
				}

    		    		$sql = "select count(distinct $colname) cnt from `" . $arg['table'] . "` where gb_hash = '" . $arg['gb_hash'] . "'";
    		    		$result = $conn->my_query($sql);
    		    		if(!$result || $conn->my_error()) return(return_error('SQL error:', $arg['tmp_shard'], $conn->my_error()));

    		    		$row = $conn->my_fetch_assoc();
    		    		if(!$row) return(return_error('No row found for given gb_hash:' . $arg['gb_hash'], $arg['tmp_shard'], 'ERR_SQ_NO_ROW_FOUND'));

    		    		$percentile_at = $func_arg[1]['base_expr'];
    		    		$limit = floor( .01 * $percentile_at * $row['cnt'] );
				if($limit < 1) $limit = 0;

    		    		$sql = "insert into `" . $arg['func_call_id'] . "` select distinct '" . $arg['gb_hash'] . "', $colname from `" . $arg['table'] . "` where gb_hash ='" . $arg['gb_hash'] . "' order by $colname limit $limit,1";
				$conn->my_query($sql);
    		    		if(!$result || $conn->my_error()) return(return_error('SQL error:', $arg['tmp_shard'], $conn->my_error()));
				

    		    		return true;

    		    	break;
    		}

	}
}

?>
