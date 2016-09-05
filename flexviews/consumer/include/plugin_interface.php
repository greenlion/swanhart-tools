<?php

interface FlexCDC_Plugin_Interface {
  static function plugin_init($instance, $IAM);

  static function plugin_deinit($instance, $IAM); 

	static function begin_trx($uow_id, $gsn, $instance, $IAM );

	static function commit_trx($uow_id, $gsn, $instance, $IAM);

	static function rollback_trx($uow_id, $instance, $IAM);

	static function insert($row, $db, $table, $trx_id, $gsn, $instance, $IAM); 
	
	static function delete($row, $db, $table, $trx_id, $gsn, $instance, $IAM); 
	
	static function update_before($row, $db, $table, $trx_id, $gsn, $instance, $IAM);

	static function update_after($row, $db, $table, $trx_id, $gsn, $instance, $IAM);
}

?>
