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


function isLoggingEnabled($logName){

  $loggingEnabledDir = __DIR__ . "/../bin/logging-enabled";
  if (file_exists($loggingEnabledDir . "/" . $logName)){
    return true;
  } else {
    return false;
  }

}

function getLoggingAvailable(){

  $loggingAvailableDir = __DIR__ . "/../bin/logging-available";
  $files = scandir($loggingAvailableDir);

  $logscripts = Array();

  foreach ($files as $file) {
    if ($file != "." && $file != ".."){
      $logscripts[] = [ 'name' => $file ] ;
    }
  }

  foreach ($logscripts as &$logscript){
    $logscript['enabled'] = isLoggingEnabled($logscript['name']);
  }

  return $logscripts;
}

function getDevices18s20(){
  $res = `../bin/wrapper getW1DevicePaths`;	

#  echo $res;
  $devices = explode("\n", $res);

  $res = Array();
  foreach ($devices as $device){
    if ($device != ""){
      $alias = `../bin/wrapper getAlias {$device}`;
      $res[] = ["devicepath" => $device, "alias" => $alias];
    }
  }

  return $res;
}

function getDevices($type = "18s20"){

  $devices=Array();

  switch($type){

    case "18s20":
      $res = getDevices18s20();
      break;
  }

  return $res;

}

#=============================================================
# Maintenance functions
#=============================================================
function gitPull(){
	$curRes = `sudo -u pi ../bin/wrapper gitPull`;	
        return $curRes;
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

function setSetpoint($temperature = 0){
	$curRes = `sudo -u pi ../bin/wrapper setSetpoint {$temperature}`;
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

function getProcesses(){

        $ret['input']   = array("status"=>"not running", "pid"=>"");
        $ret['control'] = array("status"=>"not running", "pid"=>"");
        $ret['output']  = array("status"=>"not running", "pid"=>"");

        #--- find all processes in the form "SCREEN -d -m input" for input, control, and output
        exec('ps ahxwwo pid:1,command:1 | grep -E "[S]CREEN.*(input|control|output)" | sed -e "s/SCREEN -d -m \.\///"', $curRes);

        foreach ($curRes as $row) {
          $exp = explode(" ", $row);
          $ret[$exp[1]]['pid'] = $exp[0] ;
          $ret[$exp[1]]['status'] = "running"; 
        }

        return $ret;
}


function getPid(){
  $config = getAppConfig();
  return $config;
}
?>
