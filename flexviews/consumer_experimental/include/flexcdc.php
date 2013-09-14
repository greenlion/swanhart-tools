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
require_once('binlog_parser.php');

/* 
The exit/die1() functions normally exit with error code 0 when a string is passed in.
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
	echo($message);
	fputs($ERROR_FILE, $message);
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

	protected $sent_fde = false;
  	
	# Settings to enable bulk import
	protected $inserts = array();
	protected $deletes = array();
	protected $bulk_insert = true;
  	
	protected $mvlogDB = NULL;
	public	$mvlogList = array();
	protected $activeDB = NULL;
	protected $onlyDatabases = array();
	protected $cmdLine;

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

	private $binlog_parser = false;

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
		$data = "";
		$this->binlog_parser = new binlog_event_consumer($data,0);
		
		if(!$settings) {
			$settings = $this->read_settings();
			$this->settings = $settings;
		}
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

		#get the source MySQL version so we can construct a format description event later
		$sql = "SELECT version()";
		$stmt = my_mysql_query($sql, $this->source) or die1('Could not get source server version\n');
		$row = mysql_fetch_array($stmt);
		$this->serverVersion = $row[0];

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
				$execCmdLine = sprintf("%s -R --start-position=%d --stop-position=%d %s", $this->cmdLine, $row['exec_master_log_pos'], $row['master_log_size'], $row['master_log_file']);
				$execCmdLine .= " 2>&1";
				echo  "-- $execCmdLine\n";
				$proc = popen($execCmdLine, "r");
				if(!$proc) {
					die1('Could not read binary log using mysqlbinlog\n');
				}

				$this->binlogPosition = $row['exec_master_log_pos'];
				$this->logName = $row['master_log_file'];
				$this->process_binlog($proc, $row['master_log_file'], $row['exec_master_log_pos']);
				#echo "SHAAZAAM!\n";

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
					usleep($sleep_time * 1000000);
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

		$stmt = my_mysql_query("select uow_id, gsn_hwm from {$this->mvlogDB}.{$this->mview_uow} order by uow_id desc limit 1",$this->dest) 
			or die1('COULD NOT GET GSN_HWM:' . mysql_error($this->dest) . "\n");

		$row = mysql_fetch_array($stmt);
		$this->uow_id = $row[0];
		$this->gsn_hwm = $row[1];

		$this->binlog_parser->gsn = $this->gsn_hwm;
		$this->binlog_parser->mvlogs = $this->mvlogs;
		$this->binlog_parser->mvlogDB = $this->mvlogDB;
		$this->binlog_parser->dest = $this->dest;

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

        	#$this->set_capture_pos();
		$sql = sprintf("INSERT INTO `" . $this->mview_uow . "` values(NULL,str_to_date('%s', '%%y%%m%%d %%H:%%i:%%s'),%d);",rtrim($this->timeStamp),$this->gsn_hwm);
		my_mysql_query($sql,$this->dest) or die1("COULD NOT CREATE NEW UNIT OF WORK:\n$sql\n" .  mysql_error());
		 
		$sql = "SELECT LAST_INSERT_ID();";
		$stmt = my_mysql_query($sql, $this->dest) or die1("COULD NOT EXEC:\n$sql\n" . mysql_error($this->dest));
		$row = mysql_fetch_array($stmt);	
		$this->uow_id = $row[0];
	}

    
    /* Called when a transaction commits */
	function commit_transaction() {
		$this->set_capture_pos();
		$sql = "UPDATE `{$this->mvlogDB}`.`{$this->mview_uow}` SET `commit_time`=str_to_date('%s','%%y%%m%%d %%H:%%i:%%s'), `gsn_hwm` = %d WHERE `uow_id` = %d";
		$sql = sprintf($sql, rtrim($this->timeStamp),$this->gsn_hwm,$this->uow_id);
		my_mysql_query($sql, $this->dest) or die1('COULD NOT UPDATE ' . $this->mvlogDB . "." . $this->mview_uow . ':' . mysql_error($this->dest) . "\n");
		my_mysql_query("COMMIT", $this->dest) or die1("COULD NOT COMMIT TRANSACTION;\n" . mysql_error());
	}

	/* Called when a transaction rolls back */
	function rollback_transaction() {
		my_mysql_query("ROLLBACK", $this->dest) or die1("COULD NOT ROLLBACK TRANSACTION;\n" . mysql_error());
		#update the capture position and commit, because we don't want to keep reading a truncated log
		$this->set_capture_pos();
		my_mysql_query("COMMIT", $this->dest) or die1("COULD NOT COMMIT TRANSACTION LOG POSITION UPDATE;\n" . mysql_error());
		
	}

	/* Called for statements in the binlog.  It is possible that this can be called more than
	 * one time per event.  If there is a SET INSERT_ID, SET TIMESTAMP, etc
	 */	
	function statement($sql) {
		#$m = memory_get_usage(true);
		#echo "IN STATEMENT: ALLOCATED MEMORY {bytes:$m mega:" . ($m / 1024 / 1024) . " giga:" . ($m / 1024 / 1024 / 1024) . "}\n";
		if($sql[0] == '/') {
			$end_comment = strpos($sql, ' ');
			$sql = trim(substr($sql, $end_comment, strlen($sql) - $end_comment));
		}
		$command = "";
		$args = "";
	
		$space_pos = strpos($sql, ' ');	
		if($space_pos !== false) {
			$command = trim(strtoupper(trim(substr($sql, 0, $space_pos))));
			$args = trim(substr($sql, $space_pos));
		} else {
			$command = trim($sql);
		}

		if($args ) { 
			if($command !== 'DELIMITER') $args = str_replace($this->delimiter, '', $args);
		} else {
			$command = str_replace($this->delimiter, '', $command);
		}

		switch(strtoupper(trim($command))) {
			#register change in delimiter so that we properly capture statements
			case 'DELIMITER':
				$this->delimiter = $args;
				break;
				
			#ignore SET and USE for now.  I don't think we need it for anything.
			case 'SET':
				break;
			case 'USE':
				$this->activeDB = $args;	
				break;
				
			#NEW TRANSACTION
			case 'BEGIN':
				#echo "STARTING NEW TRANSACTION!\n";
				$this->start_transaction();
				break;

			case 'BINLOG':
				unset($sql);
				$args = trim($args,"'\n");
				#$m = memory_get_usage(true);
				#echo "BEFORE BINLOG PARSE: ALLOCATED MEMORY {bytes:$m mega:" . ($m / 1024 / 1024) . " giga:" . ($m / 1024 / 1024 / 1024) . "}\n";
				$this->binlog_parser->set('uow_id', $this->uow_id);
				$this->binlog_parser->set('server_id', $this->serverId);
				$this->binlog_parser->consume($args);
				$this->gsn_hwm = $this->binlog_parser->get('gsn');

				#$m = memory_get_usage(true);
				#echo "AFTER CONSUME: ALLOCATED MEMORY {bytes:$m mega:" . ($m / 1024 / 1024) . " giga:" . ($m / 1024 / 1024 / 1024) . "}\n";
				#$this->binlog_parser->reset();
		
				#$m = memory_get_usage(true);
				#echo "AFTER RESET: ALLOCATED MEMORY {bytes:$m mega:" . ($m / 1024 / 1024) . " giga:" . ($m / 1024 / 1024 / 1024) . "}\n";

				break;

			#END OF BINLOG, or binlog terminated early, or mysqlbinlog had an error
			case 'ROLLBACK':
				$this->binlog_parser->reset();
				#echo "DO ROLLBACK WORK!\n";
				$this->rollback_transaction();
				break;
				
			case 'COMMIT':

				$this->binlog_parser->reset();
				#echo "DO COMMIT WORK\n";
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

		$sql = "";
		while($line = fgets($proc)) {
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
				if (preg_match('/^#([0-9]+\s+[0-9:]+)\s+server\s+id\s+([0-9]+)\s+end_log_pos ([0-9]+).*$/', $line,$matches)) {
					$this->timeStamp = $matches[1];
					$this->binlogPosition = $matches[3];
					$this->binlogServerId = $matches[2];
					#echo "HERE: {$this->binlogPosition}\n";
				}
			} else {
				#if($binlogStatement) {
				#	$binlogStatement .= "\n";
				#}
				$binlogStatement .= $line;
				$pos=false;				
				if($pos = strpos(substr(trim($line), -strlen($this->delimiter)), $this->delimiter) !== false)  {
					#process statement
					$this->statement($binlogStatement);
					$binlogStatement = "";
				} 
			}
		}
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
		$v_done=FALSE;
		$v_column_name=NULL;
		$v_data_type=NULL;
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
			
		$v_sql = FlexCDC::concat('CREATE TABLE IF NOT EXISTS`', $this->mvlogDB ,'`.`' ,$v_schema_name, '_', $v_table_name,'` ( dml_type INT DEFAULT 0, uow_id BIGINT, `fv$server_id` INT UNSIGNED,fv$gsn bigint, ', $v_sql, 'KEY(uow_id, dml_type) ) ENGINE=INNODB');
		$create_stmt = my_mysql_query($v_sql, $this->dest);
		if(!$create_stmt) die1('COULD NOT CREATE MVLOG. ' . $v_sql . "\n");
		$exec_sql = " INSERT IGNORE INTO `". $this->mvlogDB . "`.`" . $this->mvlogs . "`( table_schema , table_name , mvlog_name ) values('$v_schema_name', '$v_table_name', '" . $v_schema_name . "_" . $v_table_name . "')";
		my_mysql_query($exec_sql) or die1($exec_sql . ':' . mysql_error($this->dest) . "\n");

		return true;
	
	}
}

