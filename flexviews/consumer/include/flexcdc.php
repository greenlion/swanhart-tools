<?php
/*  FlexCDC is part of Flexviews for MySQL
    Copyright 2008-2010 Justin Swanhart

    FlexViews is free software: you can redistribute it and/or modify
    it under the terms of the Lesser GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    FlexViews is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FlexViews in the file COPYING, and the Lesser extension to
    the GPL (the LGPL) in COPYING.LESSER.
    If not, see <http://www.gnu.org/licenses/>.
*/

error_reporting(E_ALL);
ini_set('memory_limit', 1024 * 1024 * 1024);
define('SOURCE', 'source');
define('DEST', 'dest');

/* 
The exit/die() functions normally exit with error code 0 when a string is passed in.
We want to exit with error code 1 when a string is passed in.
*/
function die1($error = 1,$error2=1) {
	if(is_string($error)) { 
		echo1($error . "\n");
		exit($error2);
	} else {
		exit($error);
	}
}

function echo1($message) {
	global $ERROR_FILE;
	fputs(isset($ERROR_FILE) && is_resource($ERROR_FILE) ? $ERROR_FILE : STDERR, $message);

}

function my_mysql_query($a, $b=NULL, $debug=false) {
	if($debug) echo "$a\n";

	if($b) {
	$r = mysql_query($a, $b);
		} else { 
	$r = mysql_query($a);
	}

	if(!$r) {
		echo1("SQL_ERROR IN STATEMENT:\n$a\n");
		if($debug) {
			$pr = mysql_error();
			echo1(print_r(debug_backtrace(),true));
			echo1($pr);
		}
	}

	return $r;
}

class FlexCDC {
	static function concat() {
    	$result = "";
    	for ($i = 0;$i < func_num_args();$i++) {
      		$result .= func_get_arg($i);
    	}
    	return $result;
  	}
  	
  	static function split_sql($sql) {
		$regex=<<<EOREGEX
/
|(\(.*?\))   # Match FUNCTION(...) OR BAREWORDS
|("[^"](?:|\"|"")*?"+)
|('[^'](?:|\'|'')*?'+)
|(`(?:[^`]|``)*`+)
|([^ ,]+)
/x
EOREGEX
;
		$tokens = preg_split($regex, $sql,-1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		return $tokens;	

	}
  	
	# Settings to enable bulk import
	protected $inserts = array();
	protected $deletes = array();
	protected $bulk_insert = true;
  	
	protected $mvlogDB = NULL;
	public	$mvlogList = array();
	protected $activeDB = NULL;
	protected $onlyDatabases = array();
	protected $cmdLine;

	protected $tables = array();

	protected $mvlogs = 'mvlogs';
	protected $binlog_consumer_status = 'binlog_consumer_status';
	protected $mview_uow = 'mview_uow';

	protected $source = NULL;
	protected $dest = NULL;

	protected $serverId = NULL;
	
	protected $binlogServerId=1;

	protected $gsn_hwm;
	
	public  $raiseWarnings = false;
	
	public  $delimiter = ';';

	protected $log_retention_interval = "10 day";
	public function get_source($new = false) {
		if($new) return $this->new_connection(SOURCE);
		return $this->source;
	}
	
	public function get_dest($new = false) {
		if($new) return $this->new_connection(DEST);
		return $this->dest;
	}

	function new_connection($connection_type) {
		$S = $this->settings['source'];
		$D = $this->settings['dest'];
		switch($connection_type) {
			case 'source': 
				/*TODO: support unix domain sockets */
				$handle = mysql_connect($S['host'] . ':' . $S['port'], $S['user'], $S['password'], true) or die1('Could not connect to MySQL server:' . mysql_error());
				return $handle;
			case 'dest':
				$handle = mysql_connect($D['host'] . ':' . $D['port'], $D['user'], $D['password'], true) or die1('Could not connect to MySQL server:' . mysql_error());
				return $handle;
		}
		return false;
	}

	#Construct a new consumer object.
	#By default read settings from the INI file unless they are passed
	#into the constructor	
	public function __construct($settings = NULL, $no_connect = false) {
		if(!$settings) {
			$settings = $this->read_settings();
		} 
		$this->settings = $settings;
		if(!$this->cmdLine) $this->cmdLine = `which mysqlbinlog`;
		if(!$this->cmdLine) {
			die1("could not find mysqlbinlog!",2);
		}
		
		
		#only record changelogs from certain databases?
		if(!empty($settings['flexcdc']['only_database'])) {
			$vals = explode(',', $settings['flexcdc']['only_databases']);
			foreach($vals as $val) {
				$this->onlyDatabases[] = trim($val);
			}
		}

		if(!empty($settings['flexcdc']['mvlogs'])) $this->mvlogs=$settings['flexcdc']['mvlogs'];
		if(!empty($settings['flexcdc']['binlog_consumer_status'])) $this->binlog_consumer_status=$settings['flexcdc']['binlog_consumer_status'];
		if(!empty($settings['flexcdc']['mview_uow'])) $this->mview_uow=$settings['flexcdc']['mview_uow'];

		if(!empty($settings['flexcdc']['log_retention_interval'])) $this->log_retention_interval=$settings['flexcdc']['log_retention_interval'];
		
		#the mysqlbinlog command line location may be set in the settings
		#we will autodetect the location if it is not specified explicitly
		if(!empty($settings['flexcdc']['mysqlbinlog'])) {
			$this->cmdLine = $settings['flexcdc']['mysqlbinlog'];
		} 
		
		#build the command line from user, host, password, socket options in the ini file in the [source] section
		foreach($settings['source'] as $k => $v) {
			$this->cmdLine .= " --$k=$v";
		}
		
		#database into which to write mvlogs
		$this->mvlogDB = $settings['flexcdc']['database'];
		
		$this->auto_changelog = $settings['flexcdc']['auto_changelog'];		
		#shortcuts

		if(!empty($settings['raise_warnings']) && $settings['raise_warnings'] != 'false') {
 			$this->raiseWarnings=true;
		}

		if(!empty($settings['flexcdc']['bulk_insert']) && $settings['flexcdc']['bulk_insert'] != 'false') {
			$this->bulk_insert = true;
		}

		if(!$no_connect) {	
			$this->source = $this->get_source(true);
			$this->dest = $this->get_dest(true);
		}

		$this->settings = $settings;
	    
	}

		

	protected function initialize() {
		if($this->source === false) $this->source = $this->get_source(true);
		if($this->dest === false) $this->dest = $this->get_dest(true);
		$this->initialize_dest();
		$this->get_source_logs();
		$this->cleanup_logs();
		
	}
	
	public function table_exists($schema, $table) {
		$sql = "select 1 from information_schema.tables where table_schema = '$schema' and table_name='$table' limit 1";
		$stmt = @my_mysql_query($sql, $this->dest);
		if(!$stmt) return false;

		if(mysql_fetch_array($stmt) !== false) {
			mysql_free_result($stmt);
			return true;
		}
		return false;
	}

	public function table_ordinal_datatype($schema,$table,$pos) {
		static $cache;

		$key = $schema . $table . $pos;
		if(!empty($cache[$key])) {
			return $cache[$key];
		} 

		$log_name = $schema . '_' . $table;
		$table  = mysql_real_escape_string($table, $this->dest);
		$pos	= mysql_real_escape_string($pos);

		$sql = 'select data_type from information_schema.columns where table_schema="%s" and table_name="%s" and ordinal_position="%s"';

		$sql = sprintf($sql, $this->mvlogDB, $log_name, $pos+4);

		$stmt = my_mysql_query($sql, $this->dest);
		if($row = mysql_fetch_array($stmt) ) {
			$cache[$key] = $row[0];	
			return($row[0]);
		}
		return false;
			
		
	}

	public function table_ordinal_is_unsigned($schema,$table,$pos) {
		/* NOTE: we look at the LOG table to see the structure, because it might be different from the source if the consumer is behind and an alter has happened on the source*/
		static $cache;

		$key = $schema . $table . $pos;
		if(!empty($cache[$key])) {
			return $cache[$key];
		} 

		$log_name = $schema . '_' . $table;
		$table  = mysql_real_escape_string($table, $this->dest);
		$pos	= mysql_real_escape_string($pos);
		$sql = 'select column_type like "%%unsigned%%" is_unsigned from information_schema.columns where table_schema="%s" and table_name="%s" and ordinal_position=%d';

		$sql = sprintf($sql, $this->mvlogDB, $log_name, $pos+4);

		$stmt = my_mysql_query($sql, $this->dest);
		if($row = mysql_fetch_array($stmt) ) {
			$cache[$key] = $row[0];	
			return($row[0]);
		}
		return false;
			
		
	}
	
	public function setup($force=false , $only_table=false) {
		$sql = "SELECT @@server_id";
		$stmt = my_mysql_query($sql, $this->source);
		$row = mysql_fetch_array($stmt);
		$this->serverId = $row[0];
		if(!mysql_select_db($this->mvlogDB,$this->dest)) {
			 my_mysql_query('CREATE DATABASE ' . $this->mvlogDB) or die1('Could not CREATE DATABASE ' . $this->mvlogDB . "\n");
			 mysql_select_db($this->mvlogDB,$this->dest);
		}

		if($only_table === false || $only_table == 'mvlogs') {
			if($this->table_exists($this->mvlogDB, $this->mvlogs, $this->dest)) {
				if(!$force) {
					trigger_error('Table already exists:' . $this->mvlogs . '. Setup aborted! (use --force to ignore this error)' , E_USER_ERROR);
					return false;
				}
				my_mysql_query('DROP TABLE `' . $this->mvlogDB . '`.`' . $this->mvlogs . '`;') or die1('COULD NOT DROP TABLE: ' . $this->mvlogs . "\n" . mysql_error() . "\n");
			}	
			my_mysql_query("CREATE TABLE 
					 `" . $this->mvlogs . "` (table_schema varchar(50), 
                             table_name varchar(50), 
                             mvlog_name varchar(50),
                             active_flag boolean default true,
                             primary key(table_schema,table_name),
                             unique key(mvlog_name)
                     	) ENGINE=INNODB DEFAULT CHARSET=utf8;"
		            , $this->dest) or die1('COULD NOT CREATE TABLE ' . $this->mvlogs . ': ' . mysql_error($this->dest) . "\n"); 
		}

		if($only_table === false || $only_table == 'mview_uow') {
			if(FlexCDC::table_exists($this->mvlogDB, $this->mview_uow, $this->dest)) {
				if(!$force) {
					trigger_error('Table already exists:' . $this->mview_uow . '. Setup aborted!' , E_USER_ERROR);
					return false;
				}
				my_mysql_query('DROP TABLE `' . $this->mvlogDB . '`.`' . $this->mview_uow . '`;') or die1('COULD NOT DROP TABLE: ' . $this->mview_uow . "\n" . mysql_error() . "\n");
			}		            
			my_mysql_query("CREATE TABLE 
			 			 `" . $this->mview_uow . "` (
						  	`uow_id` BIGINT AUTO_INCREMENT,
						  	`commit_time` DATETIME,
							`gsn_hwm` bigint NOT NULL DEFAULT 1,
						  	PRIMARY KEY(`uow_id`),
						  	KEY `commit_time` (`commit_time`)
						) ENGINE=InnoDB DEFAULT CHARSET=latin1;"
				    , $this->dest) or die1('COULD NOT CREATE TABLE ' . $this->mview_uow . ': ' . mysql_error($this->dest) . "\n");

			my_mysql_query("INSERT INTO `" . $this->mview_uow . "` VALUES (1, NULL, 1);", $this->dest) or die1('COULD NOT INSERT INTO:' . $this->mview_uow . "\n");
		}	
		if($only_table === false || $only_table == 'binlog_consumer_status') {
			if(FlexCDC::table_exists($this->mvlogDB, $this->binlog_consumer_status, $this->dest)) {
				if(!$force) {
					trigger_error('Table already exists:' . $this->binlog_consumer_status .'  Setup aborted!' , E_USER_ERROR);
					return false;
				}
				my_mysql_query('DROP TABLE `' . $this->mvlogDB . '`.`' . $this->binlog_consumer_status . '`;') or die1('COULD NOT DROP TABLE: ' . $this->binlog_consumer_status . "\n" . mysql_error() . "\n");
			}	
			my_mysql_query("CREATE TABLE 
						 `" . $this->binlog_consumer_status . "` (
  						 	`server_id` int not null, 
  							`master_log_file` varchar(100) NOT NULL DEFAULT '',
  							`master_log_size` int(11) DEFAULT NULL,
  							`exec_master_log_pos` int(11) default null,
  							PRIMARY KEY (`server_id`, `master_log_file`)
						  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
			            , $this->dest) or die1('COULD NOT CREATE TABLE ' . $this->binlog_consumer_status . ': ' . mysql_error($this->dest) . "\n");
			
		
			#find the current master position
			$stmt = my_mysql_query('FLUSH TABLES WITH READ LOCK', $this->source) or die1(mysql_error($this->source));
			$stmt = my_mysql_query('SHOW MASTER STATUS', $this->source) or die1(mysql_error($this->source));
			$row = mysql_fetch_assoc($stmt);
			$stmt = my_mysql_query('UNLOCK TABLES', $this->source) or die1(mysql_error($this->source));
			$this->initialize();

			my_mysql_query("COMMIT;", $this->dest);
			
			$sql = "UPDATE `" . $this->binlog_consumer_status . "` bcs 
			           set exec_master_log_pos = master_log_size 
			         where server_id={$this->serverId} 
			           AND master_log_file < '{$row['File']}'";
			$stmt = my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error($this->dest) . "\n");

			$sql = "UPDATE `" . $this->binlog_consumer_status . "` bcs 
			           set exec_master_log_pos = {$row['Position']} 
			         where server_id={$this->serverId} 
			           AND master_log_file = '{$row['File']}'";
			$stmt = my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error($this->dest) . "\n");
		}
		
		my_mysql_query("commit;", $this->dest);
		
		return true;
		
			
	}
	#Capture changes from the source into the dest
	public function capture_changes($iterations=1) {
				
		$this->initialize();
		
		$count=0;
		$sleep_time=0;
		while($iterations <= 0 || ($iterations >0 && $count < $iterations)) {
			$this->initialize();
			#retrieve the list of logs which have not been fully processed
			#there won't be any logs if we just initialized the consumer above
			$sql = "SELECT bcs.* 
			          FROM `" . $this->mvlogDB . "`.`" . $this->binlog_consumer_status . "` bcs 
			         WHERE server_id=" . $this->serverId .  
			       "   AND exec_master_log_pos < master_log_size 
			         ORDER BY master_log_file;";
			
		
			#echo " -- Finding binary logs to process\n";
			$stmt = my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error() . "\n");
			$processedLogs = 0;
			while($row = mysql_fetch_assoc($stmt)) {
				++$processedLogs;
				$this->delimiter = ';';
	
				if ($row['exec_master_log_pos'] < 4) $row['exec_master_log_pos'] = 4;
				$execCmdLine = sprintf("%s --base64-output=decode-rows -v -R --start-position=%d --stop-position=%d %s", $this->cmdLine, $row['exec_master_log_pos'], $row['master_log_size'], $row['master_log_file']);
				$execCmdLine .= " 2>&1";
				echo  "-- $execCmdLine\n";
				$proc = popen($execCmdLine, "r");
				if(!$proc) {
					die1('Could not read binary log using mysqlbinlog\n');
				}

				$line = fgets($proc);
				if(preg_match('%/mysqlbinlog:|^ERROR:%', $line)) {
					die1('Could not read binary log: ' . $line . "\n");
				}	

				$this->binlogPosition = $row['exec_master_log_pos'];
				$this->logName = $row['master_log_file'];
				$this->process_binlog($proc, $row['master_log_file'], $row['exec_master_log_pos'],$line);
				$this->set_capture_pos();	
				my_mysql_query('commit', $this->dest);
				pclose($proc);
			}

			if($processedLogs) ++$count;

			#we back off further each time up to maximum
			if(!empty($this->settings['flexcdc']['sleep_increment']) && !empty($this->settings['flexcdc']['sleep_maximum'])) {
				if($processedLogs) {
					$sleep_time=0;
				} else {
					$sleep_time += $this->settings['flexcdc']['sleep_increment'];
					$sleep_time = $sleep_time > $this->settings['flexcdc']['sleep_maximum'] ? $this->settings['flexcdc']['sleep_maximum'] : $sleep_time;
					#echo1('sleeping:' . $sleep_time . "\n");
					sleep($sleep_time);
				}
			}

		}
		return $processedLogs;

	}
	
	protected function read_settings() {
		
		if(!empty($argv[1])) {
			$iniFile = $argv[1];
		} else {
			$iniFile = "consumer.ini";
		}
	
		$settings=@parse_ini_file($iniFile,true) or die1("Could not read ini file: $iniFile\n");
		if(!$settings || empty($settings['flexcdc'])) {
			die1("Could not find [flexcdc] section or .ini file not found");
		}

		return $settings;

	}

	
	protected function refresh_mvlog_cache() {
		
		$this->mvlogList = array();
			
		$sql = "SELECT table_schema, table_name, mvlog_name from `" . $this->mvlogs . "` where active_flag=1";
		$stmt = my_mysql_query($sql, $this->dest);
		while($row = mysql_fetch_array($stmt)) {
			$this->mvlogList[$row[0] . $row[1]] = $row[2];
		}
	}
	
	/* Set up the destination connection */
	function initialize_dest() {
		#my_mysql_query("SELECT GET_LOCK('flexcdc::SOURCE_LOCK::" . $this->server_id . "',15)") or die1("COULD NOT OBTAIN LOCK\n");
		mysql_select_db($this->mvlogDB) or die1('COULD NOT CHANGE DATABASE TO:' . $this->mvlogDB . "\n");
		my_mysql_query("commit;", $this->dest);
		$stmt = my_mysql_query("SET SQL_MODE=STRICT_ALL_TABLES");
		$stmt = my_mysql_query("SET SQL_LOG_BIN=0", $this->dest);
		if(!$stmt) die1(mysql_error());
		my_mysql_query("BEGIN;", $this->dest) or die1(mysql_error());

		$stmt = my_mysql_query("select @@max_allowed_packet", $this->dest);
		$row = mysql_fetch_array($stmt);
		$this->max_allowed_packet = $row[0];	

		$stmt = my_mysql_query("select gsn_hwm from {$this->mvlogDB}.{$this->mview_uow} order by uow_id desc limit 1",$this->dest) 
			or die('COULD NOT GET GSN_HWM:' . mysql_error($this->dest) . "\n");

		$row = mysql_fetch_array($stmt);
		$this->gsn_hwm = $row[0];

		#echo1("Max_allowed_packet: " . $this->max_allowed_packet . "\n");
		
	}
	
	/* Get the list of logs from the source and place them into a temporary table on the dest*/
	
	function get_source_logs() {
		/* This server id is not related to the server_id in the log.  It refers to the ID of the 
		 * machine we are reading logs from.
		 */
		$sql = "SELECT @@server_id";
		$stmt = my_mysql_query($sql, $this->source);
		$row = mysql_fetch_array($stmt) or die1($sql . "\n" . mysql_error() . "\n");
		$this->serverId = $row[0];


		$sql = "select @@binlog_format";
		$stmt = my_mysql_query($sql, $this->source);
		$row = mysql_fetch_array($stmt) or die1($sql . "\n" . mysql_error() . "\n");

		if($row[0] != 'ROW') {
			die1("Exiting due to error: FlexCDC REQUIRES that the source database be using ROW binlog_format!\n");
		}
		
		$stmt = my_mysql_query("SHOW BINARY LOGS", $this->source);
		if(!$stmt) die1(mysql_error());
		$has_logs = false;	
		while($row = mysql_fetch_array($stmt)) {
			if(!$has_logs) {
				my_mysql_query("CREATE TEMPORARY table log_list (log_name char(50), primary key(log_name))",$this->dest) or die1(mysql_error());
				$has_logs = true;
			}
			$sql = sprintf("INSERT INTO `" . $this->binlog_consumer_status . "` (server_id, master_log_file, master_log_size, exec_master_log_pos) values (%d, '%s', %d, 0) ON DUPLICATE KEY UPDATE master_log_size = %d ;", $this->serverId,$row['Log_name'], $row['File_size'], $row['File_size']);
			my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error() . "\n");
	
			$sql = sprintf("INSERT INTO log_list (log_name) values ('%s')", $row['Log_name']);
			my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error() . "\n");
		}
	}
	
	/* Remove any logs that have gone away */
	function cleanup_logs() {
		$sql = "DELETE bcs.* FROM `" . $this->binlog_consumer_status . "` bcs where exec_master_log_pos >= master_log_size and server_id={$this->serverId} AND master_log_file not in (select log_name from log_list)";
		my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error() . "\n");

		$sql = "DROP TEMPORARY table IF EXISTS log_list";
		my_mysql_query($sql, $this->dest) or die1("Could not drop TEMPORARY TABLE log_list\n");
		
	}

	function purge_table_change_history() {
		$conn = $this->get_dest(true);
		$stmt = my_mysql_query("SET SQL_LOG_BIN=0", $conn) or die1($sql . "\n" . mysql_error() . "\n");
		
		$sql = "select max(uow_id) from {$this->mvlogDB}.{$this->mview_uow} where commit_time <= NOW() - INTERVAL " . $this->log_retention_interval;
		$stmt = my_mysql_query($sql, $conn) or die1($sql . "\n" . mysql_error() . "\n");
		$row = mysql_fetch_array($stmt);
		$uow_id = $row[0];
		if(!trim($uow_id)) return true;
		$sql = "select min(uow_id) from {$this->mvlogDB}.{$this->mview_uow} where uow_id > {$uow_id}";
		$stmt = my_mysql_query($sql, $conn) or die1($sql . "\n" . mysql_error() . "\n");
		$row = mysql_fetch_array($stmt);
		$next_uow_id = $row[0];
		if(!trim($next_uow_id)) $uow_id = $uow_id - 1; /* don't purge the last row to avoid losing the gsn_hwm */

		$sql = "select concat('`','{$this->mvlogDB}', '`.`', mvlog_name,'`') mvlog_fqn from {$this->mvlogDB}.{$this->mvlogs} where active_flag = 1";
		$stmt = my_mysql_query($sql, $conn) or die1($sql . "\n" . mysql_error() . "\n");
		$done=false;
		$iterator = 0;
		/* Delete from each table in small 5000 row chunks, commit every 50000 */
		while($row = mysql_fetch_array($stmt)) {
			my_mysql_query("START TRANSACTION", $conn) or die1($sql . "\n" . mysql_error() . "\n");
			while(!$done) {
				++$iterator;
				if($iterator % 10 === 0) {
					my_mysql_query("COMMIT", $conn) or die1($sql . "\n" . mysql_error() . "\n");
					my_mysql_query("START TRANSACTION", $conn) or die1($sql . "\n" . mysql_error() . "\n");
				}
				$sql = "DELETE FROM {$row[0]} where uow_id <= {$uow_id} LIMIT 5000";
				my_mysql_query($sql, $conn) or die1($sql . "\n" . mysql_error() . "\n");
				if(mysql_affected_rows($conn)===0) $done=true; 
			}
			my_mysql_query("COMMIT", $conn) or die1($sql . "\n" . mysql_error() . "\n");
		}
		my_mysql_query("START TRANSACTION", $conn) or die1($sql . "\n" . mysql_error() . "\n");
		$sql = "DELETE FROM {$this->mvlogDB}.{$this->mview_uow} where uow_id <= {$uow_id} LIMIT 5000";
		my_mysql_query($sql, $conn) or die1($sql . "\n" . mysql_error() . "\n");
		my_mysql_query("COMMIT", $conn) or die1($sql . "\n" . mysql_error() . "\n");
	}

	/* Update the binlog_consumer_status table to indicate where we have executed to. */
	function set_capture_pos() {
		$sql = sprintf("UPDATE `" . $this->mvlogDB . "`.`" . $this->binlog_consumer_status . "` set exec_master_log_pos = %d where master_log_file = '%s' and server_id = %d", $this->binlogPosition, $this->logName, $this->serverId);
		
		my_mysql_query($sql, $this->dest) or die1("COULD NOT EXEC:\n$sql\n" . mysql_error($this->dest));
		
	}

	/* Called when a new transaction starts*/
	function start_transaction() {
		my_mysql_query("START TRANSACTION", $this->dest) or die1("COULD NOT START TRANSACTION;\n" . mysql_error());
        	$this->set_capture_pos();
		$sql = sprintf("INSERT INTO `" . $this->mview_uow . "` values(NULL,str_to_date('%s', '%%y%%m%%d %%H:%%i:%%s'),%d);",rtrim($this->timeStamp),$this->gsn_hwm);
		my_mysql_query($sql,$this->dest) or die1("COULD NOT CREATE NEW UNIT OF WORK:\n$sql\n" .  mysql_error());
		 
		$sql = "SET @fv_uow_id := LAST_INSERT_ID();";
		my_mysql_query($sql, $this->dest) or die1("COULD NOT EXEC:\n$sql\n" . mysql_error($this->dest));

	}

    
    /* Called when a transaction commits */
	function commit_transaction() {
		//Handle bulk insertion of changes
		if(!empty($this->inserts) || !empty($this->deletes)) {
			$this->process_rows();
		}
		$this->inserts = $this->deletes = $this->tables = array();

		$this->set_capture_pos();
		$sql = "UPDATE `{$this->mvlogDB}`.`{$this->mview_uow}` SET `commit_time`=str_to_date('%s','%%y%%m%%d %%H:%%i:%%s'), `gsn_hwm` = %d WHERE `uow_id` = @fv_uow_id";
		$sql = sprintf($sql, rtrim($this->timeStamp),$this->gsn_hwm);
		my_mysql_query($sql, $this->dest) or die('COULD NOT UPDATE ' . $this->mvlogDB . "." . $this->mview_uow . ':' . mysql_error($this->dest) . "\n");
		my_mysql_query("COMMIT", $this->dest) or die1("COULD NOT COMMIT TRANSACTION;\n" . mysql_error());
	}

	/* Called when a transaction rolls back */
	function rollback_transaction() {
		$this->inserts = $this->deletes = $this->tables = array();
		my_mysql_query("ROLLBACK", $this->dest) or die1("COULD NOT ROLLBACK TRANSACTION;\n" . mysql_error());
		#update the capture position and commit, because we don't want to keep reading a truncated log
		$this->set_capture_pos();
		my_mysql_query("COMMIT", $this->dest) or die1("COULD NOT COMMIT TRANSACTION LOG POSITION UPDATE;\n" . mysql_error());
		
	}

	/* Called when a row is deleted, or for the old image of an UPDATE */
	function delete_row() {
		$this->gsn_hwm+=1;
		$key = '`' . $this->mvlogDB . '`.`' . $this->mvlog_table . '`';
		$this->tables[$key]=array('schema'=>$this->db ,'table'=>$this->base_table); 
		if ( $this->bulk_insert ) {
			if(empty($this->deletes[$key])) $this->deletes[$key] = array();
			$this->row['fv$gsn'] = $this->gsn_hwm;
			$this->deletes[$key][] = $this->row;
			if(count($this->deletes[$key]) >= 10000) {
				$this->process_rows();	
			}
		} else {
			$row=array();
			foreach($this->row as $col) {
				if($col[0] == "'") {
					 $col = trim($col,"'");
				}
				$col = mysql_real_escape_string($col);
				$row[] = "'$col'";
			}
			$valList = "(-1, @fv_uow_id, {$this->binlogServerId},{$this->gsn_hwm}," . implode(",", $row) . ")";
			$sql = sprintf("INSERT INTO `%s`.`%s` VALUES %s", $this->mvlogDB, $this->mvlog_table, $valList );
			my_mysql_query($sql, $this->dest) or die1("COULD NOT EXEC SQL:\n$sql\n" . mysql_error() . "\n");
		}
	}

	/* Called when a row is inserted, or for the new image of an UPDATE */
	function insert_row() {
		$this->gsn_hwm+=1;
		$key = '`' . $this->mvlogDB . '`.`' . $this->mvlog_table . '`';
		$this->tables[$key]=array('schema'=>$this->db ,'table'=>$this->base_table); 
		if ( $this->bulk_insert ) {
			if(empty($this->inserts[$key])) $this->inserts[$key] = array();
			$this->row['fv$gsn'] = $this->gsn_hwm;
			$this->inserts[$key][] = $this->row;
			if(count($this->inserts[$key]) >= 10000) {
				$this->process_rows();	
			}
		} else {
			$row=array();
			foreach($this->row as $col) {
				if($col[0] == "'") {
					 $col = trim($col,"'");
				}
				$col = mysql_real_escape_string($col);
				$row[] = "'$col'";
			}
			$valList = "(1, @fv_uow_id, $this->binlogServerId,{$this->gsn_hwm}," . implode(",", $row) . ")";
			$sql = sprintf("INSERT INTO `%s`.`%s` VALUES %s", $this->mvlogDB, $this->mvlog_table, $valList );
			my_mysql_query($sql, $this->dest) or die1("COULD NOT EXEC SQL:\n$sql\n" . mysql_error() . "\n");
		}
	}

	function process_rows() {
		$i = 0;
		
		while($i<2) {
			$valList =  "";
			if ($i==0) {
				$data = $this->inserts;
				$mode = 1;
			} else {
				$data = $this->deletes;
				$mode = -1;
			}		
			$tables = array_keys($data);
			foreach($tables as $table) {
				$rows = $data[$table];	
				
				$sql = sprintf("INSERT INTO %s VALUES ", $table);
				foreach($rows as $the_row) {	
					$row = array();
					$gsn = $the_row['fv$gsn'];
					unset($the_row['fv$gsn']);
					foreach($the_row as $pos => $col) {
						if($col[0] == "'") {
							$col = "'" . mysql_real_escape_string(trim($col,"'")) . "'";
							
						}
					
						$datatype = $this->table_ordinal_datatype($this->tables[$table]['schema'],$this->tables[$table]['table'],$pos+1);
						switch(trim($datatype)) {
							case 'int':
							case 'tinyint':
							case 'mediumint':
							case 'smallint':
							case 'bigint':
							case 'serial':
							case 'decimal':
							case 'float':
							case 'double':
								if($this->table_ordinal_is_unsigned($this->tables[$table]['schema'],$this->tables[$table]['table'],$pos+1)) {
									if($col[0] == "-" && strpos($col, '(')) {
										$col = substr($col, strpos($col,'(')+1, -1);
									}
								} else {
									if(strpos($col,' ')) $col = substr($col,0,strpos($col,' '));
								}

								$last_point = strrpos($col, '.');
								$first_point = strpos($col, '.');
								if($last_point !== $first_point) {
									$mod_str=substr($col, 0, $last_point-1);
									$mod_str=str_replace('.','',$mod_str);
									$col = $mod_str .= substr($col, $last_point);
								}	
							break;

							case 'timestamp':
								$col = 'from_unixtime(' . $col . ')';
							break;

							case 'datetime': 
								$col = "'" . mysql_real_escape_string(trim($col,"'")) . "'";
							break;
						}

						$row[] = $col;
					}

					if($valList) $valList .= ",\n";	
					$valList .= "($mode, @fv_uow_id, $this->binlogServerId,$gsn," . implode(",", $row) . ")";
					$bytes = strlen($valList) + strlen($sql);
					$allowed = floor($this->max_allowed_packet * .9);  #allowed len is 90% of max_allowed_packet	
					if($bytes > $allowed) {
						my_mysql_query($sql . $valList, $this->dest) or die1("COULD NOT EXEC SQL:\n$sql\n" . mysql_error() . "\n");
						$valList = "";
					}
					
				}
				if($valList) {
					my_mysql_query($sql . $valList, $this->dest) or die1("COULD NOT EXEC SQL:\n$sql\n" . mysql_error() . "\n");
					$valList = '';
				}
			}

			++$i;
		}

		unset($this->inserts);
		unset($this->deletes);
		$this->inserts = array();
		$this->deletes = array();

	}

	/* Called for statements in the binlog.  It is possible that this can be called more than
	 * one time per event.  If there is a SET INSERT_ID, SET TIMESTAMP, etc
	 */	
	function statement($sql) {

		$sql = trim($sql);
		#TODO: Not sure  if this might be important..
		#      In general, I think we need to worry about character
		#      set way more than we do (which is not at all)
		if(substr($sql,0,6) == '/*!\C ') {
			return;
		}
		
		if($sql[0] == '/') {
			$end_comment = strpos($sql, ' ');
			$sql = trim(substr($sql, $end_comment, strlen($sql) - $end_comment));
		}
		
		preg_match("/([^ ]+)(.*)/", $sql, $matches);
		
		//print_r($matches);
		
		$command = $matches[1];
		$command = str_replace($this->delimiter,'', $command);
		$args = $matches[2];
	
		switch(strtoupper($command)) {
			#register change in delimiter so that we properly capture statements
			case 'DELIMITER':
				$this->delimiter = trim($args);
				break;
				
			#ignore SET and USE for now.  I don't think we need it for anything.
			case 'SET':
				break;
			case 'USE':
				$this->activeDB = trim($args);	
				$this->activeDB = str_replace($this->delimiter,'', $this->activeDB);
				break;
				
			#NEW TRANSACTION
			case 'BEGIN':
				$this->start_transaction();
				break;
			#END OF BINLOG, or binlog terminated early, or mysqlbinlog had an error
			case 'ROLLBACK':
				$this->rollback_transaction();
				break;
				
			case 'COMMIT':
				$this->commit_transaction();
				break;
				
			#Might be interestested in CREATE statements at some point, but not right now.
			case 'CREATE':
				break;
				
			#DML IS BAD....... :(
			case 'INSERT':
			case 'UPDATE':
			case 'DELETE':
			case 'REPLACE':
			case 'TRUNCATE':
				/* TODO: If the table is not being logged, ignore DML on it... */
				if($this->raiseWarnings) trigger_error('Detected statement DML on a table!  Changes can not be tracked!' , E_USER_WARNING);
				break;

			case 'RENAME':

				
				#TODO: Find some way to make atomic rename atomic.  split it up for now
				$tokens = FlexCDC::split_sql($sql);
				
				$clauses=array();
				$new_sql = '';
				$clause = "";
				for($i=4;$i<count($tokens);++$i) {
					#grab each alteration clause (like add column, add key or drop column)
					if($tokens[$i] == ',') {
						$clauses[] = $clause;
						$clause = "";
					} else {
						$clause .= $tokens[$i]; 
					}		
				}
				if($clause) $clauses[] = $clause;
				$new_clauses = "";
				
				foreach($clauses as $clause) {
					
					$clause = trim(str_replace($this->delimiter, '', $clause));
					$tokens = FlexCDC::split_sql($clause);
					$old_table = $tokens[0];
					if(strpos($old_table, '.') === false) {
						$old_base_table = $old_table;
						$old_table = $this->activeDB . '.' . $old_table;
						$old_schema = $this->activeDB;
						
					} else {
						$s = explode(".", $old_table);
						$old_schema = $s[0];
						$old_base_table = $s[1];
					}
					$old_log_table = str_replace('.','_',$old_table);
					
					$new_table = $tokens[4];
					if(strpos($new_table, '.') === false) {
						$new_schema = $this->activeDB;
						$new_base_table = $new_table;
						$new_table = $this->activeDB . '.' . $new_table;
						
					} else {
						$s = explode(".", $new_table);
						$new_schema = $s[0];
						$new_base_table = $s[1];
					}
					
					$new_log_table = str_replace('.', '_', $new_table);
										
					$clause = "$old_log_table TO $new_log_table";
							
					
					$sql = "DELETE from `" . $this->mvlogs . "` where table_name='$old_base_table' and table_schema='$old_schema'";
					
					my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error($this->dest) . "\n");
					$sql = "REPLACE INTO `" . $this->mvlogs . "` (mvlog_name, table_name, table_schema) values ('$new_log_table', '$new_base_table', '$new_schema')";
					my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error($this->dest) . "\n");
					
					$sql = 'RENAME TABLE ' . $clause;
					@my_mysql_query($sql, $this->dest);# or die1('DURING RENAME:\n' . $new_sql . "\n" . mysql_error($this->dest) . "\n");
					my_mysql_query('commit', $this->dest);					
				
					$this->mvlogList = array();
					$this->refresh_mvlog_cache();
					
					
				}
						
				break;
			#ALTER we can deal with via some clever regex, when I get to it.  Need a test case
			#with some complex alters
			case 'ALTER':
				/* TODO: If the table is not being logged, ignore ALTER on it...  If it is being logged, modify ALTER appropriately and apply to the log.*/
				$tokens = FlexCDC::split_sql($sql);
				$is_alter_table = -1;
				foreach($tokens as $key => $token) {
					if(strtoupper($token) == 'TABLE') {
						$is_alter_table = $key;
						break;
					}
				}
				if(!preg_match('/\s+table\s+([^ ]+)/i', $sql, $matches)) return;
				
				if(empty($this->mvlogList[str_replace('.','',trim($matches[1]))])) {
					return;
				}
				$table = $matches[1];
				#switch table name to the log table
				if(strpos($table, '.')) {
				  $s = explode('.', $table);
				  $old_schema = $s[0];
				  $old_base_table = $s[1];
				} else {
				  $old_schema = $this->activeDB;
				  $old_base_table = $table;
				}
				unset($table);
				
				$old_log_table = $s[0] . '_' . $s[1];
				
				#IGNORE ALTER TYPES OTHER THAN TABLE
				if($is_alter_table>-1) {
					$clauses = array();
					$clause = "";

					for($i=$is_alter_table+4;$i<count($tokens);++$i) {
						#grab each alteration clause (like add column, add key or drop column)
						if($tokens[$i] == ',') {
							$clauses[] = $clause;
							$clause = "";
						} else {
							$clause .= $tokens[$i]; 
						}		
					}	
					$clauses[] = $clause;
					
					
					$new_clauses = "";
					$new_log_table="";
					$new_schema="";
					$new_base_Table="";
					foreach($clauses as $clause) {
						$clause = trim(str_replace($this->delimiter, '', $clause));
						
						#skip clauses we do not want to apply to mvlogs
						if(!preg_match('/^ORDER|^DISABLE|^ENABLE|^ADD CONSTRAINT|^ADD FOREIGN|^ADD FULLTEXT|^ADD SPATIAL|^DROP FOREIGN|^ADD KEY|^ADD INDEX|^DROP KEY|^DROP INDEX|^ADD PRIMARY|^DROP PRIMARY|^ADD PARTITION|^DROP PARTITION|^COALESCE|^REORGANIZE|^ANALYZE|^CHECK|^OPTIMIZE|^REBUILD|^REPAIR|^PARTITION|^REMOVE/i', $clause)) {
							
							#we have four "header" columns in the mvlog.  Make it so that columns added as
							#the FIRST column on the table go after our header columns.
							$tokens = preg_split('/\s/', $clause);
														
							if(strtoupper($tokens[0]) == 'RENAME') {
								if(strtoupper(trim($tokens[1])) == 'TO') {
									$tokens[1] = $tokens[2];
								}
								
								if(strpos($tokens[1], '.') !== false) {
									$new_log_table = $tokens[1];
									$s = explode(".", $tokens[1]);
									$new_schema = $s[0];
									$new_base_table = $s[1];
								} else {
									$new_base_table = $tokens[1];
									$new_log_table = $this->activeDB . '.' . $tokens[1];
								}
								$new_log_table = str_replace('.', '_', $new_log_table);
								$clause = "RENAME TO $new_log_table";
																			
							}
							
							if(strtoupper($tokens[0]) == 'ADD' && strtoupper($tokens[count($tokens)-1]) == 'FIRST') {
								$tokens[count($tokens)-1] = 'AFTER `fv$gsn`';
								$clause = join(' ', $tokens);
							}
							if($new_clauses) $new_clauses .= ', ';
							$new_clauses .= $clause;
						}
					}
					if($new_clauses) {
						$new_alter = 'ALTER TABLE ' . $old_log_table . ' ' . $new_clauses;
						
						my_mysql_query($new_alter, $this->dest) or die1($new_alter. "\n" . mysql_error($this->dest) . "\n");
						if($new_log_table) {
							$sql = "DELETE from `" . $this->mvlogs . "` where table_name='$old_base_table' and table_schema='$old_schema'";
							my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error($this->dest) . "\n");

							$sql = "INSERT INTO `" . $this->mvlogs . "` (mvlog_name, table_name, table_schema) values ('$new_log_table', '$new_base_table', '$new_schema')";
							
							my_mysql_query($sql, $this->dest) or die1($sql . "\n" . mysql_error($this->dest) . "\n");
							$this->mvlogList = array();
							$this->refresh_mvlog_cache();
						}
					}
				}	
											 
				break;

			#DROP probably isn't bad.  We might be left with an orphaned change log.	
			case 'DROP':
				/* TODO: If the table is not being logged, ignore DROP on it.  
				 *       If it is being logged then drop the log and maybe any materialized views that use the table.. 
				 *       Maybe throw an errro if there are materialized views that use a table which is dropped... (TBD)*/
				if($this->raiseWarnings) trigger_error('Detected DROP on a table!  This may break CDC, particularly if the table is recreated with a different structure.' , E_USER_WARNING);
				break;
				
			#I might have missed something important.  Catch it.	
			#Maybe this should be E_USER_ERROR
			default:
				#if($this->raiseWarnings) trigger_error('Unknown command: ' . $command, E_USER_WARNING);
				if($this->raiseWarnings) trigger_error('Unknown command: ' . $command, E_USER_WARNING);
				break;
		}
	}
	
	static function ignore_clause($clause) {
		$clause = trim($clause);
		if(preg_match('/^(?:ADD|DROP)\s+(?:PRIMARY KEY|KEY|INDEX)')) {
			return true;
		}
		return false;
	} 
	
	function process_binlog($proc, $lastLine="") {
		$binlogStatement="";
		$this->timeStamp = false;

		$this->refresh_mvlog_cache();
		$sql = "";

		#read from the mysqlbinlog process one line at a time.
		#note the $lastLine variable - we process rowchange events
		#in another procedure which also reads from $proc, and we
		#can't seek backwards, so this function returns the next line to process
		#In this case we use that line instead of reading from the file again
		while( !feof($proc) || $lastLine !== '') {
			if($lastLine) {
				#use a previously saved line (from process_rowlog)
				$line = $lastLine;
				$lastLine = "";
			} else {
				#read from the process
				$line = trim(fgets($proc));
			}

			#echo "-- $line\n";
			#It is faster to check substr of the line than to run regex
			#on each line.
			$prefix=substr($line, 0, 5);
			if($prefix=="ERROR") {
				if(preg_match('/Got error/', $line)) 
				die1("error from mysqlbinlog: $line");
			}
			$matches = array();

			#Control information from MySQLbinlog is prefixed with a hash comment.
			if($prefix[0] == "#") {
				$binlogStatement = "";
				if (preg_match('/^#([0-9]+\s+[0-9:]+)\s+server\s+id\s+([0-9]+)\s+end_log_pos ([0-9]+).*/', $line,$matches)) {
					$this->timeStamp = $matches[1];
					$this->binlogPosition = $matches[3];
					$this->binlogServerId = $matches[2];
					#$this->set_capture_pos();
				} else {
					#decoded RBR changes are prefixed with ###				
					if($prefix == "### I" || $prefix == "### U" || $prefix == "### D") {
						if(preg_match('/### (UPDATE|INSERT INTO|DELETE FROM)\s([^.]+)\.(.*$)/', $line, $matches)) {
							$this->db          = trim($matches[2],'`');
							$this->base_table  = trim($matches[3],'`');

							if($this->db == $this->mvlogDB && $this->base_table == $this->mvlogs) {
								$this->refresh_mvlog_cache();
							}
							
							if(empty($this->mvlogList[$this->db . $this->base_table])) {
								if($this->auto_changelog && !strstr($this->base_table,'_delta') ) {
								 		$this->create_mvlog($this->db, $this->base_table);  
								 		$this->refresh_mvlog_cache();
								}
							} else {
								$this->mvlog_table = $this->mvlogList[$this->db . $this->base_table];
								$lastLine = $this->process_rowlog($proc, $line);
							}

							
						}
					} 
				}
		 
			}	else {
				
				if($binlogStatement) {
					$binlogStatement .= " ";
				}
				$binlogStatement .= $line;
				$pos=false;				
				if(($pos = strpos($binlogStatement, $this->delimiter)) !== false)  {
					#process statement
					$this->statement($binlogStatement);
					$binlogStatement = "";
				} 
			}
		}
	}
	
	
	function process_rowlog($proc) {
		$sql = "";
		$skip_rows = false;
		$line = "";
		#if there is a list of databases, and this database is not on the list
		#then skip the rows
		if(!empty($this->onlyDatabases) && empty($this->onlyDatabases[trim($this->db)])) {
			$skip_rows = true;
		}

		# loop over the input, collecting all the input values into a set of INSERT statements
		$this->row = array();
		$mode = 0;
		
		while($line = fgets($proc)) {
			$line = trim($line);	
            #DELETE and UPDATE statements contain a WHERE clause with the OLD row image
			if($line == "### WHERE") {
				if(!empty($this->row)) {
					switch($mode) {
						case -1:
							$this->delete_row();
							break;
						case 1:
							$this->insert_row();
							break;
						default:
							die1('UNEXPECTED MODE IN PROCESS_ROWLOG!');
					}
					$this->row = array();
				}
				$mode = -1;
				
			#INSERT and UPDATE statements contain a SET clause with the NEW row image
			} elseif($line == "### SET")  {
				if(!empty($this->row)) {
					switch($mode) {
						case -1:
							$this->delete_row();
							break;
						case 1:
							$this->insert_row();
							break;
						default:
							die1('UNEXPECTED MODE IN PROCESS_ROWLOG!');
					}
					$this->row = array();
				}
				$mode = 1;
			/*} elseif(preg_match('/###\s+@[0-9]+=(-[0-9]*) .*$/', $line, $matches)) {
				$this->row[] = $matches[1];
			*/
			#Row images are in format @1 = 'abc'
			#                         @2 = 'def'
			#Where @1, @2 are the column number in the table	
			} elseif(preg_match('/###\s+@[0-9]+=(.*)$/', $line, $matches)) {
				$this->row[] = $matches[1];

			#This line does not start with ### so we are at the end of the images	
			} else {
				#echo ":: $line\n";
				if(!$skip_rows) {
					switch($mode) {
						case -1:
							$this->delete_row();
							break;
						case 1:
							$this->insert_row();
							break;
						default:
							die1('UNEXPECTED MODE IN PROCESS_ROWLOG!');
					}					
				} 
				$this->row = array();
				break; #out of while
			}
			#keep reading lines
		}
		#return the last line so that we can process it in the parent body
		#you can't seek backwards in a proc stream...
		return $line;
	}

	function drop_mvlog($schema, $table) {

		#will implicit commit	
		$sql = "DROP TABLE IF EXISTS " . $this->mvlogDB . "." . "`%s_%s`";	
		$sql = sprintf($sql, mysql_real_escape_string($schema), mysql_real_escape_string($table));
		if(!my_mysql_query($sql)) return false;

		my_mysql_query("BEGIN", $this->dest);
		$sql = "DELETE FROM " . $this->mvlogDB . ". " . $this->mvlogs . " where table_schema = '%s' and table_name = '%s'";	
		$sql = sprintf($sql, mysql_real_escape_string($schema), mysql_real_escape_string($table));
		if(!my_mysql_query($sql)) return false;

		return my_mysql_query('commit');

	}

	#AUTOPORTED FROM FLEXVIEWS.CREATE_MVLOG() w/ minor modifications for PHP
	function create_mvlog($v_schema_name,$v_table_name) { 
		// loop flag
		$v_done=FALSE;
		// each column in $v_table_name
		$v_column_name=NULL;
		// each column's datatype
		$v_data_type=NULL;
		// changelog name
		$mv_logname=NULL;
		// SQL to create & populate the changelog
		$v_sql=NULL;
	
		$cursor_sql = "SELECT COLUMN_NAME, IF(COLUMN_TYPE='TIMESTAMP', 'TIMESTAMP', COLUMN_TYPE) COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$v_table_name' AND TABLE_SCHEMA = '$v_schema_name'";
	
		$cur_columns = my_mysql_query($cursor_sql, $this->source);
		$v_sql = '';
	
		while(1) {
			if( $v_sql != '' ) {
				$v_sql = FlexCDC::concat($v_sql, ', ');
			}
	
			$row = mysql_fetch_array($cur_columns);
			if( $row === false ) $v_done = true;
	
			if( $row ) {
				$v_column_name = '`'. $row[0] . '`';
				$v_data_type = $row[1];
			}
	
			if( $v_done ) {
				mysql_free_result($cur_columns);
				break;
			}
	
			$v_sql = FlexCDC::concat($v_sql, $v_column_name, ' ', $v_data_type);
		}
	
		if( trim( $v_sql ) == "" ) {
			trigger_error('Could not access table:' . $v_table_name, E_USER_ERROR);
		}

		$mv_logname = 'mvlog_' . md5(md5($v_schema_name) .  md5($v_table_name));
		
		$v_sql = FlexCDC::concat('CREATE TABLE IF NOT EXISTS `',$mv_logname ,'` ( dml_type INT DEFAULT 0, uow_id BIGINT, `fv$server_id` INT UNSIGNED,fv$gsn bigint, ', $v_sql, 'KEY(uow_id, dml_type) ) ENGINE=INNODB');
		$create_stmt = my_mysql_query($v_sql, $this->dest);
		if(!$create_stmt) die1('COULD NOT CREATE MVLOG. ' . $v_sql . "\n");
		$exec_sql = " INSERT IGNORE INTO `". $this->mvlogDB . "`.`" . $this->mvlogs . "`( table_schema , table_name , mvlog_name ) values('$v_schema_name', '$v_table_name', '" . $mv_logname . "')";
		my_mysql_query($exec_sql) or die1($exec_sql . ':' . mysql_error($this->dest) . "\n");

		return true;
	
	}
}

