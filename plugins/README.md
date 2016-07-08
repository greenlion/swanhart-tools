#WarpSQL OLAP Suite

This is a set of extensions to MariaDB called TroySQL.  These extensions are designed to improve
the performance of MariaDB for OLAP (reporting) queries.  It enables both a single node, or multi
node distributed operation on snow and starflake schemata, which are the most common type of 
schema for "internet of things" (sensor data), and traditional ROLAP analysis.  Parallel queries
can run dozens, hundreds, or even thousands of times faster, depending on your number of cores
and how many machines you use.

To use the following features, you must execute the install_olap.sql
script.

## Q daemon plugin
WarpSQL Q is a daemon plugin version of the gearmand message queue. 
The plugin starts and stops with the server. Q listens on 127.0.0.1:43078 
and will not accept connections from external machines. 
The port is not currently configurable.

You can create your own gearman workers, connect them
to the Q daemon, and get results from the workers
right inside the database.  

###Note on security
The Q daemon does not have any security.  Since Q 
only listens on localhost, it is unlikely that
an attacker can abuse the system, but it allows
anyone with access to the machine to run unauthenicated
queries against the database.  

## MPP Layer
The WarpSQL MPP layer is made available through
Diabolos. Diabolos is a daemon plugin that creates 
Q workers which process queries.  

Query results are made available through a simple
UDF/stored routine layer to execute queries and 
get result sets (or using a rewrite plugin).

Automatic one-node parallel query support is included, 
and MPP/sharding is supported as well for OLAP 
scale-out.

### MPP notes 
This extension is based on the latest incarnation of Shard-Query.
WarpSQL has improved SQL support, improved support for machines with
many shards (sharded dimension database to save many multiples of space),
easier configuration, and vastly improved loading performance due 
to a C++ loader.  There are other improvements too, like support for
non-recursive common table expressions.

The "mpp" filter must be enabled in rewrite_filters;

## Flexviews Materialized Views natively supported

### Flexviews rewrite extension
Adds support for commands like:

  * CREATE MATERIALIZED VIEW LOG ON schema.table;

  * CREATE INCREMENTAL MATERIALIZED VIEW schema.view
  * AS
  * SELECT ...;
  
  * REFRESH schema.table TO TIMESTAMP '2015-01-01 12:34:56' ALGORITHM COMPUTE;
  * REFRESH schema.table ALGORITHM APPLY; 
  * REFRESH schema.table REWIND 1 WEEK;

  TODO: Full list to come.

### FlexCDC daemon plugin
FlexCDC is a daemon plugin for collecting binary log changes into
change log tables (Change Data Capture).  The daemon plugin
needs to be set up using commands:

  * SHOW CDC STATUS;


