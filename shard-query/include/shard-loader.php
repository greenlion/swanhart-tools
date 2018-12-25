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
require_once('chunkit.php');
require_once("S3.php");
if (!defined('SQ_DEBUG'))
  require_once('shard-query.php');

ini_set('memory_limit', '4G'); // because CURL buffers into memory

if (!function_exists('posix_mkfifo')) {
  function posix_mkfifo($path, $mode = '0444') {
    $mkfifo = trim(`which mkfifo`);
    if ($mkfifo) {
      #don't clobber existing files
      if (file_exists($path))
        return false;
      `$mkfifo --mode=$mode $path`;
      
      #if the file exists, then the call worked
      return file_exists($path);
    } else {
      return false;
    }
  }
}

/*USAGE:

$SQ = new ShardQuery();
$loader = new ShardLoader($SQ, "\t");
$loader->load_gearman('/tmp/test_load.txt','test_load', null, 8);
*/
define('DEFAULT_CHUNK_SIZE', 16 * 1024 * 1024);

class ShardLoader {
  
  public function __construct($SQ, $delimiter = ",", $enclosure = "", $line_terminator = "\n", $use_fifo = true, $chunk_size = DEFAULT_CHUNK_SIZE, 
                              $charset = 'latin1', $ignore = false, $replace = false, $line_starter="", $escape = "\\") {
    $this->SQ              = $SQ;
    #$delimiter             = str_replace(array( '\\'), array( "\\\\"), $delimiter); 
    #$escape                = str_replace(array( '\\'), array( "\\\\"), $escape);

    $this->delimiter       = $delimiter;
    $this->enclosure       = $enclosure;
    $this->line_terminator = $line_terminator;
    $this->use_fifo        = $use_fifo;
    
    $this->chunk_size = $chunk_size;

    $this->charset = $charset;
    $this->ignore = $ignore;
    $this->replace = $replace;
    $this->line_starter = $line_starter;
    $this->escape = $escape;
    
    #FIXME
    #support terminators that don't end in newline
    if (substr($this->line_terminator, -1, 1) !== "\n") {
      throw new Exception('Line terminator must end in newline.');
    }
  }

  protected function curl_get_file_size( $url ) {
    // Assume failure.
    $result = -1;

    $curl = curl_init( $url );

    // Issue a HEAD request and follow any redirects.
    curl_setopt( $curl, CURLOPT_NOBODY, true );
    curl_setopt( $curl, CURLOPT_HEADER, true );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );

    $data = curl_exec( $curl );
    curl_close( $curl );

    if( $data ) {
      $content_length = "unknown";
      $status = "unknown";

      if( preg_match( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches ) ) {
        $status = (int)$matches[1];
      }

      if( preg_match( "/Content-Length: (\d+)/", $data, $matches ) ) {
        $content_length = (int)$matches[1];
      }

      // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
      if( $status == 200 || ($status > 300 && $status <= 308) ) {
        $result = $content_length;
      }
    }
    return $result;
  }
  protected function extract_s3_path($url) {
     if(preg_match("#s3://([^/]+)/(.*)#", $url, $matches)) return $matches;
     return false;
  }
  
  /*
  Load a file.
  */
  public function load($path, $table, $chunk_size = null, $segment_count = null, $columns_str = null, $set_str = null, $ignore ="", $replace = "") {
    if (!isset($chunk_size)) {
      $chunk_size = $this->chunk_size;
    }
    $fs = false;
    $url = false;

    if (!isset($segment_count)) {
      $chunker = new ChunkIt($this->line_terminator);
      if(strstr($path, "s3://")) {
        if(!isset($this->SQ->state->aws_access_key) || !isset($this->SQ->state->aws_secret_key)) {
          $this->errors[] = "Missing AWS secret key or AWS access key.  Please set them in the schemata_config table";
          return false;
        }
	$pathinfo = $this->extract_s3_path($path);
        $bucket = $pathinfo[1]; 
        $file = $pathinfo[2];
        $s3 = new S3($this->SQ->state->aws_access_key, $this->SQ->state->aws_secret_key);
	$info = @$s3->getObjectInfo($bucket, $file);
        if(!$info) {
          $this->errors[] = "Could not get S3 information for $path";
          return false;
        }
        $segment_count = floor($info['size'] / $chunk_size);
        echo "Getting file offsets (may take awhile)\n";
        $info = $chunker->s3_find_offsets($s3, $bucket, $file,$info['size'],$chunk_size, $segment_count);

      } elseif(strstr($path, "http://") || strstr($path, "https://")) {
        $fs = curl_get_file_size($path);
        if (!$fs) {
          $this->errors[] = "Could not get size of $path";
          return false;
        }
        $segment_count = floor($fs / $chunk_size);
        $info = $chunker->http_find_offsets($path, $fs,$chunk_size, $segment_count);
      } else { /* NON-S3 load here */
        $fs = filesize($path);

        if (!$fs) {
          $this->errors[] = "Could not get size of $path";
          return false;
        }
      
        $segment_count = floor($fs / $chunk_size);
        $info    = $chunker->find_offsets($path, $segment_count);

      }

    }
    if(!isset($pathinfo)) $pathinfo=$path;
    foreach ($info as $segment) {
      $return = $this->load_segment($pathinfo, $table, $segment['start'], $segment['end'], $columns_str, $set_str, $ignore, $replace);
    }
    if(!isset($this->errors)) $this->errors=array(); 
    echo "done!\n";
    
  }

  public function load_from_handle($fh, $table, $chunk_size = null) {
    if($chunk_size === null) $chunk_size = 1024 * 1024 * 256;
    $buffer = array();
    $buflen = 0;

    while($line = fgets($fh)) {
      $buffer[] = $line;
      $buflen += strlen($line);

      if($buflen >= $chunk_size) {
        $this->load_segment($buffer, $table, null, null, null, null, null, null,true);
        $buflen = 0;
        $buffer = array();
      }
    }
    if($buflen > 0) {
      $this->load_segment($buffer, $table, null, null, null, null, null, null, true);
    }
    if(!isset($this->errors)) $this->errors=array(); 
    
  }
  
  /*
  Load a file via gearman.  Again, by default, split the file into 16MB chunks
  Note that the file pointed to by $path must be available to the workers
  so you may have to put the files on an NFS mount point or use EBS snapshots, or S3, etc.
  */
  public function load_gearman($path, $table, $chunk_size = null, $segment_count = null, $columns_str = null, $set_str = null, $ignore= "", $replace="") {
    $SQ = $this->SQ;
    if (!isset($chunk_size)) {
      $chunk_size = $this->chunk_size;
    }
    

    if (!isset($segment_count)) {
      $chunker = new ChunkIt($this->line_terminator);
      if(strstr($path, "s3://")) {
        if(!isset($this->SQ->state->aws_access_key) || !isset($this->SQ->state->aws_secret_key)) {
          $this->errors[] = "Missing AWS secret key or AWS access key.  Please set them in the schemata_config table";
          return false;
        }
	$pathinfo = $this->extract_s3_path($path);
        $bucket = $pathinfo[1]; 
        $file = $pathinfo[2];
        $path = $pathinfo; 
        $s3 = new S3($this->SQ->state->aws_access_key, $this->SQ->state->aws_secret_key);
	$info = @$s3->getObjectInfo($bucket, $file);
        if(!$info) {
          $this->errors[] = "Could not get S3 information for $path";
          return false;
        }
        $segment_count = floor($info['size'] / $chunk_size);
        echo "Getting file offsets (may take awhile)\n";
        $info = $chunker->s3_find_offsets($s3, $bucket, $file,$info['size'],$chunk_size, $segment_count);
      } elseif(strstr($path, "http://") || strstr($path, "https://")) {
        $fs = $this->curl_get_file_size($path);
        if (!$fs) {
          $this->errors[] = "Could not get size of $path";
          return false;
        }
        $segment_count = floor($fs / $chunk_size);
        $info = $chunker->http_find_offsets($path, $fs,$chunk_size, $segment_count);

      } else { /* NON-S3 load here */
        $fs = @filesize($path);

        if (!$fs) {
          $this->errors[] = "Could not get size of $path";
          return false;
        }
      
        $segment_count = floor($fs / $chunk_size);
        $info    = $chunker->find_offsets($path, $segment_count);

      }
    }

    if ($segment_count === 0.0)
      $segment_count = 1;

    $jobs = array();
    
    $loadspec = array(
      'line_terminator' => $this->line_terminator,
      'enclosure' => $this->enclosure,
      'delimiter' => $this->delimiter,
      'columns_str' => $columns_str,
      'set_str' => $set_str,
      'ignore' => $ignore,
      'replace' => $replace
    );
    $job_id   = $SQ->state->mapper->register_job(null, 0, 0, $table, "[LOAD DATA] FILE:$path", "load", count($info));
    echo "Scheduling jobs for file chunks:\n";
    foreach ($info as $segment) {
      echo "  Table: $table\n   File: " . print_r($path,true) . "\nOffsets: from_pos: {$segment['start']}, to_pos: {$segment['end']}\n";
      $jobs[] = array(
        'job_id' => $job_id,
        'path' => $path,
        'table' => $table,
        'start' => $segment['start'],
        'end' => $segment['end'],
        'loadspec' => $loadspec
      );
    }
    
    $set = $this->create_gearman_set($jobs);
    echo "Running background jobs for loading\n";
    $this->run_set($SQ->state, $set);
    return true;
    
  }
  
  public function load_segment(&$path, $table, $start_pos, $end_pos, $columns_str = null, $set_str = null, $ignore="", $replace="", $from_buffer = null) {
    $SQ             = $this->SQ;
    $shard_col_pos  = null;
    $errors         = array();
    $loader_handles = array();
    $loader_fifos   = array();
    $bucket = null; 
    $file = null;

    $buffer = false; 
    if($from_buffer === null && !is_array($path)) { 
      if (!trim($path))
        throw new Exception('Empty path not supported');
    } elseif($from_buffer === null) {
      $bucket = $path[1];
      $file = $path[2];
    } else {
      $buffer = &$path;
    }

    $delimiter = $this->delimiter;
    $delimiter = str_replace('|', '\\|', $delimiter);
    if ($this->enclosure != "") {
      $regex = "/{$this->enclosure}([^{$this->enclosure}]*){$this->enclosure}|{$delimiter}/ix";
    } else {
      $regex = "/{$delimiter}/";
    }
    
    $all_shards = $SQ->state->shards;
    $exit_error = false;
    
    /*
    Get the column list for the given table.
    */
    if(!$columns_str) {
      $db  = $SQ->tmp_shard['db'];
      $dal = SimpleDAL::factory($SQ->tmp_shard);
      if ($dal->my_error()) {
        echo $dal->my_error();
        $errors[] = array(
          'error' => "Could not get list of columns for table",
          'file_pos' => $start_pos
        );
        return $errors;
      }
    
      $table = $dal->my_real_escape_string($table);
      $db    = $dal->my_real_escape_string($db);
    
      $sql  = "set group_concat_max_len=1024*1024*4;";
      $stmt = $dal->my_query($sql);
      if (!$stmt) {
        throw new Exception($dal->my_error());
      }
    
      $sql  = "select group_concat(column_name order by ordinal_position) columns_str from information_schema.columns where table_schema='$db' and table_name = '$table'";
      $stmt = $dal->my_query($sql);
      if (!$stmt) {
        throw new Exception($dal->my_error());
      }
    
      $row = $dal->my_fetch_assoc($stmt);
      $columns_str = $row['columns_str'];
      $loader_handles[$SQ->tmp_shard['shard_name']] = $dal;
      $dal = null;
    }
    
    /*
    Try to find the shard column in the list of columns. (if it isn't found don't do anything)
    */
    if (isset($SQ->shard_column)) {
      $columns = explode(",", $columns_str);
      foreach ($columns as $key => $column) {
        if ($column == $SQ->shard_column) {
          $shard_col_pos = $key;
          break;
        }
      }
    }

    #handle s3
    if($bucket != null) {
      $fname = tempnam("/tmp", mt_rand(1,999999999));
      unlink($fname);
      echo "Fetching a chunk from S3 for loading (tempname: $fname)\n";
      $s3 = new S3($SQ->state->aws_access_key, $SQ->state->aws_secret_key);
      @$s3->getObject($bucket, $file, $fname, array($start_pos, $end_pos));

      #because the chunks are in individual new files, reset the offsets for the small file
      $start_pos = 0; $end_pos = filesize($fname);

      if (!$fh = fopen($fname, 'rb')) {
        $errors[] = "could not open input stream or S3 failure";
        return $errors;
      }
      unlink($fname);
    } elseif($from_buffer === null && (strstr($path,'http://') || strstr($path,'https://'))) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $path);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Shard-Query/loader');
        curl_setopt($curl, CURLOPT_RANGE, $start_pos . "-" . $end_pos);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        curl_close($curl);
        if (!$result) {
          $errors[] = "could not open input stream or HTTP failure";
          return $errors;
        }
        $fname = tempnam("/tmp", mt_rand(1,999999999));
        if (!$fh = fopen($fname, 'wb')) {
          $errors[] = "could not open output stream";
          return $errors;
        }
        if(!fputs($fh, $result)) {
          $errors[] = "Could not put contents into file";
          return $errors;
        }
        unset($result);
        $start_pos = 0; $end_pos = ftell($fh); // the "chunk" on disk starts at 0
        fclose($fh);
        $fh = fopen($fname, 'rb'); 
        unlink($fname);
    } else {
      if($from_buffer===null) {
        if (!$fh = fopen($path, 'rb')) {
          $errors[] = "could not open input stream or S3 failure";
          return $errors;
        }
      }
    }
    
    /*
    Since the table does not contain the shard key, LOAD DATA INFILE should just be used
    to serially load the table onto each shard.
    */
    if (!isset($shard_col_pos)) {
      foreach ($all_shards as $shard_name => $shard) {
        $fifo = $this->start_fifo($table, $shard, $columns_str, $set_str, $ignore, $replace);
        if (!$fifo) {
          $err      = "Could not start a FIFO to a destination database.  This will result in too many errors, so failing completely.\n";
          $errors[] = array(
            'error' => $err,
            'file_pos' => $line_start
          );
          return $errors;
        }

        if($from_buffer === null) {
          if (fseek($fh, $start_pos, SEEK_SET) === -1)
            throw new Exception('could not seek to start pos');
          while (!feof($fh) && ftell($fh) < $end_pos) {
            $line   = fgets($fh);
            $result = fwrite($fifo['fh'], $line);
            if ($result === false) {
              $err      = "Could not write to a destination FIFO.  This will result in too many errors, so failing completely.\n";
              $errors[] = array(
                'error' => $err,
                'file_pos' => $line_start
              );
              return $errors;
            }
          }
        } else {
	  foreach($buffer as $line) {
            $result = fwrite($fifo['fh'], $line);
            if ($result === false) {
              $err      = "Could not write to a destination FIFO.  This will result in too many errors, so failing completely.\n";
              $errors[] = array(
                'error' => $err,
                'file_pos' => $line_start
              );
              return $errors;
            }
          }
        }
        fclose($fifo['fh']);
        sleep(1);
        foreach($fifo['ph']['pipes'] as $pipe) {
          fclose($pipe);
        }
        proc_close($fifo['ph']['ph']);
      }

      fclose($fh); // this will also unlink the temporary file 
      
      if (!empty($errors))
        return $errors;
      else
        return true;
      
    }
    
    
    /*
    Figure out on which shard this row belongs.  A buffer for each shard is created.  Each buffer is loaded serially.
    */
    $out_data = array();
    if($from_buffer === null) {
      if (fseek($fh, $start_pos, SEEK_SET) === -1) {
        throw new Exception('could not seek to start pos');
      }
    }
    #while ($!feof($fh) && ftell($fh) < $end_pos) {
    while (1) {
      /* exit conditions for loop are either EOF on input, read past chunk boundary or consume all pre-filled buffer*/
      if($from_buffer !== null && empty($buffer)) { 
        break;
      } elseif($from_buffer === null) {
        if(feof($fh) || ftell($fh) >= $end_pos)  {
          break;
        }
      }
      if($from_buffer === null) { 
          $line = fgets($fh);
      } else {
          $line = array_pop($buffer);
      }
      $values = preg_split($regex, $line, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
      if(count($values) == 0) {
        $errors[] = "could not split line: $line\n";
        return $errors;
      }
      $values[count($values) - 1] = trim($values[count($values) - 1], $this->line_terminator);
      
      #lookup the value to see which shard it goes to
      $info = $SQ->map_shard($SQ->shard_column, $values[$shard_col_pos], $SQ->state->current_schema, '=', true);
      
      if (!$info) {
        $err      = "Discarded row because the partition mapper did not return a valid result.\n";
        $errors[] = array(
          'error' => $err,
          'file_pos' => $line_start
        );
        continue;
      }
      
      if (count($info) > 1) {
        $err      = "Discarded row because the partition mapper returned more than one shard for this row.\n";
        $errors[] = array(
          'error' => $err,
          'file_pos' => $line_start
        );
        continue;
      }
      
      $shard_name = @array_pop(array_keys(array_pop($info)));
      
      if (empty($out_data[$shard_name]))
        $out_data[$shard_name] = array();
      $out_data[$shard_name][] = $line;
    }
   
    #print_r($out_data); exit;
 
    foreach ($out_data as $shard_name => $lines) {
      $fifo = $this->start_fifo($table, $SQ->state->shards[$shard_name], $columns_str, $set_str, $ignore, $replace);
      if (!$fifo) {
        $err      = "Could not start a FIFO to a destination database.\n";
        $errors[] = array(
          'error' => $err,
          'file_pos' => $line_start
        );
        return $errors;
      }
      foreach ($lines as $line) {
        $result = fwrite($fifo['fh'], $line);
        if ($result === false) {
          $err      = "Could not write to a destination FIFO.\n";
          $errors[] = array(
            'error' => $err,
            'file_pos' => $line_start
          );
          return $errors;
        }
      }
      fclose($fifo['fh']);
      sleep(1);
      foreach($fifo['ph']['pipes'] as $pipe) {
        fclose($pipe);
      }
      proc_close($fifo['ph']['ph']);
    }
 
    if (!empty($errors))
      return $errors;
    
    /*
    ALL OK
    */
    return true;
    
  }
  
  
  #used by the loader to insert a row
  protected function do_insert($values, $table, $columns_str, &$dal) {
    $vals = "";
    
    foreach ($values as $val) {
      if ($vallist)
        $vallist .= ",";
      if (!is_numeric($val))
        $val = "'" . $dal->my_real_escape_string($val) . "'";
      $vallist .= $val;
    }
    
    $sql = "INSERT INTO $table ($columns_str) VALUES ($vallist);";
    
    if (!$dal->my_query($sql)) {
      return false;
    }
    
    return true;
  }
  
  protected function start_fifo($table, $shard, $columns_str="", $set_str="", $ignore = "", $replace = "") {
    #generate a safe unguessable name in a safe location
    $path = tempnam(sys_get_temp_dir(), mt_rand(1, 99999999));
    
    #get rid of the file created by tempname
    if (!unlink($path))
      return false;
    
    if (!posix_mkfifo($path, 0666))
      return false;

    if($ignore == "") $ignore=""; else $ignore = "IGNORE";
    if($replace == "") $replace=""; else $replace = "REPLACE";

    #note $ignore and replace are mutually exclusive in the SQL grammar - putting them together produces a error if both are used which is good
    $load = "LOAD DATA INFILE \"$path\" {$replace}{$ignore} INTO TABLE `{$shard['db']}`.`$table`";
    if($this->charset !== "" && $this->charset !== null) $load .= " CHARACTER SET {$this->charset} ";
    $sql  = "";
    
    $delimiter = $this->delimiter;
    if ($delimiter == "\t")
      $delimiter = "\\t";
    $sql = ' FIELDS TERMINATED BY "' . $delimiter . '" ';
    
    if ($this->enclosure != "") {
      $sql .= "OPTIONALLY ENCLOSED BY \"" . $this->enclosure . "\" ";
    }

    //$sql .= "ESCAPED BY '" . $this->escape . "' ";

    $line_terminator = str_replace(array(
      "\r",
      "\n"
    ), array(
      "\\r",
      "\\n"
    ), $this->line_terminator);
    
    $line_starter = str_replace(array(
      "\r",
      "\n"
    ), array(
      "\\r",
      "\\n"
    ), $this->line_starter);

    $sql .= 'LINES TERMINATED BY "' . $line_terminator . '" ';
    
    if($columns_str) $sql .= "($columns_str)";
    
    $load .= $sql;

    $load .= $set_str == null ? '' : $set_str;

    $cmdline = "mysql -u{$shard['user']} -h{$shard['host']} -P{$shard['port']} {$shard['db']}";
    if (!empty($shard['password']) && trim($shard['password'])) {
      $cmdline .= " -p{$shard['password']}";
    }
    $cmdline .= " -e '{$load}'";
    $pipes = null;
  
#   echo "CMDLINE: $cmdline\n";
 
    #open the MySQL client reading from the FIFO
    $ph = proc_open($cmdline, array(), $pipes);
    if (!$ph)
      return false;
    
    #open the FIFO for writing
    #this won't block because we've started a reader in a coprocess
    $fh = fopen($path, 'wb');
    
    #unlink the file so it disappears when this process dies
    unlink($path);

    return (array(
      'fh' => $fh,
      'ph' => array(
        'ph' => $ph,
        'pipes' => $pipes
      )
    ));
    
  }
  
  
  protected function create_gearman_set($jobs) {
    $task_type = Net_Gearman_task::JOB_BACKGROUND;
    $set       = new Net_Gearman_Set();
    
    foreach ($jobs as $job) {
      #don't bother attaching callbacks since it is a background job
      $task = new Net_Gearman_Task("loader", $job, md5(serialize($job)), $task_type);
      $set->addTask($task);
    }
    
    return $set;
    
  }
  
  protected function run_set(&$state, $set) {
    
    $state->client->runSet($set);
  }
  
}

