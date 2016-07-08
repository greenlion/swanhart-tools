<?php
require_once 'bin_common.php';
require_once('shard-query.php');
$mpstat = trim(file_get_contents("/tmp/mpstat.txt"));
$iostat = file_get_contents("/tmp/iostat.txt");
$top = file_get_contents("/tmp/top.txt");
$top = explode("\n", $top);
$found = 0;
$out = "";
$iostat = explode("\n", $iostat);
$mpstat = explode("\n", $mpstat);
$out = "";
foreach($mpstat as $line) {
  if(preg_match('/^Average/', $line)) break;
  $out .= $line . "\n";
}
$mpstat = trim($out);

$out = "";
$keep = 0;
foreach($iostat as $line) {
  if(preg_match('/^Device/', $line)) {
    if(!$found) {
      $keep = 0;
      $found = 1;
    } else {
      $keep =1;
      $found = 2;
    }
  }
  if($keep) {
   $out .= $line. "\n";
  }
}
$iostat = trim($out);

$keep = 0;
$out = "";
foreach($top as $line) {
  if(preg_match('/^top -/', $line)) {
    if(!$found) {
      $found = 1;
      continue;
    } else {
      $keep = 1;
    }
  }
  if($keep) {
    if($line == "") {
      break;
    }
    $out .= $line. "\n";
  }
}
$out = trim($out);
$line = "-----------------------------------------------------------------------------------------------------------------------------";
$out = "$line\nCPU statistics\n$line\n$mpstat\n\n$line\nDisk/IO statistics\n$line\n$iostat\n\n$line\nMemory/CPU summary\n$line\n$out\n";

$hostname = trim(`hostname`);
$SQ = new ShardQuery();
$out = $SQ->mapper->conn->my_escape_string($out);
$sql = "update shards set extra_info = '$out' where host='$hostname'";
echo $sql . "\n";
$SQ->mapper->conn->my_query($sql);
echo $SQ->mapper->conn->my_error() . "\n";

