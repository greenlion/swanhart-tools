<?php
set_include_path(get_include_path() . PATH_SEPARATOR . '../../include/');
require_once('const.php');
require_once('common.php');
require_once('shard-query.php');
require_once('DAL/simple-dal.php');

if(empty($_REQUEST)) {
echo "badreq";
exit;
}
/*
Array
(
    [u] => shard_query
    [p] => eebd527782291eb8b096d6f1060a1c6e
    [s] => c89e1a33c96b09ad173e52816036e90d
    [sn] => default
    [h] => 10.215.90.224
)
*/

#this node is not configured so it can't register other nodes!
if(!file_exists('../protected/configured')) {
echo "noconfig";
exit;
}

#check password to ensure it matches
$secret = trim(file_get_contents('../protected/configured'));
$md5secret = md5($secret);
$salt = $_REQUEST['s'];
$hashed = md5($secret . $salt);
$pass = $_REQUEST['p'];
$user = $_REQUEST['u'];
if(md5($md5secret . $salt) != $pass) {
echo "badauth";
exit;
}

$schema = $_REQUEST['sn'];
$host = $_REQUEST['h'];

switch(strtolower($_REQUEST['store'])) {
case 'iee':
	$port = array(0=>5029);
break;
case 'ice':
	$port = array(0=>5029);
break;
case 'mysql':
default:
	$port = array(0=>3306);  

}


$mapper = new DirectoryShardKeyMapper(array('host'=>'127.0.0.1','user'=>'root','db'=>'sq','password'=>''));
foreach($port as $the_port) {
	$shard = array('host'=>$host, 'user'=>$user, 'password'=>$secret, 'db'=>$schema, 'port'=>$the_port);
	$mapper->add_shard($host, $shard, true, true, null, $schema) ;
}
echo "registered";
