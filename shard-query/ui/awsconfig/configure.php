<?php
if(!file_exists('/var/lock/subsys/local.2')) {
echo "<h1>THIS NODE HAS NOT COMPLETED INITIALIZING.  CHECK BACK SOON<hr></h1><br>";
echo "<a href='configure.php'>retry</a>";
exit;
}
$ip = trim(`ip addr|grep eth0|grep inet|cut -d' ' -f6|cut -d'/' -f1`);
#generate a good enough random password
$pw = substr(md5(mt_rand(0,9999999999)),0,6);
$pw .= chr(mt_rand(65,90));
$pw .= chr(mt_rand(97,122));
$pw .= chr(mt_rand(65,90));
$pw = str_replace('0','J', $pw);
?>
<script>
function checkform() {
  document.getElementById('submit').disabled=true;
  if( document.getElementById('pw').value != document.getElementById('pw2').value) { 
  }else if ( 
     document.getElementById('c').value != ''&&
     document.getElementById('u').value != ''&&
     document.getElementById('pw').value != '' &&
     document.getElementById('h').value != '' &&
     document.getElementById('s').value != ''
    ) 
  {
    document.getElementById('submit').disabled=false;
  } 
  return document.getElementById('submit').disabled;
}
</script>
<?php

if(!empty($_REQUEST['type'])) {
?><img width=480 src="logo.png"><br>
<a href="configure.php"><< BACK</a><p>
<form method="post" action="configure2.php" onsubmit="javscript:checkform()">
<?php
  if($_REQUEST['type'] == 'r') {
?>
<input type="hidden" name="type" value="r">
<h2>Shard-Query: Create new database grid <hr></h2>
<table>
<tr>
<td>Repository host (save this for later):<td><input name="host" id='h' type="text" size=32 value="<?php echo $ip;?>"> <br>
</tr>
<tr><td>Schema name:<td><input onchange="javascript:checkform()" name="schema_name" id='s' type="text" value="default"><br></tr>
<!-- <tr><td>Mapper Type:<td><select name="mapper_type"><option value="directory">Directory</option><option value="hash">Hash</option></select><br></tr> -->
<tr><td>Shard column name:<td><input name="column"  id='c' value="" onchange="javascript:checkform()" type="text"><img src="greenq.png"><br></tr>
<tr><td>Shard column data type:<td><select name="column_datatype"><option value="integer">Integer</option><option value="string">String</option></select><br></tr>
<tr><td>Database type:<td><select name="store"><option value="iee">Infobright Enterprise Edition 4</option><option value="ice">Infobright Community 4</option><option value="mysql" selected=1>Oracle MySQL 5.6</option></select><br></tr>
<p>
<tr><td>Username:<td><input type="text" id='u' name="username" value="shard_query"><br></tr>
<tr><td>Password:<td><input type="text" onchange="javascript:checkform()" name="password" id=pw value="<?php echo $pw;?>"><br></tr>
<tr><td>Re-enter password:<td><input onchange="javascript:checkform()" id=pw2 type="password" name="pw" value=""><br></tr>
<!-- <tr><td>This node will also be a storage node:<td><input type="checkbox" name="is_storage_node" value="false"><br></tr> -->
</table>
<input onclick="return javascript:checkform()" id=submit type="submit" value="Go!" disabled=true><p>
You will need to enter the repository host address, the username and the password on each storage node.<br>
<b>Please record these items for future reference.</b><p>
The username and password will also be used when connecting to Shard-Query or using the web interface.<p>
<b>passwords are case sensitive</b>
<p>
<b>*</b>To use Infobright Enterprise Edition you must enter a license key on the next screen
<?php
 } else { 
?>
<h2>Shard-Query: Join an existing database grid <hr></h2>
<input type="hidden" name="type" value="s">
<table>
<tr>
<td>Repository host:<td><input name="host" type="text" size=32 value=""> <br>
<tr><td>Username:<td><input type="text" name="username" value="shard_query"><br></tr>
<tr><td>Password:<td><input type="text" name="password" value=""><br></tr>
<tr><td>Schema name:<td><input type="text" name="schema_name" value=""><br></tr>
<tr><td>Database type:<td><select name="store"><option value="iee">Infobright Enterprise Edition 4</option><option value="ice">Infobright Community 4</option><option value="mysql" selected=1>Oracle MySQL 5.6</option></select><br></tr>
</table>
<input type="submit" value="Go!">
<p><br>

<?php
 }
} else {
?>
<center><img src="logo.png"><br>
<p>
<font size="+2">
<a href="configure.php?type=r">Create a NEW Shard-Query grid (this node will be the config repository)</a><br>
OR<br>
<a href="configure.php?type=s">Add this node to an existing grid</a><br>
</font>
</center>
<?php
}
?>
