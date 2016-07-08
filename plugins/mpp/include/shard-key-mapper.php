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
        function remove_shard($shard_name, $schema_name = null);
        function add_schema($schema_name, $is_default_schema = true);
        function add_column($column, $schema_name = null,$datatype = 'integer',$next_value=1);
        function move_key($column, $key, $tables, $dest_shard, $schema_name = null );
        function lock_key($column, $key, $lock_mode, $schema_name = null);
        function unlock_key($column, $key, $schema = null); 
        function get_column_info($column_name, $schema_name = null);
        function new_key($column, $key = null, $shard_name = null, $schema_name = null);
        function &map($column, $key, $schema_name=null, $operator = "=", $create_on_miss = true);
        function get_column($shema_name = null);
}


class DirectoryShardKeyMapper implements ShardKeyMapper{
	var $conn = null;  
  var $cache = array();

	#The constructor accepts an array which includes host, user, password and db
	public function __construct($config, $no_change_db = false,$connect=false) {

		if(empty($config['host']) || empty($config['user']) || empty($config['db'])) {
			throw new Exception('Missing one of required options: host, user or db');
		}

		$this->config = $config;
		if($connect) $this->connect($config);

	} 
	
	private function connect($config, $no_change_db = false) {
    if($this->conn) {
		  $this->conn->my_ping();
    }

		if($this->conn) return $this->conn;
		$this->conn = SimpleDAL::factory($config);
		
		if($this->conn->my_error()) throw new Exception($this->conn->my_error());
	
		if(!$no_change_db) { 
			$this->conn->my_select_db($this->config['db']);
			if($this->conn->my_error()) throw new Exception($this->conn->my_error());
		}

	}

	private function execute($sql, $retry = false) {
    $this->reset_fetch = true;
    $md5 = md5($sql);
    if(preg_match('/^select/i',$sql)) {
      if(isset($this->cache[$md5])) return $this->cache[$md5];
    }
		$this->connect($this->config);
		$stmt = $this->conn->my_query($sql);
    while(!$stmt && $retry) {
      $e = $this->conn->my_error();
      if($e == 1213 || $e == 1205) continue; // retry statement on deadlock or lock wait timeout
      if($e == 1022) {  /* duplicate key error returns false*/
        return false;
      }
      /* any other error */
			throw new Exception('Error while running:' . "\n$sql\n" . $this->conn->my_error());
		  if($stmt = $this->conn->my_query($sql)) break;
    }
	
		if(!$stmt) throw new Exception('Error while running:' . "\n$sql\n" . $this->conn->my_error());

    if(preg_match('/^select/i',$sql)) {
      $rows = array();
      while($row = $this->conn->my_fetch_assoc($stmt)) {
        $rows[]=$row;
      }
      $this->cache[$md5] = $rows;
		  return $rows;
    } 

    return true;

	}

  public function fetch($rows) {
    static $row_num = 0;
    static $row_count = 0;
    if($this->reset_fetch) { 
      $row_num = 0;
      $row_count = count($rows);
      $this->reset_fetch = false;
    }

    if($row_count == 0 || $row_num >= $row_count) {
      return false;
    }
    $row = $rows[$row_num];
    ++$row_num;
    return $row;
  }

	public function my_insert_id() {

		return $this->conn->my_insert_id();

	}


	public function escape($val) {
		$this->connect($this->config, true);
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
        	$lock_info = $this->fetch($stmt);
        	if($lock_info['i'] !== '1') return false; #did not obtain lock

        	$this->execute('START TRANSACTION');

        	$sql ="select id, resultset_jobs + function_jobs + loader_jobs total_jobs from jobs where completion_percent != 100.00 for update";
        	$stmt = $this->execute($sql);

        	while($row = $this->fetch($stmt)) {
        	    $completion_percent = 0;
        	    $sql = "select sum(completion_type = 'ok') ok_sum, 
        	                   sum(completion_type = 'error') error_sum,
        	                   count(*) complete_count,
        	                   max(complete_time) max_complete_time
        	              from job_worker_status  
        	             where job_id = {$row['id']};";
        	    $stmt2 = $this->execute($sql);

        	    $job_row = $this->fetch($stmt2);
        	
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

    	    $sql = "select id,schema_name, is_default_schema from schemata where enabled = true";
    	    $stmt = $this->execute($sql);
    	    $rows = array();

    	    while($row = $this->fetch($stmt)) {
    	        $rows[] = $row;
    	    }

    	    return $rows;
    	
    	}

  public function get_schema_id($schema_name = "__default") {
    $schemata = $this->get_schemata();
    $id = false;
    foreach($schemata  as $schema) {
      if($schema_name != "__default") {
        if($schema['schema_name'] == $schema_name) {
          $id = $schema['id'];
          break;
        }
      } elseif($schema['is_default_schema'] == 1) {
        $id = $schema['id'];
        break;
      }
    }
    
    return $id;
  }

  public function get_column_id($schema_id, $column_name) { 
    $sql = "select id from shard_columns where column_name = '$column_name' and schema_id = $schema_id";
    $stmt = $this->execute($sql);
    $row = $this->fetch($stmt);
    return $row['id'];
  }

	public function refresh_information_schema($schema_name=null, $shard_name="%") {
    $schema_id = $this->get_schema_id($schema_name);
   
		$shard_name = $this->escape($shard_name);

		$sql = "select * from shards where schema_id = $schema_id and shard_name like '$shard_name';";
		$stmt = $this->execute($sql);
		while($row = $this->fetch($stmt)) {
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
	
		$params = array();	
		$sql = "select var_name, var_value from schemata_config where schema_id = " . $this->get_schema_id($schema_name);
		$stmt = $this->execute($sql);
		while($row = $this->fetch($stmt)) {
			$params[$row['var_name']] = $row['var_value'];	
		}
		return $params;

	}
	
	public function get_shards($schema_name = null, $coord_only = false) {

		if(!isset($schema_name)) {
			$sql = "SELECT /*boo*/ shards.*,username as user FROM shards join schemata on schema_id = schemata.id where is_default_schema = true";
		} else {
			$sql = "SELECT /*foo*/ shards.*,username as user FROM shards join schemata on schema_id = schemata.id where schema_name = '" . $this->escape($schema_name) . "'";

		}

		if($coord_only) $sql .= " AND coord_shard = true";

		$stmt = $this->execute($sql);
	
		$shards = array();
		while($row = $this->fetch($stmt)) {
			$shard = array();
			foreach($row as $key => $val) {
				if(is_numeric($key)) continue;
				$shard[$key] = $val;
			}
				
			$shards[$row['shard_name']] = $shard;
		}

		return $shards;

	}
	
	public function add_shard($shard_name, $shard, $coord_shard = true, $accepts_new_rows = true, $extra_info = "", $schema_name = null) {

    if(empty($shard['user'])) $username = "NULL"; else $username="'" . $this->escape($shard['user']) . "'";
    if(empty($shard['db'])) $db = "NULL"; else $db="'" . $this->escape($shard['db']) . "'";
    if(empty($shard['password'])) $password = "NULL"; else $password="'" . $this->escape($shard['password']) . "'";

    if(empty($shard['port'])) $port = "NULL"; else $port="'" . $this->escape($shard['port']) . "'";

    if(empty($shard['host'])) $host = "NULL"; else $host="'" . $this->escape($shard['host']) . "'";
    if(empty($shard['shard_rdbms'])) $shard_rdbms = "'mysql'"; else $shard_rdbms="'" . $shard['shard_rdbms'] . "'";
    $schema_id = $this->get_schema_id($schema_name);

		$shard_name = $this->escape($shard_name);

		$extra_info = "'" . $this->escape($extra_info) . "'";
		$coord_shard = $this->escape($coord_shard);
		

    $sql = "INSERT IGNORE INTO shards (schema_id, shard_name, extra_info, shard_rdbms, coord_shard, accepts_new_rows, username, password, host, port, db) VALUES(";
    $sql .= "$schema_id, '$shard_name',$extra_info,$shard_rdbms,'$coord_shard','$accepts_new_rows',$username,$password,$host,$port,$db)";

    $this->execute($sql);

	}

	public function remove_shard($shard_name, $schema_name = null) {

		if(isset($schema_name)) {
			$schema_name = $this->escape($schema_name);
			$sql = "delete shards.* from shards join schemata on schema_id = schemata.id where schema_name = '$schema_name' and shard_name = '$shard_name'";
		} else {
			$sql = "delete shards.* from shards join schemata on schema_id = schemata.id where is_default_schema = true and shard_name = '$shard_name'";
		}
		$this->execute($sql);
		return true;

	}
	
	public function add_schema($schema_name, $is_default_schema = 1) {

		//check to see if we already have a default schema
		if($is_default_schema == 1){
			$sql = "select count(*) as cnt from schemata where is_default_schema = 1";
			$stmt = $this->execute($sql);
			$row = $this->fetch($stmt);
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


		$sql =  "INSERT INTO shard_columns (schema_id, column_name,  datatype) VALUES (";
		$sql .= $this->get_schema_id($schema_name) . ",'$column','$datatype')";

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
    $schema_id = $this->get_schema_id($schema_name);
    $column_id = $this->get_column_id($schema_id, $column_name);

		$sql = "select * from shard_columns where schema_id = $schema_id and id = $column_id"; 

		$stmt = $this->execute($sql);

		$row = $this->fetch($stmt);
		if(!$row) throw new Exception('Specified column is not a shard key in the given schema');

		return $row;

	}

	public function new_key($column, $key = null, $shard_name = null, $schema_name = null) {
    $schema_id = $this->get_schema_id($schema_name);
		$col_info = $this->get_column_info($column, $schema_name);		

		$shards = $this->get_shards($schema_name);
    $row = false;
		if(!isset($shard_name)) {
      $row = $shards[array_rand($shards)];
		} else {
      foreach($shards as $shard) {
        if($shard['shard_name'] == $shard_name) {
          $row = $shard;
          break;
        }
      }
		}

    if(!$row) throw new exception("NEW_KEY_ERROR: Could not find specifed shard");

		$shard_id = $row['id'];
		$shard_name = $row['shard_name'];

    $column_id = $this->get_column_id($schema_id, $column);

		if($col_info['datatype'] != 'string') {
			$sql = "INSERT INTO shard_map (column_id, shard_id, key_value) VALUES (";
			$sql .= "$column_id, {$row['id']}, $key);";
		} else {
			$sql = "INSERT INTO shard_map_string (column_id, shard_id, key_value) VALUES (";
			$sql .= "$column_id, {$row['id']}, $key);";
		}

    /* if false, somebody else beat us to creating a new map for this key*/
		if(!$this->execute($sql, true)) return false;
		        
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
    while(1) {
      $null = null;
      if($operator == "between") return $null;

		  $col_info = $this->get_column_info($column, $schema_name);		
		  $col_info_str = join('', $col_info);

		  #$this->execute('start transaction');


		  if($col_info['datatype'] != 'string') {
		    if(!is_numeric($key)) throw new Exception('Tried to map a string value to an integer mapping');
        if($col_info['is_range']) {
          if($operator == ">" || $operator == ">=") {
  		  	  $sql = "select shard_name,key_value as val from shard_map join shards on shard_id = shards.id where column_id = {$col_info['id']} and $key $operator range_begin";
          }elseif($operator == "<" || $operator == "<=") {
		  	    $sql = "select shard_name,key_value as val from shard_map join shards on shard_id = shards.id where column_id = {$col_info['id']} and $key $operator range_end";
          }elseif(strtolower($operator) == 'between') {
            $operands = explode(" AND ", $key);
		  	    $sql = "select shard_name,key_value as val from shard_map join shards on shard_id = shards.id where column_id = {$col_info['id']} and {$operands[0]} >= range_begin and {$operands[1]} <= range_end";
          } else {
            return false;
          }
        } else {
		  	  $sql = "select shard_name,key_value as val from shard_map join shards on shard_id = shards.id where column_id = {$col_info['id']} and key_value {$operator} {$key}";
        }
		  } else {
		  	$sql = "select shard_name,key_value as val from shard_map_string join shards on shard_id = shards.id where column_id = {$col_info['id']} and key_value {$operator} {$key}";
		  }

		  $stmt = $this->execute($sql);	

		  $map = array();

		  while($row = $this->fetch($stmt)) {
		  	$map[] = array($row['shard_name'] => $row['val']);
		  }

		  if(empty($map)) {
		  	if($create_on_miss == true) {
          if($col_info['is_range']) {
            return false;
          }
		  		#map to a random shard
		  		$info = $this->new_key($column, $key, null, $schema_name);
          /* duplicate key means we beat us to map the new key, retry reading from DB for mapping */
          if($info == false) {
            continue;
          }
		  		$map = array($info);
		  	} else {
		  		$map = false;
		  	}
		  }
		  #$this->execute('commit');

		  return($map);
    }
	}

	public function get_column($schema_name = null) {
		$column = false;
		if(!isset($schema_name)) {
			$sql = "select sequence_name as column_name from column_sequences join schemata on schema_id = schemata.id where sequence_type = 'shard_column' and is_default_schema = true";
		} else {
			$sql = "select sequence_name as column_name from column_sequences join schemata on schema_id = schemata.id where sequence_type = 'shard_column' and schema_name = '$schema_name'";
		}
		$stmt = $this->execute($sql);	
		while( $row = $this->fetch($stmt) ) {
			$column = $row['column_name'];
		}

		return $column;

	}

	public function get_gearman_servers($schema_name = null) {

		$sql = "select concat(hostname, ':', port) as server from gearman_job_servers where enabled = 1 and schema_id = " . $this->get_schema_id($schema_name);
		$stmt=$this->execute($sql);
		$servers = array();
		while( $row = $this->fetch($stmt) ) {
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


	#returns a shard object
  public function &map($column, $key, $schema_name=null, $operator = "=", $create_on_miss = true) {
    static $null = null;
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
