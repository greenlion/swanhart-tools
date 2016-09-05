<?php
require_once('plugin_interface.php');

class json_file implements FlexCDC_Plugin_Interface {
	static $path = "";
	static $add_metadata = false;
	static $fp = false;
	static $buffer = array();
	static $filter = array();

	static function to_utf8($dat) { 
		if (is_string($dat)) return utf7_encode($dat); 
		if (!is_array($dat)) return $dat; 
		$ret = array(); 
		foreach($dat as $i=>$d) $ret[$i] = utf8_encode_all($d); 
		return $ret; 
	} 

	static function open() {
		$IAM::$fp = false;
		$IAM::$fp = fopen($IAM::$path, 'a+');
		if(!$IAM::$fp) {
			echo "JSON_FILE_OPEN: error opening file " . $IAM::$fp . "\n";
			exit(1);
		}
		return $IAM::$fp;
	}

	static function close() {
		if($IAM::$fp && !fclose($IAM::$fp)) {
			echo "JSON_FILE_CLOSE: error closing file " . $IAM::$fp . "\n";
			exit(2);
		}
		$IAM::$fp = false;
		$IAM::$buffer = array();
		return true;
	}

	static function plugin_init($instance,$IAM) {
		$IAM::$path = trim($instance->settings['json_file']['path']);
		if(isset($instance->settings['json_file']['add_metadata']) && 
		   trim($instance->settings['json_file']['add_metadata']) == 1
		) {
			$IAM::$add_metadata = true;
		}

		if(isset($instance->settings['json_file']['filter'])) {
			$tmp = explode(',',$instance->settings['json_file']['filter']);
			foreach($tmp as $tbl) {
				$t2 = explode('.', $tbl);
				if(count($t2) != 2) {
					echo "JSON_FILE_INIT - Invalid schema filter: filter must be schema.table\n";
					exit(4);
				}
				$IAM::$filter[] = $t2;
			} 
		}

		echo "JSON_FILE_INIT - Writing to file: " . $IAM::$path . "\n";
		echo "JSON_FILE_INIT - Adding metadata: " . ($IAM::$add_metadata == true ? 'TRUE' : 'FALSE') . "\n";
		if(!empty($IAM::$filter)) {
			echo "JSON_FILE_INIT - Filters:\n" . print_r($IAM::$filter,true);
		}
	}

	static function skip_table($schema, $table) {
		foreach($IAM::$filter as $filt) {
			if($filt[0] == $schema && $filt[1] == $table) {
				echo "SKIPPING $schema.$table\n";
				return true;
			}
		}
		return false;
	}

	static function plugin_deinit($instance,$IAM) {
		$IAM::close();
		echo "JSON_FILE_SHUTDOWN - OK\n";
	}

	static function begin_trx($uow_id, $gsn,$instance,$IAM) {
		$IAM::open();
	}

	static function commit_trx($uow_id, $gsn,$instance,$IAM) {
		if(empty($IAM::$buffer)) return;
		foreach($IAM::$buffer as $line) {
			if(!fwrite($IAM::$fp, $line)) {
				echo "JSON_FILE_WRITE - error writing to file\n";
				exit(3);
			} 
		}
		$IAM::close();
	}

	static function rollback_trx($uow_id=false, $instance,$IAM) {
		// discard the buffer
		$IAM::close();
	}

	static function insert($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
		if($IAM::skip_table($db, $table)) return;
		$row['MYSQL_ACTION'] = 'I';
		if($IAM::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		$IAM::$buffer[] = json_encode($IAM::to_utf8($row)) . "\n";
	}
	
	static function delete($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
		if($IAM::skip_table($db, $table)) return;
		$row['MYSQL_ACTION'] = 'D';
		if($IAM::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		$IAM::$buffer[] = json_encode($IAM::to_utf8($row)) . "\n";
	}
	
	static function update_before($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
		if($IAM::skip_table($db, $table)) return;
		$row['MYSQL_ACTION'] = 'UB';
		if($IAM::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		echo "TABLE: $db.$table\n";
		$IAM::$buffer[] = json_encode($IAM::to_utf8($row)) . "\n";
	}

	static function update_after($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
		if($IAM::skip_table($db, $table)) return;
		$row['MYSQL_ACTION'] = 'UA';
		if($IAM::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		$IAM::$buffer[] = json_encode($IAM::to_utf8($row)) . "\n";
	}
}
