#!/bin/bash

this_dir=$(cd $(dirname $0);pwd)

. /etc/os-release 

echo -n "* Checking pre requisites apt-transport-https jq"
dpkg -s apt-transport-https jq >/dev/null 2>&1 || {
  echo "  - Installing apt-transport-https and jq"
  sudo apt-get update
  sudo apt-get -y install apt-transport-https jq
}
echo "  - Done!"

echo -n "* Checking for repo key for grafana: "
apt-key list | grep "Bintray" --silent || {
  echo -n "* Installing repo key for grafana: "
  curl --silent https://bintray.com/user/downloadSubjectPublicKey?username=bintray | sudo apt-key add -
}
echo "  - Done!"

echo -n "* Checking for grafana repo source"
[ ! -f /etc/apt/sources.list.d/grafana.list ] && {
  echo "  - Adding repo for influxdb"

  test $VERSION_ID = "7" && echo "deb https://dl.bintray.com/fg2it/deb wheezy main" | sudo tee -a /etc/apt/sources.list.d/grafana.list
  test $VERSION_ID = "8" && echo "deb https://dl.bintray.com/fg2it/deb jessie main" | sudo tee -a /etc/apt/sources.list.d/grafana.list
  test $VERSION_ID = "9" && echo "deb https://dl.bintray.com/fg2it/deb stretch main" | sudo tee -a /etc/apt/sources.list.d/grafana.list
}
echo "  - Done!"

echo -n "* Checking if grafana is installed"
dpkg -s grafana >/dev/null 2>&1 || {
  echo "  - Installing grafana"
  sudo apt-get update && sudo apt-get -y install grafana

}
echo "  - Done!"

sudo tail -5 /etc/grafana/grafana.ini | grep "auth.anonymous" --silent || {
echo "  - Configuring grafana"
#--- remove login page (no authentication, beware of internetz)
cat<<EOT | sudo tee -a /etc/grafana/grafana.ini
[auth.anonymous]
enabled = true
org_name = Main Org.
org_role = Admin
EOT
}


echo -n "* Enabling grafana at boot"
case $VERSION_ID in
  7)
    sudo update-rc.d grafana-server defaults
    sudo service grafana-server start
    ;;
  *)
    sudo /bin/systemctl daemon-reload
    sudo systemctl enable grafana-server.service
    sudo systemctl start grafana-server.service
    ;;
esac
echo "  - Done!"

echo -n "* Waiting 3 seconds for grafana to start properly"
sleep 3
echo "  - Done!"

echo "* Setting up datasource and dashboard"


echo "  - Deleting data source: smoker"
#--- delete datasource (this works without cookies, since we removed authentification above)
curl --silent -X DELETE 'http://localhost:3000/api/datasources/name/smoker'

echo
echo "  - Adding data source: smoker"
#--- add datasource (this works without cookies, since we removed authentification above)
curl --silent -H 'Content-Type: application/json;charset=UTF-8' -X POST --data-binary '{"Name":"smoker","Type":"influxdb","Access":"proxy","url":"http://localhost:8086","database":"smoker","basicAuth":false,"isDefault":true}' http://localhost:3000/api/datasources/

echo
echo "  - Delete dashboard: smoker"
#--- delete dashboard
curl --silent -X DELETE 'http://localhost:3000/api/dashboards/db/smoker'

echo
echo "  - Adding dashboard: smoker"
#--- add dashboard
curl --silent -H 'Content-Type: application/json;charset=UTF-8' 'http://localhost:3000/api/dashboards/db/' -X POST -d @./bin/smoker.dashboard.json

echo
echo "  - Setting dashboard as home: smoker"
#--- set the dashboard  as Home
curl --silent -H 'Content-Type: application/json;charset=UTF-8' 'http://localhost:3000/api/user/preferences/' -X PUT --data-binary '{"homeDashboardId":'$(curl --silent http://localhost:3000/api/dashboards/db/smoker | jq '.dashboard.id')'}'

echo "  - Done!"

