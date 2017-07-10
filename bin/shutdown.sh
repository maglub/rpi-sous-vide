#!/bin/bash

this_dir=$(cd $(dirname $0); pwd)
. $this_dir/../conf/app.conf

#--- Zero the control files, to avoid accidents
echo 0 > $temperatureFile
echo 0 > $heaterDutyFile
echo 0 > $setpointFile 

/home/pi/rpi-sous-vide/bin/wrapper killProcesses
/home/pi/rpi-sous-vide/bin/wrapper setHeater off

