<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 */
/* $Id: */
/*
Copyright (c) 2010, Justin Swanhart
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the <organization> nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
define('SQ_RDBMS_MYSQL','mysql');
define('SQ_RDBMS_PDO_MYSQL','pdo-mysql');
define('SQ_RDBMS_PDO_PGSQL','pdo-pgsql');
namespace DAL;

interface SimpleDALinterface {

  function __construct($server = null, $connect = true, $force_new = true) ;
  function my_insert_id($conn = null);
  function &my_connect($server = null, $force_new = true) ;
  function my_affected_rows($stmt = null) ;
  function &my_query($sql = '', $conn = null) ;
  function &my_unbuffered_query ( $query, $conn = null ) ;
  function &my_fetch_assoc($stmt = null) ;
  function &my_fetch_array($stmt = null) ;
  function &my_errno(&$conn = null) ;
  function &my_error(&$conn = null) ;
  function my_select_db($db, $conn=null) ;
  function my_field_type ($result=null ,$field_offset = 0) ;
  function my_free_result($stmt = null) ;
  function my_close(&$conn = null) ;
  function my_begin($conn = null) ;
  function my_autocommit($state = true, $conn = null) ;
  function my_commit($conn = null) ;
  function my_rollback($conn = null) ;
  function my_real_escape_string($string, $conn = null) ;
  function my_escape_string($string, $conn = null) ;

}

class SimpleDAL {
  public static function factory($server = null, $connect = true, $force_new = true) {
    if(!isset($server)) throw new Exception('Server required');

    if(empty($server['rdbms_type'])) $server['rdbms_type'] = 'mysql';

    switch($server['rdbms_type']) {
      case SQ_RDBMS_MYSQL:
      case SQ_RDBMS_PDO_MYSQL:
        $server['dsn-prefix'] = 'mysql';
        return new PDODAL($server, $connect, $force_new);
        break;
      case SQ_RDBMS_PDO_PGSQL:
        $server['dsn-prefix'] = 'pgsql';
        return new PDODAL($server, $connect, $force_new);
        break;
      default:
        /* this probably won't work, but it is worth trying */
        $server['dsn-prefix'] = $server['rdbms_type'];
        return new PDODAL($server, $connect, $force_new);
    
    }
  }
}

class PDODAL implements SimpleDALinterface {
  private $conn = null;
  private $stmt = null;
  private $server = null;
  function __construct($server = null, $connect = true, $force_new = false) {
    if ($connect)
      $this->my_connect($server, $connect, $force_new);
  }
  
  function my_insert_id($conn = null) {
    if (isset($conn))
        return $con->lastInsertId();
    
    if ($this->conn)
        return $this->conn->lastInsertId();

    return false;
  }
  
  function &my_connect($server = null, $force_new = false) {
    if (!$server)
        return false;
    
    if (empty($server['user']))
        $server['user'] = 'root';
    if (empty($server['password']))
        $server['password'] = '';
     if(!isset($server['dsn-prefix'])) $server['dsn-prefix'] = isset($server['rdbms_type']) ? $server['rdbms_type'] : $server['shard_rdbms'];
    if (!isset($server['db']))
        $dsn = $server['dsn-prefix'] . ':host=' . $server['host'] . (!empty($server['port']) ? ';port=' . $server['port'] : '');
    else
        $dsn = $server['dsn-prefix'] . ':host=' . $server['host'] . (!empty($server['port']) ? ';port=' . $server['port'] : '') . ';dbname=' . $server['db'];
   
    $server['dsn-prefix'] = $dsn; 

    try {
        $conn = new PDO($dsn, $server['user'], $server['password'], array(PDO::MYSQL_ATTR_LOCAL_INFILE => 1));
    }
    catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        return false;
    }
    
    $this->conn   = $conn;
    $this->server = $server;
    $this->conn_config = $server;
    return $conn;
  }
  
  function my_affected_rows($stmt = null) {
    if (isset($stmt)) {
      return $stmt->rowCount();
    } elseif ($this->stmt) {
      return $this->stmt->rowCount();
    }
    return false;
  }

  function &my_query($sql = '', $conn = null) {
    if(SQ_DEBUG) echo "DRV_PDO:$sql\n";
    if (isset($conn)) {
      if(strpos($conn->server['dsn-prefix'],'mysql') !== false) {
        $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
      }
      $this->stmt = $conn->query($sql);
    } elseif ($this->conn) {
      if(strpos($conn->server['dsn-prefix'],'mysql') !== false) {
        $this->conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
      }
        $this->stmt = $this->conn->query($sql);
    } else {
        return false;
    }
    return $this->stmt;
  }

  function my_ping($conn = null) {
    if(!$conn) $conn = $this->conn;
    try {
      $this->my_query('SELECT 1');
    } catch (PDOException $e) {
      $this->my_connect($this->conn_config);            // Don't catch exception here, so that re-connect fail will throw exception
    } 
    return true;
  }

  function &my_unbuffered_query($query, $conn = null) {
    if(SQ_DEBUG) echo "DRV_PDO_UB:$query\n";
    if (isset($conn)) {
      if(strpos($conn->server['dsn-prefix'],'mysql') !== false) {
        $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
      }
      $res = $this->my_query($query, $conn);
    } elseif ($this->conn) {
      $this->conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
      $res = $this->my_query($query);
    }
    return $res;
  }

  function &my_fetch_assoc($stmt = null) {
    if ($stmt === null) {
      $stmt = $this->stmt;
    }
    if(!$stmt) return false;
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return $r;
  }

  function &my_fetch_array($stmt = null, $mode = null) {
    if (!$stmt)
      $stmt = $this->stmt;
    $r = $stmt->fetch(PDO::FETCH_NUM);
    return $r;
  }

  function &my_errno(&$conn = null) {
    if (!isset($conn)) {
      $conn = $this->conn;
    }
    $ei = $conn->errorInfo();
    if(!$ei) return false;
    $r = sprintf('%s',$ei[1]);
    return $r;
  }

  function &my_error(&$conn = null) {  
    if (!isset($conn)) {
      $conn = $this->conn;
    }
    $ei = $conn->errorInfo();
    if(!$ei) return false;
    $r = sprintf('%s%s',$ei[1],$ei[2]);
    return $r;
  }

  function my_select_db($db, $conn = null) {
    $server = $this->server;
      
    //reconnect with dbname=
    try {
      $conn = new PDO($server['dsn-prefix'] . ':host=' . $server['host'] . (!empty($server['port']) ? ';port=' . $server['port'] : '') . ';dbname=' . $db, $server['user'], $server['password'],array(PDO::MYSQL_ATTR_LOCAL_INFILE => 1));
    }
    catch (PDOException $e) {
      echo 'Connection failed: ' . $e->getMessage();
      return false;
    }
      
    $this->conn = $conn;
    return true;
  }

  function my_field_type($result = null, $field_offset = 0) {
    if (!isset($result))
      $result = $this->stmt;
    return $result->getColumnMeta($field_offset);
  }

  function my_free_result($stmt = null) {
    if ($stmt)
      return $stmt->closeCursor();
    if ($this->stmt)
      return $this->stmt->closeCursor();
    return true;
  }

  function my_close(&$conn = null) {
    if (!isset($conn)) {
      $conn = $this->conn;
    }
    if(!$conn) return false;

    $stmt = $this->my_query('select connection_id() pid',$conn);
    $row = $this->my_fetch_assoc($stmt);
    $pid = $row['pid'];
    $stmt = $this->my_query('kill ' . $pid);
    return true;
  }

  function my_begin($conn = null) {
    if (!$conn) {
      return $this->conn->beginTransaction();
    } else {
      return $conn->beginTransaction();
    }
  }

  function my_autocommit($state = true, $conn = null) {
      //autocommit unless using pdo transactions      
  }

  function my_commit($conn = null) {
    if (!$conn) {
      return $this->conn->commit();
    } else {
      return $conn->commit();
    }
  }

  function my_rollback($conn = null) {
    if (!$conn) {
      return $this->conn->rollBack();
    } else {
      return $conn->rollBack();
    }
  }

  function my_real_escape_string($string, $conn = null) {
    if ($conn != null) {
      $pdo_quote = $conn->quote($string);
      //strip leanding and trailing '
      return substr($pdo_quote, 1, strlen($pdo_quote) - 2);
    }
    if ($this->conn) {
      $pdo_quote = $this->conn->quote($string);
      //strip leanding and trailing '
      return substr($pdo_quote, 1, strlen($pdo_quote) - 2);
    }
    return false;
  }

  function my_escape_string($string, $conn = null) {
    return $this->my_real_escape_string($string, $conn);
  }

  function my_metadata($stmt = null) {
     if($stmt === null) $stmt = $this->stmt;
     if($stmt === false) return false;
     $cnt = $stmt->columnCount();
     $meta = array();
     for($i=0;$i<$cnt;++$i) {
         $meta[] = $stmt->getColumnMeta($i);
     }
     return $meta;
  }

  function enumerate_partitions($schema_name, $table_name, $all_partition_types, $conn = null) {
    switch($this->server['dsn-prefix']) {
      case 'mysql':
        $schema_name=trim($schema_name,'`');
        $table_name=trim($table_name,'`');
        $sql = SimpleDAL::get_mysql_partition_sql($schema_name, $table_name, $all_partition_types);
      break;

      default:
        return false;
    }  
      
    $stmt = $this->my_query($sql, $conn);
    if(!$stmt) return false;
    $rows = null;
    $partition_names = array();
    $partition_expression = null;
    $rows = null;
    while($row = $this->my_fetch_assoc()) { 
      if($all_partition_types) {
        $partition_names[] = $row['partition_name'];
      } else {
        $rows[$row['partition_name']] = $row['where_expression'];
        $partition_expression = $row['partition_expression'];
      }

    }
    if(isset($partition_names[0]) && $partition_names[0] == "") unset($partition_names);

    if(isset($rows) || count($partition_names) > 0) { 
      $return = array ( 'where_clauses' => $rows, 'partition_expression' => $partition_expression, 'partition_names' => $partition_names );
    } else {
      $return = false;
    }

    return $return;
  
  }

}
