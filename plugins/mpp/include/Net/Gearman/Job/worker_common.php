<?php
set_include_path(get_include_path() . PATH_SEPARATOR . '../../..');

require_once 'Net/Gearman/Job.php';
require_once 'DAL/simple-dal.php';
require_once 'shard-query.php';
require_once 'shard-key-mapper.php';
require_once 'common.php';

if(function_exists('gc_enable')) gc_enable();

function return_error($reason, $shard, $info, $query_id = null, $job_type = "store_resultset" ) {
	return return_message($reason . ':' . $info, $shard, 'error',0,0,$query_id);
}

function return_ok($done, $hashes, $query_id, $job_type = "store_resultset") {
	if(isset($query_id)) {
		register_completion($query_id, $job_type, 'ok');
	}

	return array('done' => $done, 'hashes' => $hashes);

}

function return_message($message, $shard, $message_type = 'error', $affected_row_count = 0, $last_insert_id = 0, $query_id = null ) {
	if(isset($query_id)) {
		register_completion($query_id, "store_resultset", $message_type);
	}
        return array( $message_type => "[message:$message] [node:{$shard['shard_name']}] [arc:$affected_row_count] [insert_id:$last_insert_id]" ) ;
}

function register_completion($query_id, $job_type, $completion_type, $completion_message = null) {
	require('shard-query-config.php');
	if(!$completion_message) $completion_message = "NULL"; else $completion_message = "'" . str_replace("'","''",$completion_message) . "'";
	$sql = "INSERT INTO job_worker_status ( job_id, worker_type, completion_type, completion_message, complete_time )";
	$sql .=" VALUES ( $query_id, '$job_type', '$completion_type', $completion_message, NOW()) ";
	$conn = SimpleDAL::factory($config_database);
	$conn->my_select_db($config_database['db']);
	$conn->my_query($sql);
}
