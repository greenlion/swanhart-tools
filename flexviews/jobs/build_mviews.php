<?php

# build_mviews.php
# connect to the specified MySQL server and spawn a child to refresh each
# materialized view

define('SERVER',"127.0.0.1");
define('USER',"flexviews");
define('PASS',"changeme");
define('DB',"flexviews");

main();

function get_conn() {
   return(mysqli_connect(SERVER, USER, PASS, DB));
}

function log_and_die($message) {
  echo "\t$message\n";
  die($message);
}

function refresh_mview($mview_id, $method) {
  $conn = get_conn();
  if (!$conn) log_and_die(mysqli_connect_error());

  $sql = "SELECT GET_LOCK('mview_refresh_$mview_id', 1) as l";
  $stmt = mysqli_query($conn,$sql);
  if (!$stmt) log_and_die(mysqli_error($conn));
 
  $row = mysqli_fetch_assoc($stmt);

  if($row['l']==1) {  
    $sql = "CALL flexviews.refresh($mview_id, '$method', NULL)";
    $stmt = mysqli_query($conn,$sql);
    if (!$stmt) log_and_die(mysqli_error($conn));
    $sql = "DO RELEASE_LOCK('mview_refresh_$mview_id')";
    $stmt = mysqli_query($conn,$sql);
    if (!$stmt) log_and_die(mysqli_error($conn));
  } else {
    mysqli_close($conn);
    log_and_die('Could not obtain refresh lock');
  }
}

function main() {
  while(1) {
    echo "#WAKEUP\n";
    $conn = get_conn();

    if (!$conn) log_and_die(mysqli_connect_error());

    $sql = "select mview_id from flexviews.mview_compute_schedule where unix_timestamp(now()) - unix_timestamp(last_computed_at) > compute_interval_seconds";

    $stmt = mysqli_query($conn,$sql);

    if (!$stmt) log_and_die(mysqli_error($conn));

    echo "#Delta computation phase starting\n";
    while ($row = mysqli_fetch_assoc($stmt)) {
      $pid = pcntl_fork();
   
      echo "#Spawning a child for {$row['mview_id']}\n";
      if ($pid == 0) {
        refresh_mview($row['mview_id'], 'COMPUTE');
        exit;
      } else {
        $childCount++;
      }
    }
    mysqli_close($conn);
    while($childCount) {
      pcntl_wait($status);
      --$childCount;
      echo "#$childCount children remain\n";
    }

    $conn = get_conn();
    $sql = "select mview_id from flexviews.mview_apply_schedule where unix_timestamp(now()) - unix_timestamp(last_applied_at) > apply_interval_seconds";
  
    $stmt = mysqli_query($conn,$sql);

    if (!$stmt) log_and_die(mysqli_error($conn));

    echo "#Delta application phase starting\n";
    while ($row = mysqli_fetch_assoc($stmt)) {
      $pid = pcntl_fork($status);

      echo "#Spawning a child for {$row['mview_id']}\n";
      if ($pid == 0) {
        refresh_mview($row['mview_id'], 'APPLY');
        exit;
      } else {
        $childCount++;
      }
    }
    mysqli_close($conn);
    while($childCount) {
      pcntl_wait($status);
      --$childCount;
      echo "#$childCount children remain\n";
    }

    sleep(60);
  }
}
?>
