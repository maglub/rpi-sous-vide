#!/bin/bash

this_dir=$(cd $(dirname $0); pwd)
. $this_dir/../conf/app.conf
. $this_dir/functions

ps -ef | grep [S]CREEN
