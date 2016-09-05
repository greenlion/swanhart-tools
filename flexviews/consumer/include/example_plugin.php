<?php
require_once('plugin_interface.php');

class FlexCDC_Plugin implements FlexCDC_Plugin_Interface {

	static function plugin_init($instance,$IAM) {
		echo "$IAM PLUGIN STARTUP\n";
	}

	static function plugin_deinit($instance,$IAM) {
		echo "$IAM PLUGIN SHUTDOWN\n";
	}

	static function begin_trx($uow_id, $gsn,$instance,$IAM) {
		echo "$IAM START TRANSACTION: trx_id: $uow_id, Prev GSN: $gsn\n";
	}

	static function commit_trx($uow_id, $gsn,$instance,$IAM) {
		echo "$IAM COMMIT: trx_id: $uow_id, Last GSN: $gsn\n";
	}

	static function rollback_trx($uow_id=false, $instance,$IAM) {
		echo "$IAM ROLLBACK: trx_id: $uow_id\n";
	}

	static function insert($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
		echo "$IAM TRX_ID: $trx_id, Schema:$db, Table: $table, DML: INSERT, AT: $gsn\n"; print_r($row);	
	}
	
	static function delete($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
		echo "$IAM TRX_ID: $trx_id, Schema:$db, Table: $table, DML: DELETE, AT: $gsn\n"; print_r($row);	
	}
	
	static function update_before($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
		echo "$IAM TRX_ID: $trx_id, Schema:$db, Table: $table, DML: UPDATE (OLD), AT: $gsn\n"; print_r($row);	
	}

	static function update_after($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
		echo "$IAM TRX_ID: $trx_id, Schema:$db, Table: $table, DML: UPDATE (NEW), AT: $gsn\n"; print_r($row);	
	}
}
