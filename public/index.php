<?php
	session_start();

        //set up environment for cli
        if (!(isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== "")) {
                $_SERVER['HTTP_HOST'] = "cron";
                // add ".." to the directory name to point to "./html"
                $_SERVER['DOCUMENT_ROOT'] = __DIR__ . "/..";
                $argv = $GLOBALS['argv'];
                array_shift($GLOBALS['argv']);
                $pathInfo = $argv[0];
        }

	require_once("./stub.php");

	require_once($root . "myfunctions.inc.php");
	require_once($root . "auth.inc.php");
	require_once($root."/../vendor/autoload.php");
#	$config = getAppConfig($root . "/../etc/rpi-sous-vide.conf");

        #--- instantiate Slim and SlimJson
        $app = new \Slim\Slim(array(
             'templates.path' => $root . '/templates')
        );

        //if run from the command-line
        if ($_SERVER['HTTP_HOST'] === "cron"){
                // Set up the environment so that Slim can route
                $app->environment = Slim\Environment::mock([
                    'PATH_INFO'   => $pathInfo
                ]);
        }

  #===============================================
  # Authenticate per HTTP AUTH if requested
  #  - i.e Nothing will happen if the user has not sent any username/password in the headers
  #  - note: perhaps this should be prohibited if the request is not done per https, as the
  #          credentials are hashed, but not encrypted
  #===============================================
  authenticateHttpAuth();

// define the engine used for the view
$app->view(new \Slim\Views\Twig());

// configure Twig template engine
$app->view->parserOptions = array(
   'charset' => 'utf-8',
   'cache' => realpath('templates/cache'),
   'auto_reload' => true,
   'strict_variables' => false,
   'autoescape' => true
);

$app->view->parserExtensions = array(new \Slim\Views\TwigExtension());

$twig = $app->view()->getEnvironment();
$twig->addGlobal('devicename', gethostname());
$twig->addGlobal('isOffline', (isset($config['isOffline']) && $config['isOffline'] == "true")?true:false);
#$twig->addGlobal('config', $config);
$twig->addGlobal('isAuthenticated', isAuthenticated());

#===================================================
# Main
#===================================================

$app->get('/', function () use ($app) {
  
  $app->render('index.html', [ "temperature" => getTemperatureByFile(), "setpoint" => getSetpointByFile(), "heaterDuty" => getHeaterDutyByFile() ]);
})->name("root");

#=============================================================
# /config
#=============================================================
$app->map('/config', function () use ($app,$root) {
  if ($app->request()->isPost()) {

    #--- actionTemperature

    #--- actionProcesses
    $action = $app->request->post('action');
    switch ($action) {
      case "Start":
        $res = startProcesses(); 
        break;

      case "Stop":
        $res = killProcesses(); 
        break;

      case "Temperature":
        $temperature = $app->request->post('temperature');
        $res = setSetpoint($temperature);
        break;

      case "Temperature0":
        $res = setSetpoint(0);
        break;

      case "Temperature70":
        $res = setSetpoint(70);
        break;

      case "Temperature80":
        $res = setSetpoint(80);
        break;

      case "Temperature90":
        $res = setSetpoint(90);
        break;

      case "Temperature100":
        $res = setSetpoint(100);
        break;

    }
   $returnTo = ($app->request->post('returnTo') != null)?$app->request->post('returnTo'):"config"; 
   $app->redirect($app->urlFor($returnTo));
  }

/*
  $processes['input']['status'] = getInputRunning();
  $processes['input']['pid'] = getInputPid();
  $processes['control']['status'] = getControlRunning();
  $processes['control']['pid'] = getControlPid();
  $processes['output']['status'] = getOutputRunning();
  $processes['output']['pid'] = getOutputPid();
*/

  $processes = getProcesses();

  $setpoint = getSetpointByFile();

  $app->render('config.html', ["processes"=>$processes, "setpoint"=>$setpoint]);

})->via('GET', 'POST')->name('config');

#=============================================================
# /login
#=============================================================
$app->map('/login', function () use ($app) {

    $username = null;

    if ($app->request()->isPost()) {
        $username = "admin";
        $password = $app->request->post('password');

	$result = authenticate($username, $password);
        #$result = $app->authenticator->authenticate($username, $password);

        if ($result) {
			$_SESSION["username"] = "admin";
			$_SESSION["role"] = "admin";
            $app->redirect('/');
        } else {
            $messages = "Wrong password";
            $app->flashNow('error', $messages);
        }
    }

    $app->render('login.html', []);
})->via('GET', 'POST')->name('login');

$app->get('/logout', function() use ($app){
	$_SESSION = array();
	session_destroy();
    $app->redirect('/');
	
});

$app->get('/admin', function () use ($app, $root) {
    $crontab = shell_exec("sudo -u pi ${root}/../bin/wrapper getCrontab 2>/dev/null");
   $app->render('admin.html', [ "crontab" => $crontab ]);
});

#====================================================
# REST API
#====================================================
$app->get('/api/temperature', function() use ($app, $root){

#  if (isAuthenticated()){
    #$curRes = [ "temperature" => getTemperature() , "status" => "ok" ];
  $curRes = [ "temperature" => getTemperatureByFile() , "status" => "ok" ];
  echo json_encode($curRes);

#    echo "{\"temperature\":\"{$curRes}\", \"status\":\"ok\"}\n";
#  } else {
#    echo "{\"status\":\"error\", \"message\":\"not authenticated\"}\n";
#  }
  return 0;
});

$app->get('/api/setpoint', function() use ($app, $root){
  $curRes = [ "setpoint" => getSetpointByFile() , "status" => "ok" ];
  echo json_encode($curRes);
  return 0;
});

$app->get('/api/heaterduty', function() use ($app, $root){
  $curRes = [ "heaterduty" => getHeaterDutyByFile() , "status" => "ok" ];
  echo json_encode($curRes);
  return 0;
});

$app->get('/api/all', function() use ($app, $root){
  $curPid = getPid();
  $curTemperature = getTemperatureByFile();
  $curSetpoint = getSetpointByFile();
  $curHeaterDuty = getHeaterDutyByFile();
  $inputRunning = getInputRunning();
  $controlRunning = getControlRunning();
  $outputRunning = getOutputRunning();

  $curRes = [ "kp"          => isset($curPid["pid_kp"])?$curPid["pid_kp"]:0,
              "ki"          => isset($curPid["pid_ki"])?$curPid["pid_ki"]:0,
              "kd"          => isset($curPid["pid_kd"])?$curPid["pid_kd"]:0,
              "outMin"      => isset($curPid["pid_outMin"])?$curPid["pid_outMin"]:0,
              "outMax"      => isset($curPid["pid_outMax"])?$curPid["pid_outMax"]:0,
              "temperature" => $curTemperature,
              "setpoint"    => $curSetpoint,
              "status"      => "ok" ,
              "inputRunning"    => $inputRunning,
              "controlRunning"  => $controlRunning,
              "outputRunning"   => $outputRunning,
              "heaterDuty"  => $curHeaterDuty ];

  echo json_encode($curRes);
  return 0;
});

$app->get('/api/pid', function() use ($app, $root){
  $curPid = getPid();
  $curRes = [ "kp" => $curPid["pid_kp"], "ki" => $curPid["pid_ki"], "kd" => $curPid["pid_kd"], "outMin" => $curPid["pid_outMin"], "outMax" => $curPid["pid_outMax"],  "status" => "ok" ];
  echo json_encode($curRes);
  return 0;
});

  $app->run();

?>
