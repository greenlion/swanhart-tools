<?php

class FlexCDC_Plugin {
	static function insert($row, $gsn) {
		echo "INSERT AT: $gsn\n"; print_r($row);	
	}
	
	static function delete($row, $gsn) {
		echo "DELETE AT: $gsn\n"; print_r($row);	
	}
	
	static function update_before($row, $gsn) {
		echo "UPDATE (OLD) AT: $gsn\n"; print_r($row);	
	}

	static function update_after($row, $gsn) {
		echo "UPDATE (NEW) AT: $gsn\n"; print_r($row);	
	}
}
