<?php

global $root;
$vars = $_REQUEST;
require_once($root . "dbconfig.inc.php");

#-----------------------------------
# getAppConfig()
#-----------------------------------
function getAppConfig($configFile = __DIR__ . "/../etc/piLogger.conf"){
	#--- from http://inthebox.webmin.com/one-config-file-to-rule-them-all
	$file=$configFile;
	$lines = file($file);
	$config = array();
 
	foreach ($lines as $line_num=>$line) {
	  # Comment?
	  if ( ! preg_match("/#.*/", $line) ) {
	    # Contains non-whitespace?
	    if ( preg_match("/\S/", $line) ) {
	      list( $key, $value ) = str_replace('"','',explode( "=", trim( $line ), 2));
	      $config[$key] = $value;
	    }
	  }
	}
 
	return $config;
}


#=============================================================
# Sous vide specific functions
#=============================================================
function getTemperature(){
	$curRes = `/home/pi/rpi-sous-vide/bin/wrapper getTemperature`;	
	return $curRes;
} 
?>
