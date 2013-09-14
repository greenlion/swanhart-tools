<?php

require_once 'worker_common.php';
require_once 'Net/Gearman/Job.php';
require_once 'salted-cache.php';
require_once 'DAL/simple-dal.php';

class Net_Gearman_Job_shard_query_worker extends Net_Gearman_Job_Common {
	private $cache = null;
	private $cache_rules = null;

	public function run($arg) {
		require 'shard-query-config.php';
		if(isset($cache_servers) && !empty($cache_servers)) $this->cache = new SaltedCache($cache_servers);
		if(isset($cache_rules) && !empty($cache_rules)) $this->cache_rules = $cache_rules;
		if(!$arg) return false;

		$arg = (object)$arg;

		$fields = false;
		$errors = false;
		$rows = false;
		$sql = "";
		$has_rows = false;
		$resultset = null;
		if(!isset($arg->schema_name)) $arg->schema_name=null;
		$SQ = new ShardQuery($arg->schema_name);
		print_r($arg);

		if(empty($arg->sql) || !trim($arg->sql)) return false;

		if(stripos($arg->sql, 'select database()') !== false) {
			$fields = array(array( 'type' => 250, 'name' => 'DATABASE()'));
			$rows = array(array($SQ->state->schema_name));
			$resultset = array('fields' => &$fields, 'rows' => &$rows);
		} elseif(preg_match('/^SHOW/i', $arg->sql)) {
			$shard_id = array_rand($SQ->state->shards);
			$DAL = SimpleDAL::factory($SQ->state->shards[$shard_id]);
			$DAL->my_select_db($SQ->state->shards[$shard_id]['db']);

			if(preg_match('/show\s+databases/i', $arg->sql)) {
				$databases = $SQ->state->mapper->get_schemata();

				$fields = array(array( 'type' => 250, 'name' => 'Database'));
			/*	$rows = array();
				foreach($databases as $schema_info) {
					$rows[] = array($schema_info['schema_name']);
				}		
			*/
				$rows = array(array($SQ->state->schema_name));

				$resultset = array('fields' => &$fields, 'rows' => &$rows);

			} elseif( $stmt = $DAL->my_query($arg->sql)) {
				$row = $DAL->my_fetch_assoc();
				foreach($row as $field => $val) {
					$rows[0][] = $val;
					$fields[] = array( 'type' => 250, 'name' => $field );
				}
				while($row = $DAL->my_fetch_array($stmt,MYSQL_NUM)) $rows[] = $row;
				print_r($rows);
				$resultset = array('fields' => &$fields, 'rows'=>&$rows);
			}

			$DAL->my_close();
			unset($DAL);

		} elseif (preg_match('/select\s+.*\sfrom\s.*/i', $arg->sql)) {
			$cache_ttl = null;
			if(isset($this->cache)) {
				$patterns = $this->cache_rules;
				foreach($patterns as $pattern => $ttl) {
					if(preg_match($pattern, $arg->sql)) {
						$cache_ttl = $ttl;
						$resultset = $this->cache->get($arg->sql, $arg->schema_name);
						break;
					}
				}
			}

			if(!$resultset) {
				$stmt = $SQ->query($arg->sql);
				if(!empty($SQ->errors)) $errors = trim(str_replace(array("\n","Array","(",")","  "),"",print_r($SQ->errors, true)));
				if($stmt) {
					$has_rows = true;
					$rows = array(array());
					# get the first row and use it to construct the list of fields + collect row data
					# in this first fetch we process data one output column at a time
					$row = $SQ->DAL->my_fetch_assoc($stmt);
					foreach($row as $field => $val) {
						$rows[0][] = $val;
						$fields[] = array( 'type' => 250, 'name' => $field );
					}

					# fetch the rest of the rows numerically and stuff into $rows, a row at a time
					while($row = $SQ->DAL->my_fetch_array($stmt,MYSQL_NUM)) $rows[] = $row;

					$resultset = array('fields' => &$fields, 'rows'=>&$rows);

				}
				if(isset($cache_ttl)) {
					$this->cache->set($arg->sql, $resultset, $cache_ttl, $arg->schema_name);
				}
			
				# return the actual object so that the proxy can finish aggregation and drop the table
			}
		} else {
			$sql = $arg->sql;
		}

		return json_encode(array('resultset' => $resultset, 'errors'=>$errors, 'sql' => $sql, 'has_rows' => $has_rows));
	}

	function make_resultset($SQ, &$stmt = null) {
		$resultset = null;


		return $resultset;
	}
}


?>
