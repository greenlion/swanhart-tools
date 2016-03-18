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
		json_file::$fp = false;
		json_file::$fp = fopen(json_file::$path, 'a+');
		if(!json_file::$fp) {
			echo "JSON_FILE_OPEN: error opening file " . json_file::$fp . "\n";
			exit(1);
		}
		return json_file::$fp;
	}

	static function close() {
		if(json_file::$fp && !fclose(json_file::$fp)) {
			echo "JSON_FILE_CLOSE: error closing file " . json_file::$fp . "\n";
			exit(2);
		}
		json_file::$fp = false;
		json_file::$buffer = array();
		return true;
	}

	static function plugin_init($instance) {
		json_file::$path = trim($instance->settings['json_file']['path']);
		if(isset($instance->settings['json_file']['add_metadata']) && 
		   trim($instance->settings['json_file']['add_metadata']) == 1
		) {
			json_file::$add_metadata = true;
		}

		if(isset($instance->settings['json_file']['filter'])) {
			$tmp = explode(',',$instance->settings['json_file']['filter']);
			foreach($tmp as $tbl) {
				$t2 = explode('.', $tbl);
				if(count($t2) != 2) {
					echo "JSON_FILE_INIT - Invalid schema filter: filter must be schema.table\n";
					exit(4);
				}
				json_file::$filter[] = $t2;
			} 
		}

		echo "JSON_FILE_INIT - Writing to file: " . json_file::$path . "\n";
		echo "JSON_FILE_INIT - Adding metadata: " . (json_file::$add_metadata == true ? 'TRUE' : 'FALSE') . "\n";
		if(!empty(json_file::$filter)) {
			echo "JSON_FILE_INIT - Filters:\n" . print_r(json_file::$filter,true);
		}
	}

	static function skip_table($schema, $table) {
		foreach(json_file::$filter as $filt) {
			if($filt[0] == $schema && $filt[1] == $table) {
				echo "SKIPPING $schema.$table\n";
				return true;
			}
		}
		return false;
	}

	static function plugin_deinit($instance) {
		json_file::close();
		echo "JSON_FILE_SHUTDOWN - OK\n";
	}

	static function begin_trx($uow_id, $gsn,$instance) {
		json_file::open();
	}

	static function commit_trx($uow_id, $gsn,$instance) {
		if(empty(json_file::$buffer)) return;
		foreach(json_file::$buffer as $line) {
			if(!fwrite(json_file::$fp, $line)) {
				echo "JSON_FILE_WRITE - error writing to file\n";
				exit(3);
			} 
		}
		json_file::close();
	}

	static function rollback_trx($uow_id=false, $instance) {
		// discard the buffer
		json_file::close();
	}

	static function insert($row, $db, $table, $trx_id, $gsn,$instance) {
		if(json_file::skip_table($db, $table)) return;
		$row['MYSQL_ACTION'] = 'I';
		if(json_file::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		json_file::$buffer[] = json_encode(json_file::to_utf8($row)) . "\n";
	}
	
	static function delete($row, $db, $table, $trx_id, $gsn,$instance) {
		if(json_file::skip_table($db, $table)) return;
		$row['MYSQL_ACTION'] = 'D';
		if(json_file::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		json_file::$buffer[] = json_encode(json_file::to_utf8($row)) . "\n";
	}
	
	static function update_before($row, $db, $table, $trx_id, $gsn,$instance) {
		if(json_file::skip_table($db, $table)) return;
		$row['MYSQL_ACTION'] = 'UB';
		if(json_file::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		echo "TABLE: $db.$table\n";
		json_file::$buffer[] = json_encode(json_file::to_utf8($row)) . "\n";
	}

	static function update_after($row, $db, $table, $trx_id, $gsn,$instance) {
		if(json_file::skip_table($db, $table)) return;
		$row['MYSQL_ACTION'] = 'UA';
		if(json_file::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		json_file::$buffer[] = json_encode(json_file::to_utf8($row)) . "\n";
	}
}
