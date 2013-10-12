#!/usr/bin/env bash

echo "INSTALL PERCONA"
rpm -Uhv http://www.percona.com/downloads/percona-release/percona-release-0.0-1.x86_64.rpm
yum -y install Percona-Server\*-56-\*

echo '[mysqld_safe]
open_files_limit=8192

[mysqld]
innodb_buffer_pool_size=3G
innodb_stats_persistent=0
innodb_buffer_pool_instances=8
table_open_cache=8192
table_definition_cache=8192
binlog_cache_size=8M
binlog_stmt_cache_size=128K
innodb_flush_log_at_trx_commit=2
innodb_flush_method=O_DIRECT_NO_FSYNC
innodb_log_buffer_size=24M
innodb_log_file_size=256M
innodb_adaptive_hash_index_partitions=8
innodb_thread_concurrency=16
binlog_format=ROW' > /etc/my.cnf

echo "INSTALL PREREQS"
yum -y install php php-mysql php-pcntl php-pear libevent-devel openssl-devel boost-devel gperf libuuid-devel lua-devel

echo "COMPILE GEARMAN"
mkdir /tmp/build
cd /tmp/build
wget https://launchpad.net/gearmand/1.2/1.1.11/+download/gearmand-1.1.11.tar.gz
tar zxovf gearmand-1.1.11.tar.gz
cd gearmand-1.1.11
./configure
make
make install
mkdir -p /usr/local/var/log
chmod a+rxw /usr/local/var/log
/usr/local/sbin/gearmand -d -L 192.168.33.50 -p 7001

echo "BOOTSTRAP MYSQL"
cd /vagrant
service mysql start
chkconfig mysql on
mysql -uroot -e'delete from mysql.user where user=""; flush privileges;'
mysql -uroot -e'grant all on shard1.* to shardquery@"%" identified by "CHANGEME" WITH GRANT OPTION'
mysql -uroot -e'grant all on shard2.* to shardquery@"%" identified by "CHANGEME" WITH GRANT OPTION'
mysql -uroot -e'grant all on shard3.* to shardquery@"%" identified by "CHANGEME" WITH GRANT OPTION'
mysql -uroot -e'grant all on shard4.* to shardquery@"%" identified by "CHANGEME" WITH GRANT OPTION'
mysql -uroot -e'grant all on sqrepo.* to shardquery@"%" identified by "CHANGEME" WITH GRANT OPTION'
mysqladmin -uroot create shard1 
mysqladmin -uroot create sqrepo

echo "INSTALL SQ REPO"
mysql -uroot sqrepo < shard_query.sql

echo "INSTALL DOOPHP"
cp -r /usr/share/shard-query/bundle/dooframework /usr/share
chmod -R a+r /usr/share/dooframework
find /usr/share/dooframework -type d -exec chmod a+x {} \;

echo "CREATE SHARD-QUERY INI FILE"
#shard-query needs a .ini file to set up the virtual schema
#this schema consists of only one node (this is called a scale-up config)
echo '[default]
user=shardquery
password=CHANGEME

[config]
gearman=192.168.33.50:7001
db=sqrepo
host=192.168.33.50
; this is the name of the VIRTUAL schema, WHICH IS NOT (usually) the actual schema in the db
schema_name=default 
mapper=directory
; the default data will use the SSB dataset
; **** CHANGE THE SHARD KEY HERE ****
column=LO_OrderDateKey
column_datatype=integer
; **********************************
is_default_schema=1
inlist=*
between=*
inlist_merge_threshold=256
inlist_merge_size=128
star_schema=false
coord_engine=MYISAM
gearmand_path=/usr/local/sbin

[shard1]
db=shard1
host=192.168.33.50

[shard2]
db=shard2
host=192.168.33.50

[shard3]
db=shard3
host=192.168.33.50

[shard4]
db=shard4
host=192.168.33.50
' > /usr/share/shard-query/provision.ini

echo "CREATE VIRTUAL SCHEMA"
php setup_virtual_schema.php --ini=/usr/share/shard-query/provision.ini --user=shardquery --password=CHANGEME

cd /usr/share/shard-query/bin
./start_workers

cd ..
cp tools/*.conf /etc/httpd/conf.d

service httpd start

echo "BUILD UDF FOR PROXY"
cd /tmp/build
wget https://launchpad.net/gearman-mysql-udf/trunk/0.6/+download/gearman-mysql-udf-0.6.tar.gz
tar zxovf gearman-mysql-udf-0.6.tar.gz
cd gearman-mysql-udf-0.6
./configure
make
make install

ln -s /usr/local/lib/libgearman* /usr/lib64/mysql/plugin/

mysql -uroot -e '
CREATE FUNCTION gman_do RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION gman_do_high RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION gman_do_low RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION gman_do_background RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION gman_do_high_background RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION gman_do_low_background RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE AGGREGATE FUNCTION gman_sum RETURNS INTEGER
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION gman_servers_set RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
'

mysql -uroot -e'select gman_servers_set("192.168.33.50:7001")'

echo "BUILD PROXY"
cd /tmp/build
wget http://dev.mysql.com/get/Downloads/MySQL-Proxy/mysql-proxy-0.8.3-linux-rhel5-x86-64bit.tar.gz/from/http://cdn.mysql.com/
tar zxovf mysql-proxy-0.8.3-linux-rhel5-x86-64bit.tar.gz
mv mysql-proxy-0.8.3-linux-rhel5-x86-64bit /usr/local/mysql-proxy
cd /usr/local/mysql-proxy/bin

echo "START PROXY"
./mysql-proxy -s /usr/share/shard-query/proxy/mysqlproxy.lua --daemon

