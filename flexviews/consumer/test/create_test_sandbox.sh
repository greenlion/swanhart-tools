mkdir -p data
make_sandbox 5.5.3 --upper_directory=$PWD --sandbox_directory=sandbox --sandbox_port=54321 --install_version=5.5 --no_show --my_clause="log_bin=mysql-bin" --my_clause="binlog_format=row" --my_clause="server_id=54321" --my_clause="log_slave_updates" --my_clause="default_storage_engine=innodb" --no_ver_after_name     --no_show     --force 

