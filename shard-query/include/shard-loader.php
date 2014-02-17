<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 encoding=utf-8: */
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
require_once('shard-query.php');
if(!function_exists('posix_mkfifo')) {
    function posix_mkfifo($path, $mode = '0444') {
        $mkfifo = trim(`which mkfifo`);
        if($mkfifo) {
            #don't clobber existing files
            if(file_exists($path)) return false;
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

    public function __construct($SQ, $delimiter = ",", $enclosure = "", $line_terminator = "\n", $use_fifo = true, $chunk_size = DEFAULT_CHUNK_SIZE) {
        $this->SQ = $SQ;
	$delimiter = str_replace(array('\\'),array("\\\\"), $delimiter);
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->line_terminator = $line_terminator;
        $this->use_fifo = $use_fifo;

	$this->chunk_size = $chunk_size;

        #FIXME
        #support terminators that don't end in newline
        if(substr($this->line_terminator, -1, 1) !== "\n") {
            throw new Exception ('Line terminator must end in newline.');
        }
    }

    /* 
    Load a file.  
    */
    public function load($path, $table, $chunk_size = null, $segment_count = null) {
	if(!isset($chunk_size)) {
		$chunk_size = $this->chunk_size;
	}

        if(!isset($segment_count)) {
            $fs = filesize($path);
            if(!$fs) return false;

            $segment_count = floor($fs / $chunk_size);
        }

        $chunker = new ChunkIt($this->line_terminator);
        $info = $chunker->find_offsets($path, $segment_count);
    
        foreach($info as $segment) {
 
            $return = $this->load_segment($path, $table, $segment['start'], $segment['end']); 
        }
        echo "done!\n";

    }

    /* 
    Load a file via gearman.  Again, by default, split the file into 16MB chunks
    Note that the file pointed to by $path must be available to the workers
    so you may have to put the files on an NFS mount point of use EBS snapshots, etc.
    */
    public function load_gearman($path, $table, $chunk_size = null, $segment_count = null) {
        $SQ = $this->SQ;
	if(!isset($chunk_size)) {
		$chunk_size = $this->chunk_size;
	}

        if(!isset($segment_count)) {
            $fs = filesize($path);
            if(!$fs) return false;
            $segment_count = floor($fs / $chunk_size);
        }
        if($segment_count === 0.0) $segment_count = 1;
        $chunker = new ChunkIt($this->line_terminator);
        $info = $chunker->find_offsets($path, $segment_count);

        $jobs = array();

        $loadspec = array('line_terminator' => $this->line_terminator, 'enclosure' => $this->enclosure, 'delimiter' => $this->delimiter);
        $job_id = $SQ->state->mapper->register_job(null, 0, 0, $table, "[LOAD DATA LOCAL] FILE:$path","load",count($info));
        foreach($info as $segment) {
            echo "  Table: $table\n   File: $path\nOffsets: from_pos: {$segment['start']}, to_pos: {$segment['end']}\n";
            $jobs[] = array( 'job_id' => $job_id, 'path' => $path, 'table' => $table, 'start' => $segment['start'], 'end' => $segment['end'], 'loadspec' => $loadspec );
        }

        $set = $this->create_gearman_set($jobs);
        $this->run_set($SQ->state, $set);

    }

	public function load_segment($path, $table, $start_pos=0, $end_pos=null) {
        $SQ = $this->SQ;
		$shard_col_pos = null;
		$errors=array();
		$loader_handles = array();
		$loader_fifos = array();

		if(!trim($path)) throw new Exception('Empty path not supported');
        $delimiter = $this->delimiter;
        $delimiter = str_replace('|', '\\|', $delimiter);
		if($this->enclosure != "") {
			$regex = "/{$this->enclosure}([^{$this->enclosure}]*){$this->enclosure}|{$delimiter}/ix";
		} else {
			$regex = "/{$delimiter}/";
		}

        $all_shards = $SQ->state->shards;
        $exit_error = false;

        /*
        Get the column list for the given table.
        */
        $db = $SQ->tmp_shard['db'];
        $dal = SimpleDAL::factory($SQ->tmp_shard);
        if($dal->my_error()) { echo $dal->my_error(); $errors[]=array('error'=>"Could not get list of columns for table", 'file_pos'=>$start_pos); return $errors; }

        $table = $dal->my_real_escape_string($table);
        $db = $dal->my_real_escape_string($db);

        $sql = "set group_concat_max_len=1024*1024*4;";
        $stmt = $dal->my_query($sql);
        if(!$stmt) {
            throw new Exception($dal->my_error());
        }

        $sql = "select group_concat(column_name order by ordinal_position) columns_str from information_schema.columns where table_schema='$db' and table_name = '$table'";
        $stmt = $dal->my_query($sql);
        if(!$stmt) {
            throw new Exception($dal->my_error());
        }

        $row = $dal->my_fetch_assoc($stmt);
        $columns_str = $row['columns_str'];
        $loader_handles[$SQ->tmp_shard['shard_name']] = $dal; 
        $dal = null;

        /*
        Try to find the shard column in the list of columns. (if it isn't found don't do anything)
        */
        if(isset($SQ->shard_column)) {
            $columns = explode(",", $columns_str);
			foreach($columns as $key => $column) {
				if($column == $SQ->shard_column) {
					$shard_col_pos = $key;
					break;
				}
			}
        } 

        /* 
        Check the end parameter
        */
        if(!isset($end_pos) || $end_pos <= 0) {
            $end_pos = filesize($path);
        }
        if(!$end_pos) throw new Exception('Could not get filesize of input file, or file is of zero length!');

		if(!$fh = fopen($path,'rb')) throw new Exception('could not open input stream\n');

        /*
        Since the table does not contain the shard key, LOAD DATA INFILE should just be used
        to serially load the table onto each shard.
        */
		if(!isset($shard_col_pos)) {
            foreach($all_shards as $shard_name => $shard) {
			    $fifo = $this->start_fifo($table,$shard,$columns_str);
                if(!$fifo) {
				    $err = "Could not start a FIFO to a destination database.  This will result in too many errors, so failing completely.\n";
					$errors[]=array('error' => $err, 'file_pos' => $line_start);
                    return $errors;
                }
                if(fseek($fh, $start_pos, SEEK_SET) === -1) throw new Exception('could not seek to start pos');
		        while(!feof($fh) && ftell($fh) < $end_pos) {
                    $line = fgets($fh); 
					$result = fwrite($fifo['fh'], $line);
                    if($result === false) {
					        $err = "Could not write to a destination FIFO.  This will result in too many errors, so failing completely.\n";
					        $errors[]=array('error' => $err, 'file_pos' => $line_start);
                            return $errors;
                    }
                }
                fclose($fifo['fh']);
            }

            if(!empty($errors)) return $errors; else return true;
	
        }


        /*
        Figure out on which shard this row belongs.  A buffer for each shard is created.  Each buffer is loaded serially.
        */	
        $out_data=array();
        if(fseek($fh, $start_pos, SEEK_SET) === -1) throw new Exception('could not seek to start pos');
        while(!feof($fh) && ftell($fh) < $end_pos) {
            $line = fgets($fh); 
		        		
            $values = preg_split($regex, $line,-1,PREG_SPLIT_NO_EMPTY| PREG_SPLIT_DELIM_CAPTURE );
            $values[count($values)-1] = trim($values[count($values)-1], $this->line_terminator);

            #lookup the value to see which shard it goes to
            $info=$SQ->map_shard($SQ->shard_column, $values[$shard_col_pos], $SQ->state->current_schema, '=', true);

            if(!$info) {
                $err = "Discarded row because the partition mapper did not return a valid result.\n";
                $errors[]=array('error' => $err, 'file_pos' => $line_start);
                continue;
            } 

            if(count($info) > 1) {
                $err = "Discarded row because the partition mapper returned more than one shard for this row.\n";
                $errors[]=array('error' => $err, 'file_pos' => $line_start);
                continue;
            }
                
            $shard_name = array_pop(array_keys(array_pop($info)));
                
            if(empty($out_data[$shard_name])) $out_data[$shard_name] = array();
            $out_data[$shard_name][] = $line;
        }
        
		foreach($out_data as $shard_name => $lines) {
		    $fifo = $this->start_fifo($table,$SQ->state->shards[$shard_name],$columns_str);
            if(!$fifo) {
			    $err = "Could not start a FIFO to a destination database.\n";
				$errors[]=array('error' => $err, 'file_pos' => $line_start);
                return $errors;
            }
            foreach($lines as $line) {
				$result = fwrite($fifo['fh'], $line);
                if($result === false) {
				    $err = "Could not write to a destination FIFO.\n";
					$errors[]=array('error' => $err, 'file_pos' => $line_start);
                    return $errors;
                }
            }
            fclose($fifo['fh']);
		}				

	    if(!empty($errors)) return $errors;

        /*
        ALL OK
        */
        return true;
	
	}


	#used by the loader to insert a row
	protected function do_insert($values,$table,$columns_str,&$dal) {
		$vals = "";
	
        foreach($values as $val) {
            if($vallist) $vallist .= ",";
            if(!is_numeric($val)) $val = "'" . $dal->my_real_escape_string($val) . "'";
            $vallist .= $val;
        }
	
		$sql = "INSERT INTO $table ($columns_str) VALUES ($vallist);";

		if(!$dal->my_query($sql)) {
			return false;
		}

		return true;
	}

	protected function start_fifo($table,$shard,$columns_str) {
        #generate a safe unguessable name in a safe location
        $path = tempnam(sys_get_temp_dir(), mt_rand(1,99999999)); 

        #get rid of the file created by tempname
        if(!unlink($path)) return false;
        
		if(!posix_mkfifo($path,'0444')) return false;
        
		$load="LOAD DATA LOCAL INFILE \"$path\" INTO TABLE `{$shard['db']}`.`$table`";
		$sql = "";
        
        $delimiter = $this->delimiter;
        if($delimiter == "\t") $delimiter = "\\t";
		$sql = ' FIELDS TERMINATED BY "' . $delimiter . '" ';

		if($this->enclosure != "") {
			$sql .= "OPTIONALLY ENCLOSED BY \"" . $this->enclosure . "\" ";
		}
		$line_terminator = str_replace(array("\r","\n"),array("\\r","\\n"), $this->line_terminator);

		$sql .= 'LINES TERMINATED BY "' . $line_terminator . '" ';

		$sql .= "($columns_str)";

		$load .= $sql;

		$cmdline = "mysql -u{$shard['user']} -h{$shard['host']} -P{$shard['port']} {$shard['db']}";
		if(!empty($shard['password']) && trim($shard['password'])) {
			$cmdline .= " -p{$shard['password']}";
		}
		$cmdline .= " -e '{$load}'";
		$pipes=null;	

        #open the MySQL client reading from the FIFO
		$ph = proc_open($cmdline, array(), $pipes);
		if(!$ph) return false;

        #open the FIFO for writing
		#this won't block because we've started a reader in a coprocess 
		$fh = fopen($path, 'wb');	
   
        #unlink the file so it disappears when this process dies 
        unlink($path);

		return(array('fh' => $fh, 'ph' => array('ph'=>$ph, 'pipes'=>$pipes)));

	}


	protected function create_gearman_set( $jobs ) {
        $task_type = Net_Gearman_task::JOB_BACKGROUND;
		$set = new Net_Gearman_Set();
   
        foreach($jobs as $job) { 
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
