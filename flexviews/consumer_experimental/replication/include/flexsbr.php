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

require_once('include/flexcdc.php');

class FlexSBR extends FlexCDC {
	protected $mvlogDB = 'replication'; #directory with replication metadata
	protected $activeDB = NULL;
	protected $cmdLine;

	protected $source = NULL;
	protected $dest = NULL;

	protected $serverId = NULL;
	protected $binlogServerId=1;
	public  $raiseWarnings = false;
	
	public  $delimiter = ';';
	
	#Construct a new consumer object.
	#By default read settings from the INI file unless they are passed
	#into the constructor	
	public function __construct($settings = NULL) {
		
		if(!$settings) {
			$settings = $this->read_settings();
		}
		if(!$this->cmdLine) $this->cmdLine = `which mysqlbinlog`;
		
		#the mysqlbinlog command line location may be set in the settings
		#we will autodetect the location if it is not specified explicitly
		if(!empty($settings['replication']['mysqlbinlog'])) {
			$this->cmdLine = $settings['replication']['mysqlbinlog'];
		} 

		$this->mvlogDB=$settings['replication']['database'];
		if(!empty($settings['replication']['binlog_consumer_status'])) {
			$this->binlog_consumer_status=$settings['replication']['binlog_consumer_status'];	
		}
		
		#build the command line from user, host, password, socket options in the ini file in the [source] section
		foreach($settings['source'] as $k => $v) {
			$this->cmdLine .= " --$k=$v";
		}
		
		#shortcuts
		$S = $settings['source'];
		$D = $settings['dest'];

		if(!empty($settings['raise_warnings']) && $settings['raise_warnings'] != 'false') {
 			$this->raiseWarnings=true;
		}

		if(!empty($settings['replication']['bulk_insert']) && $settings['replication']['bulk_insert'] != 'false') {
			$this->bulk_insert = true;
		}
	
		/*TODO: support unix domain sockets */
		$this->source = mysql_connect($S['host'] . ':' . $S['port'], $S['user'], $S['password'], true) or die('Could not connect to MySQL server:' . mysql_error());
		$this->dest = mysql_connect($D['host'] . ':' . $D['port'], $D['user'], $D['password'], true) or die('Could not connect to MySQL server:' . mysql_error());
	    
	}

	public function setup($force=false) {
		#FlexSBR only needs a single state table
		return parent::setup($force, 'binlog_consumer_status');	
	}
	

	protected function read_settings() {
		
		if(!empty($argv[1])) {
			$iniFile = $argv[1];
		} else {
			$iniFile = "replication.ini";
		}
	
		$settings=@parse_ini_file($iniFile,true) or die("Could not read ini file: $iniFile\n");
		if(!$settings || empty($settings['replication'])) {
			die("Could not find [replication] section or .ini file not found");
		}

		return $settings;

	}

	function refresh_mvlog_cache() {
		return true;
	}

	/* Set up the destination connection */
	function initialize_dest() {
		#my_mysql_query("SELECT GET_LOCK('flexcdc::SOURCE_LOCK::" . $this->server_id . "',15)") or die("COULD NOT OBTAIN LOCK\n");
		mysql_select_db($this->mvlogDB,$this->dest) or die('COULD NOT CHANGE DATABASE TO:' . $this->mvlogDB . "\n");
		$stmt = my_mysql_query("BEGIN;", $this->dest) or die(mysql_error());
		if(!$stmt) die(mysql_error());

		$stmt = my_mysql_query("select @@max_allowed_packet", $this->dest);
		$row = mysql_fetch_array($stmt);
		$this->max_allowed_packet = $row[0];	

		
	}
	
	/* Get the list of logs from the source and place them into a temporary table on the dest*/
	
	function get_source_logs() {
		/* This server id is not related to the server_id in the log.  It refers to the ID of the 
		 * machine we are reading logs from.
		 */
		$sql = "SELECT @@server_id";
		$stmt = my_mysql_query($sql, $this->source);
		$row = mysql_fetch_array($stmt) or die($sql . "\n" . mysql_error() . "\n");
		$this->serverId = $row[0];


		$stmt = my_mysql_query("SHOW BINARY LOGS", $this->source);
		if(!$stmt) die(mysql_error());
		$has_logs = false;	
		while($row = mysql_fetch_array($stmt)) {
			if(!$has_logs) {
				my_mysql_query("CREATE TEMPORARY table log_list (log_name char(50), primary key(log_name))",$this->dest) or die(mysql_error());
				$has_logs = true;
			}
			$sql = sprintf("INSERT INTO `" . $this->mvlogDB . "`.`" . $this->binlog_consumer_status ."`(server_id, master_log_file, master_log_size, exec_master_log_pos) values (%d, '%s', %d, 0) ON DUPLICATE KEY UPDATE master_log_size = %d ;", $this->serverId,$row['Log_name'], $row['File_size'], $row['File_size']);
			my_mysql_query($sql, $this->dest) or die($sql . "\n" . mysql_error() . "\n");
	
			$sql = sprintf("INSERT INTO log_list (log_name) values ('%s')", $row['Log_name']);
			my_mysql_query($sql, $this->dest) or die($sql . "\n" . mysql_error() . "\n");
		}
	}

	
	/* Called when a new transaction starts*/
	function start_transaction() {
		my_mysql_query("START TRANSACTION", $this->dest) or die("COULD NOT START TRANSACTION;\n" . mysql_error());
        $this->set_capture_pos();

	}

    
    /* Called when a transaction commits */
	function commit_transaction() {
		$this->set_capture_pos();
		my_mysql_query("COMMIT", $this->dest) or die("COULD NOT COMMIT TRANSACTION;\n" . mysql_error());
	}

	/* Called when a transaction rolls back */
	function rollback_transaction() {
		my_mysql_query("ROLLBACK", $this->dest) or die("COULD NOT ROLLBACK TRANSACTION;\n" . mysql_error());
		#update the capture position and commit, because we don't want to keep reading a truncated log
		$this->set_capture_pos();
		my_mysql_query("COMMIT", $this->dest) or die("COULD NOT COMMIT TRANSACTION LOG POSITION UPDATE;\n" . mysql_error());
		
	}

	/* Called when a row is deleted, or for the old image of an UPDATE */
	function delete_row() {
		#RBR is no-opped in the SBR replicator
		return;
	
	}

	/* Called when a row is inserted, or for the new image of an UPDATE */
	function insert_row() {
		#RBR is a no-op in SBR replication
		return false;

	}

	function process_rows() {
		#RBR is a no-op in SBR replication
		return;

	}

	/* Called for statements in the binlog.  It is possible that this can be called more than
	 * one time per event.  If there is a SET INSERT_ID, SET TIMESTAMP, etc
	 */	
	function statement($sql) {
		$sql = trim($sql);
		if(substr($sql,0,6) == '/*!\C ') {
			return;
		}
	/*	
		if($sql[0] == '/') {
			$end_comment = strpos($sql, ' ');
			$sql = trim(substr($sql, $end_comment, strlen($sql) - $end_comment));
		}
*/
		
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
				
				
			case 'USE':
				 #fall through and execute the use
				$this->activeDB = trim($args);	
				$this->activeDB = str_replace($this->delimiter,'', $this->activeDB);
			
			default:
				my_mysql_query($sql, $this->dest) or die('Could not execute [SBR] statement:' . $sql . ':' . mysql_error($this->dest) . "\n");
				break;
		}
	}
	
}

