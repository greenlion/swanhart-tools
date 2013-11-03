This is basically "shard-query-lite".  All gearman features and all sharding features are removed.  
The idea is that this code will be called from inside of the MySQL server, passing in two structures
which indicate information about the tables used and the connection information.
