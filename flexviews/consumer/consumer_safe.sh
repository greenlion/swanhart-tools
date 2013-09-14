#!/bin/bash

while true
do
  php run_consumer.php --pid=flexcdc.pid $* > /dev/null 2>&1 < /dev/null
  if [ $? -eq 0 ]; then
    exit 0
  fi
  echo "Restarting FlexCDC!"
  sleep 1
done
