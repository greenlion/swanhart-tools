#!/bin/bash
SLEEPTIME=".25"
VERBOSE=1
HOST=$1
USER=$2
PASS=$3
if [ "$HOST" != "" ]
then
  HOST="-h $HOST"
fi

if [ "$USER" = "" ]
then
  USER="-u root"
else
  USER="-u $USER"
fi

if [ "$PASS" != "" ]
then
  PASS="-p $PASS"
fi

SHUTCMD="mysqladmin $USER $HOST $PASS shutdown"

while [ 1 ] 
do
  COUNT=`mysqladmin $USER $HOST $PASS extended-status | grep Slave_open |cut -d "|" -f3 | cut -d ' ' -f2`
  if [ "$COUNT" = "0" ]
  then
    echo "Shutting down MySQL"
    $SHUTCMD || exit 1
    exit 0
  fi
  if [ "$VERBOSE" != "0" ]; then echo "Slave still has $COUNT open temp tables"; fi;
  sleep $SLEEPTIME
done
exit 1 #should never reach here
