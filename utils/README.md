This directory contains open source utilities and scripts for MySQL

pin_in_buffer_pool.sql - Keep a table in the buffer pool with a SQL event

row_len_stats.sql - report avg, min, and max row lengths for a table (full table scan, so it is expensive):

```
mysql> call test.row_len_stats('fastbit','fbdata');
+-------------------+--------------------------------------------------+
| Using Expression: | @expr                                            |
+-------------------+--------------------------------------------------+
| Using Expression: | LENGTH(concat(`c1`,`c2`,`c3`,`v1`,`extra_data`)) |
+-------------------+--------------------------------------------------+
1 row in set (0.02 sec)

+---------+---------+----------+----------+
| min_len | max_len | avg_len  | COUNT(*) |
+---------+---------+----------+----------+
|     111 |     119 | 117.6789 | 10000000 |
+---------+---------+----------+----------+
1 row in set (41.19 sec)

Query OK, 0 rows affected (41.19 sec)
```

