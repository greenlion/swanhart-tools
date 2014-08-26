<?php

class FlexCDC_Plugin {

	static function begin_trx($uow_id, $gsn) {
		echo "Starting trx_id: $uow_id, Prev GSN: $gsn\n";
	}

	static function commit_trx($uow_id, $gsn) {
		echo "COMMIT trx_id: $uow_id, Last GSN: $gsn\n";
	}

	static function rollback_trx($uow_id) {
		echo "ROLLBACK trx_id: $uow_id\n";
	}

	static function insert($row, $db, $table, $trx_id, $gsn) {
		echo "TRX_ID: $trx_id, Schema:$db, Table: $table, DML: INSERT, AT: $gsn\n"; print_r($row);	
	}
	
	static function delete($row, $db, $table, $trx_id, $gsn) {
		echo "TRX_ID: $trx_id, Schema:$db, Table: $table, DML: DELETE, AT: $gsn\n"; print_r($row);	
	}
	
	static function update_before($row, $db, $table, $trx_id, $gsn) {
		echo "TRX_ID: $trx_id, Schema:$db, Table: $table, DML: UPDATE (OLD), AT: $gsn\n"; print_r($row);	
	}

	static function update_after($row, $db, $table, $trx_id, $gsn) {
		echo "TRX_ID: $trx_id, Schema:$db, Table: $table, DML: UPDATE (NEW), AT: $gsn\n"; print_r($row);	
	}
}
