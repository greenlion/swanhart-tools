<?php
$ip = trim(`ip addr|grep eth0|grep inet|cut -d' ' -f6|cut -d'/' -f1`);
if(empty($_REQUEST)) exit;

function my_query($sql, $conn) {
  #echo $sql . "<br>\n";
  $r = mysql_query($sql, $conn) or die("Failed to execute query.  Node setup failed!\n" . mysql_error($conn));
}

function fwl($fh, $line) {
	fwrite($fh, $line . "\n");
}

#set up the mysql permissions for shard-query
$conn = mysql_connect('127.0.0.1:3306','root', '') or die(mysql_error());

#this query should return empty set, if not then this node already has data
$stmt = mysql_query("select count(*) from information_schema.schemata where schema_name not in ('information_schema', 'performance_schema','test','mysql') having count(*) > 0;");
$cnt = 0;
while($row = mysql_fetch_assoc($stmt)) ++$cnt;
if($cnt>0 && empty($_REQUEST['force'])) {
  echo "<b>ERROR: This node appears to be set up already!<br>";
  $url = "";
  foreach($_REQUEST as $k => $v) {
    if($url) $url .= "&"; else $url .= "?";
    $url .= $k . "=" . urlencode($v);
  }
  $url = "configure2.php$url&force=1";
  echo "<A href='$url'>Click here to FORCE install this node.</a><p><b>THIS IS DESTRUCTIVE AND WILL RESULT IN THE LOSS OF ANY DATA ON THIS NODE.</b>";
  exit;
}

$ports = array(3306, 5029);
foreach($ports as $port) {
	$conn = mysql_connect('127.0.0.1:'. $port,'root', '') or die(mysql_error());
	$stmt = my_query("grant all on *.* to '" . 
        	         mysql_real_escape_string($_REQUEST['username']) . "'@'%'" . 
                	 " identified by '" . mysql_real_escape_string($_REQUEST['password']) . "' with grant option;",$conn);
	$stmt = my_query('drop database if exists `' . mysql_real_escape_string($_REQUEST['schema_name']) . '`;',$conn);
	$stmt = my_query('create database `' . mysql_real_escape_string($_REQUEST['schema_name']) . '`;',$conn);
	if($_REQUEST['type'] == 'r') $stmt = my_query('drop database if exists sq', $conn);
	$stmt = my_query('create database if not exists sq', $conn);
}

if($_REQUEST['type'] == 'r') {
	$fh = fopen('bootstrap.ini', 'w');
	fwl($fh, '[default]');
	fwl($fh, 'user="' . $_REQUEST['username'] . '"');
	fwl($fh, 'password="' . $_REQUEST['password'] . '"');
	fwl($fh, '[config]');
	fwl($fh, 'db="sq"');
	fwl($fh, 'schema_name="' . $_REQUEST['schema_name'] . '"');
	fwl($fh, 'host="' . $_REQUEST['host'] . '"');
	fwl($fh, 'mapper="directory"');
	fwl($fh, 'column="' . $_REQUEST['column'] . '"');
	fwl($fh, 'column_datatype="' . $_REQUEST['column_datatype'] . '"');
	fwl($fh, 'is_default_schema=1');
	fwl($fh, 'between="nocolumn"');
	fwl($fh, 'inlist="nocolumn"');
	fwl($fh, 'star_schema=false');
	fwl($fh, 'coord_engine=myisam');
	fwl($fh, 'gearman="' . $_REQUEST['host'] . ':7001"');
	switch($_REQUEST['store']) {
		case 'mysql':
			fwl($fh, '[' . $_REQUEST['host'] . ']');
			fwl($fh, 'db="' . $_REQUEST['schema_name'] . '"');
			fwl($fh, 'host="' . $_REQUEST['host'] . '"');
			fwl($fh, 'port=3306');
			break;
		case 'iee':
			fwl($fh, '[' . $_REQUEST['host'] . ']');
			fwl($fh, 'db="' . $_REQUEST['schema_name'] . '"');
			fwl($fh, 'host="' . $_REQUEST['host'] . '"');
			fwl($fh, 'port=5029');
			break;
		case 'ice':
			fwl($fh, '[' . $_REQUEST['host'] . ']');
			fwl($fh, 'db="' . $_REQUEST['schema_name'] . '"');
			fwl($fh, 'host="' . $_REQUEST['host'] . '"');
			fwl($fh, 'port=5029');/*
			fwl($fh, '[' . $_REQUEST['host'] . ']');
			fwl($fh, 'db="' . $_REQUEST['schema_name'] . '"');
			fwl($fh, 'host="' . $_REQUEST['host'] . '"');
			fwl($fh, 'port=5030');*/
			break;
	}
	
	$cmd = "mysql -uroot sq < ../../setup/shard_query.sql";
	$result = exec($cmd);
	require_once('web_bootstrap.php');
} else {
	echo "<img width=480 src=\"logo.png\"><hr><br>";
	$salt = md5(mt_rand(1,999999999));
	$hash = md5(md5($_REQUEST['password']) . $salt);

	$url = "http://" . $_REQUEST['host'] . "/shard-query/regnode.php?";
	$url .="u=" . $_REQUEST['username'];
	$url .="&p=" . $hash;
	$url .="&s=" . $salt;
	$url .="&sn=" . $_REQUEST['schema_name'];
	$url .="&h=" . $ip;

	echo "Registering with repository<br>\n";
	$resp = trim(file_get_contents($url));
	if($resp !== "registered") {
		echo "ERROR DURING NODE REGISTRATION: $resp<br>\n";
		exit;
	}

	$config = array( 
		'rdbms-type' => 'pdo-mysql',
		'db' => 'sq',
		'host' => $_REQUEST['host'],
		'port' => 3306,
		'user' => $_REQUEST['username'],
		'password' => $_REQUEST['password'],
		'mapper_type' => 'directory',
		'default_virtual_schema' => $_REQUEST['schema_name']
	);
	echo "Persisting config database pointer<br>\n";
	if(file_put_contents('../../include/config.inc', serialize($config)) === false){
        	echo "Failure Saving config!";
	}
	echo "<p><b>Completed!\n";
}
