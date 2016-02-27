#ASYNC - A tool for background parallel query execution for MySQL using ANY client

## To setup:
```
mysql -uroot < setup.sql
```
```
mysql -uroot -e 'set global event_scheduler=1';
```

## To add queries to queue:

Note: you must use fully qualified table names in your select statements, because otherwise the `async` schema will be used for all queries!
```
CALL async.queue('CREATE TABLE ...');
CALL async.queue('SELECT ...');
```

The queue function returns the QUERY_NUMBER assigned to the query for pickup later with the async.check and async.wait or async.wait_all or async.wait_list function.  It also places the QUERY_NUMBER into the variables @query_number and @query_list.

## To wait for a query
```CALL async.wait(QUERY_NUMBER);```

## To wait for all submitted queries in the order they were submitted
```CALL async.wait_all;```

## To wait for a specific list of queries in order
```CALL async.wait_list('1,2,3')```

