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

function getPluginInfo($type, $pluginName){
  $pluginInfo = `../bin/wrapper getPluginInfo {$type} {$pluginName}`;
  return $pluginInfo;
}


function isPluginEnabled($type, $pluginName){

  #--- input, output, control => only one can be enabled, symlink in ./bin
  #--- logging can have any number of plugins enabled, symlink in logging-enabled, nginx/lighttpd style

  $returnVal = false;

  switch ($type) {
    case "logging":
      $returnVal = isLoggingEnabled($pluginName);
      break;
    default:
      $binDir = __DIR__ . "/../bin";
      #--- the file "$type" has to exist in the bin directory, and symlink to a
      #--- file in the $type-avaiable directory
      if (file_exists($binDir . "/" . $type)){
        $symlinkDestination = readlink($binDir . "/" . $type);
        if ( $type . "-available/" . $pluginName == $symlinkDestination ) {
          $returnVal = true;
        } else {
          $returnVal = false;
        }
      } else {
        $returnVal = false;
      }
  }

  return $returnVal;
}

function getPluginAvailable($type){

  $pluginDir = __DIR__ . "/../bin/" . $type . "-available";
  $files = scandir($pluginDir);

  $scripts = Array();

  foreach ($files as $file) {
    if ($file != "." && $file != ".."){
      $scripts[] = [ 'name' => $file ] ;
    }
  }

  foreach ($scripts as &$script){
    $script['enabled'] = isPluginEnabled($type, $script['name']);
    $script['info'] = getPluginInfo($type, $script['name']);
  }

  return $scripts;
}

/*
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
*/

function getDeviceAlias($device){
  $alias = `../bin/wrapper getAlias {$device}`;
  return $alias;
}

function getDevices18s20(){
  $res = `../bin/wrapper getW1DevicePaths`;	

#  echo $res;
  $devices = explode("\n", $res);

  $res = Array();
  foreach ($devices as $device){
    if ($device != ""){
      $alias = getDeviceAlias($device);
      $temperature = getTemperatureByAliasByFile($alias);
      $res[] = ["devicepath" => $device, "alias" => $alias, "temperature" => $temperature ];
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

        if ($curRes == "Already up-to-date.\n") {
          $result['changed'] = false;
        } else {
          $result['changed'] = true;
        }

        $result['output'] = $curRes;

        return $result;
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

function getTemperatureByAliasByFile($alias){
	$curRes = `../bin/wrapper getTemperatureByAliasFromFile $alias`;
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

function genAliasFile(){
  $curRes = `sudo -u pi ../bin/wrapper genAliasFile`;

  return true;
}

function configAlert(){
  #--- this function will just check a couple of things that should/could be configured in the web gui

  $config = Array();

  #--- initial assumption is that all is ok
  $config["config_ok"] = true;

  #--- things to check
  $config["aliasesFile"] = file_exists("/home/pi/rpi-sous-vide/conf/aliases.conf") ;
  $config["grafanaInstalled"] = file_exists("/etc/grafana/grafana.ini") ;

  #--- if one or more things are not ok, set config_ok to false
  #--- only certain things should alert
  if (! $config["aliasesFile"]) { $config["config_ok"] = false ; };

#  #--- if one or more things are not ok, set config_ok to false
#  foreach ($config as $key => $value) {
#    print "$key = $value<br>\n";
#    if (! $value) { $config["config_ok"] = false ; }
#  } 

  return $config;
}


function getPid(){
  $config = getAppConfig();
  return $config;
}
?>
