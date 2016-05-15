#!/bin/bash

this_dir=$(cd `dirname $0`; pwd)
binDir=$this_dir/bin
configDir=$this_dir/conf


cat<<EOT
#================================
# Run composer
#================================
EOT
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
cd $this_dir
composer install
cd -

