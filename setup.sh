#!/bin/bash

this_dir=$(cd `dirname $0`; pwd)
binDir=$this_dir/bin
configDir=$this_dir/conf

#cat<<EOT
##================================
## Install ansible
##================================
#EOT
#sudo apt-get install gcc libffi-dev libssl-dev python-dev
#sudo easy_install pip
#sudo pip install ansible
#sudo apt-get -y install ansible

cat<<EOT
#================================
# php5 lighttpd
#================================
EOT
curInstallPackages=""
for package in php5-cgi php5 php5-sqlite php5-cli php5-rrd php5-curl lighttpd
do
  sudo dpkg -s $package >/dev/null 2>&1 || { echo "  - Adding package $package to the install list" ; curInstallPackages="$curInstallPackages $package" ; }
done

[ -n "$curInstallPackages" ] && { echo "  - Installing packages: $curInstallPackages" ; sudo apt-get -q -y install $curInstallPackages ; }

[ -f /etc/lighttpd/lighttpd.conf ]  && { sudo mv /etc/lighttpd/lighttpd.conf /etc/lighttpd/lighttpd.conf.org ; sudo ln -s $configDir/lighttpd/lighttpd.conf /etc/lighttpd ; }
[ ! -h /etc/lighttpd/conf-enabled/10-accesslog.conf ] && sudo ln -s $configDir/lighttpd/conf-enabled/10-accesslog.conf /etc/lighttpd/conf-enabled
[ ! -h /etc/lighttpd/conf-enabled/10-dir-listing.conf ] && sudo ln -s $configDir/lighttpd/conf-enabled/10-dir-listing.conf /etc/lighttpd/conf-enabled
[ ! -h /etc/lighttpd/conf-enabled/10-cgi.conf ] && sudo ln -s $configDir/lighttpd/conf-enabled/10-cgi.conf /etc/lighttpd/conf-enabled

#--- make sure Apache2 is disabled, if installed

dpkg -s apache2 >/dev/null 2>&1 && {
  sudo service apache2 stop
  sudo update-rc.d apache2 disable > /dev/null
  sudo service lighttpd restart
}

cat<<EOT
#================================
# Run composer
#================================
EOT
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
cd $this_dir
composer install
cd -

