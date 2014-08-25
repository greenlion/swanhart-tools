<?php

class FlexCDC_Plugin {
	static function insert($row, $db, $table, $gsn) {
		echo "IN $db, For table: $table, INSERT AT: $gsn\n"; print_r($row);	
	}
	
	static function delete($row, $db, $table, $gsn) {
		echo "IN $db, For table: $table, DELETE AT: $gsn\n"; print_r($row);	
	}
	
	static function update_before($row, $db, $table, $gsn) {
		echo "IN $db, For table: $table, UPDATE (OLD) AT: $gsn\n"; print_r($row);	
	}

	static function update_after($row, $db, $table, $gsn) {
		echo "IN $db, For table: $table, UPDATE (NEW) AT: $gsn\n"; print_r($row);	
	}
}
