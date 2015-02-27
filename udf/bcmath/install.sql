-- aggregate sum
create aggregate function bcsum returns string soname 'udf_bcmath.so';

-- add two numbers
create function bcadd returns string soname 'udf_bcmath.so';

-- subtract two numbers
create function bcsub returns string soname 'udf_bcmath.so';

-- multiply two numbers
create function bcmul returns string soname 'udf_bcmath.so';

-- divide two numbers
create function bcdiv returns string soname 'udf_bcmath.so';

-- returns 0 if number is the same, neg if first is smaller than second
create function bccomp returns int soname 'udf_bcmath.so';
