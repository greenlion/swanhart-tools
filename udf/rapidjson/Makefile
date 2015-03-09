udf: 
	g++ -std=c++11 $* -shared -I `mysql_config --include` `mysql_config --cxxflags` rapid_flatten_json.cpp -o rapid_flatten_json_udf.so 

install: udf
	cp rapid_flatten_json_udf.so `mysql_config --plugindir`
	mysql -uroot -e "create function rapid_flatten_json returns string soname 'rapid_flatten_json_udf.so'" -f
	mysql -uroot -e "create function rapid_extract_all returns string soname 'rapid_flatten_json_udf.so'" -f
	mysql -uroot -e "create database rapidjson"
	mysql -uroot rapidjson < routines.sql

