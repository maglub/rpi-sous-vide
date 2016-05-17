<?php

global $root;
$vars = $_REQUEST;
require_once($root . "dbconfig.inc.php");

#-----------------------------------
# authenticate()
#-----------------------------------
function authenticate($username, $password){
	if (checkPassword('admin', genPasswordHash($password))){
		return true;
	} else {
		return false;
	}
}

#-----------------------------------
# isAuthenticated()
#-----------------------------------
function isAuthenticated(){
	if (isset($_SESSION['username']) && $_SESSION['username'] == "admin"){
		return true;
	} else {
		return false;
	}
}

#-----------------------------------
# getAdminPassword()
#-----------------------------------
function checkPassword($userName, $passwordHash){
  global $db;

  $sql = "select username from passwd where username = :userName and password = :passwordHash";
  $sth = $db->prepare($sql);
  $sth->execute(array(':passwordHash' => $passwordHash, ':userName'=> $userName));

  if($row = $sth->fetch()){
    $userNameResult = $row['username']; 
  }
  
  return ($userName == $userNameResult)?true:false;
}

#-----------------------------------
# genPasswordHash()
#-----------------------------------
function genPasswordHash($password){
  $hash = crypt( $password , 'rpi-sous-vide' );
  return $hash;
}

#-----------------------------------
# dropPasswdTable()
#-----------------------------------
function dropPasswdTable(){
  global $db;
	
  $sql = "drop table if exists passwd";
  $sth = $db->exec($sql);
	
  return 0;
}

#-----------------------------------
# createPasswdTable()
#-----------------------------------
function createPasswdTable(){
  global $db;
	
  $sql = "create table if not exists passwd(uid integer, username string, password string);";
  $sth = $db->exec($sql);

  return 0;
}

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
	
	return 0;
} 
?>