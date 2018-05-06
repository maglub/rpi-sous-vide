<?php
	session_start();
        ini_set ( 'display_errors', 'On' );

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


        // SLIM v3.3 Middleware PSR7 Request-Response Objects
#        use Psr\Http\Message\ServerRequestInterface;
#        use Psr\Http\Message\ResponseInterface;


	require_once($root . "myfunctions.inc.php");
	require_once($root . "auth.inc.php");
	require_once($root."/../vendor/autoload.php");

#	$config = getAppConfig($root . "/../etc/rpi-sous-vide.conf");

#        //if run from the command-line
#        if ($_SERVER['HTTP_HOST'] === "cron"){
#                // Set up the environment so that Slim can route
#                $app->environment = Slim\Environment::mock([
#                    'PATH_INFO'   => $pathInfo
#                ]);
#        }

// ------------------------------------------------------
// --- instantiate Slim
// ------------------------------------------------------
$configuration = [
  'settings' => [
    'displayErrorDetails' => true,
    'debug' => true
  ]
];

#--- the template path for Twig/View
$templatePath = $root. '/templates';

$app = new \Slim\App ($configuration);
$container = $app->getContainer();

// Register component on container
$container ['view'] = function ($container) {
	global $templatePath;
	$view = new \Slim\Views\Twig ($templatePath);
	$view->addExtension ( new \Slim\Views\TwigExtension ( $container ['router'], $container ['request']->getUri () ) );
        $view->addExtension(new Knlv\Slim\Views\TwigMessages(new Slim\Flash\Messages()));


        #--- global variables for Twig/View
        #--- Note to self: Figure out if there is a way to pass global variables outside this function
        $view->getEnvironment()->addGlobal('isAuthenticated', isAuthenticated());
        $view->getEnvironment()->addGlobal('configAlert', configAlert());

	return $view;
};

// Register provider
$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

  #===============================================
  # Authenticate per HTTP AUTH if requested
  #  - i.e Nothing will happen if the user has not sent any username/password in the headers
  #  - note: perhaps this should be prohibited if the request is not done per https, as the
  #          credentials are hashed, but not encrypted
  #===============================================
#  authenticateHttpAuth();

#===================================================
# Debugging routes, to help with checking flash messages when
# figuring out replacing setGlobal (slim 2) with addGlobal (slim 3)
#===================================================
$app->get('/foo', function ($req, $res, $args) {
    // Set flash message for next request
    $this->flash->addMessage('Test', 'This is a message');

    // Redirect
    return $res->withStatus(302)->withHeader('Location', '/bar');
});

$app->get('/bar', function ($req, $res, $args) {
    // Get flash messages from previous request
    $messages = $this->flash->getMessages();
    print_r($messages);
});

#===================================================
# Main
#===================================================
$app->any('/', function ($request, $response, $args = []) {
  
  $processes = getProcesses();

  return $this->view->render($response, 'index.html', [ "temperature" => getTemperatureByFile(),
                                                        "setpoint" => getSetpointByFile(),
                                                        "heaterDuty" => getHeaterDutyByFile() ,
                                                        "processes" => $processes ]);
} )->setName('root');


#=============================================================
# /login
#=============================================================
$app->map(['GET','POST'], '/login', function ($request, $response) use ($app) {

  $username = null;
  if ($request->isPost() ) {
    $allPostPutVars = $request->getParsedBody();
    $username = "admin";
    $password = $allPostPutVars['password'];
    $result = authenticate($username, $password);

    if ($result) {
      $_SESSION["username"] = "admin";
      $_SESSION["role"] = "admin";
      $returnTo = "root";
      return $response->withStatus(302)->withHeader('Location',$request->getUri()->withPath($this->router->pathFor($returnTo)));
    } else {
      $messages = "Wrong password";
      $this->flash->addMessage('error', $messages);
      $returnTo = "login";
      return $response->withStatus(302)->withHeader('Location',$request->getUri()->withPath($this->router->pathFor($returnTo)));
    }
  }

  return $this->view->render($response, 'login.html', []);

})->setName('login');

$app->get('/logout', function($request, $response) use ($app){

  $_SESSION = array();
  session_destroy();

  $returnTo = "root";
  return $response->withStatus(302)->withHeader('Location',$request->getUri()->withPath($this->router->pathFor($returnTo)));

});

#=============================================================
# /action # route for actions
#=============================================================
$app->map( ['GET','POST'],'/action', function ($request, $response, $args = []) use ($app,$root) {
  $gitResult = "";

  if ($request->isPost() ) {

    //POST or PUT
    $allPostPutVars = $request->getParsedBody();
    foreach($allPostPutVars as $key => $param){
       //POST or PUT parameters list
    }

    $action = $allPostPutVars['action'];
    switch ($action) {
      case "Start":
        $res = startProcesses();
        break;

      case "Stop":
        $res = killProcesses();
        break;

      case "Temperature":
        $temperature = $allPostPutVars['temperature'];
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

      case "git-pull":
        $gitResult = gitPull();
        $this->flash->addMessage('gitresult', $gitResult['output']);
        break;

      case "genAliasFile":
        $res = genAliasFile();
        break;
    }

  }

  $returnTo = (isset($allPostPutVars['returnTo']))?$allPostPutVars['returnTo']:"root";
  return $response->withStatus(302)->withHeader('Location',$request->getUri()->withPath($this->router->pathFor($returnTo)));

})->setName('action');



#=============================================================
# /config
#=============================================================
//$app->map('/config', function () use ($app,$root) {
$app->map( ['GET','POST'],'/config', function ($request, $response, $args = []) use ($app,$root) {

  $gitResult['output'] = $this->flash->getMessage('gitresult')[0];

  $processes = getProcesses();
  $setpoint  = getSetpointByFile();
  $inputscripts = getPluginAvailable("input");
  $controlscripts = getPluginAvailable("control");
  $outputscripts = getPluginAvailable("output");
  $logscripts = getPluginAvailable("logging");
  $devices = getDevices();

  if (isset($gitResult['output'])) {
    $gitResult['parsedOutput'] = Parsedown::instance()->text(
                  "```\n" .
                  $gitResult['output'] .
                  "\n```"
		);
  } else {
    $gitResult = Array();
  }

  return $this->view->render($response, 'config.html',
                            [ "processes"=>$processes,
                              "setpoint"=>$setpoint,
                              "inputscripts" => $inputscripts,
                              "controlscripts" => $controlscripts,
                              "outputscripts" => $outputscripts,
                              "logscripts" => $logscripts,
                              "devices" => $devices,
                              "gitresult" => $gitResult
                              ]);

})->setName('config');

#====================================================
# REST API
#====================================================
$app->get('/api/temperature', function() use ($app, $root){

  $curRes = [ "temperature" => getTemperatureByFile() , "status" => "ok" ];
  echo json_encode($curRes);

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
  $curSetpoint    = getSetpointByFile();
  $curHeaterDuty  = getHeaterDutyByFile();

  $processes = getProcesses();

  $curRes = [ "kp"          => isset($curPid["pid_kp"])?$curPid["pid_kp"]:0,
              "ki"          => isset($curPid["pid_ki"])?$curPid["pid_ki"]:0,
              "kd"          => isset($curPid["pid_kd"])?$curPid["pid_kd"]:0,
              "outMin"      => isset($curPid["pid_outMin"])?$curPid["pid_outMin"]:0,
              "outMax"      => isset($curPid["pid_outMax"])?$curPid["pid_outMax"]:0,
              "temperature" => $curTemperature,
              "setpoint"    => $curSetpoint,
              "status"      => "ok" ,
              "processes"   => $processes,
              "heaterDuty"  => $curHeaterDuty
            ];

  echo json_encode($curRes);
  return 0;
});

$app->get('/api/pid', function() use ($app, $root){
  $curPid = getPid();
  $curRes = [ "kp"     => $curPid["pid_kp"],
              "ki"     => $curPid["pid_ki"],
              "kd"     => $curPid["pid_kd"],
              "outMin" => $curPid["pid_outMin"],
              "outMax" => $curPid["pid_outMax"],
              "status" => "ok" ];
  echo json_encode($curRes);
  return 0;
});

  $app->run();

?>
