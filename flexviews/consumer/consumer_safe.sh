#!/bin/bash
FAILURE_SLEEP_TIME=$(grep failure_sleep_time consumer.ini|cut -d= -f2)
FAILURE_ERROR_LOG=$(grep failure_error_log consumer.ini|cut -d= -f2)
FAILURE_EMAIL_ADDRESS=$(grep failure_email_address consumer.ini|cut -d= -f2)

if [ -z $FAILURE_SLEEP_TIME ]; then
  FAILURE_SLEEP_TIME=300
fi

if [ -z $FAILURE_ERROR_LOG ]; then 
  FAILURE_ERROR_LOG=flex_cdc_log.log
fi


while true
do
  php run_consumer.php --pid=flexcdc.pid $* 2>&1 >> $FAILURE_ERROR_LOG
  if [ $? -eq 0 ]; then
    exit 0
  fi
  sleep 1

  #if [ ! -z $FAILURE_EMAIL_ADDRESS ]; then
  #  FAILURE_EMAIL_BODY=$(tail flexcdc.err)
  #  echo $FAILURE_EMAIL_BODY | mail $FAILURE_EMAIL_ADDRESS
  #fi

  #echo "Restarting FlexCDC in $FAILURE_SLEEP_TIME seconds!"  
  #sleep $FAILURE_SLEEP_TIME
done
