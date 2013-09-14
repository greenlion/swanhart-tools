<?php

//$dbconfig[ Environment or connection name] = array(Host, Database, User, Password, DB Driver, Make Persistent Connection?);
/**
 * Database settings are case sensitive.
 * To set collation and charset of the db connection, use the key 'collate' and 'charset'
 * array('localhost', 'database', 'root', '1234', 'mysql', true, 'collate'=>'utf8_unicode_ci', 'charset'=>'utf8'); 
 */
require_once('const.php');
require_once('shard-query-config.php');

if($config_database['rdbms_type'] == 'mysql' || $config_database['rdbms_type'] == 'pdo-mysql' )
	$dbconfig['dev'] = array($config_database['host'], $config_database['db'], $config_database['user'], $config_database['password'], 'mysql', true);

if($config_database['rdbms_type'] == 'pdo-pgsql')
	$dbconfig['dev'] = array($config_database['host'], $config_database['db'], $config_database['user'], $config_database['password'], 'pgsql', true);


$dbconfig['prod'] = $dbconfig['dev'];
?>
