<?php

namespace Lti;

require_once( getcwd() . '/LTI/OAuth.php');

// toggle to troubleshoot
$troubleshoot = false;
if( $troubleshoot ){
	ini_set('display_errors',1);
	ini_set('display_startup_errors',1);
	ini_set('error_reporting', E_ALL);
	error_reporting(E_ALL|E_STRICT);
}

/**
 * LTI v1 endpoint
 * */
class Lti_endpoint
{
	/**
	 * these properties are defined as constants in the lti_model
	 * */
	protected $_role_instructor = 'Instructor';
	protected $_role_learner    = 'Learner';
	protected $_role_system     = 'sysrole';
	protected $_role_observer   = 'observer';
	public $db; // @TODO connect to db to store info
	public $lti_model;
	public $lti_outcomes;

	/**
	 * custom constructor
	 * keeps lti values alive by storing lti data in lti_model and passing ltikey from request to request
	 * */
	public function __construct() {
		// load lti models
		$this->lti_model = new \Lti\lti_model();
		$this->lti_outcomes = new \Lti\lti_outcomes();
		// LTI TESTING
		if( isset( $_POST['oauth_consumer_key'] ) && $_POST['oauth_consumer_key'] == 'lti.sso.test' ){
			ini_set('display_errors', 1);
			ini_set('display_startup_errors', 1);
			error_reporting(E_ALL);
		}
		// put lti data into lti model
		$get_vars = $this->input->get( null, true );
		$post_vars = $this->input->post( null, true );
		$this->lti_model->getLtiData( $get_vars, $post_vars );
	}

/*  +----------------------------+
    |  DEFAULT ENDPOINT FOR LTI  |
    +----------------------------+  */
	/**
	 * Main LTI request endpoint.
	 * Expects LTI values posted directly from an LMS consumer.
	 * Validates the posted LTI request.
	 * Once validated and oauth authenticated, splits off into role workflows.
	 * */
	public function handlePost( $post ) {
		// -----------------
		// MANUAL VALIDATION
		// -----------------
		// check for direct post
		if( empty( $post ) ){
			$this->_error('No posted values were detected.');
			return;
		}
		// verify this is a valid lti request
		$messages = array();
		if( ! $this->lti_model->isValidLtiRequest( $post, $messages ) ) {
			$this->_error( $messages );
			return;
		}
		// Insure we have a valid consumer_key
		if( empty( $post['oauth_consumer_key'] ) ){
			$this->_error('Missing oauth_consumer_key in request.');
			return;
		}
		// retrieve the secret for the given consumer key
		$oauth_consumer_key = $post['oauth_consumer_key'];
		$secret = $this->lti_model->getSecret( $oauth_consumer_key );
		// If we don't have a matching oauth_consumer_key in our system, return with an error.
		if( $secret === false ){
			$error_text = "Your consumer key is not valid.";
			$this->_error($error_text);
			return;
		}
		// ----------------------------------
		// USE OAUTH TO VALIDATE TRANSMISSION
		// ----------------------------------
		// add consumer to the data store
		$store = new TrivialOAuthDataStore();
		$store->add_consumer($oauth_consumer_key, $secret);
		
		// Create an oauth server instance
		// passing the data store into its constructor
		$server = new OAuthServer($store);
		
		// Create an instance of the HMAC SHA1 signature method
		$method = new OAuthSignatureMethod_HMAC_SHA1();
		
		// Add the HMAC SHA1 method to the oauth server.
		$server->add_signature_method($method);
		
		// Create an oauth request from post data
		$request = OAuthRequest::from_request( null, 'https://lti.sso.test:443/' );
		
		// Verify the request setting the $validOauth to true on success
		// and returning with error on failure.
		try {
			$server->verify_request($request);
		}
		catch (Exception $e) {
			$this->_error($e->getMessage());
			return;
		}
		
		// replace any stored lti values with these newly posted values
		$this->lti_model->setLtiData( $post );
		
		// forward the user to the appropriate workflow
		$this->_forwardRole();
	}

	/**
	 * forward the current request to an appropriate workflow based on role
	 * @return void
	 * */
	protected function _forwardRole(){
		// get the user's primary role according to LTI
		$role = $this->lti_model->getPrimaryLtiRole();
		if( empty( $role ) ) {
			$this->_error( 'unexpected role: ' . $this->lti_model->getLtiDataValue('roles') );
			return;
		}
		switch( $role ) { // run tasks appropriate to the given role
			case $this->_role_instructor : $this->_runInstructor(); break;
			case $this->_role_learner    : $this->_runLearner();    break;
			case $this->_role_system     : $this->_runInstructor(); break;
			case $this->_role_observer   : $this->_runLearner();    break;
			default                      : $this->_runDefault();    break;
		}
	}

	/**
	 * run through learner role workflow
	 * @return void
	 */
	private function _runLearner(){
		$test = false;
		$messages = array();
/* +-------------------------------+
   |  RETRIEVE REQUESTED RESOURCE  |
   +-------------------------------+  */
		// display the requested resource
		$this->_displayResource();
	}

	/**
	 * Run through instructor workflow
	 * @return void
	 */
	private function _runInstructor(){
		$test = false;
		$messages = array();
/* +-------------------------------+
   |  RETRIEVE REQUESTED RESOURCE  |
   +-------------------------------+  */
		// display the requested resource
		$this->_displayResource();
	}

	/**
	 * stub for default action
	 * */
	private function _runDefault() {
		echo '_runDefault(). Hi, I am just a stub function and I need to be implemented.';
		return;
	}

	/**
	 * diplay the requested resource
	 * @return void
	 */
	private function _displayResource() {
		// determine which action to take
		$lti_message_type = $this->lti_model->getLtiDataValue('lti_message_type');
		switch( $lti_message_type ){
			case 'ContentItemSelectionRequest' : // take user to the content item selector
				$this->_getContentItemSelectionRequest();
				break;
			case 'basic-lti-launch-request' : // retrieve the requested resource
			default :
				// store LTI basic outcomes service details when an outcome is requested
				$this->lti_outcomes->saveLtiOutcomesValues();
				$this->getContent( $custom_resource_id );
		}
	}

	/**
	 * allow the user to build custom LTI links to service provider content
	 * @return void
	 * */
	private function _getContentItemSelectionRequest(){
		// get the ltikey
		$ltikey = $this->lti_model->getLtiKey();
		// with 303 redirect
		header( 'Location: ' . "https://lti.sso.test/search/lti_search?ltikey={$ltikey}" );
	}

	/**
	 * get content by its id
	 * @param int $id
	 * @return void
	 * */
	private function _getContent( $id ) {
		// get the ltikey
		$ltikey = $this->lti_model->getLtiKey();
		// with 303 redirect
		header( 'Location: ' . "https://lti.sso.test/content/{$container_id}?ltikey={$ltikey}" );
	}

	/**
	 * outcomes testing
	 * @param string $action [outcomes_action_replace|outcomes_action_read|outcomes_action_delete]
	 * @param string $test (optional) [request|response]
	 * */
	public function outcomes( $action, $test=null ){
		switch( $test ){
			case 'request' : // valid test
			case 'response' : // valid test
				break;
			default :
				$test=null; // no test
		}
		switch( $action ){
			case 'outcomes_action_replace' :
				$score = '0.95';
				$this->lti_outcomes->replaceResultRequest( $score, $test );
				break;
			case 'outcomes_action_read' :
				$this->lti_outcomes->readResultRequest( $test );
				break;
			case 'outcomes_action_delete' :
				$this->lti_outcomes->deleteResultRequest( $test );
				break;
		}
	}

	/**
	 * Error method
	 * @param mixed $message
	 */
	private function _error( $message ){
		if( is_array( $message ) ){
			$message = implode( "<br />\n",  $message );
		}
		$log = new \stdClass();
		$log->message = "lti v1 error: {$message}";
		$log->post = $_POST;
		if( empty( $log->post ) ){
			if( ! empty( $this->lti_model ) ){
				$log->post = $this->lti_model->getLtiData();
			}
		}
		$log->datetime = date( 'Y-m-d H:i:s' );
		$log->class = __CLASS__;
		$log->method = __METHOD__;
		$log->file = __FILE__;
		$encoded_log = json_encode( $log );
		error_log( $encoded_log );
		
		// return error message
		$return_url = $this->lti_model->getLtiDataValue( 'launch_presentation_return_url' );
		if( empty( $return_url ) && isset( $_POST['launch_presentation_return_url'] ) ){
			$return_url = $_POST['launch_presentation_return_url'];
		}
		if( ! empty( $return_url ) ){
			$request_params = array( 'lti_errormsg' => $message );
			$conjunction = ( strpos( $return_url, '?' ) === false ) ? '?' : '&';
			$redirect = $return_url . $conjunction . http_build_query( $request_params );
			header( 'Location: ' . $redirect );
			exit;
		}
		else{
			$message .= "<pre>" . print_r( $_POST, true ) . "</pre>";
			$data = array( 'error' => $message );
			var_dump( $data );
		}
	}

}
