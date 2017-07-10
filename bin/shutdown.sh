#!/bin/bash

this_dir=$(cd $(dirname $0); pwd)
. $this_dir/../conf/app.conf
. $this_dir/functions

#--- Zero the control files, to avoid accidents
echo 0 > $temperatureFile
echo 0 > $heaterDutyFile
echo 0 > $setpointFile 

#--- call functions in the functions file
killProcesses
setHeater off

