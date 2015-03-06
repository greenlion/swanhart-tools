#!/bin/bash

# this script implements:
# http://dev.mysql.com/doc/refman/5.6/en/replication-features-temptables.html

# pass login parameters to this script the same way you would to mysql and mysqladmin

SLEEPTIME=".5"
VERBOSE=1

while [ 1 ]
do
  mysql -e 'STOP SLAVE SQL_THREAD;' "$@" || exit 1
  COUNT=`mysqladmin "$@" extended-status | grep Slave_open_temp_tables | cut -d "|" -f3 | cut -d ' ' -f2` || exit 1
  if [ "$COUNT" = "0" ]
  then
    echo "Shutting down MySQL"
    mysqladmin "$@" shutdown || exit 1
    exit 0
  fi
  if [ "$VERBOSE" != "0" ]; then echo "Slave still has $COUNT open temp tables"; fi;
  mysql -e 'START SLAVE SQL_THREAD;' "$@" || exit 1
  sleep $SLEEPTIME
done
exit 1 #should never reach here
