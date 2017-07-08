#!/bin/bash

this_dir=$(cd `dirname $0`; pwd)
binDir=$this_dir/bin
configDir=$this_dir/conf

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
for package in php5-cgi php5 php5-sqlite php5-cli php5-rrd php5-curl lighttpd
do
  echo "  - Checking $package"
  sudo dpkg -s $package >/dev/null 2>&1 || { echo "  - Adding package $package to the install list" ; curInstallPackages="$curInstallPackages $package" ; }
done

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
# Run composer
#================================
EOT

echo "* Fetching composer"
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
cd $this_dir
composer install
cd -

