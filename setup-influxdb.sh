#!/bin/bash

this_dir=$(cd $(dirname $0);pwd)

. /etc/os-release 

echo -n "* Checking pre requisite apt-transport-https"
dpkg -s apt-transport-https >/dev/null 2>&1 || {
  echo "  - Installing apt-transport-https"
  sudo apt-get update
  sudo apt-get -y install apt-transport-https
}
echo "  - Done!"

echo -n "* Installing repo key for influxdb: "
curl -sL https://repos.influxdata.com/influxdb.key  | sudo apt-key add -
echo "  - Done!"


echo -n "* Checking for influxdb repo source"
[ ! -f /etc/apt/sources.list.d/influxdb.list ] && {
  echo "  - Adding repo for influxdb"

  test $VERSION_ID = "7" && echo "deb https://repos.influxdata.com/debian wheezy stable" | sudo tee /etc/apt/sources.list.d/influxdb.list
  test $VERSION_ID = "8" && echo "deb https://repos.influxdata.com/debian jessie stable" | sudo tee /etc/apt/sources.list.d/influxdb.list
  test $VERSION_ID = "9" && echo "deb https://repos.influxdata.com/debian stretch stable" | sudo tee /etc/apt/sources.list.d/influxdb.list
}
echo "  - Done!"

echo -n "* Checking if influxdb is installed"
dpkg -s influxdb >/dev/null 2>&1 || {
  echo "  - Installing influxdb"
  sudo apt-get update && sudo apt-get -y install influxdb
}
echo "  - Done!"

echo -n "* Enabling influxdb at boot"
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
echo "  - Done!"

#--- create the database
echo -n "* Checking for database: smoker"
influx -execute "show databases;" | grep -w smoker --silent || {
echo "  - Creating database: smoker"
influx -execute "create database smoker;"
}
echo "  - Done!"

#--- enable logging of metrics to the influxdb
echo -n "* Enabling influxdb logging for the application"
[ ! -h $this_dir/bin/logging-enabled/influxdb.local ] && {
  ln -s ../logging-available/influxdb.local $this_dir/bin/logging-enabled/influxdb.local
}
echo "  - Done!"

