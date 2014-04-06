<?php
echo "Enter username (user):";
$user = fgets(STDIN);
echo "Enter password (mpp):";
$pass = fgets(STDIN);

if($user=="") $user="user";
if($pass=="") $pass="mpp";

$cred = array($user => $pass);
file_put_contents("protected/config/user.inc", serialize($cred));
