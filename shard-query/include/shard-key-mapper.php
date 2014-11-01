<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 encoding=utf-8 */
/* $Id: */
/*
Copyright (c) 2010, Justin Swanhart
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the <organization> nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

error_reporting(E_ALL);
require_once('common.php');

interface ShardKeyMapper {
        function __construct($config, $no_change_db);
        function get_shards($schema_name = null);
        function add_shard($shard_name, $shard, $coord_shard = true, $accepts_new_rows = true, $extra_info = "NULL", $schema_name = null);
        function refresh_shard_cache($schema_name = null);
        function remove_shard($shard_name, $schema_name = null);
        function add_schema($schema_name, $is_default_schema = true);
        function add_column($column, $schema_name = null,$datatype = 'integer',$next_value=1);
        function move_key($column, $key, $tables, $dest_shard, $schema_name = null );
        function lock_key($column, $key, $lock_mode, $schema_name = null);
        function unlock_key($column, $key, $schema = null); 
        function get_column_info($column_name, $schema_name = null);
        function new_key($column, $key = null, $shard_name = null, $schema_name = null);
        function &map($column, $key, $schema_name=null, $operator = "=", $create_on_miss = true);
        function &get_keys($column, $shard_name, $schema_name = null);
        function &get_key_distribution($column, $schema_name = null);
        function get_column($shema_name = null);
}


class DirectoryShardKeyMapper implements ShardKeyMapper{
	var $shard_cache = array();
	var $conn = null;  

	#The constructor accepts an array which includes host, user, password and db
	public function __construct($config, $no_change_db = false) {

		if(empty($config['host']) || empty($config['user']) || empty($config['db'])) {
			throw new Exception('Missing one of required options: host, user or db');
		}

		$this->config = $config;

		$this->connect($config, $no_change_db);

	} 
	
	private function connect($config, $no_change_db = false) {

		if($this->conn) return $this->conn;
		$this->conn = SimpleDAL::factory($config);
		
		if($this->conn->my_error()) throw new Exception($this->conn->my_error());
	
		if(!$no_change_db) { 
			$this->conn->my_select_db($this->config['db']);
			if($this->conn->my_error()) throw new Exception($this->conn->my_error());
		}

	}

	private function execute($sql, $check_for_errors = true) {
		$this->conn->my_ping();
		$stmt = $this->conn->my_query($sql);
	
		if($check_for_errors) {
			if(!$stmt) throw new Exception('Error while running:' . "\n$sql\n" . $this->conn->my_error());
		}

		return $stmt;

	}

	public function my_insert_id() {

		return $this->conn->my_insert_id();

	}


	public function escape($val) {

		return $this->conn->my_escape_string($val);

	}
	
    	public function register_job($shard_id=null, $resultset_jobs=0, $function_jobs=0, $tables_used='', $job_result_sql='', $job_type = 'query', $loader_jobs = 0) {

        	if($job_result_sql != "") $job_result_sql = "'" . $this->escape($job_result_sql) . "'"; else $job_result_sql = "''"; 
        	if($tables_used != "") $tables_used = "'" . $this->escape($tables_used) . "'"; else $tables_used = "''";
        	if( !$shard_id || ($shard_id && !is_numeric($shard_id))) $shard_id = "NULL";

        	if(!is_numeric($resultset_jobs)) $resultset_jobs = 0;
        	if(!is_numeric($function_jobs)) $function_jobs = 0;
        	if(!is_numeric($loader_jobs)) $loader_jobs = 0;

        	$job_type = $this->escape($job_type);

        	$sql = "insert into jobs (id, shard_id, resultset_jobs, function_jobs, tables_used, job_result_sql, job_type, loader_jobs)";
        	$sql .= "VALUES ( NULL, $shard_id, $resultset_jobs, $function_jobs, $tables_used, $job_result_sql, '$job_type', $loader_jobs)";

        	$this->execute($sql);
        	$id = $this->my_insert_id();

        	return $id;

    	}

    	public function sweep_jobs() {

        	$sql = "SELECT GET_LOCK('sweep_lock',0) as i";
        	$stmt = $this->execute($sql);
        	$lock_info = $this->conn->my_fetch_assoc($stmt);
        	if($lock_info['i'] !== '1') return false; #did not obtain lock

        	$this->execute('START TRANSACTION');

        	$sql ="select id, resultset_jobs + function_jobs + loader_jobs total_jobs from jobs where completion_percent != 100.00 for update";
        	$stmt = $this->execute($sql);

        	while($row = $this->conn->my_fetch_assoc($stmt)) {
        	    $completion_percent = 0;
        	    $sql = "select sum(completion_type = 'ok') ok_sum, 
        	                   sum(completion_type = 'error') error_sum,
        	                   count(*) complete_count,
        	                   max(complete_time) max_complete_time
        	              from job_worker_status  
        	             where job_id = {$row['id']};";
        	    $stmt2 = $this->execute($sql);

        	    $job_row = $this->conn->my_fetch_assoc($stmt2);
        	
        	    if ($job_row['error_sum'] > 0) { 
        	        $job_status = 'error'; 
        	    } else {
        	        $job_status = ( $job_row['complete_count'] >= $row['total_jobs'] ? 'completed' : 'running' );
        	    }

        	    $completion_percent = (($job_row['complete_count']) / $row['total_jobs']) * 100;
        	    if($completion_percent > 100) $completion_percent = '100.00';

        	    $sql = "UPDATE jobs set end_time = IF($completion_percent = 100.00, '{$job_row['max_complete_time']}', NULL), completion_percent = $completion_percent, job_status='$job_status' where id = " . $row['id'];
    
        	    $this->execute($sql);

        	    $sql = "DELETE job_worker_status.* 
        	              from job_worker_status 
        	              join jobs on jobs.id = job_worker_status.job_id 
        	               and jobs.completion_percent = 100.00";

        	    $this->execute($sql);
        	    
        	}

        	$this->execute('COMMIT');

        	$sql = "SELECT RELEASE_LOCK('sweep_lock')";
        	$this->execute($sql);

    	}

    	public function update_job($job_id, $res_count, $func_count, $tables, $sql, $load_count = 0) {

    	    $sql= $this->escape($sql);
    	    $tables= $this->escape($tables);
    	    if(!is_numeric($job_id)) return false;
    	    if(!is_numeric($res_count)) return false;
    	    if(!is_numeric($func_count)) return false;

    	    $sql = "update jobs set loader_jobs = $load_count, resultset_jobs = $res_count, function_jobs = $func_count, tables_used = '$tables', job_result_sql = '$sql' where id = $job_id;";
    	    return $this->execute($sql);

    	}

    	public function get_schemata() {

    	    $sql = "select schema_name, is_default_schema from schemata where enabled = true";
    	    $stmt = $this->execute($sql);
    	    $rows = array();

    	    while($row = $this->conn->my_fetch_assoc()) {
    	        $rows[] = $row;
    	    }

    	    return $rows;
    	
    	}

	public function refresh_information_schema($schema_name=null, $shard_name="%") {

		if(isset($schema_name)) {
			$subquery = "(select id from schemata where schema_name='" . $this->escape($schema_name) . "' limit 1)";
		} else {
			$subquery = "(select id from schemata where is_default_schema = true limit 1)";
		}
		$shard_name = $this->escape($shard_name);

		$sql = "select * from shards where schema_id = $subquery and shard_name like '$shard_name';";
		$stmt = $this->execute($sql);
		while($row = $this->conn->my_fetch_assoc($stmt)) {
			#these calls block and setting some kind of timeout would be nice but oh well
			$conn = SimpleDAL::factory($row);
			if(!$conn) continue;
			$shard_id = $row['shard_id'];
			$sql = "replace into is_tables select *, $shard_id from information_schema.tables;";
			$conn->my_query($sql);
			$sql = "replace into is_columns select *, $shard_id from information_schema.columns;";
			$conn->my_query($sql);
			$sql = "replace into is_partitions select *, $shard_id from information_schema.partitions;";
			$conn->my_query($sql);
			$conn->my_query('FLUSH TABLES');
	
		}

	}

	public function get_params($schema_name = null) {

		if(isset($schema_name)) {
			$subquery = "(select id from schemata where schema_name='" . $this->escape($schema_name) . "' limit 1)";
		} else {
			$subquery = "(select id from schemata where is_default_schema = true limit 1)";
		}
	
		$params = array();	
		$sql = "select var_name, var_value from schemata_config where schema_id = $subquery";
		$stmt = $this->execute($sql);
		while($row = $this->conn->my_fetch_assoc($stmt)) {
			$params[$row['var_name']] = $row['var_value'];	
		}
		return $params;

	}
	
	public function get_shards($schema_name = null, $coord_only = false) {

		if(!$coord_only && isset($this->shard_cache[isset($schema_name) ? $schema_name : '.'])) return $this->shard_cache[isset($schema_name) ? $schema_name : '.'];
		if(!isset($schema_name)) {
			$sql = "SELECT shards.*,username as user FROM shards join schemata on schema_id = schemata.id where is_default_schema = true";
		} else {
			$sql = "SELECT shards.*,username as user FROM shards join schemata on schema_id = schemata.id where schema_name = '" . $this->escape($schema_name) . "'";

		}

		if($coord_only) $sql .= " AND coord_shard = true";

		$stmt = $this->execute($sql);
	
		$shards = array();
		while($row = $this->conn->my_fetch_assoc($stmt)) {
			$shard = array();
			foreach($row as $key => $val) {
				if(is_numeric($key)) continue;
				$shard[$key] = $val;
			}
				
			$shards[$row['shard_name']] = $shard;
		}
	
		if(!$coord_only) $this->shard_cache[isset($schema_name) ? $schema_name : '.'] = $shards;

		return $shards;

	}
	
	public function add_shard($shard_name, $shard, $coord_shard = true, $accepts_new_rows = true, $extra_info = "", $schema_name = null) {

                if(empty($shard['user'])) $username = "NULL"; else $username="'" . $this->escape($shard['user']) . "'";
                if(empty($shard['db'])) $db = "NULL"; else $db="'" . $this->escape($shard['db']) . "'";
                if(empty($shard['password'])) $password = "NULL"; else $password="'" . $this->escape($shard['password']) . "'";

                if(empty($shard['port'])) $port = "NULL"; else $port="'" . $this->escape($shard['port']) . "'";

                if(empty($shard['host'])) $host = "NULL"; else $host="'" . $this->escape($shard['host']) . "'";
                if(empty($shard['shard_rdbms'])) $shard_rdbms = "'mysql'"; else $shard_rdbms="'" . $shard['shard_rdbms'] . "'";

		if(isset($schema_name)) {
			$subquery = "(select id from schemata where schema_name='" . $this->escape($schema_name) . "' limit 1)";
		} else {
			$subquery = "(select id from schemata where is_default_schema = true limit 1)";
		}

		$shard_name = $this->escape($shard_name);

		$extra_info = "'" . $this->escape($extra_info) . "'";
		$coord_shard = $this->escape($coord_shard);
		

                $sql = "INSERT IGNORE INTO shards (schema_id, shard_name, extra_info, shard_rdbms, coord_shard, accepts_new_rows, username, password, host, port, db) VALUES(";
                $sql .= "$subquery, '$shard_name',$extra_info,$shard_rdbms,'$coord_shard','$accepts_new_rows',$username,$password,$host,$port,$db)";

                $this->execute($sql);

		$this->refresh_shard_cache($schema_name);
	}

	public function refresh_shard_cache($schema_name = null) {

		$this->shard_cache[isset($schema_name) ? $schema_name : '.' ] = null;
		$this->get_shards($schema_name);
		return true;

	}

	public function remove_shard($shard_name, $schema_name = null) {

		if(isset($schema_name)) {
			$schema_name = $this->escape($schema_name);
			$sql = "delete shards.* from shards join schemata on schema_id = schemata.id where schema_name = '$schema_name' and shard_name = '$shard_name'";
		} else {
			$sql = "delete shards.* from shards join schemata on schema_id = schemata.id where is_default_schema = true and shard_name = '$shard_name'";
		}
		$this->execute($sql);
		$this->refresh_shard_cache($schema_name);
		return true;

	}
	
	public function add_schema($schema_name, $is_default_schema = 1) {

		//check to see if we already have a default schema
		if($is_default_schema == 1){
			$sql = "select count(*) as cnt from schemata where is_default_schema = 1";
			$stmt = $this->execute($sql);
			$row = $this->conn->my_fetch_assoc($stmt);
			if($row['cnt'] > 0) {
				throw new Exception('Another Schemata is set as default');
			}
		}

		#this will throw an exception if the unique key is violated	
		$sql = "insert into schemata (schema_name, is_default_schema) values('" . $this->escape($schema_name) . "', $is_default_schema)";
		$this->execute($sql);

		return true;
	
	}

	public function add_column($column, $schema_name = null,$datatype = 'integer',$next_value=1) {

		if(isset($schema_name)) {
			$subquery = "(select id from schemata where schema_name='" . $this->escape($schema_name) . "' limit 1)";
		} else {
			$subquery = "(select id from schemata where is_default_schema = true limit 1)";
		}

		$sql =  "INSERT INTO column_sequences (schema_id, sequence_name, next_value, datatype) VALUES (";
		$sql .= "$subquery,'$column','$next_value','$datatype')";

		$this->execute($sql);

		return true;

	}

	public function move_key($column, $key, $tables, $dest_shard, $schema_name = null ) {

		throw new Exception('Not yet implemented.');

	}

	public function lock_key($column, $key, $lock_mode, $schema_name = null) {

		throw new Exception('Not implemented.');

	}

	public function unlock_key($column, $key, $schema_name = null) {

		throw new Exception('Not implemented.');

	}

	public function get_column_info($column_name, $schema_name = null, $type = 'shard_column') {

        	static $info_cache = array();

		if(isset($schema_name)) {
        		if(!empty($info_cache[$column_name . $schema_name])) {
        			return $info_cache[$column_name . $schema_name];
        		}
			$column_name = $this->escape($column_name);
			$type = $this->escape($type);
			$schema_name = $this->escape($schema_name);
			$sql = "select column_sequences.* from column_sequences join schemata on schemata.id = schema_id where schema_name = '$schema_name' and sequence_name='$column_name' and sequence_type = '{$type}'";
		} else {
			if(!empty($info_cache[$column_name . '_default'])) {
				return $info_cache[$column_name . '_default'];
			}
			$column_name = $this->escape($column_name);
        	    	$type = $this->escape($type);
        	    	$schema_name = $this->escape($schema_name);
			$sql = "select column_sequences.* from column_sequences join schemata on schemata.id = schema_id where is_default_schema=true and sequence_type = '{$type}' ";
		}

		$stmt = $this->execute($sql);

		$row = $this->conn->my_fetch_assoc($stmt);
		if(!$row) throw new Exception('Specified column is not a shard key in the given schema');

        	if(isset($schema_name)) {
        	        $info_cache[$column_name . $schema_name] = $row;
        	} else {
        	        $info_cache[$column_name . '_default'] = $row;
        	}

		return $row;

	}

	public function increment_sequence($sequence_name, $new_val = null, $schema_name = null) {

		if(isset($schema_name)) {
			$subquery = "(select id from schemata where schema_name='" . $this->escape($schema_name) . "' limit 1)";
		} else {
			$subquery = "(select id from schemata where is_default_schema = true limit 1)";
		}

		$col_info = $this->get_column_info($sequence_name, $schema_name,'sequence');		
		if($col_info['datatype'] != 'integer') return false;

		if(isset($new_val)) {
			if($col_info['next_value'] < $key) {
				$sql = "INSERT INTO column_sequences (schema_id, sequence_name,next_value) values (";
				$sql .= "$subquery, '$column', 1) ON DUPLICATE KEY UPDATE next_value = last_insert_id($key)";
			}
		} else {
			#increment non-string columns.  this query won't do anything on string columns (0 rows returned by select for string columns)
			$sql = "INSERT INTO column_sequences (schema_id, sequence_name,next_value) VALUES(";
			$sql .= "$subquery, '$sequence_name', 1) ";
			$sql .= "ON DUPLICATE KEY UPDATE next_value = last_insert_id(next_value+1)";
			$this->execute($sql);
			$new_val = $this->my_insert_id();

		}

		return $new_val;

	}


	public function new_key($column, $key = null, $shard_name = null, $schema_name = null) {

		if(isset($schema_name)) {
			$subquery = "(select id from schemata where schema_name='" . $this->escape($schema_name) . "' limit 1)";
		} else {
			$subquery = "(select id from schemata where is_default_schema = true limit 1)";
		}

		$col_info = $this->get_column_info($column, $schema_name);		

		if(isset($key)) {

			if($col_info['datatype'] != 'string') {
				if($col_info['next_value'] < $key) {
					$sql = "INSERT INTO column_sequences (schema_id, sequence_name,next_value) values (";
					$sql .= "$subquery, '$column', 1) ON DUPLICATE KEY UPDATE next_value = last_insert_id($key)";
				} 
			}

		} else {

			#increment non-string columns.  this query won't do anything on string columns (0 rows returned by select for string columns)
			$sql = "INSERT INTO column_sequences (schema_id, sequence_name,next_value) VALUES(";
			$sql .= "$subquery, '$column', 1) ";
			$sql .= "ON DUPLICATE KEY UPDATE next_value = last_insert_id(next_value+1)";
			$this->execute($sql);
			$key = $this->my_insert_id();

		}

		if(!isset($shard_name)) {
			$sql = "select id, shard_name from shards where schema_id = $subquery order by rand() limit 1";
		} else {
			$sql = "select id, shard_name from shards where schema_id = $subquery and shard_name = '$shard_name' limit 1";
		}
		$stmt = $this->execute($sql);
		$row = $this->conn->my_fetch_assoc($stmt);
		if(!$row) throw new Exception('Could not locate the shard for the row');

		$shard_id = $row['id'];
		$shard_name = $row['shard_name'];

        	$column_id_sql = "( select id from column_sequences where sequence_type = 'shard_column' and sequence_name = '$column' and schema_id = $subquery )";

		if($col_info['datatype'] != 'string') {
			$sql = "INSERT INTO shard_map (column_id, shard_id, key_value) VALUES (";
			$sql .= "$column_id_sql, {$row['id']}, $key);";
		} else {
			$sql = "INSERT INTO shard_map (column_id, shard_id, key_string_value) VALUES (";
			$sql .= "$column_id_sql, {$row['id']}, $key);";
		}

		$this->execute($sql);
		        
		return array($shard_name => $key);

	}

	#returns an array which maps shard names to id values
	#will create the mapping if it does not exist

	/*
	output:
	$map[] = array('shard1' => 1), array('shard20', 5), ...

	You can map with LIKE, IN, equals(=) or null safe equals(<=>).  
	$key should be an IN list "(1,2,3,4,5)" if the operator is IN

	*/
	public function &map($column, $key, $schema_name=null, $operator = "=",  $create_on_miss = true) {
        $null = null;
        if($operator == "between") return $null;

		static $map_cache = array();

		$col_info = $this->get_column_info($column, $schema_name);		
		$col_info_str = join('', $col_info);
		if(!empty($map_cache[$col_info_str . $key . $operator])) {
			return $map_cache[$col_info_str . $key . $operator];
		}

		$this->execute('start transaction');

		if($col_info['datatype'] != 'string') {
			if($operator == "=" || $operator == "<=>") {
				if(!is_numeric($key)) throw new Exception('Tried to map a string value to an integer mapping');
			} 
			$sql = "select shard_name,key_value as val from shard_map join shards on shard_id = shards.id where column_id = {$col_info['id']} and key_value {$operator} {$key} FOR UPDATE";
		} else {
			$sql = "select shard_name,key_string_value as val from shard_map join shards on shard_id = shards.id where column_id = {$col_info['id']} and key_string_value {$operator} {$key} FOR UPDATE";
		}

		$stmt = $this->execute($sql);	

		$map = array();

		while($row = $this->conn->my_fetch_assoc($stmt)) {
			$map[] = array($row['shard_name'] => $row['val']);
		}

		if(empty($map)) {
			if($create_on_miss == true) {
				#map to a random shard
				$info = $this->new_key($column, $key, null, $schema_name);
				$map = array($info);
			} else {
				$map = false;
			}
		}
		$this->execute('commit');

		$map_cache[$col_info_str . $key . $operator] = $map;

		return($map);
	}

	#get all the keys which belong to a particular shard
	public function &get_keys($column, $shard_name, $schema_name = null) {
		
		$col_info = $this->get_column_info($column, $schema_name);

		if($col_info['datatype'] != 'string') {
			$sql = "select key_value val from shard_map join shards on shards_id = shards.id where shard_name = '$shard_name' and column_id = {$col_info['id']}";
		} else {
			$sql = "select key_string_value val from shard_map join shards on shards_id = shards.id where shard_name = '$shard_name' and column_id = {$col_info['id']}";
		}
		$stmt = $this->execute($sql);	
		$keys = array();

		while($row = $this->conn->my_fetch_assoc($stmt)) {
			$keys[] = $row['val'];
		}
	
		return $keys;
	}

	public function &get_key_distribution($column, $schema_name = null) {

		if(isset($schema_name)) {	
			$schema_name = $this->escape($schema_name);
			$sql = 
			"select shard_name, count(*) cnt 
			   from shard_map 
			   join shards 
			     on shard_id = shards.id 
			   join column_sequences 
			     on column_id = column_sequences.id 
			  where column_name = '$column' 
			    and sequence_type = 'shard_column' 
			    and schema_name = '$schema_name' 
			  group by shard_name";
		} else {
			$sql = 
			"select shard_name, count(*) cnt 
			   from shard_map 
			   join shards 
			     on shard_id = shards.id 
			   join column_sequences 
			     on column_id = column_sequences.id 
			  where column_name = '$column' 
			    and sequence_type = 'shard_column' 
			    and is_default_schema = true 
			  group by shard_name";
		}

		$stmt = $this->execute($sql);
		while($row = $this->conn->my_fetch_assoc($stmt)) {
			$rows[$row['shard_name']] = $row['cnt'];
		}
	
		return $rows;
	}


	public function get_column($schema_name = null) {
		$column = false;
		if(!isset($schema_name)) {
			$sql = "select sequence_name as column_name from column_sequences join schemata on schema_id = schemata.id where sequence_type = 'shard_column' and is_default_schema = true";
		} else {
			$sql = "select sequence_name as column_name from column_sequences join schemata on schema_id = schemata.id where sequence_type = 'shard_column' and schema_name = '$schema_name'";
		}
		$stmt = $this->execute($sql);	
		while( $row = $this->conn->my_fetch_assoc($stmt) ) {
			$column = $row['column_name'];
		}

		return $column;

	}

	public function get_gearman_servers($schema_name = null) {

		if(isset($schema_name)) {
			$subquery = "(select id from schemata where schema_name='" . $this->escape($schema_name) . "' limit 1)";
		} else {
			$subquery = "(select id from schemata where is_default_schema = true limit 1)";
		}

		$sql = "select concat(hostname, ':', port) as server from gearman_job_servers where schema_id = $subquery and enabled = 1";
		$stmt=$this->execute($sql);
		$servers = array();
		while( $row = $this->conn->my_fetch_assoc($stmt) ) {
			$servers[] = $row['server'];
		}
		return $servers;

	}
	
}

class HashShardKeyMapper extends DirectoryShardKeyMapper{

	public function __construct($config, $no_connect = false ) {
		parent::__construct($config);
	} 

	public function remove_shard($shard_id, $schema = null) {
		throw new Exception('Can not remove shard: Removing a shard would change the hash distribution');
	}
	
	public function move_key($column, $key, $tables, $dest_shard, $schema_name = null ) {
		throw new Exception('Hash partitions are fixed.');
	}

	public function lock_key($column, $key, $lock_mode, $schema = null) {
		throw new Exception('Locks are not supported.');
	}

	public function unlock_key($column, $key, $schema = null) {
		throw new Exception('Locks are not supported.');
	}

	#get a new key value for the given partition
	public function new_key($column, $key = null, $shard_name = null, $schema_name = null) {
		throw new Exception('Hash partitioning does not support key generation');
	}

	#get all the keys which belong to a particular shard
	public function &get_keys($column, $shard_name, $schema_name = null) {
		throw new Exception('Hash partitioning does not use a data store and can not enumerate keys');
	}

	public function &get_key_distribution($column, $schema_name = null) {
		throw new Exception('Hash partitioning does not use a data store and can not compute the distribution of keys');
	}

	#returns a shard object
        public function &map($column, $key, $schema_name=null, $operator = "=", $create_on_miss = true) {

        	static $null = null;
		static $cache = array();
		$shards = $this->get_shards($schema_name);

        	if($operator == "BETWEEN") {
        	    preg_match("/([0-9]+) AND ([0-9]+)/", $key, $matches);
        	    if(!$matches) return $null;
        	    $operator = "in";
        	    $out = "";
        	    for($i=$matches[1];$i<=$matches[2];++$i) {
        	        if($out) $out .= ",";
        	        $out .= $i;
        	    }
        	    $key = "($out)";
        	}

		if($operator != '=' && strtolower($operator) != 'in') return $null;

		if(strtolower($operator) == "in") {
			$vals = explode(',', trim($key,'()'));
			$prev_item = "";
			$list = array();
			foreach($vals as $item) {
				if($prev_item != "") $item = $prev_item . $item;
				#did explode split a string containing an embedded comma?
				if(!is_numeric($item) && !in_array(substr(trim($item), -1), array("'",'"'))) {
					$prev_item = $item;
				} else {
					$prev_item = "";
				}

				$list[] = $item;
			}

		} else {
			$list = array($key);
		}


		$map = array();
		$shard_names = array_keys($shards);
		foreach($list as $item) {

			if(is_numeric($item)) {
				$map[] = array($shard_names[ $item % count($shard_names) ] => $item);
			} else {
				$map[] = array($shard_names[ (abs(crc32($item)) % count($shard_names)) ] => $item);
			}

		}

		return $map;

	}

}

class NoneShardKeyMapper extends HashShardKeyMapper{

	public function __construct($config, $no_connect = false ) {
		parent::__construct($config);
	} 

	public function &map($column, $key, $schema_name=null, $operator = "=", $create_on_miss = true) {
		static $null = null;
		return $null;
	}

}
