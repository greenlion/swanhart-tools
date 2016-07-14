<?php

require_once 'worker_common.php';
require_once 'Net/Gearman/Job.php';
require_once('shard-query.php');
require_once('shard-query-config.php');

class Net_Gearman_Job_new_loader extends Net_Gearman_Job_Common {

	public function run($arg) {
    global $config_database;
		if(!$arg) return false;
    $arg=(object)$arg;

    $SQ = new ShardQuery($arg->schema_name);

    $info = explode('::',$arg->info);
    $delimiter = $arg->delimiter;
    $shard_name = $info[0];

    $shard = $SQ->shards[$shard_name];
    $shard['db'] = $info[1];
    $shard = (object)$shard;

    $table = $info[2];

    $path = $info[3];

    $conn = mysqli_connect($shard->host, $shard->user, $shard->password, $shard->db, $shard->port);
    $sql = "LOAD DATA LOCAL INFILE '$path' INTO TABLE $table FIELDS TERMINATED BY '$delimiter'";
    echo($sql);
    mysqli_query($conn, $sql);
    if(mysqli_error($conn)) {
      echo mysqli_error($conn) . "\n";
      return false;
    }

//    $SQ->mapper->conn->my_query("UPDATE new_loader SET splits_done = splits_done + 1 where id = {$arg->job_id}");

    return true;
    
	}

}


?>
