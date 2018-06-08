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
	$file_handler = new \Monolog\Handler\StreamHandler("../logs/samlsp.log");
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
$app->get('/login', function (Request $request, Response $response) {
	$this->logger->addInfo("login");
	$sp = new \Samlsp\ServiceProvider();
	$response->getBody()->write( $sp->requestAuthenticationAssertion() );
	return $response;
});
$app->post('/consume', function (Request $request, Response $response) {
	// set email in cookie
	$post = $request->getParsedBody();
	$sp = new \Samlsp\ServiceProvider();
	$response->getBody()->write( $sp->validateAuthenticationAssertion() );
	if( ! empty( $post['email'] ) ){
		setcookie( \Samlsp\ServiceProvider::COOKIE_NAME, $post['email'] );
		// redirect user to dashboard
		return $response->withStatus(302)->withHeader('Location', '/dashboard');
	}
	else{
		return $response->withStatus(400)->withHeader('Location', '/login');
	}
});
$app->get('/logout', function (Request $request, Response $response) {
	setcookie( \Samlsp\ServiceProvider::COOKIE_NAME, '' ); // clear cookie
	$response = $this->view->render($response, 'logout.phtml', []);
	return $response;
});
$app->get('/dashboard', function (Request $request, Response $response) {
	$user_id_cookie = isset( $_COOKIE[ \Samlsp\ServiceProvider::COOKIE_NAME ] ) ? $_COOKIE[ \Samlsp\ServiceProvider::COOKIE_NAME ] : null;
	if( empty( $user_id_cookie ) ){
		return $response->withStatus(302)->withHeader('Location', '/login');
	}
	else{
		$response = $this->view->render($response, 'dashboard.phtml', [ 'user_email' => $user_id_cookie ]);
	}
	return $response;
});
$app->get('/metadata', function (Request $request, Response $response) {
	$this->logger->addInfo("metadata");
	$sp = new \Samlsp\ServiceProvider();
	$response->getBody()->write( $sp->metadata() );
	return $response;
});

/*  +-----------+
    |  RUN APP  |
    +-----------+  */
$app->run();
