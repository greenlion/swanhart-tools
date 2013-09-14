<?php

	class util{
			static function build_PDO_DSN($shard){
				if(is_array($shard)) $shard = (object)$shard;

				//mysql and pgsql currently supported and have same dsn format 
				//http://www.electrictoolbox.com/php-pdo-dsn-connection-string/
				if($shard->shard_rdbms  == 'pdo-mysql' || $shard->shard_rdbms  == 'mysql' ){
						//check for port
						if(isset($shard->port))
							return "mysql:host={$shard->host};dbname={$shard->db};port={$shard->port}";
						else
							return "mysql:host={$shard->host};dbname={$shard->db}";
				}

				if($shard->shard_rdbms  == 'pdo-pgsql'){
						//check for port
						if(isset($shard->port))
							return "pgsql:host={$shard->host};dbname={$shard->db};port={$shard->port}";
						else
							return "pgsql:host={$shard->host};dbname={$shard->db}";
				}
				return null;
			}
	}
?>
