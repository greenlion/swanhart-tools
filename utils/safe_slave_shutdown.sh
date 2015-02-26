#!/bin/bash
HOST=$1
USER=$2
PASS=$3
if [ "$HOST" = "" ]
then
  HOST=""
else
  HOST="-h $HOST"
fi

if [ "$USER" = "" ]
then
  USER="-u root"
else
  USER="-u $USER"
fi

if [ "$PASS" = "" ]
then
  PASS=""
else
  PASS="-p $PASS"
fi

SHUTCMD="mysqladmin $USER $HOST $PASS shutdown"
echo $SHUTCMD

while [ 1 ] 
do
  COUNT=`mysqladmin $USER $HOST $PASS extended-status | grep Slave_open |cut -d "|" -f3 | cut -d ' ' -f2`
  if [ "$COUNT" = "0" ]
  then
    echo "Shutting down MySQL"
    $SHUTCMD || exit 1
    exit 0
  fi
  echo "Slave still has $COUNT open temp tables"
  sleep 1
done
exit 1 #should never reach here
