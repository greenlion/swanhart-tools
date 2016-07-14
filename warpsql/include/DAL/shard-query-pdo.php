<?php

class PDODAL implements SimpleDALinterface
{
    private $conn = null;
    private $stmt = null;
    private $server = null;
    function __construct($server = null, $connect = true, $force_new = false)
    {
        if ($connect)
            $this->my_connect($server, $connect, $force_new);
    }
    
    function my_insert_id($conn = null)
    {
        if (isset($conn))
            return $con->lastInsertId();
        
        if ($this->conn)
            return $this->conn->lastInsertId();
        return false;
    }
    
    function &my_connect($server = null, $force_new = false)
    {
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
    
    function my_affected_rows($stmt = null)
    {
        if (isset($stmt)) {
            return $stmt->rowCount();
        } elseif ($this->stmt) {
            return $this->stmt->rowCount();
        }
        return false;
    }

    function &my_query($sql = '', $conn = null)
    {
	if(SQ_DEBUG) echo "DRV_PDO:$sql\n";
        if (isset($conn)) {
			$conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
			$this->stmt = $conn->query($sql);
        } elseif ($this->conn) {
			$this->conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
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

    function &my_unbuffered_query($query, $conn = null)
    {
        if(SQ_DEBUG) echo "DRV_PDO_UB:$query\n";
        if (isset($conn)) {
           $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
           $res = $this->my_query($query, $conn);
        } elseif ($this->conn) {
           $this->conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
           $res = $this->my_query($query);
        }
        return $res;
    }

    function &my_fetch_assoc($stmt = null)
    {
        if ($stmt === null)
            $stmt = $this->stmt;
	if(!$stmt) return false;
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
	return $r;
    }

    function &my_fetch_array($stmt = null, $mode = null)
    {
        if (!$stmt)
            $stmt = $this->stmt;
	$r = $stmt->fetch(PDO::FETCH_NUM);
	return $r;
    }

	function &my_errno(&$conn = null)
	{
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

    function my_select_db($db, $conn = null)
    {
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

    function my_field_type($result = null, $field_offset = 0)
    {
        if (!isset($result))
            $result = $this->stmt;
        return $result->getColumnMeta($field_offset);
    }

    function my_free_result($stmt = null)
    {
        if ($stmt)
            return $stmt->closeCursor();
        if ($this->stmt)
            return $this->stmt->closeCursor();
        return true;
    }

    function my_close(&$conn = null)
    {
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

    function my_begin($conn = null)
    {
        if (!$conn) {
            return $this->conn->beginTransaction();
        } else {
            return $conn->beginTransaction();
        }
    }

    function my_autocommit($state = true, $conn = null)
    {
        //autocommit unless using pdo transactions      
    }

    function my_commit($conn = null)
    {
        if (!$conn) {
            return $this->conn->commit();
        } else {
            return $conn->commit();
        }
    }

    function my_rollback($conn = null)
    {
        if (!$conn) {
            return $this->conn->rollBack();
        } else {
            return $conn->rollBack();
        }
    }

    function my_real_escape_string($string, $conn = null)
    {
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

    function my_escape_string($string, $conn = null)
    {
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

		case 'pgsql':
			#FIXME: probably broken
			$sql = "
			SELECT chk.consrc as expression
			  FROM pg_catalog.pg_inherits i
			  JOIN pg_catalog.pg_class c1
			    ON i.inhparent= c1.oid
			  JOIN pg_catalog.pg_class c2
			    ON c2.oid = i.inhrelid
			  JOIN pg_catalog.pg_constraint chk
			    ON chk.contype = 'c'
			   AND chk.conrelid = c2.oid
			  JOIN pg_catalog.pg_namespace n 
			    ON n.oid = c1.relnamespace 
			 WHERE c1.relname = '$table_name'
			   and n.nspname = '$schema_name;";

		break;

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
?>
