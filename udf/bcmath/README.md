This directory contains MySQL UDF for doing arbitrary precision math with the
number library from 'bc', the GNU Bench Calculator.

This work is based on bc and is released under the same license as bc (GPL).

Note: This library uses STRINGS to represent numbers.  Each function takes the 
scale for the computation as the first parameter.  When using the aggregate
stored routines, you may set @bc_scale to an integer value greater than zero
to use an arbitrary scale different from the default of 10.

## Basic bc functions that you can use to build more complicated functions
```
### fast aggregate sum (aggregate UDF instead of aggregate stored routine)
note: if using float our double, use FORMAT_AS_DECIMAL on the value being passed into this function (see below)
create /*M!100300 or replace*/ aggregate function bcsum returns string soname 'udf_bcmath.so';

### add two numbers
create /*M!100300 or replace*/ function bcadd returns string soname 'udf_bcmath.so';

### subtract two numbers
create /*M!100300 or replace*/ function bcsub returns string soname 'udf_bcmath.so';

### multiply two numbers
create /*M!100300 or replace*/ function bcmul returns string soname 'udf_bcmath.so';

### divide two numbers
create /*M!100300 or replace*/ function bcdiv returns string soname 'udf_bcmath.so';

### raise one number to another
create /*M!100300 or replace*/ function bcpow returns string soname 'udf_bcmath.so';

### square root
create /*M!100300 or replace*/ function bcsqrt returns string soname 'udf_bcmath.so';

### returns 0 if number is the same, neg if first is smaller than second
create /*M!100300 or replace*/ function bccomp returns int soname 'udf_bcmath.so';
```

Use the FORMAT_AS_DECIMAL(...) function to convert FLOAT or DOUBLE exponential values to decimal format.
The bc_stddev, bc_stddev_samp, bc_variance, bc_variance_samp, bc_min, and bc_max functions.
will automatically convert from exponential notation to decimal implicitly.
```
##This table contains a DOUBLE column which one pow(2,64) value and two pow(2,63) values.
##Because standard deviation uses squares of 64 bit values, there is an overflow and
##MySQL returns invalid values.
MariaDB [bcnum]> select * from t1;
+-----------------------+
| c1                    |
+-----------------------+
| 1.8446744073709552e19 |
|  9.223372036854776e18 |
|  9.223372036854776e18 |
+-----------------------+
3 rows in set (0.000 sec)
```

#Note that the output of the MySQL STDDEV() function is quite wrong!
```
MariaDB [bcnum]> select bc_stddev(c1),stddev(c1) from t1;
+------------------------------------------+-----------------------+
| bc_stddev(c1)                            | stddev(c1)            |
+------------------------------------------+-----------------------+
| 8695878550221854989.25238385459117182587 | 4.3479392751109274e18 |
+------------------------------------------+-----------------------+
1 row in set (0.001 sec)
```

See install for examples on how to use the functions.  You may set a desired scale with the @bc_scale 
client variable before calling the aggregate functions.
