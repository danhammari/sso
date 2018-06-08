<?php

namespace Samlsp;

class ServiceProvider
{
	/**
	 * constants
	 * */

	/**
	 * properties
	 * */
	public    $saml_settings = null;
	public    $auth          = null;
	public    $third_party   = null;
	protected $_client_key   = null;
	protected $_redirect_uri = null;

	/**
	 * constructor
	 * */
	public function __construct(){
		$saml_response = null;
		if( isset( $_POST['SAMLResponse'] ) ){
			$saml_response = $_POST['SAMLResponse'];
		}
		// get the saml settings for this client
		$this->saml_settings = $this->getSamlSettings();
		// set up auth object using the dynamically located settings
		$this->auth = new \OneLogin_Saml2_Auth( $this->saml_settings );
	}

	/**
	 * get SAML settings
	 * @return array
	 * */
	public function getSamlSettings(){
		if( empty( $this->saml_settings ) ){
			$settings = array();
			$settings['sp']  = $this->getServiceProviderSettings();
			$idp_found = $this->lookupIdpSettings();
			if( false && $idp_found ){ // @TODO store IdP settings in database
				$settings['idp'] = $this->getIdpSettingsFromDatabase();
			}
			else{
				$settings['idp'] = $this->getIdentityProviderSettings();
			}
			$this->saml_settings = $settings;
		}
		return $this->saml_settings;
	}

	/**
	 * get the service provider settings for SAML
	 * @return array
	 * */
	public function getServiceProviderSettings(){
		$service_provider_settings = array (
			'entityId' => "https://samlsp.sso.test/metadata",
			'assertionConsumerService' => array (
				'url' => "https://samlsp.sso.test/consume",
			),
			'singleLogoutService' => array (
				'url' => "https://samlsp.sso.test/sls",
			),
			'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:unspecified',
			'NameIDPolicy' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:unspecified',
			);
		return $service_provider_settings;
	}

	/**
	 * try to automatically detect the identity provider
	 * @return boolean
	 * */
	public function lookupIdpSettings(){
		$idp_found = false;
		// check to see if a client key value is already known
		if( ! empty( $this->_client_key ) ){
			$idp_found = true;
		}
		// check the SAML request to see if the identity provider was indicated
		if( ! $idp_found ){
			$client_key = $this->getClientKeyFromSamlResponse();
			if( ! empty( $client_key ) ){
				$this->_client_key = $client_key;
				$idp_found = true;
			}
		}
		return $idp_found;
	}

	/**
	 * get the client key
	 * @return string
	 * */
	public function getClientKey(){
		if( empty( $this->_client_key ) ){
			$idp_found = $this->lookupIdpSettings();
		}
		return $this->_client_key;
	}

	/**
	 * get client key from SAML Response
	 * @return string
	 * */
	public function getClientKeyFromSamlResponse(){
		$client_key = null;
		// detect client by base64-decoding the value posted as SAMLResponse
		try{
			if( ! empty( $_POST['SAMLResponse'] ) ){
				$saml_response_xml = base64_decode( $_POST['SAMLResponse'] );
				$dom_document = new \DOMDocument();
				$dom_document->loadXML( $saml_response_xml );
				if( $dom_document ){
					$saml_identifier_nodes = array(
						//   XML_NODE_NAME                  DB_COLUMN                          XML_NODE_ATTTRIBUTE
						'EntityDescriptor'   => array( 'db_column' => 'entityId', 'attribute' => 'entityID' ), // entityID
						'saml2:Issuer'       => array( 'db_column' => 'entityId', 'attribute' => false      ), // entityID
						'saml:Issuer'        => array( 'db_column' => 'entityId', 'attribute' => false      ), // entityID
						'Issuer'             => array( 'db_column' => 'entityId', 'attribute' => false      ), // entityID
						'ds:X509Certificate' => array( 'db_column' => 'x509cert', 'attribute' => false      ), // signing certificate
					);
					forEach( $saml_identifier_nodes as $node_name => $node_identifiers ){
						if( empty( $client_key ) ){
							$node_list = $dom_document->getElementsByTagName( $node_name );
							$node_list_length = $node_list->length;
							if( $node_list_length > 0 ){
								forEach( $node_list as $dom_node ){
									// determine if the DOMElement has a node_type
									if( property_exists( $dom_node, 'node_type' ) ){
										// use the node_type to determine which properties to check
										switch( $dom_node->node_type ){
											case XML_ELEMENT_NODE : // DOMElement
												$attribute_name = $node_identifiers['attribute'];
												if( $attribute_name !== false && $dom_node->hasAttribute( $attribute_name ) ){
													$dom_attr = $dom_node->getAttributeNode( $attribute_name );
													if( $dom_attr && $dom_attr->node_type == XML_ATTRIBUTE_NODE ){
														$value = $dom_attr->value;
													}
													else{
														$attributes = $dom_node->attributes; // DOMNamedNodeMap
														$value = $attributes->getNamedItem( $attribute_name )->nodeValue;
													}
												}
												else{
													$value = $dom_node->nodeValue;
												}
												break;
											case XML_ATTRIBUTE_NODE : // DOMAttr
												$value = $dom_node->value;
												break;
											case XML_TEXT_NODE : // DOMText
											default :
												$value = $dom_node->nodeValue;
												break;
										}
										// @TODO look up client key in database connection
										$temp_client_key = null; // add logic to lookup client key in database
										if( ! empty( $temp_client_key ) ){
											$client_key = $temp_client_key;
											break;
										}
									}
									else{ // if do not know what type of DOMElement
										$attribute_name = $node_identifiers['attribute'];
										if( $attribute_name !== false && $dom_node->hasAttribute( $attribute_name ) ){
											$dom_attr = $dom_node->getAttributeNode( $attribute_name );
											if( $dom_attr && $dom_attr->node_type == XML_ATTRIBUTE_NODE ){
												$value = $dom_attr->value;
											}
											else{
												$attributes = $dom_node->attributes; // DOMNamedNodeMap
												$value = $attributes->getNamedItem( $attribute_name )->nodeValue;
											}
										}
										else{
											$value = $dom_node->nodeValue;
										}
										// @TODO look up client key in database connection
										$temp_client_key = null; // add logic to lookup client key in database
										if( ! empty( $temp_client_key ) ){
											$client_key = $temp_client_key;
											break;
										}
									}
								}
							}
						}
					}
				}
			}
		}
		catch( \Exception $e ){
			// ignore exceptions
		}
		return $client_key;
	}

	/**
	 * get field value from user data
	 * @param string $field_name
	 * @return mixed
	 * */
	public function getFieldValueFromUserData( $field_name ){
		$field_value = '';
		$userdata = $this->auth->getAttributes();
		if( array_key_exists( $field_name, $userdata ) ){
			$field_value = $userdata[ $field_name ];
		}
		return $field_value;
	}

	/**
	 * use client key value to retrieve identity provider (IdP) configuration settings from database table
	 * @param string $client_key (optional)
	 * @return array
	 * */
	public function getIdpSettingsFromDatabase( $client_key=null){
		// @TODO hook into database where IdP settings are copied
		$idp_settings = array();
		if( empty( $client_key ) ){
			$client_key = $this->_client_key;
		}
		if( ! empty( $client_key ) ){
			// @TODO fetch data table row for identity provider
			$temp_settings = array();
			if( ! empty( $temp_settings ) ){
				forEach( $temp_settings AS $property => $value ){
					switch( $property ){
						case 'client_key' :
							$this->_client_key = $value;
							break;
						case 'third_party' :
							$this->third_party = $value;
							break;
						case 'entityId' :
						case 'x509cert' :
							$idp_settings[ $property ] = $value;
							break;
						case 'ArtifactResolutionService' :
						case 'AssertionConsumerService' :
						case 'AttributeService' :
						case 'singleSignOnService' :
						case 'singleLogoutService' :
							if( ! empty( $value ) ){
								$idp_settings[ $property ] = array( 'url' => $value );
							}
							break;
					}
				}
			}
		}
		return $idp_settings;
	}

	/**
	 * scrape identity provider metadata to get settings
	 * @return array
	 * */
	public function getIdentityProviderSettings(){
		$idp_settings = [];
		$url = 'https://samlidp.sso.test/metadata';
		try{
			$curl_handle = curl_init();
			curl_setopt( $curl_handle, CURLOPT_URL,           $url );
			curl_setopt( $curl_handle, CURLOPT_PORT,          8081 );
			curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER,   1 );
			curl_setopt( $curl_handle, CURLOPT_SSL_VERIFYHOST,   0 );
			curl_setopt( $curl_handle, CURLOPT_SSL_VERIFYSTATUS, 0 );
			curl_setopt( $curl_handle, CURLOPT_SSL_VERIFYPEER,   0 );
			curl_setopt( $curl_handle, CURLOPT_FOLLOWLOCATION,   1 );
			curl_setopt( $curl_handle, CURLOPT_VERBOSE,          1 );
			curl_setopt( $curl_handle, CURLOPT_HEADER,           1 );
			$idp_metadata = curl_exec( $curl_handle );
			curl_close( $curl_handle );
			var_dump( $idp_metadata );die( __FILE__.__LINE__ );
			if( ! empty( $idp_metadata ) ){
				$dom_document = new \DOMDocument();
				$dom_document->loadXML( $idp_metadata );
				if( $dom_document ){
					$saml_values = [
						'entityId'                  => null,
						'ArtifactResolutionService' => null,
						'AssertionConsumerService'  => null,
						'AttributeService'          => null,
						'singleSignOnService'       => null,
						'singleLogoutService'       => null,
						'x509cert'                  => null,
					];
					$saml_identifier_nodes = array(
					//  XML_NODE_NAME                         SAML_VALUES                                  XML_NODE_ATTTRIBUTE
						'EntityDescriptor'          => array( 'saml_value' => 'entityId',                  'attribute' => 'entityID' ), // entityID
						'saml2:Issuer'              => array( 'saml_value' => 'entityId',                  'attribute' => false      ), // entityID
						'saml:Issuer'               => array( 'saml_value' => 'entityId',                  'attribute' => false      ), // entityID
						'Issuer'                    => array( 'saml_value' => 'entityId',                  'attribute' => false      ), // entityID
						'ArtifactResolutionService' => array( 'saml_value' => 'ArtifactResolutionService', 'attribute' => 'Location' ), // ArtifactResolutionService
						'AssertionConsumerService'  => array( 'saml_value' => 'AssertionConsumerService',  'attribute' => 'Location' ), // AssertionConsumerService
						'AttributeService'          => array( 'saml_value' => 'AttributeService',          'attribute' => 'Location' ), // AttributeService
						'singleSignOnService'       => array( 'saml_value' => 'SingleSignOnService',       'attribute' => 'Location' ), // singleSignOnService
						'singleLogoutService'       => array( 'saml_value' => 'singleLogoutService',       'attribute' => 'Location' ), // singleLogoutService
						'ds:X509Certificate'        => array( 'saml_value' => 'x509cert',                  'attribute' => false      ), // signing certificate
					);
					forEach( $saml_identifier_nodes as $node_name => $node_identifiers ){
						if( empty( $saml_values[ $node_identifiers['saml_value'] ] ) ){
							$node_list = $dom_document->getElementsByTagName( $node_name );
							$node_list_length = $node_list->length;
							if( $node_list_length > 0 ){
								forEach( $node_list as $dom_node ){
									$value = null;
									// determine if the DOMElement has a node_type
									if( property_exists( $dom_node, 'node_type' ) ){
										// use the node_type to determine which properties to check
										switch( $dom_node->node_type ){
											case XML_ELEMENT_NODE : // DOMElement
												$attribute_name = $node_identifiers['attribute'];
												if( $attribute_name !== false && $dom_node->hasAttribute( $attribute_name ) ){
													$dom_attr = $dom_node->getAttributeNode( $attribute_name );
													if( $dom_attr && $dom_attr->node_type == XML_ATTRIBUTE_NODE ){
														$value = $dom_attr->value;
													}
													else{
														$attributes = $dom_node->attributes; // DOMNamedNodeMap
														$value = $attributes->getNamedItem( $attribute_name )->nodeValue;
													}
												}
												else{
													$value = $dom_node->nodeValue;
												}
												break;
											case XML_ATTRIBUTE_NODE : // DOMAttr
												$value = $dom_node->value;
												break;
											case XML_TEXT_NODE : // DOMText
											default :
												$value = $dom_node->nodeValue;
												break;
										}
									}
									else{ // if do not know what type of DOMElement
										$attribute_name = $node_identifiers['attribute'];
										if( $attribute_name !== false && $dom_node->hasAttribute( $attribute_name ) ){
											$dom_attr = $dom_node->getAttributeNode( $attribute_name );
											if( $dom_attr && $dom_attr->node_type == XML_ATTRIBUTE_NODE ){
												$value = $dom_attr->value;
											}
											else{
												$attributes = $dom_node->attributes; // DOMNamedNodeMap
												$value = $attributes->getNamedItem( $attribute_name )->nodeValue;
											}
										}
										else{
											$value = $dom_node->nodeValue;
										}
									}
									if( ! empty( $value ) ){
										$saml_values[ $node_identifiers['saml_value'] ] = $value;
									}
								}
							}
						}
					}
				}
			}
		}
		catch( \Exception $e ){
			// ignore exceptions
		}
		echo "<pre>"; var_dump( $saml_values );die(__FILE__.__LINE__);
		$idp_settings = array (
			'entityId' => $saml_values['entityId'],
			'ArtifactResolutionService' => array(
				'url' => $saml_values['ArtifactResolutionService'],
			),
			'AssertionConsumerService' => array(
				'url' => $saml_values['AssertionConsumerService'],
			),
			'AttributeService' => array(
				'url' => $saml_values['AttributeService'],
			),
			'singleSignOnService' => array (
				'url' => $saml_values['singleSignOnService'],
			),
			'singleLogoutService' => array (
				'url' => $saml_values['singleLogoutService'],
			),
			'x509cert' => $saml_values['x509cert'],
		);
		// @TODO store the scraped values in the database for ease of reference
		return $idp_settings;
	}
}
