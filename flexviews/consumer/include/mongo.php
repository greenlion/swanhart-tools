<?php
require_once('plugin_interface.php');

//this pulls in the mongodb client
require_once('vendor/autoload.php');

class mongo implements FlexCDC_Plugin_Interface {
  static $buffer = array(); // holds records per-schame and per table inside a transaction
  static $filter = array(); // whitelist of tables to replicate
  static $client = null; // connection to the mongod instance
  static $stream;

  static $user, $password, $host, $db;

  // JSON data must be utf8 encoded 
  static function to_utf8($data) { 
    if (is_string($data)) return utf8_encode($data); 
    if (!is_array($data)) return $data; 
    $ret = array(); 
    foreach($data as $i=>$d) $ret[$i] = to_utf8($d); 
    return $ret; 
  } 

  static function plugin_init($instance, $IAM) {
    // The class name will also be the [section] name in the .ini file
    echo "$IAM plugin initialization starting";

    // read the user, password, database, and host keys from the .ini file
    if(isset($instance->settings[$IAM])) {

      if(!isset($instance->settings[$IAM]['user'])) {
        $IAM::$user = "root";
      } else {
        $IAM::$user = $instance->settings[$IAM]['user'];
      }

      if(!isset($instance->settings[$IAM]['password'])) {
        $IAM::$password = "secret";
      } else {
        $IAM::$password = $instance->settings[$IAM]['password'];
      }

      if(!isset($instance->settings[$IAM]['host'])) {
        $IAM::$host = "127.0.0.1";
      } else {
        $IAM::$host = $instance->settings[$IAM]['host'];
      }

      if(!isset($instance->settings[$IAM]['database'])) {
        $IAM::$db = "admin";
      } else {
        $IAM::$db = $instance->settings[$IAM]['database'];
      }

      if(!isset($instance->settings[$IAM]['port'])) {
        $IAM::$port = 27017;
      } else {
        $IAM::$port = $instance->settings[$IAM]['port'];
      }

      if(isset($instance->settings[$IAM]['filter'])) {
        $tmp = explode(',',$instance->settings['$IAM']['filter']);
        foreach($tmp as $tbl) {
          $t2 = explode('.', $tbl);
          if(count($t2) != 2) {
            echo "$IAM - Invalid schema filter: filter must be schema.table\n";
            exit(4);
          }
          $IAM::$filter[] = $t2;
        } 
      }
    }
    
    $client = $IAM::connect($IAM);

    if(!empty($IAM::$filter)) {
      echo "$IAM - Filters:\n" . print_r($IAM::$filter,true);
    }

    echo "$IAM plugin initialized.";

  }

  // get the mongodb client connection
  static function connect($IAM) {

    // make a new connection?
    if($IAM::$client == null) {

      if(!strstr(":", $host)) {
        $host = $IAM::host . ":" . $IAM::port;
      }

      $user = $IAM::user;
      $pass = $IAM::password;
      $host = $IAM::host;
      $port = $IAM::port;
      $db   = $IAM::db;

      // will throw exception if it can't connect
      $IAM::$client = new MongoDB\Client("mongodb://$user:$pass@$host:$port/$db");
        
    } else {

      // otherwise, make sure the connection is alive 
      $client = $IAM::$client;
      $cursor = $client->command(array('ping' => 1));
      if($cursor->toArray()[0] !== 1.0) {
        throw new Exception("mongodb connection ping failure");
      }

    }

    $IAM::$stream = array();
    $IAM::$seq = 0;
    return $client;
  }

  /* determine if the table is on the table filter blacklist */
  static function skip_table($schema, $table, $IAM) {
    foreach($IAM::$filter as $filt) {
      if($filt[0] == $schema && $filt[1] == $table) {
        echo "{$IAM} SKIPPING $schema.$table\n";
        return true;
      }
    }
    return false;
  }

  static function plugin_deinit($instance, $IAM) {
    echo "{$IAM} SHUTDOWN\n";
  }

  /* This function just resets the buffer of changes */
  static function begin_trx($uow_id, $gsn,$instance, $IAM) { 
    $IAM::$stream = array();
    $IAM::$seq = 0;
  }

  /* This function will replay all of the row changes in the MySQL
     transactionally serially against the MongoDB instances.  

     Each database is translated to a MongoDB database and
     each table is translated to a MongoDB collection.

     Any errors will throw an exception.  If an error happens
     in the middle of the transaction, you could end up an
     inconsistent replica. You should avoid transactions that
     change multiple rows if possible.
  */
  static function commit_trx($uow_id, $gsn,$instance, $IAM) {
    $client = $IAM::connect();
    // write out all the documents in the transaction
    if(empty($IAM::$stream)) return;
    $client = $IAM::connect();

    foreach($stream as $e) {
      if($e['db'] == "admin") continue; 

      $collection = $client->$e['db']->$e['table'];
      try { 
        switch($e['type']) {

          case 'i':
            $res = $collection->insertOne($e->row);    
            $action = "insert";
          break;

          case 'd':
            $res = $collection->deleteOne($e->row);
            $action = "delete";
          break;

          case 'u':
            $res = $collection->updateOne($e->before,array('$set' => $e->after));
            $action = "update";
          break;
        }
      } catch(Exception $ex) {
        echo "$IAM An exception occured during a MongoDB $action operation: [\n" . trim(print_r($ex, true)) . "]\n";
        exit(9);
      }
    }

    $IAM::$stream = array();
  }

  /* mongo can't rollback so do nothing */
  static function rollback_trx($uow_id=false, $instance, $IAM) {
  }

  /* The row change functions just bundle up the changes into a stream.
     The changes are applied in the commit function.
  */
  static function insert($row, $db, $table, $trx_id, $gsn,$instance, $IAM) {
    if($IAM::skip_table($db, $table, $IAM)) return;
    $IAM::$stream[$IAM::$seq++]=array('db'=>$db,'table'=>$table,'type'=>'i','row'=>json_encode($IAM::to_utf8($row)));
  }
  
  static function delete($row, $db, $table, $trx_id, $gsn,$instance, $IAM) {
    if($IAM::skip_table($db, $table)) return;
    $IAM::$stream[$IAM::$seq++]=array('db'=>$db,'table'=>$table,'type'=>'d','row'=>json_encode($IAM::to_utf8($row)));
  }
  
  static function update_before($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
    if($IAM::skip_table($db, $table)) return;
    $IAM::$stream[$IAM::$seq++]=array('db'=>$db,'table'=>$table,'type'=>'u','before'=>json_encode($IAM::to_utf8($row)));
  }

  static function update_after($row, $db, $table, $trx_id, $gsn,$instance,$IAM) {
    if($IAM::skip_table($db, $table)) return;
    $IAM::$stream[(count($IAM::stream)-1)]['after']=json_encode($IAM::to_utf8($row));
  }

}
