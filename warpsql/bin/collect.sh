#!/bin/bash
mpstat -P ALL 5 1 2>&1 > /tmp/mpstat.txt &
iostat -kx 5 2 2>&1 > /tmp/iostat.txt &
top -bn2 2>&1 > /tmp/top.txt &
wait
php process_update.php




