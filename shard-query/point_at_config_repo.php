#!/usr/bin/php
<?php
set_include_path(get_include_path() . PATH_SEPARATOR . './include/');
require_once('const.php');
require_once('common.php');
require_once('shard-query.php');
require_once('DAL/simple-dal.php');
#clear the screen
echo "\033[2J\033[;H";
echo "***********************************************************\n";
echo "* Shard-Query                                             *\n";
echo "* Configuration Repository Information utility.           *\n";
echo "***********************************************************\n\n";

echo "Config Repository:\n";
echo "=============================\n";
echo "Shard-Query stores the sharding configuration in a database.\n";
echo "This script configures this installation of Shard-Query to use an existing configuration repo.\n";
echo "\n";


echo("Enter the hostname of the configuration repo [127.0.0.1] >");
$repo_host = read_line();
if(!trim($repo_host)) $repo_host = '127.0.0.1';

echo("Enter the port of the configuration repo [3306] >");
$repo_port = read_line();
if(!trim($repo_port)) $repo_port = 3306;

echo("Enter the database/schema name of the configuration repo [shard_query] >");
$repo_db = read_line();
if(!trim($repo_db)) $repo_db = "shard_query";

echo("Enter the username to connect to the repo as [shard_query] >");
$repo_user = read_line();
if(!trim($repo_user)) $repo_user = "shard_query";

echo("Enter the password [shard_query] >");
$repo_pass = read_line();
if(!trim($repo_pass)) $repo_pass = "shard_query";

echo("Enter the mapper type (hash|directory|none) [directory] >");
$mapper_type = read_line();
if(!trim($mapper_type)) $mapper_type = "directory";

echo("Enter the default virtual schema [default] >");
$virtual_schema= read_line();
if(!trim($virtual_schema)) $virtual_schema = "default";

$config = array( 
 'rdbms-type' => 'pdo-mysql',
 'db' => $repo_db,
 'host' => $repo_host,
 'port' => $repo_port,
 'user' => $repo_user,
 'password' => $repo_pass,
 'mapper_type' => $mapper_type,
 'default_virtual_schema' => $virtual_schema
);
echo "\033[2J\033[;H";
echo "* Persisting config database pointer to include/\n";
if(file_exists('include/config.inc')) {
	echo "WARNING: An existing configuration exists.  Do you want to overwrite the existing configuration? [y] >";
	$yesno = read_line();
	if(substr(strtolower($yesno),0,1) != "y") { echo "\nABORTED!\n"; exit; }
}
if(file_put_contents('include/config.inc', serialize($config)) === false){
	echo "Failure Saving config.inc!";
}
echo "Completed!\n";
