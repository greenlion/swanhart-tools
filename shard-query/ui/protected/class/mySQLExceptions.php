<?php

class mySQLExceptions{

	static $regs = array(
						'Integrity constraint violation' =>'/Integrity constraint violation: \d+ Duplicate entry \'(?P<value1>.*?)\' for key \'(?P<field1>.*?)\'/s',
						'Foreign key constraint violation' =>'/foreign key constraint fails \(`(?P<table1>.*?)`.`(?P<table2>.*?)`, CONSTRAINT `.*?` FOREIGN KEY \(`(?P<field1>.*?)`\) REFERENCES `.*?` \(`(?P<field2>.*?)`\)/s'
					);

	public static function Parse($msg){

		$error = array();

		foreach(self::$regs as $i=>$value){
			if(preg_match($value, $msg, $matches)){
					$error['type'] = $i;
					$error['tables'] = (isset($matches["table1"]) ? $matches["table1"] : '') . (isset($matches["table2"]) ? "," . $matches["table2"] : '');
					$error['fields'] = (isset($matches["field1"]) ? $matches["field1"] : '') . (isset($matches["field2"]) ? "," . $matches["field2"] : '');
					$error['values'] = (isset($matches["value1"]) ? $matches["value1"] : '') . (isset($matches["value2"]) ? "," . $matches["value2"] : '');
			}
		}
		return $error;
	}
}

?>