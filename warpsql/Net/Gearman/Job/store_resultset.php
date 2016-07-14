<?php
#THIS JOB INSERTS DATA DIRECTLY INTO the tmp_shard
#This job also uses an unbuffered query
require_once 'worker_common.php';

if(function_exists('gc_enable')) gc_enable();


class Net_Gearman_Job_store_resultset extends Net_Gearman_Job_Common
{
    public function run($arg) {
	static $max_allowed_packet = null;

	$errors = false;
	$stmt = false;
	if(!$arg) return;

	$arg = (object)$arg;

	#broadcast simple queries like DML and DDL
	if($arg->action == 'no_resultset') {
		return $this->broadcast($arg);
	}

	#store the resultset in a table
	$conn = SimpleDAL::factory($arg->shard);
	if($conn->my_error()) { 
		return(return_error('Failed to connect to storage node',$arg->shard,$conn->my_error(), $arg->query_id));
	}

	$tmp_conn = SimpleDAL::factory($arg->tmp_shard);
	if(!$tmp_conn) return(return_error('Failed to connect to coordination node', $arg->tmp_shard, $tmp_conn->my_error(),$arg->query_id));

	if(!$conn->my_select_db($arg->shard['db'])) return(return_error('Could not change to database on storage node', $arg->shard, $conn->my_error(),$arg->query_id));
	if(!$tmp_conn->my_select_db($arg->tmp_shard['db'])) return(return_error('Could not change to database on coordinator node', $arg->tmp_shard, $tmp_conn->my_error(),$arg->query_id));

	if($max_allowed_packet == null) {
		$stmt = $tmp_conn->my_query("select @@max_allowed_packet");
		if(!$stmt) return(return_error('Could not get max_allowed_packet', $this->tmp_shard, $tmp_conn->my_error(),$arg->query_id));
		$row = $tmp_conn->my_fetch_array();
		$tmp_conn->my_free_result();
		$max_allowed_packet = $row[0] - 4096;
	}
/*
	$sql = "CREATE TABLE IF NOT EXISTS {$arg->table_name} ";
	$create_subquery = preg_replace('/\s+limit\s+\d+.*$/i', '', $arg->sql) . ' LIMIT 0';

	if(stristr($arg->sql, 'GROUP BY')) { 
		if(!empty($arg->agg_key_cols) && $arg->agg_key_cols) $sql .= "(UNIQUE KEY gb_key (" . $arg->agg_key_cols . "))";
	}

	$sql .= " ENGINE=". $arg->engine;
	$sql .= " AS $create_subquery ";
			
	if(!$tmp_conn->my_query($sql)) {
		return(return_error('Could not create coordination table {' . $sql . "}", $this->tmp_shard, $tmp_conn->my_error(),$arg->query_id));
	}
*/

	/* for indexed subqueries */
	#FIXME:
	#$sql = "select column_name from information_schema.columns where table_schema='" . $tmp_conn->my_escape_string($arg->tmp_shard['db']) . "' and table_name = '" . $arg->table_name . "' and ordinal_position=1 limit 1;";
	#$tmp_conn->my_query($sql);
	#$row = $tmp_conn->my_fetch_assoc();
	#$column_name = $row['column_name'];
	#$sql = "ALTER TABLE {$arg->table_name} ADD KEY({$column_name});";
	#$tmp_conn->my_query($sql); 
			
	#}

	$stmt = $conn->my_unbuffered_query($arg->sql);    
	if($conn->my_error()) {
		return(return_error('Failed to execute query on node',$arg->shard,$conn->my_error(),$arg->query_id));
	}

	$created_sql = false;	
	$sql = "INSERT INTO `{$arg->table_name}` VALUES ";
	$values = "";
	$odku="";
	if(!empty($arg->coord_odku))  {
		$odku=' ON DUPLICATE KEY UPDATE ' . join(",\n",$arg->coord_odku);
	}
	$got_rows = 0;
	$hashes['gb_hash'] = array();
 
	while($stmt && $row = $conn->my_fetch_assoc($stmt)) {
		if(!empty($row['gb_hash'])) $hashes['gb_hash'][$row['gb_hash']] = 1;
		++$got_rows;

		
		if($values) $values .= ",";
		$val_list = "";
		foreach($row as $col => $val) {
			if($val_list != "") $val_list .= ',';
			if(is_numeric($val)) {
				$val_list .= "{$val}";
			} elseif($val == null) {
				$val_list .= "NULL";
			} else {  
				$val_list .= "'" . $tmp_conn->my_real_escape_string($val) . "'";
			}
		}
		$values .= "({$val_list})";

		if(strlen($values) >= $max_allowed_packet) {
			$tmp_conn->my_query('begin');
			if(!$tmp_conn->my_query($sql . $values . $odku)) {
				echo "FAILED\n";
				@$tmp_conn->my_query('rollback');
				return(return_error('Error while inserting: ' . $sql . $values . $odku, $arg->tmp_shard, $tmp_conn->my_error(),$arg->query_id));
			}
			$tmp_conn->my_query('commit');
			$values = "";

		}
	}

	#any rows left over?
	if($values) {
		if(!$tmp_conn->my_query($sql . $values . $odku)) {
			@$tmp_conn->my_query('rollback');
			$tmp_conn->my_close();
			$conn->my_close();
			return(return_error('Error while inserting: ' . $sql . $values . $odku, $arg->tmp_shard, $tmp_conn->my_error(),$arg->query_id));
		}
		$tmp_conn->my_query('commit');
	}

	if($stmt) $conn->my_free_result($stmt);
	$conn->my_close();
	$tmp_conn->my_close();

	#free resources
	unset($tmp_conn);
	unset($conn);
	unset($sql);
	unset($values);
	
    	return(return_ok($got_rows, $hashes,$arg->query_id));
    }

	private function broadcast($arg) {
		if(!$arg) return;
        	$arg = (object)$arg;

        	$conn = SimpleDAL::factory($arg->shard);
        	if($conn->my_error()) {
        	        return(return_message($conn->my_error(),$arg->shard,$arg->query_id));
        	}

        	if(!$conn->my_select_db($arg->shard['db'])) {
        	        return(return_message($conn->my_error(),$arg->shard,$arg->query_id));
        	}

        	$stmt = $conn->my_query($arg->sql);
        	if(!$stmt) {
        	        return(return_message($conn->my_error(),$arg->shard,$arg->query_id));
        	}

        	$last_insert_id = 0;
        	if($affected_row_count = $conn->my_affected_rows() == 0) $affected_row_count="0";


        	if(preg_match('/insert |last_insert_id/', $arg->sql)) {
        	        $last_insert_id = $conn->my_insert_id();
        	}

        	unset($conn);

        	return(return_message('success', $arg->shard, 'info', $affected_row_count, $last_insert_id,$arg->query_id));

	}
}

?>
