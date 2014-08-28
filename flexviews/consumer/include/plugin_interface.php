<?php

interface FlexCDC_Plugin_Interface {
        static function plugin_init($instance);

        static function plugin_deinit($instance);

	static function begin_trx($uow_id, $gsn, $instance);

	static function commit_trx($uow_id, $gsn, $instance);

	static function rollback_trx($uow_id, $instance);

	static function insert($row, $db, $table, $trx_id, $gsn, $instance); 
	
	static function delete($row, $db, $table, $trx_id, $gsn, $instance); 
	
	static function update_before($row, $db, $table, $trx_id, $gsn, $instance);

	static function update_after($row, $db, $table, $trx_id, $gsn, $instance);
}

?>
