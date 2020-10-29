PROJECT DEPRECATION WARNING:
=================
Shard-Query has some issues working on MySQL 8 and I do not intend to update it to support MySQL 8.
Flexviews has some issues on MariaDB 10.4 and likewise, I do not intend to update it to support MariaDB 10.4+

Consider using vitess for sharding (warning: does not support as much syntax as Shard-Query for scatter-gather queries)

If you would like these tools updated to support newer versions, contact greenlion@gmail.com to discuss *sponsoring* the
work to update the tools.

swanhart-tools 
==============

This is where you can find most open source tools written and primarily maintained by Justin Swanhart

Shard-Query - MPP scaleout for MySQL with window functions and support for complex SQL (basically open source RedShift)

  * Shard-Query website: http://shardquery.wordpress.com

Flexviews 
  * Incrementally refreshable (fast refresh) materialized views (like oracle materialized views or DB2 materialized views).    
  * Includes FlexCDC, a MySQL change data capture tool which supports ALTER table.

  * Flexviews manual: http://greenlion.github.io/swanhart-tools/flexviews/manual.html
  * Flexviews INSTALL guide: https://github.com/greenlion/swanhart-tools/blob/master/flexviews/INSTALL


