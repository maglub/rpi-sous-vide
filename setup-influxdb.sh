#!/bin/bash

sudo apt-get update
sudo apt-get -y install apt-transport-https

curl -sL https://repos.influxdata.com/influxdb.key  | sudo apt-key add -
. /etc/os-release 

test $VERSION_ID = "7" && echo "deb https://repos.influxdata.com/debian wheezy stable" | sudo tee /etc/apt/sources.list.d/influxdb.list
test $VERSION_ID = "8" && echo "deb https://repos.influxdata.com/debian jessie stable" | sudo tee /etc/apt/sources.list.d/influxdb.list
test $VERSION_ID = "9" && echo "deb https://repos.influxdata.com/debian stretch stable" | sudo tee /etc/apt/sources.list.d/influxdb.list

sudo apt-get update && sudo apt-get -y install influxdb

case $VERSION_ID in
  7)
    sudo update-rc.d influxdb defaults
    sudo service influxdb start
    ;;
  *)
    sudo systemctl enable influxdb.service
    sudo systemctl start influxdb.service
    ;;
esac

#--- create the database
influx -execute "create database smoker;"

#--- enable logging of metrics to the influxdb
ln -s ../logging-available/influxdb.local bin/logging-enabled/influxdb.local

