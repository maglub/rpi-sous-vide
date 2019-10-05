#!/bin/bash

this_dir=$(cd $(dirname $0); pwd)
. $this_dir/../conf/app.conf
. $this_dir/functions

[ -z "$bootSetPoint" ] && bootSetPoint=0

#--- Zero the control files, to avoid accidents
echo 0 > $temperatureFile
echo 0 > $heaterDutyFile
echo $bootSetPoint > $setpointFile 

cd /home/pi/rpi-sous-vide/bin

screen -d -m ./input
screen -d -m ./input-mcp3208-wrapper
screen -d -m ./control
screen -d -m ./output
