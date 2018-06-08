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
	$file_handler = new \Monolog\Handler\StreamHandler("../logs/samlidp.log");
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
	$user_id_cookie = isset( $_COOKIE[ \Samlidp\IdentityProvider::COOKIE_NAME ] ) ? $_COOKIE[ \Samlidp\IdentityProvider::COOKIE_NAME ] : null;
	if( empty( $user_id_cookie ) ){ // not yet logged in
		$response = $this->view->render($response, 'login.phtml', []);
	}
	else{ // already logged in; go to dashboard
		$response = $response->withStatus(302)->withHeader('Location', '/dashboard');
	}
	return $response;
});
$app->post('/login', function (Request $request, Response $response) {
	// set email in cookie
	$post = $request->getParsedBody();
	if( ! empty( $post['email'] ) ){
		setcookie( \Samlidp\IdentityProvider::COOKIE_NAME, $post['email'] );
		// redirect user to dashboard
		return $response->withStatus(302)->withHeader('Location', '/dashboard');
	}
	else{
		return $response->withStatus(400)->withHeader('Location', '/login');
	}
});
$app->get('/logout', function (Request $request, Response $response) {
	setcookie( \Samlidp\IdentityProvider::COOKIE_NAME, '' ); // clear cookie
	$response = $this->view->render($response, 'logout.phtml', []);
	return $response;
});
$app->get('/dashboard', function (Request $request, Response $response) {
	$user_id_cookie = isset( $_COOKIE[ \Samlidp\IdentityProvider::COOKIE_NAME ] ) ? $_COOKIE[ \Samlidp\IdentityProvider::COOKIE_NAME ] : null;
	if( empty( $user_id_cookie ) ){
		return $response->withStatus(302)->withHeader('Location', '/login');
	}
	else{
		$response = $this->view->render($response, 'dashboard.phtml', [ 'user_email' => $user_id_cookie ]);
	}
	return $response;
});
$app->get('/sso', function (Request $request, Response $response) {
	$this->logger->addInfo("sso");
	$idp = new \Samlidp\IdentityProvider();
	$response->getBody()->write( $idp->sso() );
	return $response;
});
$app->get('/metadata', function (Request $request, Response $response) {
	$this->logger->addInfo("metadata");
	$idp = new \Samlidp\IdentityProvider();
	$response->getBody()->write( $idp->metadata() );
	return $response;
});

/*  +-----------+
    |  RUN APP  |
    +-----------+  */
$app->run();
