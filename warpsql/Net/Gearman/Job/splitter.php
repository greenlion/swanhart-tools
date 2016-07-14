<?php

require_once 'worker_common.php';
require_once 'Net/Gearman/Job.php';
require_once('shard-query.php');
require_once('shard-query-config.php');
class Net_Gearman_Job_splitter extends Net_Gearman_Job_Common {
	function run($arg) {
		if(!$arg) return false;
    $arg = (object)$arg;
    global $config_database;
    $db = (object)$config_database;
    $this->SQ = new ShardQuery($arg->schema_name);

    $clean_up = false;
    if($arg->is_aws || $arg->is_http) {
      $arg = $this->get_remote($arg);
      $clean_up = true;
    }

    $d = dirname(__FILE__);
    //the shard splitter must be in the path 
    $cmd =  "shard_split -t {$arg->table} -f {$arg->filename} -j {$arg->start} -e {$arg->end} -y '{$arg->delimiter}' -h {$db->host} -u {$db->user} -p {$db->password} -d {$db->db} -s {$arg->schema_name} -P {$db->port} 2>&1";

    $data = system($cmd, $retval);

    if($retval == -1) {
      echo $data;
      return false;
    }
    $data = explode("|", $data);
    $load_stats = array_shift($data);

    $split_count = count($data);
//    $sql = "UPDATE new_loader set split_count = split_count + $split_count WHERE id = {$arg->job_id}";
    $this->SQ->mapper->conn->my_query($sql);
    /* schedule loading jobs for the split files */
    $task_type = Net_Gearman_task::JOB_BACKGROUND;
    foreach($data as $info) {
      $set = new Net_Gearman_Set();
      $new_arg = $arg;
      $new_arg->info = $info;
      $task = new Net_Gearman_Task("new_loader", $new_arg, md5(rand()), $task_type);
      $set->addTask($task);
      $this->SQ->state->client->runSet($set);
    }

    if($clean_up) {
      unlink($arg->filename);
    }
  }

  function get_remote($arg) {
      if($arg->is_aws) { 
        system("mkdir -p " . $this->SQ->state->shared_path . "/loader/aws_temp/");
        $fname = tempnam($this->SQ->state->shared_path . "/loader/aws_temp/" , mt_rand(1,999999999));
        unlink($fname);
        echo "Fetching a chunk from S3 for loading (tempname: $fname)\n";
        $s3 = new S3($arg->aws_access_key, $arg->aws_secret_key);
        $new_end = $arg->end + 256000;
        if($new_end > $arg->filesize) $new_end = $arg->filesize;
        if($arg->start > 0) {
          $s3->getObject($arg->aws_bucket, $arg->aws_file, $fname, array($arg->start-1, $new_end));
          $arg->end = $arg->end - $arg->start;
          $arg->start = 1; 
        } else {
          $s3->getObject($arg->aws_bucket, $arg->aws_file, $fname, array($arg->start, $new_end));
        }
        if(!filesize($fname)) return false;
        return $fname;
      } elseif($arg->is_http) {
        system("mkdir -p " . $this->SQ->state->shared_path . "/loader/http_temp/");
        $fname = tempnam($this->SQ->state->shared_path . "/loader/http_temp/" , mt_rand(1,999999999));
        $fh = fopen($fname, "wb");
        $new_end = $arg->end + 256000;
        if($new_end > $arg->filesize) $new_end = $arg->filesize;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $arg->filename);
        curl_setopt($curl, CURLOPT_USERAGENT, 'WarpSQL/loader');
        if($arg->start > 0) {
          curl_setopt($curl, CURLOPT_RANGE, $arg->start-1 . "-" . $new_end);
          $arg->end = $arg->end - $arg->start;
          $arg->start = 1;
        } else {
          curl_setopt($curl, CURLOPT_RANGE, $arg->start . "-" . $new_end);
        }
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_FILE, $fh);
        $result = curl_exec($curl);
        curl_close($curl);
        fclose($fp);

        if (!$result) {
          $this->errors[] = "HTTP range transfer failed, or local IO error";
          return false;
        }
      }

      $arg->filename = $fname;
      $arg->filesize = filesize($fname);

      return $arg;

    }

}


?>
