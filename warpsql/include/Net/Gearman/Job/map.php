<?php

require_once 'worker_common.php';
require_once 'Net/Gearman/Job.php';
require_once('shard-query.php');
require_once('shard-query-config.php');
$cache = array();
$mapper_cache = array();
$mc = false;
class Net_Gearman_Job_map extends Net_Gearman_Job_Common {
	public function run($arg) {
    global $cache;
    global $mapper_cache;
    global $config_database;
    global $mc;

		if(!$arg) return false;
    if(!$mc) {
      $mc = new Memcache;
      $mc->connect('127.0.0.1');
    }
    $data = $mc->get($arg);
    if($data) return $data;

    $vars = explode('::',$arg);
    $schema_name = $vars[0];
    $column_name = $vars[1];
    $value = $vars[2];
    if(!empty($mapper_cache[$column_name . '_' . $schema_name])) {
      $mapper = $mapper_cache[$column_name . '_' . $schema_name];
    } else {
      switch($config_database['mapper_type']) {
        case SQ_DIRECTORY:
         $mapper = new DirectoryShardKeyMapper($config_database);
         break;
          
        case SQ_HASH:
         $mapper = new HashShardKeyMapper($config_database);
         break;
      }
      $mapper_cache[$column_name . '_' . $schema_name] = $mapper;
    }
    $map = $mapper->map($column_name,$value,$schema_name,'=',true);
    $s = @array_pop(array_keys($map[0]));
    $mc->set($arg, $s);

    return $s;
    
	}

}


?>
