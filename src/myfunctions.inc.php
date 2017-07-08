<?php

global $root;
$vars = $_REQUEST;
require_once($root . "dbconfig.inc.php");

#-----------------------------------
# getAppConfig()
#-----------------------------------
function getAppConfig($configFile = __DIR__ . "/../conf/app.conf"){
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
	$curRes = `../bin/wrapper getTemperature`;	
	return $curRes;
} 

function getTemperatureByFile(){
	$curRes = `../bin/wrapper getTemperatureFromFile`;
	return $curRes;
}

function getSetpointByFile(){
	$curRes = `../bin/wrapper getSetpointFromFile`;
	return $curRes;

}

function getHeaterDutyByFile(){
	$curRes = `../bin/wrapper getHeaterDuty`;
	return $curRes;
}

function getInputRunning(){
	$curRes = `../bin/wrapper isInputRunning`;
	return ($curRes == "0" )?true:false;
}

function getControlRunning(){
	$curRes = `../bin/wrapper isControlRunning`;
	return ($curRes == 0 )?true:false;
}

function getOutputRunning(){
	$curRes = `../bin/wrapper isOutputRunning`;
	return ($curRes == 0 )?true:false;
}

function getInputPid(){
	$curRes = `../bin/wrapper getProcessId input`;
	return $curRes;
}

function getControlPid(){
	$curRes = `../bin/wrapper getProcessId control`;
	return $curRes;
}

function getOutputPid(){
	$curRes = `../bin/wrapper getProcessId output`;
	return $curRes;
}

function killProcesses(){
	$curRes = `sudo -u pi ../bin/wrapper killProcesses`;
	return $curRes;
}

function startProcesses(){
	$curRes = `sudo -u pi ../bin/wrapper startProcesses`;
	return $curRes;
}

function getPid(){
  $config = getAppConfig();
  return $config;
}
?>
