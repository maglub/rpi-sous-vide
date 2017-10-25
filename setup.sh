#!/bin/bash

this_dir=$(cd `dirname $0`; pwd)
binDir=$this_dir/bin
configDir=$this_dir/conf

cat<<EOT

#================================
# setup directories
#================================
EOT

[ -z "$baseDir" ] && baseDir=/var/lib/rpi-sous-vide

[ -z "$dbDir" ]   && dbDir=${baseDir}/db
[ -z "$tmpFile" ] && tmpDir=${baseDir}/tmp

echo "* Setting up directories"
for dir in $baseDir $dbDir $tmpDir
do
  [ ! -d "$dir" ]     && { echo "  - Creating directory: $dir"           ; sudo mkdir -p "$dir"     ; sudo chown pi:pi "$dir" ; }
done

echo "  - Initializing the setpoint file"
[ -z "$setpointFile" ] && setpointFile="$tmpDir/setpoint"
[ ! -f "$setpointFile" ] && { echo 0 > $setpointFile ; chmod 777 $setpointFile ; }

cat<<EOT

#================================
# Config file etc/app.conf
#================================
EOT

echo "* Checking for config file $this_dir/etc/app.conf"

[ ! -f $this_dir/conf/app.conf ] && {

  echo "  - Copying template to $this_dir/etc/app.conf"
  cp $this_dir/conf/app.conf.template $this_dir/conf/app.conf 

}

cat<<EOT

#================================
# packages for php5 and lighttpd
#================================
EOT

echo "* Checking installed packages"

curInstallPackages=""

if [ -n "$(grep stretch /etc/os-release)" ]
then
for package in php-cgi php php-sqlite3 php-cli php-rrd php-curl lighttpd sqlite3 bc screen
  do
    echo -n "  - Checking $package"
    sudo dpkg -s $package >/dev/null 2>&1 || { echo "  - Adding package $package to the install list" ; curInstallPackages="$curInstallPackages $package" ; }
  done
else
  #--- older raspbian
  #--- note, php5-cgi has to come before php5. Otherwise apt-get will install apache2 to satisfy dependencies
  #--- https://wildlyinaccurate.com/installing-php-on-debian-without-apache/
  for package in php5-cgi php5 php5-sqlite php5-cli php5-rrd php5-curl lighttpd sqlite3 bc screen
  do
    echo -n "  - Checking $package"
    sudo dpkg -s $package >/dev/null 2>&1 || { echo "  - Adding package $package to the install list" ; curInstallPackages="$curInstallPackages $package" ; }
  done
fi

[ -n "$curInstallPackages" ] && { echo "  - Installing packages: $curInstallPackages" ; sudo apt-get -q -y install $curInstallPackages ; }

cat<<EOT

#================================
# Configuring lighttpd
#================================
EOT

echo "* Setting up symlinks"
[ -f /etc/lighttpd/lighttpd.conf ]  && { sudo mv /etc/lighttpd/lighttpd.conf /etc/lighttpd/lighttpd.conf.org ; sudo ln -s $configDir/lighttpd/lighttpd.conf /etc/lighttpd ; }
[ ! -h /etc/lighttpd/conf-enabled/10-cgi.conf ] && sudo ln -s $configDir/lighttpd/10-cgi.conf /etc/lighttpd/conf-enabled
[ ! -h /etc/lighttpd/conf-enabled/10-accesslog.conf ] && sudo ln -s ../conf-available/10-accesslog.conf /etc/lighttpd/conf-enabled
[ ! -h /etc/lighttpd/conf-enabled/10-dir-listing.conf ] && sudo ln -s ../conf-available/10-dir-listing.conf /etc/lighttpd/conf-enabled

echo "* Relaxing file permissions on logfiles"
sudo chmod 755 /var/log/lighttpd

#--- make sure Apache2 is disabled, if installed

dpkg -s apache2 >/dev/null 2>&1 && {
  echo -n "* Disabling apache2"
  sudo service apache2 stop >/dev/null 2>&1
  sudo update-rc.d apache2 disable > /dev/null 2>&1
  echo " - Done!"

  echo "* Restarting lighttpd"
  sudo service lighttpd restart
}

cat<<EOT

#================================
# Set up sqlite3 database
#================================
EOT

echo "* Checking if the database exists"

[ ! -f $dbDir/app.sqlite3 ] && {
  echo "  - Creating new sqlite3 database"
  sqlite3 /var/lib/rpi-sous-vide/db/app.sqlite3 "CREATE TABLE a(a number);"
}

cat<<EOT

#================================
# Setup sudoers
#================================
EOT

echo "* setting up the sudoers file"
visudo -c -f $configDir/sudoers.d/rpi-sous-vide  && {
  sudo cp $configDir/sudoers.d/rpi-sous-vide /etc/sudoers.d/rpi-sous-vide 
  sudo chown root:root /etc/sudoers.d/rpi-sous-vide 
  sudo chmod 0440 /etc/sudoers.d/rpi-sous-vide 
}

cat<<EOT

#================================
# Run composer to install php slim and its dependencies
#================================
EOT

[ ! -f /usr/local/bin/composer ] && {
  echo "* Fetching composer"
  curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer ;
}
cd $this_dir
composer install
cd -

cat<<EOT

#================================
# Setup input dependencies
#================================
EOT

echo "* Running $this_dir/bin/input --setup"

$this_dir/bin/input --setup

