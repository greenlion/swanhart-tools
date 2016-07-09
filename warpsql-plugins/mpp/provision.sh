#!/usr/bin/env bash

# $1 = provisioned, $2 = err, $3 = flag
inc_provisioned()
{
if [ $2 -eq 0 ]; then
    echo $(($1 + 1))
    touch $3
else
    echo $1
fi
}

provisioned=0
if [ ! -f /root/.percona_installed ]; then
echo "INSTALL PERCONA"
err=0
rpm -Uhv http://www.percona.com/downloads/percona-release/percona-release-0.0-1.x86_64.rpm || err=1
yum -y install Percona-Server\*-56-\* || err=1

echo '[mysqld_safe]
open_files_limit=8192

[mysqld]
innodb_buffer_pool_size=2250M
innodb_stats_persistent=1
innodb_buffer_pool_instances=8
table_open_cache=8192
table_definition_cache=8192
binlog_cache_size=8M
binlog_stmt_cache_size=128K
innodb_flush_log_at_trx_commit=2
innodb_flush_method=O_DIRECT_NO_FSYNC
innodb_log_buffer_size=24M
innodb_log_file_size=256M
innodb_thread_concurrency=16
binlog_format=ROW
innodb_change_buffer_max_size=50
innodb_max_dirty_pages_pct=25
innodb_old_blocks_time=250
innodb_old_blocks_pct=20
innodb_read_io_threads=8
innodb_io_capacity=1000
' > /etc/my.cnf || err=1
provisioned=$(inc_provisioned $provisioned $err /root/.percona_installed)
fi

if [ ! -f /root/.prereqs_installed ]; then
echo "INSTALL PREREQS"
err=0
yum -y install php php-mysql php-pcntl php-pear php-process libevent-devel openssl-devel boost-devel gperf libuuid-devel lua-devel screen unzip vim sysstat wget || err=1 
yum -y groupinstall "development tools" || err=1 
provisioned=$(inc_provisioned $provisioned $err /root/.prereqs_installed)
fi

if [ ! -f /root/.gearmand_installed ]; then
echo "COMPILE GEARMAN"
err=0
mkdir /tmp/build || err=1
cd /tmp/build
wget https://launchpad.net/gearmand/1.2/1.1.11/+download/gearmand-1.1.11.tar.gz || err=1
tar zxovf gearmand-1.1.11.tar.gz || err=1
cd gearmand-1.1.11
./configure || err=1
make || err=1
make install || err=1
mkdir -p /usr/local/var/log
chmod a+rxw /usr/local/var/log
/usr/local/sbin/gearmand -d -L 192.168.33.50 -p 7001 || err=1
grep gearmand /etc/rc.d/rc.local >/dev/null || echo '/usr/local/sbin/gearmand -d -L 192.168.33.50 -p 7001' >> /etc/rc.d/rc.local
provisioned=$(inc_provisioned $provisioned $err /root/.gearmand_installed)
fi

if [ ! -f /root/.mysql_bootstrapped ]; then
echo "BOOTSTRAP MYSQL"
err=0
cd /vagrant
service mysql start || err=1
chkconfig mysql on || err=1
mysql -uroot -e'delete from mysql.user where user=""; flush privileges;' || err=1
for i in 1 2 3 4; do
    mysqladmin -uroot create shard$i || err=1
    mysql -uroot -e"grant all on shard${i}.* to shardquery@'%' identified by 'CHANGEME' WITH GRANT OPTION" || err=1
done
mysql -uroot -e'grant all on sqrepo.* to shardquery@"%" identified by "CHANGEME" WITH GRANT OPTION' || err=1
mysqladmin -uroot create sqrepo || err=1
provisioned=$(inc_provisioned $provisioned $err /root/.mysql_bootstrapped)
fi

if [ ! -f /root/.sq_repo_installed ]; then
echo "INSTALL SQ REPO"
err=0
mysql -uroot sqrepo < shard_query.sql || err=1
provisioned=$(inc_provisioned $provisioned $err /root/.sq_repo_installed)
fi

if [ ! -f /root/.doophp_installed ]; then
echo "INSTALL DOOPHP"
err=0
cp -r /usr/share/shard-query/bundle/dooframework /usr/share || err=1
chmod -R a+r /usr/share/dooframework
find /usr/share/dooframework -type d -exec chmod a+x {} \; || err=1
provisioned=$(inc_provisioned $provisioned $err /root/.doophp_installed)
fi

if [ ! -f /root/.sq_ini_file_created ]; then
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
provisioned=$(inc_provisioned $provisioned $$ /root/.sq_ini_file_created)
fi

if [ ! -f /root/.virtual_schema_created ]; then
echo "CREATE VIRTUAL SCHEMA"
err=0
cd /usr/share/shard-query/
php setup_virtual_schema.php --batch --ini=/usr/share/shard-query/provision.ini --user=shardquery --password=CHANGEME || err=1

cd /usr/share/shard-query/bin
./start_workers || err=1

cd ..
cp tools/*.conf /etc/httpd/conf.d || err=1

service httpd start || err=1
chkconfig --level 345 httpd on || err=1
provisioned=$(inc_provisioned $provisioned $err /root/.virtual_schema_created)
fi

if [ ! -f /root/.udf_for_proxy_built ]; then
err=0
echo "BUILD UDF FOR PROXY"
cd /tmp/build
wget https://launchpad.net/gearman-mysql-udf/trunk/0.6/+download/gearman-mysql-udf-0.6.tar.gz || err=1
tar zxovf gearman-mysql-udf-0.6.tar.gz || err=1
cd gearman-mysql-udf-0.6 || err=1
./configure || err=1
make || err=1
make install || err=1

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
' || err=1

mysql -uroot -e'select gman_servers_set("192.168.33.50:7001")' || err=1
provisioned=$(inc_provisioned $provisioned $err /root/.udf_for_proxy_built)
fi

if [ ! -f /root/.proxy_built ]; then
echo "BUILD PROXY"
err=0
cd /tmp/build
wget http://dev.mysql.com/get/Downloads/MySQL-Proxy/mysql-proxy-0.8.3-linux-rhel5-x86-64bit.tar.gz/from/http://cdn.mysql.com/ -O mysql-proxy-0.8.3-linux-rhel5-x86-64bit.tar.gz || err=1
tar zxovf mysql-proxy-0.8.3-linux-rhel5-x86-64bit.tar.gz || err=1
mv mysql-proxy-0.8.3-linux-rhel5-x86-64bit /usr/local/mysql-proxy || err=1
cd /usr/local/mysql-proxy/bin || err=1

#set up a funny motd
echo "Welcome to the machine - and have a cigar.  You're gonna go far." > /etc/motd
provisioned=$(inc_provisioned $provisioned $err /root/.proxy_built)
fi

if [ ! -f /root/.proxy_started ]; then
echo "START PROXY"
err=0
grep mysqlproxy.lua /etc/rc.ld/rc.local >/dev/null || echo "/usr/local/mysql-proxy/bin/mysql-proxy -s /usr/share/shard-query/proxy/mysqlproxy.lua --daemon" >> /etc/rc.d/rc.local || err=1
/usr/local/mysql-proxy/bin/mysql-proxy -s /usr/share/shard-query/proxy/mysqlproxy.lua --daemon || err=1
provisioned=$(inc_provisioned $provisioned $err /root/.proxy_started)
fi

if [ ! -f /root/.ssb_generator_installed ]; then
echo "INSTALL SSB GENERATOR"
err=0
mkdir /ssb || err=1
chmod a+wrx /ssb || err=1
yum -y install unzip || err=1
cd /ssb || err=1
wget https://github.com/greenlion/ssb-dbgen/archive/master.zip -O master.zip || err=1
unzip master.zip || err=1
chmod a+wrx -R ssb* || err=1
cd ssb*
make 1>/dev/null 2>/dev/null 3>/dev/null || err=1
chmod -R a+wrx /ssb || err=1
provisioned=$(inc_provisioned $provisioned $err /root/.ssb_generator_installed)
fi

cp /vagrant/tools/vagrant_motd /etc/motd
echo ""
echo "Ran $provisioned provisioner blocks"
echo "
Use vagrant ssh to enter the machine.  You will be given instructions for how to generate and load data."
