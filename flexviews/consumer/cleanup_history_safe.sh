#!/bin/bash

while true
do
  php cleanup_history.php --pid=cleanup_history.pid $* > /dev/null 2>&1 < /dev/null
  if [ $? -eq 0 ]; then
    exit 0
  fi
  sleep 1
done
