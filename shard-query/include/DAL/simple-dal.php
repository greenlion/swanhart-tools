<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 encoding=utf-8: */
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

require_once('shard-query-mysql.php');
require_once('shard-query-pdo.php');

class SimpleDAL {
	public static function factory($server = null, $connect = true, $force_new = true) {
		if(!isset($server)) throw new Exception('Server required');

		if(empty($server['rdbms_type'])) $server['rdbms_type'] = 'mysql';

		switch($server['rdbms_type']) {
			case SQ_RDBMS_MYSQL:
				return new MySQLDAL($server, $connect, $force_new);
				break;
			case SQ_RDBMS_PDO_MYSQL:
				$server['dsn-prefix'] = 'mysql';
				return new PDODAL($server, $connect, $force_new);
				break;
			case SQ_RDBMS_PDO_PGSQL:
				$server['dsn-prefix'] = 'pgsql';
				return new PDODAL($server, $connect, $force_new);
				break;
			default:
				throw new Exception('Unsupported RDBMS: ' . $server['rdbms_type']);
		
		}
	}

    public static function get_mysql_partition_sql($schema_name, $table_name, $all = false) {
            if($all === true) {
                $sql = "
                select IFNULL(subpartition_name,partition_name) partition_name, IFNULL(subpartition_method, partition_method) partition_method
		from information_schema.partitions
		where table_schema = '$schema_name'
		  and table_name = '$table_name'";
            } else {
       		$sql = "select
            partition_expression, 
            CONCAT(partition_name,IF(SUBPARTITION_NAME IS NULL,'', concat('_', subpartition_name))) partition_name,
    		concat(
    		  -- get the boundary expression for the previous partition (if any)
    		  IFNULL(
    		    
    		    (select 
    		     concat(p3.partition_expression, ' >= (', 
    		       if(p3.partition_description = 'MAXVALUE', (select max(p2.partition_description) 
    		                                                 from information_schema.partitions p2 
    		                                                where p2.table_name = p3.table_name 
    		                                                  and p2.table_schema = p3.table_schema
    		                                                  and p2.partition_description != 'MAXVALUE'
    		                                                  and table_name = '$table_name' and table_schema = '$schema_name'
                                                              and (subpartition_ordinal_position is null or subpartition_ordinal_position = 1)
    		                                                 ), p3.partition_description), ') AND ')
    		from information_schema.partitions p3
    		where p.partition_description != 'MAXVALUE'
    		and p3.table_name = p.table_name and p.table_schema = p3.table_schema
    		and table_name = '$table_name' and table_schema = '$schema_name'
            and (subpartition_ordinal_position is null or subpartition_ordinal_position = 1)
    		and p3.PARTITION_ORDINAL_POSITION=(p.PARTITION_ORDINAL_POSITION-1)),''), ' ',
    		 -- get the boundary expression for the current partition     
    		  p.partition_expression, ' ', IF(p.partition_description != 'MAXVALUE', '< ', '>= '), ' (', 
    		       if(p.partition_description = 'MAXVALUE', (select max(p2.partition_description) 
    		                                                 from information_schema.partitions p2 
    		                                                where p2.table_name = p.table_name 
    		                                                  and p2.table_schema = p.table_schema
    		                                                  and p2.partition_description != 'MAXVALUE' 
                                                              and (subpartition_ordinal_position is null or subpartition_ordinal_position = 1)
    		                                                  and table_name = '$table_name' and table_schema = '$schema_name'
    		                                                ), p.partition_description), ')'
    		) as where_expression 
    		from information_schema.partitions p
    		where partition_method like 'RANGE%'
    		  and partition_expression not like '%`,`%'
    		  and table_name = '$table_name'
    		  and table_schema = '$schema_name'
              and (subpartition_ordinal_position is null or subpartition_ordinal_position = 1)
    		UNION ALL
    		select partition_expression,
            CONCAT(partition_name,IF(SUBPARTITION_NAME IS NULL,'', concat('_', subpartition_name))) partition_name,
    		  concat(p.partition_expression, ' IN (',  
    		       p.partition_description, ')'
    		) as expression
    		from information_schema.partitions p
    		where partition_method like 'LIST%'
    		  and partition_expression not like '%`,`%' 
              and (subpartition_ordinal_position is null or subpartition_ordinal_position = 1)
    		  and table_schema = '$schema_name'
    		  and table_name = '$table_name'";
            }

    
            return $sql;
    }

}
