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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;


	require_once($root . "myfunctions.inc.php");
	require_once($root . "auth.inc.php");
	require_once($root."/../vendor/autoload.php");

#	$config = getAppConfig($root . "/../etc/rpi-sous-vide.conf");

// ------------------------------------------------------
// --- instantiate Slim
// ------------------------------------------------------
$configuration = [
	'settings' => [
		'displayErrorDetails' => true,
		'debug' => true
	]
];
$container = new \Slim\Container ( $configuration );


$app = new \Slim\App ( $container );
$tpath = $root. '/templates';




#        //if run from the command-line
#        if ($_SERVER['HTTP_HOST'] === "cron"){
#                // Set up the environment so that Slim can route
#                $app->environment = Slim\Environment::mock([
#                    'PATH_INFO'   => $pathInfo
#                ]);
#        }

  #===============================================
  # Authenticate per HTTP AUTH if requested
  #  - i.e Nothing will happen if the user has not sent any username/password in the headers
  #  - note: perhaps this should be prohibited if the request is not done per https, as the
  #          credentials are hashed, but not encrypted
  #===============================================
  authenticateHttpAuth();

// Register component on container
$container ['view'] = function ($container) {
	global $tpath;
	$view = new \Slim\Views\Twig ($tpath);
	$view->addExtension ( new \Slim\Views\TwigExtension ( $container ['router'], $container ['request']->getUri () ) );
	return $view;
};


#===================================================
# Main
#===================================================

//$app->get('/', function (ServerRequestInterface $request, ResponseInterface $response, $args = []) use ($app) {
$app->any('/', function (ServerRequestInterface $request, ResponseInterface $response, $args = []) {
  
  $processes = getProcesses();

  return $this->view->render($response, 'index.html', [ "temperature" => getTemperatureByFile(),
                                                        "setpoint" => getSetpointByFile(),
                                                        "heaterDuty" => getHeaterDutyByFile() ,
                                                        "processes" => $processes ]);
} )->setName('root');

#=============================================================
# /config
#=============================================================
//$app->map('/config', function () use ($app,$root) {
$app->map( ['GET','POST'],'/config', function (ServerRequestInterface $request, ResponseInterface $response, $args = []) use ($app,$root) {

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
        break;

    }

    if ($action != "git-pull"){
      $returnTo = (isset($allPostPutVars['returnTo']))?$allPostPutVars['returnTo']:"config";
      return $response->withStatus(302)->withHeader('Location',$request->getUri()->withPath($this->router->pathFor($returnTo)));
    }


  }

  $processes = getProcesses();
  $setpoint  = getSetpointByFile();
  $logscripts = getLoggingAvailable();
  $devices = getDevices();

  if (isset($gitResult['output'])) {
    $gitResult['parsedOutput'] = Parsedown::instance()->text(
                  "```\n" .
                  $gitResult['output'] .
                  "\n```"
		);
  }

  return $this->view->render($response, 'config.html',
                            [ "processes"=>$processes,
                              "setpoint"=>$setpoint,
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
