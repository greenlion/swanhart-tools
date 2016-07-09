##WarpSQL OLAP Suite - 
You can run jobs from your queries:

The most common way to invoke the tool is:
SELECT q_do(WORKER_NAME, ARGUMENT); 

Here is how to get the JSON formatted results of
a query, processed by the WarpSQL MPP layer.
Of course the WarpSQL MPP layer must be 
installed for this to work.

SET @json := q_do("mpp_query", "select count(*) from partitioned table");

There is a helper function to put the results into
a table that can be queried:
warp_mpp.json_to_table(table_name, schema_name, json_result);

or get a resultset:
warp_mpp.json_to_resultset(json_result);


SELECT q_do_background("reverse", Host, Host) AS test FROM mysql.user;

If you ever need to remove the functions from MySQL, you can run:

DROP FUNCTION q_do;
DROP FUNCTION q_do_high;
DROP FUNCTION q_do_low;
DROP FUNCTION q_do_background;
DROP FUNCTION q_do_high_background;
DROP FUNCTION q_do_low_background;
DROP FUNCTION q_sum;
DROP FUNCTION q_servers_set;

Enjoy!
-Eric
eday@oddments.org
*/
