Easy to use high performance OLAP scale-out (grid computing) for MySQL
==
![Shard Query Logo](http://shard-query.googlecode.com/svn/trunk/doc/new-logo.gif)
What is Shard-Query
--
Shard-Query is a high performance MySQL query engine for which offers increased parallelism compared to stand-alone MySQL.  This increased parallelism is achieved by taking advantage of MySQL [partitioning](http://dev.mysql.com/doc/refman/5.5/en/partitioning.html partitioning), MySQL sharding, common MySQL query clauses like BETWEEN and IN, or some combination of the above.  

The primary goal of Shard-Query is to enable low-latency query access to extremely large volumes of data utilizing commodity hardware and open source database software.  Shard-Query is a federated query engine which is designed to perform as much work in parallel as possible over a sharded MySQL dataset, that is one that is split over multiple servers (shards) or partitioned tables.

###What kind of interfaces does Shard-Query have
  * A RESTful UI which allows you to submit queries and examine results as well as configure Shard-Query
  * A MySQL proxy script
  * A PHP Object Oriented interface

###What kind of queries are supported?
  * You can run just about all SQL queries over your dataset: 
  * For SELECT queries: 
    * All aggregate functions are supported.
      * SUM,COUNT,MIN,MAX and AVG are the fastest aggregate operations
      * SUM/COUNT(DISTINCT ..) are supported, but are slower
      * STD/VAR/etc are supported but aggregation is not pushed down at all (slowest)
      * Custom aggregate functions are now also supported.
        * PERCENTILE(expr, N) - take a percentile, for example percentile(score,90)
    
    * JOINs are supported (no self joins, or joins of tables sharded by different keys)
    * ORDER BY, GROUP BY, HAVING, WITH ROLLUP, and LIMIT are supported
  * Also upports INSERT, UPDATE, DELETE 
  * Also supports DDL such as CREATE TABLE, ALTER TABLE and DROP TABLE

###Key Features
  * MPP - distributed query engine runs fragments of queries in parallel, combining the results at the end.  
  * Supports almost all MySQL features 
  * Virtual Schema - All shards are treated as one virtual database. 
  * Automatic Sharding
  * Massively parallel loader
  * Shard Elimination - When possible, Shard-Query sends queries only to the shards containing the requested data. 
  * Shared Nothing Architecture - Aggregation, joins and filtering are always performed at the shard level which fully distributes the work
  * Works similar to a map/reduce except that it understands complex SQL.
  * Supports asynchronous queries for long running jobs


###Massively Parallel Query
The following SQL features enable parallel query execution:
  * Data level paralellism
    * partitioning
    * sharding

  * Operator level 
    * UNION
    * UNION ALL
    * IN clauses
    * BETWEEN (with integer or date operands)
    * subqueries in the FROM clause
