<?php
set_include_path(get_include_path() . PATH_SEPARATOR . './include/');
require_once('common.php');
// Author: Alex Hurd
// Author: Justin Swanhart
// This script will:
// -create a mysql user 'shard-query' if it doesnt exist. 
// -create the following databases if they dont exist : 
// 		-shard-query


//only run via CLI
if(!defined('STDIN') ) exit;

#clear the screen
echo "\033[2J\033[;H";
echo "***********************************************************\n";
echo "* Shard-Query                                             *\n";
echo "* Configuration Repository installation.                  *\n";
echo "***********************************************************\n\n";

echo "Config Repository:\n";
echo "=============================\n";
echo "Shard-Query stores the sharding configuration in a database.\n";
echo "This script initialises the configuration database.\n";
echo "YOU SHOULD ONLY RUN THIS SCRIPT ONCE PER CLUSTER!\n";
echo "\n";

#give user a chance to opt out
echo "Do you want to continue with this installer? [y] >";
$answer = read_line();
if($answer=="") $answer="y";
if(substr(strtolower($answer),0,1) != "y") exit;
echo "\033[2J\033[;H";

echo "Config Repository:\n";
echo "=============================\n";
echo "The following username, password and database will be used to\n";
echo "access the configuration repository.\n\n";
echo "NOTE: If using the local server as the repository, then for best results use 127.0.0.1 instead of 'localhost'.\n";

//get auth
echo("Enter shard query repository hostname: [127.0.0.1] >");
$sq_host = read_line();
if(trim($sq_host) == "") { $sq_host = "127.0.0.1"; }
echo("Enter shard query repository port: [3306] >");
$sq_port = read_line();
if(trim($sq_port) == "") { $sq_port = "3306"; }
echo("Enter shard query repository database/schema name: [shard_query] >");
$sq_db = read_line();
if(trim($sq_db) == "") { $sq_db = "shard_query"; }
echo("Please username (a non-root user): [shard_query] >");
$sq_username = read_line();
if(trim($sq_username) == "") { $sq_username = "shard_query"; }
echo("Enter password for the non-root user: [shard_query] >");
$sq_password = read_line();
if(trim($sq_password) == "") { $sq_password = "shard_query"; }
echo "\n";
echo "The installer must create the user specified above.\n";
echo "====================================================\n";
echo "Please enter a MySQL administrator (root, or acount w/SUPER priv) username.\n";
echo "This account must have GRANT OPTION: [root] >";
$username = read_line();
if(trim($username) == "") { $username = "root"; }
echo("Enter admin password: [default no password] >");
$password = read_line();


//connect [PDO included in doo framework]
$dbh = null;
try{
	$dbh = new PDO("mysql:host=$sq_host;port=$sq_port;", $username, $password);
} catch (PDOException $e) {
	write_line('Connection failed: ' . $e->getMessage());
	return;
}

//Check for shard_query account
$sql  = "SELECT 
		COUNT(*) as count
	FROM
		mysql.user
	WHERE
		user = '$sq_username' ";

$stmt = $dbh->query($sql);

$res = $stmt->fetch(PDO::FETCH_ASSOC);
$create_user = true;
if((int)$res['count'] != 0){
	write_line("A $sq_username user was already found.  Do you want to drop it and recreate it?\nHIT CTRL-C TO ABORT NOW WITHOUT MAKING ANY CHANGES, otherwise (y/n): [n]");
	$drop_user = read_line();
	if(strtolower($drop_user[0]) == 'y') {
		write_line("Dropping current user!");
		$sql = "DROP USER $sq_username@'%'";
		$dbh->query($sql);
		$sql = "DROP USER $sq_username@'localhost'";
		$dbh->query($sql);
		write_line("Done!");
		$create_user = true;
	} else {
		$create_user = false;
	}
}
if($create_user===true) {
	//add acount
	write_line("Creating user...");
	$sql = "CREATE USER '$sq_username'@'%' IDENTIFIED BY '$sq_password'";
	$dbh->query($sql);
	$sql = "CREATE USER '$sq_username'@'localhost' IDENTIFIED BY '$sq_password'";
	$dbh->query($sql);
	$sql = "GRANT ALL ON `shard_query`.* TO 'shard_query'@'%';";
	$dbh->query($sql);		
	$sql = "GRANT ALL ON `shard_query`.* TO 'shard_query'@'localhost';";
	$dbh->query($sql);		
}

//Create database + tables
write_line("Installing 'shard_query' database");
$cmd = "mysql -h$sq_host -P$sq_port -u {$username} ";
if(trim($password) != "") {
	$cmd .= "-p$password ";
}

$stmt = $dbh->query("CREATE DATABASE IF NOT EXISTS $sq_db") or die("Could not create database (wrong credentials?)!");
$cmd .= "-D $sq_db < shard_query.sql";
$result = exec($cmd);
//print if we have error?
if(!empty($result)){
	echo "ERROR:\n";
	write_line($result);
	exit(1);
}	
//done
write_line("Done!");

// Exit correctly
exit(0);

?>
