<?php

namespace Samlidp;

class IdentityProvider
{
	/**
	 * constants
	 * */
	const ACCOUNT_IDENTIFIER = '123456789';
	const CERTIFICATE_DIR  = 'certificates';
	const X509_CERTIFICATE = 'saml.crt';
	const SAML_PRIVATE_KEY = 'saml.pem';
	const OPENSSL_SUBJECT  = '/C=US/ST=Utah/L=SaltLakeCity/O=OpenWest/OU=IT/CN=samlidp.sso.test';
	const X509_SUBJECT     = 'C=US, ST=Utah, L=SaltLakeCity, O=OpenWest, emailAddress=helpdesk@openwest.org';
	const ASSERTION_ISSUER = 'https://samlidp.sso.test/metadata.php'; // idp entity id
	const CUSTOMER_CENTER  = 'https://samlsp.sso.test/consume.php'; // sp service consume endpoint
	const SERVICE_PROVIDER = 'https://samlsp.sso.test/sp.php'; // sp entity id
	const COOKIE_NAME      = 'samlidp_identifier';

	/**
	 * properties
	 * */
	protected $_certificate_path;
	protected $_x509_certificate_path;
	protected $_saml_private_key_path;

	/**
	 * constructor
	 * */
	public function __construct(){
		// make sure certificates are available
		$this->_x509_certificate_path = $this->getSamlCertificateFilePath( self::X509_CERTIFICATE );
		$this->_saml_private_key_path = $this->getSamlCertificateFilePath( self::SAML_PRIVATE_KEY );
		if( ! file_exists( $this->_x509_certificate_path ) || ! file_exists( $this->_saml_private_key_path ) ){
			//  --------------------------------------------------------------------------------
			//  to generate the saml.crt and saml.pem files:
			//  openssl req -new -x509 -nodes -sha256 -days 11499 -out saml.crt -keyout saml.pem
			//  --------------------------------------------------------------------------------
			$openssl_subject = self::OPENSSL_SUBJECT;
			$success = exec( "openssl req -new -x509 -nodes -sha256 -days 11499 -out {$this->_x509_certificate_path} -keyout {$this->_saml_private_key_path} -subj {$openssl_subject}" );
		}
	}

	/**
	 * get the path to the certificate directory
	 * */
	public function getCertificateDirectoryPath(){
		if( empty( $this->_certificate_path ) ){
			$this->_certificate_path = realpath( __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . self::CERTIFICATE_DIR );
		}
		return $this->_certificate_path;
	}

	/**
	 * get the filepath for a saml security file
	 * @param string $filename
	 * @return string
	 * */
	public function getSamlCertificateFilePath( $filename ){
		$filepath = $this->getCertificateDirectoryPath();
		switch( $filename ){
			case self::X509_CERTIFICATE :
			case self::SAML_PRIVATE_KEY :
				return $filepath . DIRECTORY_SEPARATOR . $filename;
				break;
			default :
				throw new \Exception( 'unknown saml security file: ' . $filename );
		}
	}

	/**
	 * get SAML IdP configuration settings
	 * @return \LightSaml\Builder\Profile\Metadata\
	 * */
	public function getIdpConfiguration(){
		// Load IdP configuration
		$entity_uri = 'https://samlidp.sso.test/metadata';
		$acs_uri = 'https://samlidp.sso.test/acs';
		$sso_uri = 'https://samlidp.sso.test/sso';
		$idp_configuration = array(
			'entity_id' => $entity_uri,
			'assertion_consumer_service_location' => $acs_uri,
			'single_sign_on_location' => $sso_uri,
			'x509cert' => \LightSaml\Credential\X509Certificate::fromFile( $this->getSamlCertificateFilePath( self::X509_CERTIFICATE ) ),
		);
		return $idp_configuration;
	}

	/**
	 * endpoint for idp metadata requests
	 * @return xml
	 * */
	public function metadata(){
		try {
			$idp_configuration = $this->getIdpConfiguration();
			$entity_descriptor_builder = new \LightSaml\Builder\EntityDescriptor\SimpleEntityDescriptorBuilder(
				$idp_configuration['entity_id'],
				$idp_configuration['assertion_consumer_service_location'],
				$idp_configuration['single_sign_on_location'],
				$idp_configuration['x509cert']
			);
			$entity_description = $entity_descriptor_builder->get();
			$serialization_context = new \LightSaml\Model\Context\SerializationContext();
			$entity_description->serialize( $serialization_context->getDocument(), $serialization_context );
			header('Content-Type: text/xml');
			echo $serialization_context->getDocument()->saveXML();
			exit(0);
		}
		catch ( Exception $e ) {
			echo $e->getMessage();
		}
	}

	/**
	 * single sign on service
	 * @param int $authentication_request_id
	 * @return void
	 * */
	public function sso( $authentication_request_id=null ){
		// determine if user is already logged in
		$user_identifier = null;
		$user_id_cookie = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? $_COOKIE[ self::COOKIE_NAME ] : null;
		if( $user_id_cookie ){
			$user_identifier = $user_id_cookie;
		}
		if( empty( $user_identifier ) ){ // not logged in
			// send to login screen if not yet logged in
			$login_uri = 'https://samlidp.sso.test/login.php';
			header( "location: {$login_uri}" );
			exit();
		}
		else{ // get user's identifier and forward to service with authentication request id
			$connect_to_db = false;
			if( $connect_to_db ){
				$sql = "SELECT email FROM user WHERE email = :user_identifier";
				$bindings = [ 'user_identifier' => $user_identifier ];
				$db = (object)[ 'db_name' => 'fake' ]; // @TODO connect to a real database
				$resource = $db->query( $sql, $bindings );
				if( $resource && $resource->numRows() > 0 ){
					$resource->setFetchMode( $db::FETCH_ASSOC );
					$row = $resource->fetch();
					$email_address = $row['email'];
				}
			}
			else{
				// for now we simply use the email address as the user identifier
				$email_address = $user_identifier;
			}
			$this->serviceRedirect( $email_address, $authentication_request_id );
		}
	}

	/**
	 * assertion consumer service
	 * */
	public function acs(){
		// assertion consumer service
	}

	/**
	 * single log out service
	 * */
	public function slo(){
		// log out user
	}

	/**
	 * SP-initiated authentication request
	 * */
	public function requestAuth(){
		try {
			$idp_configuration = $this->getIdpConfiguration();
			$idp_issuer = new \LightSaml\Model\Assertion\Issuer( self::ASSERTION_ISSUER );
			$authentication_request = new \LightSaml\Model\Protocol\AuthnRequest();
			$sso_uri = 'https://samlidp.sso.test/sso';
			$authentication_request
				->setAssertionConsumerServiceURL( $idp_configuration['assertion_consumer_service_location'] )
				->setProtocolBinding( \LightSaml\SamlConstants::BINDING_SAML2_HTTP_POST )
				->setId( \LightSaml\Helper::generateID() )
				->setIssueInstant( new \DateTime() )
				->setDestination( $sso_uri )
				->setIssuer( $idp_issuer )
			;
/*  +----------------+
    |  SIGN REQUEST  |
    +----------------+  */
			$x509_certificate = \LightSaml\Credential\X509Certificate::fromFile( $this->getSamlCertificateFilePath( self::X509_CERTIFICATE ) );
			$private_key = \LightSaml\Credential\KeyHelper::createPrivateKey( $this->getSamlCertificateFilePath( self::SAML_PRIVATE_KEY ), '', true );
			$authentication_request->setSignature( new \LightSaml\Model\XmlDSig\SignatureWriter( $x509_certificate, $private_key ) );
/*  +-----------------+
    |  EXPORT AS XML  |
    +-----------------+  */
			$export = false;
			if( $export ){
				$serialization_context = new \LightSaml\Model\Context\SerializationContext();
				$authentication_request->serialize( $serialization_context->getDocument(), $serialization_context );
				header('Content-Type: text/xml');
				echo $serialization_context->getDocument()->saveXML();
				exit;
			}
/*  +--------------------+
    |  REDIRECT BINDING  |
    +--------------------+  */
			$binding_factory = new \LightSaml\Binding\BindingFactory();
			$redirect_binding = $binding_factory->create( \LightSaml\SamlConstants::BINDING_SAML2_HTTP_POST );
			$message_context = new \LightSaml\Context\Profile\MessageContext();
			$message_context->setMessage( $authentication_request );
			$http_response = $redirect_binding->send( $message_context );
			echo $http_response->getContent();
		}
		catch ( Exception $e ) {
			echo $e->getMessage();
		}
	}

	/**
	 * IdP-initiated auth assertion redirect
	 * @param string $email_address
	 * @param int $authentication_request_id (optional)
	 * @param int $session_index (optional)
	 * @return void
	 * */
	public function serviceRedirect( $email_address, $authentication_request_id=null, $session_index=null ){
		if( empty( $authentication_request_id ) ){
			$authentication_request_id = '';
		}
		try {
/*  +-------------+
    |  ASSERTION  |
    +-------------+  */
			$assertion = new \LightSaml\Model\Assertion\Assertion();
			$assertion
				->setId( \LightSaml\Helper::generateID() )
				->setIssueInstant( new \DateTime() )
				->setIssuer( new \LightSaml\Model\Assertion\Issuer( self::ASSERTION_ISSUER ) )
			;
/*  +-----------+
    |  SUBJECT  |
    +-----------+  */
			$subject = new \LightSaml\Model\Assertion\Subject();
			$subject
				->setNameID(
					( new \LightSaml\Model\Assertion\NameID() )
						->setFormat( \LightSaml\SamlConstants::NAME_ID_FORMAT_TRANSIENT )
						->setValue( self::X509_SUBJECT )
				)
				->addSubjectConfirmation(
					( new \LightSaml\Model\Assertion\SubjectConfirmation() )
						->setMethod( \LightSaml\SamlConstants::CONFIRMATION_METHOD_BEARER )
						->setSubjectConfirmationData(
							( new \LightSaml\Model\Assertion\SubjectConfirmationData() )
								->setInResponseTo( $authentication_request_id )
								->setNotOnOrAfter( new \DateTime( '+3 MINUTE' ) )
								->setRecipient( self::CUSTOMER_CENTER )
						)
				)
			;
			$assertion->setSubject( $subject );
/*  +--------------+
    |  CONDITIONS  |
    +--------------+  */
			$conditions = new \LightSaml\Model\Assertion\Conditions();
			$conditions
				->setNotBefore( new \DateTime() )
				->setNotOnOrAfter( new \DateTime( '+3 MINUTE' ) )
				->addItem( new \LightSaml\Model\Assertion\AudienceRestriction( [ self::SERVICE_PROVIDER ] ) )
			;
			$assertion->setConditions( $conditions );
/*  +---------+
    |  ITEMS  |
    +---------+  */
			// attribute statement
			$attribute_statement = new \LightSaml\Model\Assertion\AttributeStatement();
			$attribute_statement
				->addAttribute(
					( new \LightSaml\Model\Assertion\Attribute() )
						->setName( 'email' )
						->setAttributeValue( $email_address )
				)
				->addAttribute(
					( new \LightSaml\Model\Assertion\Attribute() )
						->setName( 'account' )
						->setAttributeValue( self::ACCOUNT_IDENTIFIER )
				)
			;
			$assertion->addItem( $attribute_statement );
			// authentication statement with authentication context
			$authentication_context = new \LightSaml\Model\Assertion\AuthnContext();
			$authentication_context->setAuthnContextClassRef( \LightSaml\SamlConstants::AUTHN_CONTEXT_PASSWORD_PROTECTED_TRANSPORT );
			$authentication_statement = new \LightSaml\Model\Assertion\AuthnStatement();
			$authentication_statement
				->setAuthnInstant( new \DateTime( '-10 MINUTE' ) )
				->setSessionIndex( $session_index )
				->setAuthnContext( $authentication_context )
			;
			$assertion->addItem( $authentication_statement );
/*  +------------------+
    |  SIGN ASSERTION  |
    +------------------+  */
			$x509_certificate = \LightSaml\Credential\X509Certificate::fromFile( $this->getSamlCertificateFilePath( self::X509_CERTIFICATE ) );
			$private_key = \LightSaml\Credential\KeyHelper::createPrivateKey( $this->getSamlCertificateFilePath( self::SAML_PRIVATE_KEY ), '', true );
			$assertion->setSignature( new \LightSaml\Model\XmlDSig\SignatureWriter( $x509_certificate, $private_key ) );
/*  +------------+
    |  RESPONSE  |
    +------------+  */
			$response = new \LightSaml\Model\Protocol\Response();
			$response
				->addAssertion( $assertion )
				->setStatus( new \LightSaml\Model\Protocol\Status( new \LightSaml\Model\Protocol\StatusCode( \LightSaml\SamlConstants::STATUS_SUCCESS ) ) )
				->setID( \LightSaml\Helper::generateID() )
				->setIssueInstant( new \DateTime() )
				->setDestination( self::CUSTOMER_CENTER )
				->setIssuer( new \LightSaml\Model\Assertion\Issuer( self::ASSERTION_ISSUER ) )
			;
/*  +-----------------+
    |  SIGN RESPONSE  |
    +-----------------+  */
//			$x509_certificate = \LightSaml\Credential\X509Certificate::fromFile( $this->getSamlCertificateFilePath( self::X509_CERTIFICATE ) );
//			$private_key = \LightSaml\Credential\KeyHelper::createPrivateKey( $this->getSamlCertificateFilePath( self::SAML_PRIVATE_KEY ), '', true );
			$response->setSignature( new \LightSaml\Model\XmlDSig\SignatureWriter( $x509_certificate, $private_key ) );
/*  +-----------------+
    |  EXPORT AS XML  |
    +-----------------+  */
			$export = false;
			if( $export ){
				$serialization_context = new \LightSaml\Model\Context\SerializationContext();
				$response->serialize( $serialization_context->getDocument(), $serialization_context );
				header('Content-Type: text/xml');
				echo $serialization_context->getDocument()->saveXML();
				exit;
			}
/*  +--------------------+
    |  REDIRECT BINDING  |
    +--------------------+  */
			$binding_factory = new \LightSaml\Binding\BindingFactory();
			$redirect_binding = $binding_factory->create( \LightSaml\SamlConstants::BINDING_SAML2_HTTP_POST );
			$message_context = new \LightSaml\Context\Profile\MessageContext();
			$message_context->setMessage( $response );
			$http_response = $redirect_binding->send( $message_context );
			echo $http_response->getContent();
		}
		catch ( Exception $e ) {
			echo $e->getMessage();
		}
	}

}
