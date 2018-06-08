<?php
/*  +-----------+
    |  ALIASES  |
    +-----------+  */
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/*  +-----------------------+
    |  COMPOSER AUTOLOADER  |
    +-----------------------+  */
require_once '/composer/vendor/autoload.php';

/*  +------------------+
    |  SLIM FRAMEWORK  |
    +------------------+  */
$config['displayErrorDetails'] = true;
$app = new \Slim\App(["settings" => $config]);

/*  +------------------------+
    |  DEPENDENCY INJECTION  |
    +------------------------+  */
$container = $app->getContainer();
$container['view'] = new \Slim\Views\PhpRenderer('../templates/');
$container['logger'] = function($c) {
	$logger = new \Monolog\Logger('my_logger');
	$file_handler = new \Monolog\Handler\StreamHandler("../logs/facebook.log");
	$logger->pushHandler($file_handler);
	return $logger;
};

/*  +------------------+
    |  ROUTE HANDLING  |
    +------------------+  */
$app->get('/', function (Request $request, Response $response) {
	$response = $this->view->render($response, 'index.phtml', []);
	return $response;
});

/*  +-----------+
    |  RUN APP  |
    +-----------+  */
$app->run();
