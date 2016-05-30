<?php

#-----------------------------------
# authenticateHttpAuth()
#-----------------------------------
function authenticateHttpAuth(){
  if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])){
        $result = authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if ($result) {
                        $_SESSION["username"] = "admin";
                        $_SESSION["role"] = "admin";
        }
  }
  return 0;
}

#-----------------------------------
# authenticate()
#-----------------------------------
function authenticate($username, $password){
  if ($username === "admin" && $password === "password"){
    return true;
  } else {
    return false;
  }
#	if (checkPassword('admin', genPasswordHash($password))){
#		return true;
#	} else {
#		return false;
#	}
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
?>
