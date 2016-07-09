/* 
  For a description of the functions see:
  CLIENT_README
*/

CREATE FUNCTION q_do RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION q_do_high RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION q_do_low RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION q_do_background RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION q_do_high_background RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
CREATE FUNCTION q_do_low_background RETURNS STRING
       SONAME "libgearman_mysql_udf.so";
