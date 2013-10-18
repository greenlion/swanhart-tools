#!/bin/bash

while true
do
  #php run_consumer.php --pid=flexcdc.pid $* > /dev/null 2>&1 < /dev/null
  php run_consumer.php --pid=flexcdc.pid $*  2>&1 >> /root/flex_cdc_logs/flex_cdc_log.log 
  if [ $? -eq 0 ]; then
    exit 0
  fi
  echo "Restarting FlexCDC!"
	echo "Restarting FlexCDC!" | mail dwh_alerts@modcloth.com
  sleep 1000

done
