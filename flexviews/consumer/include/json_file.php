<?php
require_once('plugin_interface.php');

class json_file implements FlexCDC_Plugin_Interface {
	static $path = "";
	static $add_metadata = false;
	static $fp = false;
	static $buffer = "";

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
		json_file::$buffer = "";
		return true;
	}

	static function plugin_init($instance) {
		json_file::$path = trim($instance->settings['json_file']['path']);
		if(isset($instance->settings['json_file']['add_metadata']) && 
		   trim($instance->settings['json_file']['add_metadata']) == 1
		) {
			json_file::$add_metadata = true;
		}

		echo "JSON_FILE_INIT - Writing to file: " . json_file::$path . "\n";
		echo "JSON_FILE_INIT - Adding metadata: " . (json_file::$add_metadata == true ? 'TRUE' : 'FALSE') . "\n";
		
	}

	static function plugin_deinit($instance) {
		json_file::close();
		echo "JSON_FILE_SHUTDOWN - OK\n";
	}

	static function begin_trx($uow_id, $gsn,$instance) {
		json_file::open();
	}

	static function commit_trx($uow_id, $gsn,$instance) {
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
		$row['MYSQL_ACTION'] = 'I';
		if(json_file::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		json_file::$buffer[] = json_encode($row) . "\n";
	}
	
	static function delete($row, $db, $table, $trx_id, $gsn,$instance) {
		$row['MYSQL_ACTION'] = 'D';
		if(json_file::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		json_file::$buffer[] = json_encode($row) . "\n";
	}
	
	static function update_before($row, $db, $table, $trx_id, $gsn,$instance) {
		$row['MYSQL_ACTION'] = 'UB';
		if(json_file::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		json_file::$buffer[] = json_encode($row) . "\n";
	}

	static function update_after($row, $db, $table, $trx_id, $gsn,$instance) {
		$row['MYSQL_ACTION'] = 'UA';
		if(json_file::$add_metadata) {
			$row['MYSQL_TABLE'] = $table;
			$row['MYSQL_SCHEMA'] = $db;
			$row['MYSQL_TRX'] = $trx_id;
			$row['MYSQL_SEQ'] = $gsn;
		}
		json_file::$buffer[] = json_encode($row) . "\n";
	}
}
