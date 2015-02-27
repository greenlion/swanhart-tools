This directory contains MySQL UDF for doing arbitrary precision math with the
number library from 'bc', the GNU Bench Calculator.

This work is based on bc and is released under the same license as bc (GPL).

Note: This library uses STRINGS to represent numbers.  This is similar to how
MySQL used to store DECIMAL numbers in the past, before DECIMAL was (poorly)
redesigned in 5.0.

If you try to pass NUMERIC intput to the functions YOU WILL GET AN
ERROR.  CAST to string instead!

For example, the following query does not cast c1 or c2 to integer and returns
an error:
select bcadd(30,cast(c1 as char(100)),cast(c2 as char(100))) from
test.tX;
ERROR 1123 (HY000): Can't initialize function 'bcadd'; CAST numeric inputs to CHAR or use string literals

This can be resolved by casting:
mysql> select bcsum(30,bcadd(30,cast(c1 as char(100)),cast(c2 as char(100)))) from test.tX;
+-------------------------------------------------------+
| bcadd(30,cast(c1 as char(100)),cast(c2 as char(100))) |
+-------------------------------------------------------+
| 2                                                     |
+-------------------------------------------------------+
1 row in set (0.00 sec)

You should find char(100) is large enough for any regular MySQL number (BIGINT, DOUBLE,
DECIMAL,etc).

If you want to be able to handle very large numbers, then cast intput for
CHAR(65535) or convert the columns to one of the TEXT data types if you have
very large numbers.
