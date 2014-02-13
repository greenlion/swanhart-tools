set foreign_key_checks=0;
set storage_engine=INNODB;

DROP TABLE IF EXISTS `column_sequences`;
CREATE TABLE `column_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schema_id` int(11) NOT NULL,
  `sequence_type` enum('shard_column','sequence') NOT NULL DEFAULT 'shard_column',
  `sequence_name` varchar(50) NOT NULL,
  `next_value` bigint(20) DEFAULT NULL,
  `datatype` enum('string','integer') NOT NULL DEFAULT 'integer',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_id_2` (`schema_id`,`sequence_name`,`sequence_type`),
  CONSTRAINT `column_sequences_ibfk_1` FOREIGN KEY (`schema_id`) REFERENCES `schemata` (`id`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `gearman_function_names`;
CREATE TABLE `gearman_function_names` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `function_name` varchar(50) NOT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `function_name` (`function_name`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `gearman_functions`;
CREATE TABLE `gearman_functions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schema_id` int(11) NOT NULL,
  `worker_count` int(10) unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `function_name_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_worker_unique` (`schema_id`,`function_name_id`),
  KEY `fk_gearmand_functions_schema_id_idx` (`schema_id`),
  KEY `gearman_functions_ibfk_1` (`function_name_id`),
  CONSTRAINT `fk_gearmand_functions_schema_id` FOREIGN KEY (`schema_id`) REFERENCES `schemata` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `gearman_functions_ibfk_1` FOREIGN KEY (`function_name_id`) REFERENCES `gearman_function_names` (`id`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `gearman_job_servers`;
CREATE TABLE `gearman_job_servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schema_id` int(11) NOT NULL,
  `hostname` varchar(50) NOT NULL,
  `port` smallint(5) unsigned NOT NULL,
  `local` tinyint(1) NOT NULL DEFAULT '1',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `pid` int(11) DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostname_port_unique` (`hostname`,`port`),
  KEY `schema_id` (`schema_id`),
  CONSTRAINT `gearman_job_servers_ibfk_1` FOREIGN KEY (`schema_id`) REFERENCES `schemata` (`id`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `gearman_workers`;
CREATE TABLE `gearman_workers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `function_name` varchar(255) NOT NULL,
  `schema_id` int(11) NOT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `function_id` (`function_name`,`pid`)
)  DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `job_worker_status`;
CREATE TABLE `job_worker_status` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` bigint(20) NOT NULL,
  `worker_type` enum('store_resultset','function','loader') NOT NULL DEFAULT 'store_resultset',
  `completion_type` enum('ok','error') DEFAULT NULL,
  `completion_message` text,
  `complete_time` timestamp,
  PRIMARY KEY (`id`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `shard_id` int(11) DEFAULT NULL,
  `job_type` enum('query','load') DEFAULT 'query',
  `resultset_jobs` int(11) NOT NULL DEFAULT '0',
  `function_jobs` int(11) NOT NULL DEFAULT '0',
  `loader_jobs` int(11) NOT NULL DEFAULT '0',
  `tables_used` text NOT NULL,
  `job_status` enum('starting','running','completed','error') NOT NULL DEFAULT 'starting',
  `job_result_sql` text NOT NULL,
  `completion_percent` decimal(5,2) DEFAULT '0.00',
  `start_time` timestamp,
  `end_time` datetime,
  PRIMARY KEY (`id`),
  KEY `shard_id` (`shard_id`),
  KEY `completion_percent` (`completion_percent`),
  CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`shard_id`) REFERENCES `shards` (`id`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `schemata`;
CREATE TABLE `schemata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schema_name` varchar(50) NOT NULL,
  `is_default_schema` tinyint(1) NOT NULL DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_name_UNIQUE` (`schema_name`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `schemata_config`;
CREATE TABLE `schemata_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schema_id` int(11) NOT NULL,
  `var_name` varchar(255) NOT NULL,
  `var_value` text NOT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_id` (`schema_id`,`var_name`),
  CONSTRAINT `schemata_config_ibfk_1` FOREIGN KEY (`schema_id`) REFERENCES `schemata` (`id`),
  CONSTRAINT `fk2` FOREIGN KEY (`var_name`) references `schemata_config_items`(`name`)
)  AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `schemata_config_items`;
CREATE TABLE `schemata_config_items` (
  `name` varchar(255) NOT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`)
)  DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `shard_map`;
CREATE TABLE `shard_map` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `column_id` int(11) NOT NULL,
  `key_value` bigint(20) DEFAULT -1 NOT NULL,
  `key_string_value` varchar(255) DEFAULT '' NOT NULL,
  `shard_id` int(11) NOT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (`id`),
  PRIMARY KEY `column_id` (`column_id`,`key_value`,`key_string_value`),
  KEY `key_string_value` (`key_string_value`),
  KEY `shard_id` (`shard_id`),
  CONSTRAINT `shard_map_ibfk_1` FOREIGN KEY (`shard_id`) REFERENCES `shards` (`id`),
  CONSTRAINT `shard_map_ibfk_2` FOREIGN KEY (`column_id`) REFERENCES `column_sequences` (`id`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `shard_range_map`;
CREATE TABLE `shard_range_map` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `column_id` int(11) NOT NULL,
  `shard_id` int(11) NOT NULL,
  `key_value_min` bigint(20) DEFAULT NULL,
  `key_value_max` bigint(20) DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `column_id_2` (`column_id`,`key_value_min`),
  KEY `shard_id` (`shard_id`),
  KEY `column_id_3` (`column_id`,`key_value_max`),
  CONSTRAINT `shard_range_map_ibfk_1` FOREIGN KEY (`shard_id`) REFERENCES `shards` (`id`),
  CONSTRAINT `shard_range_map_ibfk_2` FOREIGN KEY (`column_id`) REFERENCES `column_sequences` (`id`)
)  DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `shards`;
CREATE TABLE `shards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schema_id` int(11) NOT NULL,
  `shard_name` varchar(50) NOT NULL,
  `extra_info` text,
  `shard_rdbms` enum('mysql','pdo-pgsql','pdo-mysql') NOT NULL DEFAULT 'mysql',
  `coord_shard` tinyint(1) NOT NULL DEFAULT '1',
  `accepts_new_rows` tinyint(1) NOT NULL DEFAULT '1',
  `username` varchar(64) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `host` varchar(255) DEFAULT NULL,
  `port` smallint(6) DEFAULT NULL,
  `db` varchar(45) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_id` (`schema_id`,`shard_name`),
  CONSTRAINT `shards_ibfk_1` FOREIGN KEY (`schema_id`) REFERENCES `schemata` (`id`)
)  AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
INSERT INTO `gearman_function_names` VALUES (1,'shard_query_worker',now()),(2,'store_resultset',now()),(3,'custom_function',now()),(4,'loader',now());
INSERT INTO `schemata_config_items` 
VALUES ('between',now()),
('inlist',now()),
('mapper',now()),
('password',now()),
('port',now()),
('user',now()),
('no_expand_partition_columns',now()),
('inlist_merge_threshold',now()),
('inlist_merge_size',now()),
('coord_engine',now()),
('gearmand_path', now()),
('star_schema', now());

create table is_tables 
(
schema_id int not null default 0, 
key(schema_id, table_schema, table_name)
) 
as select * from information_schema.tables limit 0; 

create table is_columns
(
schema_id int not null default 0, 
key(schema_id, table_schema, table_name, column_name)
) 
as select * from information_schema.columns limit 0; 

create table is_partitions
(
schema_id int not null default 0, 
key(schema_id, table_schema, table_name)
) 
as select * from information_schema.partitions where table_name is null limit 0; 
