#!/usr/bin/php
<?php
set_include_path(get_include_path() . PATH_SEPARATOR . './include/');
require_once('const.php');
require_once('common.php');
require_once('shard-query.php');
require_once('DAL/simple-dal.php');

function get_shards($params) {
	$shards = array();

	if(!empty($params['ini'])) {
		$filename = $params['ini'];
	} else { 
		die("SETUP FAILED: You must specify a bootstrap config file with the --ini option\n");
	}
	if(!file_exists($filename)) {
		die("SETUP FAILED: Could not find bootstrap file specified by the --ini option($filename)\n");
	}
	$shards = @parse_ini_file($filename, true);

	if(empty($shards['config'])) die('SETUP FAILED: A [config] section is required\n');

	$default = array('host' => '127.0.0.1', 'port'=>3306, 'user'=> 'root', 'password' => '');
	if(!empty($shards['default'])) {
		$default = array_merge($default, $shards['default']);
		unset($shards['default']);
	}

	$config_default = array ('is_default_schema' => 0, 'inlist' => '*', 
				  'between' => '*', 
				  'inlist_merge_threshold' => 128, 
				  'inlist_merge_size' => 64, 
  				  'coord_engine'=>'MYISAM', 'star_schema' => false );

	$new_shards = array();

	foreach($shards as $shard_name => $shard) {	
		if(empty($shard['db'])) $shard['db'] = $shard_name;
		if(!empty($default)) { 
			foreach($default as $key => $v) {
				if(empty($shard[$key])) $shard[$key] = $v;
			}
		}
		$new_shards[$shard_name] = $shard;
	}
	$shards = $new_shards;

	$shards['config'] = array_merge( $config_default, $shards['config']);

	return $shards;
}


$params = get_commandline();
if(has_short_opt(array('h','help'), $params)) {
	shard_query_common_help();
	directory_setup_help();
	exit;
}
echo "\033[2J\033[;H";

echo "* Reading config\n";
$shards = get_shards($params);

if(empty($shards['config'])) {
	die("SETUP FAILED: You must have a [config] entry in the .ini file.\n");
}

$config = $shards['config'];
unset($shards['config']);

if(empty($config['schema_name'])) {
	echo "NOTICE: a schema_name was not specified in the [config] section of your .ini file.  Using shard_query as the default\n";
	$config['schema_name'] = 'shard_query';
}

if(empty($config['mapper'])) {
	die("SETUP FAILED: you must specify a mapper type in the [config] section.  Specify mapper=\"none\" if you do not want to use a mapper\n");
}
echo "  -- There are " . count($shards) . " shards in this configuration (and one configuration database)\n";

echo "* Verifying config database\n";
$dal = SimpleDAL::factory($config);
$dal->my_select_db($config['db']);
if($dal->my_error()) {
	die($dal->my_error());
} 

$dal->my_query('select count(*) from schemata');
if($dal->my_error()) {
	die("VERIFICATION FAILED: " . $dal->my_error());
} 

echo "* Creating new mapper.\n";
switch($config['mapper']) {
	case 'directory':
		$mapper = new DirectoryShardKeyMapper($config);	
	break;

	case 'hash':
		$mapper = new HashShardKeyMapper($config);	
	break;

	case 'none':
		$mapper = new NoneShardKeyMapper($config);	
	break;

	default:
		throw new Exception('Unsupported mapper: ' . $config_database['mapper']);
	break;
}


if(empty($config['gearman'])) {
	/* 
	Add a Shard-Query managed gearmand
	*/
	write_line("A gearman server was not seen in your config.  Enter a host to use: [127.0.0.1]");
	$gearman_host = read_line();
	if(!trim($gearman_host)) $gearman_host = '127.0.0.1';
	write_line("A gearman port was not seen in your config.  Enter a port number to use: [7001]");
	$gearman_port = read_line();
	if(!trim($gearman_port)) $gearman_port = 7001;
	$gearman = array( array('hostname' => $gearman_host, 'port' => $gearman_port, 'is_local' => true) );
} else {
	/*
	Use externally managed gearmand
	*/
	$gearman = $config['gearman'];
	unset($config['gearman']);

	$gearmen = explode(',', $gearman);
	$gearman = array();
	foreach($gearmen as $server) {
		list($host, $port) = explode(':', $server);
		$gearman[] = array( 'hostname' => $host, 'port' => $port, 'is_local' => false ); 
		
	}
}

if(empty($config['column'])) {
	die('SETUP FAILED: You must specify a column as the sharding key.\n');
} else {
	$column = $config['column'];
	unset($config['column']);
}

if(empty($config['column_datatype'])) {
	die('SETUP FAILED: You must specify a column datatype for the sharding key ub the [config] section.  Usage: column_datatype = string|integer.\n');
} else {
	$datatype = $config['column_datatype'];
	unset($config['column_datatype']);
}

echo "\033[2J\033[;H";
$mapper->conn->my_query('BEGIN');


echo "  -- Virtual schema name: " . $config['schema_name'] . "\n";
$stmt = $mapper->conn->my_query("select id from schemata where schema_name='" . $mapper->conn->my_escape_string($config['schema_name']) . "'");
if(!$stmt) die($mapper->conn->my_error() . "\n");

$skip_create = false;
$row = $mapper->conn->my_fetch_assoc($stmt);
if($row) {
	echo("NOTICE:\n");
	echo("A virtual schema named \"{$config['schema_name']}\" already exists with id: {$row['id']}\n");
	echo "Config values in the repository will be updated.  The local config data will also be updated.\n";
	$schema_id = $row['id'];
} else {
	echo "* Registering new schema\n";
	$is_default_schema = $config['is_default_schema'];
	unset($config['is_default_schema']);
	if(!$is_default_schema) {
		$sql = "select count(*) from schemata where is_default_schema = 1;";
		$stmt = $mapper->conn->my_query($sql);
		if(!$stmt) die('SETUP FAILED: could not determine if there were any default schema set: ' . $mapper->conn->my_error());
		list($cnt) = $mapper->conn->my_fetch_array();
		if($cnt == 0) {
			$is_default_schema = 1;
		}
	}
	$mapper->add_schema($config['schema_name'], $is_default_schema );

	$schema_id = $mapper->conn->my_insert_id();
} 

$mapper->conn->my_query('DELETE IGNORE cs.* from column_sequences cs join schemata on cs.schema_id = schemata.id where schema_name = "' . $config['schema_name'] . '" and sequence_type = "shard_column"')
or die($mapper->conn->my_error());
echo "* Adding or updating shard column (will not redistribute data!): {$column}\n";
$mapper->add_column($column, $config['schema_name'],$datatype,1);
	
if(!empty($config['coord_shard'])) {
	$coord_shard_name = $config['coord_shard'];
	unset($config['coord_shard']);
} else {
	$coord_shard_name = null;
}
if(isset($config['is_default_schema'])) $is_default_schema = $config['is_default_schema'];

if(empty($config['shared_path'])) {
  write_line("Please enter a shared filesystem path available on all nodes (for a default s3 bucket use s3://bucketname): [/tmp]");
  $shared_path = read_line();
  if($shared_path == "") $shared_path = "/tmp";
  $config['shared_path'] = $shared_path;
} 

/* NEW: config for S3 */
$aws_access_key = "";
$aws_secret_key = "";
$used_s3 = false;
if(strstr($config['shared_path'], "s3")) $used_s3 = true;
echo "\033[2J\033[;H";
if(empty($config['aws_access_key'])) {
  if(!$used_s3) {
    write_line("Do you want to enter an AWS access key? This will be used if you want to load from S3. (y/n) [n]");
    $answer = read_line();
  }
  if($used_s3 || substr(strtolower(trim($answer)),0,1) == 'y') {
    write_line("Enter AWS access key (you will be asked for the secret key in a moment): ");
    $config['aws_access_key']=read_line();
  }
}

if(empty($config['aws_secret_key'])) {
  if(!$used_s3) {
    write_line("Do you want to enter an AWS secret key? This will be used if you want to load from S3. (y/n) [n]");
    $answer = read_line();
  }
  if($used_s3 || substr(strtolower(trim($answer)),0,1) == 'y') {
    write_line("Enter AWS secret key: ");
    $config['aws_secret_key']=read_line();
    
  }
}


unset($config['is_default_schema']);
echo "* Creating/Updating configuration values\n";
foreach($config as $var_name => $var_value) {
	if(in_array($var_name, array( 'user', 'db', 'host', 'port', 'password', 'schema_name' ))) continue;
	$sql = "insert into schemata_config(schema_id, var_name, var_value) values($schema_id, '" . $mapper->conn->my_escape_string($var_name) . "','" . $mapper->conn->my_escape_string($var_value) . "') ON DUPLICATE KEY UPDATE var_value = '" . $mapper->conn->my_escape_string($var_value) . "'";
	if($var_name != 'mapper') unset($config[$var_name]);
	$mapper->conn->my_query($sql) or die("SETUP FAILED: " . $mapper->conn->my_error());
}	

echo "\033[2J\033[;H";
$create_user = true;
if(empty($params['user'])) {
write_line("Please enter admin credentials for a user with GRANT option (to set up each shard): ");
write_line("Please enter mysql a administrator username: [root]");
$username = read_line();
} else {
  $username=$params['user'];
}

if(trim($username) == "") $username = "root"; 
if(empty($params['password'])) {
write_line("Enter admin password: [default no password]");
$password = read_line();
} else {
  $password = $params['password'];
}

echo "* Populating/Updating shard list\n";
$mapper->conn->my_query('DELETE IGNORE shards.* from shards join schemata on shards.schema_id = schemata.id where schema_name = "' . $config['schema_name'] . '"')
or die($mapper->conn->my_error());
foreach($shards as $shard_name => $shard) {
	if(!isset($coord_shard_name) || (isset($coord_shard_name) && $shard_name == $coord_shard_name)) $coord_shard = 1; else $coord_shard = 0; 
	$mapper->add_shard($shard_name, $shard, $coord_shard, $accepts_new_rows = true, null, $config['schema_name']) ;
	if($create_user) {
		$shard_admin = $shard;
		$shard_admin['user'] = $username;
		$shard_admin['password'] = $password;
		$dal = SimpleDAL::factory($shard_admin);
		$sql = "CREATE USER '{$shard['user']}'@'%' IDENTIFIED BY '" . $dal->my_escape_string($shard['password']) . "'";
		$dal->my_query($sql);

		$sql = 'CREATE DATABASE IF NOT EXISTS ' . $shard['db'];
		$dal->my_query($sql);
		if($dal->my_error()) {
			die("SETUP FAILED: Could not create database on $shard_name\n" . $dal->my_error() . "\n");
		}

		$sql = "GRANT ALL ON " . $shard['db'] . ".* TO '" . $shard['user'] . "'@'%'"; 
		$dal->my_query($sql);
		if($dal->my_error()) {
			die("SETUP FAILED: Could not grant permission on $shard_name\n" . $dal->my_error() . "\n");
		}
		$dal->my_close();
		unset($dal);
	} 
	$dal = SimpleDAL::factory($shard);
	$dal->my_select_db($shard['db']);
	if($dal->my_error()) {
		die("SETUP FAILED: Could not verify write access to $shard_name\n" . $dal->my_error() . "\n");
	}
	$sql = "CREATE TABLE IF NOT EXISTS shard_write_ok(c1 int);";
	$dal->my_query($sql);
	if($dal->my_error()) {
		die("SETUP FAILED: Could not verify write access to $shard_name\n" . $dal->my_error() . "\n");
	}
	$dal->my_query('DROP TABLE IF EXISTS shard_write_ok;');
	if($dal->my_error()) {
		die("SETUP FAILED: Could not verify write access to $shard_name\n" . $dal->my_error() . "\n");
	}

}			


echo "* Populating gearman servers\n";
$mapper->conn->my_query("delete IGNORE from gearman_job_servers where schema_id = $schema_id");

foreach($gearman as $server) {
	$sql = "INSERT INTO gearman_job_servers (schema_id, hostname, port, local) VALUES(";
	$sql .= "$schema_id, '{$server['hostname']}',{$server['port']}, '{$server['is_local']}')"; 

	$mapper->conn->my_query($sql) or die("SETUP FAILED" . $mapper->conn->my_error() . "\n");

}

// which OS is used
$uname = `uname`;

if(stripos($uname, 'freebsd') !== false) {
	$worker_count = `sysctl -a | grep 'hw.ncpu' | awk '{print $2}'` ;
} else {
	$worker_count = `cat /proc/cpuinfo | grep processor | wc -l` ;
}

echo "* Populating gearman functions\n";
$sql = "INSERT INTO gearman_functions(schema_id,function_name_id, worker_count, enabled)
        select $schema_id,id, $worker_count, 1
	from gearman_function_names
        ON DUPLICATE KEY UPDATE worker_count = $worker_count
;";
$mapper->conn->my_query($sql) or die("SETUP FAILED: " . $mapper->conn->my_error() . "\n");

$config['rdbms_type'] = SQ_RDBMS_PDO_MYSQL;
$config['mapper_type'] = $config['mapper'];
$config['default_virtual_schema'] = $config['schema_name'];

unset($config['mapper']);
#unset($config['mapper_type']);
unset($config['schema_name']);


//create config.in only when default schema
if($is_default_schema == 1 || !file_exists('include/config.inc')){
	
	echo "* Persisting config database pointer to include/\n";
	if(file_put_contents('include/config.inc', serialize($config)) === false){
		echo "Failure Saving config.inc!";
		return;
	}
}

$mapper->conn->my_query('COMMIT');
echo ":: DONE\n";

