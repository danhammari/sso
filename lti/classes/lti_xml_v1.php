<?php

namespace Lti;

/**
 * LTI XML model for LTI version 1
 * https://www.imsglobal.org/specs/lti/xml
 * https://www.imsglobal.org/specs/ltiv1p2/implementation-guide
 * */
class Lti_xml_v1
{
	/**
	 * constants
	 * */
	const LTI_VERSION = '1';
	const XML_VERSION = '1.0';
	const XML_ENCODING = 'UTF-8';
	const CONFIGURATION_CUSTOM = 'custom';
	const CONFIGURATION_DEFAULT = 'common_cartridge';
	const CONFIGURATION_BASIC_LTI = 'basic_lti_link';
	const CONFIGURATION_PUBLICATION = 'publication';
	const CONFIGURATION_COMMON_CARTRIDGE = 'common_cartridge';
	const CONFIGURATION_THIN_COMMON_CARTRIDGE = 'thin_common_cartridge';
	const CONFIGURATION_LTI_BASIC_OUTCOMES = 'lti_basic_outcomes';

	const OUTCOMES_ACTION_REPLACE = 'outcomes_action_replace';
	const OUTCOMES_ACTION_READ = 'outcomes_action_read';
	const OUTCOMES_ACTION_DELETE = 'outcomes_action_delete';
	const OUTCOMES_PROPERTY_ACTION = 'outcomes_property_action';
	const OUTCOMES_PROPERTY_MESSAGE_ID = 'outcomes_property_message_identifier';
	const OUTCOMES_PROPERTY_SOURCED_ID = 'outcomes_property_sourced_id';
	const OUTCOMES_PROPERTY_SCORE = 'outcomes_property_score';

	/**
	 * properties
	 * */
	protected $_configuration_name;
	protected $_custom_configuration;
	protected $_configuration_properties;

	protected $_outcomes_action;
	protected $_outcomes_message_identifier;
	protected $_outcomes_sourced_id;
	protected $_outcomes_score;
	

	/**
	 * set the current configuration_name
	 * @param string $configuration_name
	 * @return boolean
	 * */
	public function setConfiguration( $configuration_name ){
		$success = false;
		switch( $configuration_name ){
			case self::CONFIGURATION_CUSTOM :
			case self::CONFIGURATION_BASIC_LTI :
			case self::CONFIGURATION_PUBLICATION :
			case self::CONFIGURATION_COMMON_CARTRIDGE :
			case self::CONFIGURATION_THIN_COMMON_CARTRIDGE :
			case self::CONFIGURATION_LTI_BASIC_OUTCOMES :
				$this->_configuration_name = $configuration_name;
				$success = true;
		}
		return $success;
	}

	/**
	 * get the current configuration_name
	 * @return string
	 * */
	public function getConfigurationName(){
		if( empty( $this->_configuration_name ) ){
			$this->_configuration_name = self::CONFIGURATION_DEFAULT;
		}
		return $this->_configuration_name;
	}

	/**
	 * set a custom configuration
	 * @param array $configuration_properties
	 * @return void
	 * */
	public function setCustomConfiguration( $configuration_properties ){
		$this->_custom_configuration = $configuration_properties;
		$this->setConfiguration( self::CONFIGURATION_CUSTOM );
	}

	/**
	 * check to see if a configuration has a given namespace
	 * @param string $namespace
	 * @param array $configuration_properties (optional)
	 * @return boolean
	 * */
	public function configurationHasNamespace( $namespace, $configuration_name=null ){
		$configuration_properties = $this->getConfigurationProperties( $configuration_name );
		if( ! empty( $configuration_properties['namespaces'] ) ){
			forEach( $configuration_properties['namespaces'] as $namespace_hierarchy => $namespace_properties ){
				if( ! empty( $namespace_properties['name'] ) && $namespace == $namespace_properties['name'] ){
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * get the namespaces to build the xml document
	 * @param string $configuration_name (optional)
	 * @return array
	 * */
	public function getConfigurationProperties( $configuration_name=null ){
		if( empty( $configuration_name ) ){
			$configuration_name = $this->getConfigurationName();
		}
		// get expected configuration already stored
		if( ! empty( $this->_configuration_properties ) && $this->getConfigurationName() == $configuration_name ){
			$configuration_properties = $this->_configuration_properties;
		}
		else{ // get the specified LTI configuration by configuration_name
			switch( $configuration_name ){
				case self::CONFIGURATION_CUSTOM :
					if( ! empty( $this->_custom_configuration ) ){
						$configuration_properties = $this->_custom_configuration;
					}
					break;
				case self::CONFIGURATION_THIN_COMMON_CARTRIDGE :
					$configuration_properties = array( 
						'root' => 'cartridge_basiclti_link',
						'namespaces' => array(
							'xmlns'       => array( 'name' => 'xmlns', 'xml' => 'http://www.imsglobal.org/xsd/imslticc_v1p3',          'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p3/imslticc_v1p3.xsd'    ),
							'xmlns:blti'  => array( 'name' => 'blti',  'xml' => 'http://www.imsglobal.org/xsd/imsbasiclti_v1p0',       'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd' ),
							'xmlns:cclti' => array( 'name' => 'cclti', 'xml' => 'http://ltsc.ieee.org/xsd/imsccv1p3/LOM/imscclti',     'xsd' => 'http://www.imsglobal.org/profile/cc/ccv1p3/LOM/ccv1p3_lomccltilink_v1p0.xsd' ),
							'xmlns:csmd'  => array( 'name' => 'csmd',  'xml' => 'http://www.imsglobal.org/xsd/imsccv1p3/imscsmd_v1p0', 'xsd' => 'http://www.imsglobal.org/profile/cc/ccv1p3/ccv1p3_imscsmd_v1p0.xsd' ),
							'xmlns:lticm' => array( 'name' => 'lticm', 'xml' => 'http://www.imsglobal.org/xsd/imslticm_v1p0',          'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd'    ),
							'xmlns:lticp' => array( 'name' => 'lticp', 'xml' => 'http://www.imsglobal.org/xsd/imslticp_v1p0',          'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticp_v1p0.xsd'    ),
						)
					);
					break;
				case self::CONFIGURATION_BASIC_LTI :
					$configuration_properties = array(
						'root' => 'basic_lti_link',
						'namespaces' => array(
							'xmlns'       => array( 'name' => 'xmlns', 'xml' => 'http://www.imsglobal.org/xsd/imsbasiclti_v1p0', 'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0p1.xsd' ),
							'xmlns:blti'  => array( 'name' => 'blti',  'xml' => 'http://www.imsglobal.org/xsd/imsbasiclti_v1p0', 'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd' ),
							'xmlns:lticm' => array( 'name' => 'lticm', 'xml' => 'http://www.imsglobal.org/xsd/imslticm_v1p0',    'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd '     ),
							'xmlns:lticp' => array( 'name' => 'lticp', 'xml' => 'http://www.imsglobal.org/xsd/imslticp_v1p0',    'xsd' => 'http://www.imsglobal.org/lti/ltiv1p0/imslticp_v1p0.xsd'          ),
						),
					);
					break;
				case self::CONFIGURATION_LTI_BASIC_OUTCOMES :
					$configuration_properties = array(
						'root' => 'imsx_POXEnvelopeRequest',
						'namespaces' => array(
							'xmlns' => array( 'name' => 'xmlns', 'xml' => 'http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0', 'xsd' => 'https://www.imsglobal.org/lti/media/ltiv1p1/OMSv1p0_LTIv1p1Profile_SyncXSD_v1p0.xsd' ),
						),
					);
					break;
				case self::CONFIGURATION_PUBLICATION :
					$configuration_properties = array(
						'root' => 'cartridge_basiclti_link',
						'namespaces' => array(
							'xmlns'       => array( 'name' => 'xmlns', 'xml' => 'http://www.imsglobal.org/xsd/imslticc_v1p0',    'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticc_v1p0.xsd'    ),
							'xmlns:blti'  => array( 'name' => 'blti',  'xml' => 'http://www.imsglobal.org/xsd/imsbasiclti_v1p0', 'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd' ),
							'xmlns:lticm' => array( 'name' => 'lticm', 'xml' => 'http://www.imsglobal.org/xsd/imslticm_v1p0',    'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd'    ),
							'xmlns:lticp' => array( 'name' => 'lticp', 'xml' => 'http://www.imsglobal.org/xsd/imslticp_v1p0',    'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticp_v1p0.xsd'    ),
						),
					);
					break;
				case self::CONFIGURATION_COMMON_CARTRIDGE :
				case self::CONFIGURATION_DEFAULT :
				default :
					$configuration_properties = array(
						'root' => 'cartridge_basiclti_link',
						'namespaces' => array(
							'xmlns'       => array( 'name' => 'xmlns', 'xml' => 'http://www.imsglobal.org/xsd/imslticc_v1p0',    'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticc_v1p0.xsd'    ),
							'xmlns:blti'  => array( 'name' => 'blti',  'xml' => 'http://www.imsglobal.org/xsd/imsbasiclti_v1p0', 'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd' ),
							'xmlns:lticm' => array( 'name' => 'lticm', 'xml' => 'http://www.imsglobal.org/xsd/imslticm_v1p0',    'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd'    ),
							'xmlns:lticp' => array( 'name' => 'lticp', 'xml' => 'http://www.imsglobal.org/xsd/imslticp_v1p0',    'xsd' => 'http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticp_v1p0.xsd'    ),
						),
					);
					break;
			}
			// store the expected configuration for reference
			if( $this->getConfigurationName() == $configuration_name ){
				$this->_configuration_properties = $configuration_properties;
			}
		}
		return $configuration_properties;
	}

	/**
	 * retrieve a particular namespace from a given configuration
	 * @param string $namespace_name
	 * @param string $configuration_name (optional)
	 * @return mixed
	 * */
	public function getNamespace( $namespace_name, $configuration_name=null ){
		$namespace = false;
		$configuration_properties = $this->getConfigurationProperties( $configuration_name );
		$namespaces = $configuration_properties['namespaces'];
		forEach( $namespaces as $hierarchy => $attributes ){
			if( $namespace_name == $attributes['name'] ){
				$namespace = (object) array(
					'hierarchy' => $hierarchy,
					'name'      => $attributes['name'],
					'xml'       => $attributes['xml'],
					'xsd'       => $attributes['xsd'],
				);
				break;
			}
		}
		return $namespace;
	}

	/**
	 * get basic lti properties
	 * @return array
	 * */
	public function getBltiProperties(){
		$basic_learning_tools_interoperability_properties = array(
			'title'             => 'LTI SSO TEST',
			'icon'              => 'http://lti.sso.test/images/ims_global.jpg',
			'secure_icon'       => 'https://lti.sso.test/images/ims_global.jpg',
			'description'       => 'LTI SSO TEST',
			'launch_url'        => 'http://lti.sso.test/launch',
			'secure_launch_url' => 'https://lti.sso.test/launch',
		);
		// special case when LMS can only handle one link per publication
		if( $this->getConfigurationName() == self::CONFIGURATION_PUBLICATION ){
			$basic_learning_tools_interoperability_properties['launch_url'] = 'http://lti.sso.test/launch';
			$basic_learning_tools_interoperability_properties['secure_launch_url'] = 'https://lti.sso.test/launch';
		}
		return $basic_learning_tools_interoperability_properties;
	}

	/**
	 * get lti common messaging properties
	 * @return array
	 * */
	public function getLticmProperties(){
		$learning_tool_interoperatibility_common_messaging_properties = array(
			'canvas.instructure.com' => array( // platform
				'properties' => array(
					array(
						'name' => 'tool_id',
						'value' => 'lti.sso.test',
					),
					array(
						'name' => 'privacy_level',
						'value' => 'public',
					),
					array(
						'name' => 'domain',
						'value' => 'lti.sso.test',
					),
				),
/*
				'options' => array(
					'properties' => array(
						array(
							'name' => 'SISID',
							'value' => '$Canvas.user.sisSourceId',
						),
					),
					'options' => array(
					),
				),
*/
			),
			'itslearning.com' => array( // platform
				'properties' => array(
					array(
						'name' => 'tool_id',
						'value' => 'lti.sso.test',
					),
					array(
						'name' => 'privacy_level',
						'value' => 'public',
					),
					array(
						'name' => 'domain',
						'value' => 'lti.sso.test',
					),
				),

				'options' => array(
					'properties' => array(
//						// itslearning will always append the lis_person_sourcedid by default; do not need to request as a custom field
//						array(
//							'name' => 'lis_person_sourcedid',
//							'value' => 'lis_person_sourcedid',
//						),
					),
					'options' => array(
					),
				),

			),
		);
		return $learning_tool_interoperatibility_common_messaging_properties;
	}

	/**
	 * get lti common profile properties
	 * @return array
	 * */
	public function getLticpProperties(){
		$learning_tool_interoperatibility_common_profile_properties = array(
			'code'        => 'lti.sso.test',
			'name'        => 'LTI SSO TEST',
			'description' => 'LTI SSO TEST',
			'url'         => 'http://lti.sso.test/',
			'contact'     => array(
				'email'   => 'techsupport@lti.sso.test',
			),
		);
		return $learning_tool_interoperatibility_common_profile_properties;
	}

	/**
	 * get IMSX POX header properties
	 * @return array
	 * */
	public function getImsxPoxHeaderProperties(){
		$header_properties = array(
			'imsx_POXHeader' => array(
				'imsx_POXRequestHeaderInfo' => array(
					'imsx_version' => 'V1.0',
					'imsx_messageIdentifier' => $this->_outcomes_message_identifier,
				),
			),
		);
		return $header_properties;
	}
	
	/**
	 * get IMSX POX body properties
	 * @param string $action
	 * @return array
	 * */
	public function getImsxPoxBodyProperties( $action ){
		$body_properties = array();
		switch( $action ){
			case self::OUTCOMES_ACTION_REPLACE :
				$body_properties = array(
					'imsx_POXBody' => array(
						'replaceResultRequest' => array(
							'resultRecord' => array(
								'sourcedGUID' => array(
									'sourcedId' => $this->_outcomes_sourced_id,
								),
								'result' => array(
									'resultScore' => array(
										'language' => 'en',
										'textString' => $this->_outcomes_score,
									),
//									// CANVAS LMS DATA RETURN EXTENSION
//									// https://canvas.instructure.com/doc/api/file.assignment_tools.html
//									'resultData' => array( // this node is specific to Canvas LMS and can have a node of text, url, or ltiLaunchUrl
//										'text' => $this->_text,
//										'url' => $this->_url,
//										'ltiLaunchUrl' => $this->_lti_launch_url,
//									),
//									// CANVAS LMS TOTAL SCORE EXTENSION
//									// https://canvas.instructure.com/doc/api/file.assignment_tools.html
//									'resultTotalScore' => array( // this node is a Canvas LMS option that will override the normal resultScore
//										'language' => 'en',
//										'textString' => $this->_result_total_score, // integer or float values accepted
//									),
								),
							),
						),
					),
				);
				break;
			case self::OUTCOMES_ACTION_READ :
				$body_properties = array(
					'imsx_POXBody' => array(
						'readResultRequest' => array(
							'resultRecord' => array(
								'sourcedGUID' => array(
									'sourcedId' => $this->_outcomes_sourced_id,
								),
							),
						),
					),
				);
				break;
			case self::OUTCOMES_ACTION_DELETE :
				$body_properties = array(
					'imsx_POXBody' => array(
						'deleteResultRequest' => array(
							'resultRecord' => array(
								'sourcedGUID' => array(
									'sourcedId' => $this->_outcomes_sourced_id,
								),
							),
						),
					),
				);
				break;
		}
		return $body_properties;
	}

	/**
	 * set outcomes action
	 * @param string $action_name
	 * @return boolean
	 * */
	public function setOutcomesAction( $action_name ){
		$success = false;
		switch( $action_name ){
			case self::OUTCOMES_ACTION_REPLACE :
			case self::OUTCOMES_ACTION_READ :
			case self::OUTCOMES_ACTION_DELETE :
				$this->_outcomes_action = $action_name;
				$success = true;
				break;
			default :
				throw new \Exception( "Unknown outcomes action: {$action_name}" );
		}
		return $success;
	}

	/**
	 * set outcomes properties
	 * @param string $property_name
	 * @param string $property_value
	 * @return boolean
	 * */
	public function setOutcomesProperty( $property_name, $property_value ){
		$success = false;
		switch( $property_name ){
			case self::OUTCOMES_PROPERTY_MESSAGE_ID :
				$this->_outcomes_message_identifier = $property_value;
				$success = true;
				break;
			case self::OUTCOMES_PROPERTY_SOURCED_ID :
				$this->_outcomes_sourced_id = $property_value;
				$success = true;
				break;
			case self::OUTCOMES_PROPERTY_SCORE :
				$this->_outcomes_score = $property_value;
				$success = true;
				break;
			case self::OUTCOMES_PROPERTY_ACTION :
				$success = $this->setOutcomesAction( $property_value );
				break;
			default :
				throw new \Exception( "Unknown outcomes property: {$property_name}" );
		}
		return $success;
	}

	/**
	 * build xml file to describe lti setup
	 * */
	function getXml() {
		// get the LTI configuration
		$configuration_name = $this->getConfigurationName();
		$configuration_properties = $this->getConfigurationProperties( $configuration_name );

		// declare XML document
		$doc = new \DOMDocument( self::XML_VERSION, self::XML_ENCODING );

    /*  +----------------------------------------+
        |  ROOT NODE AND NAMESPACE DECLARATIONS  |
        +----------------------------------------+  */
		// create root node and set namespace and its attributes
		$root_namespace = $this->getNamespace( 'xmlns' );
		$root = $doc->createElementNS( $root_namespace->xml, $configuration_properties['root'] );
		$doc->appendChild( $root );
		// set the default schema for the doc
		$doc->createAttributeNS( $root_namespace->xml, 'xmlns' );
		// gather all the xml/xsd pairs and set the xsi:schemaLocation
		$xsd_references = array();
		forEach( $configuration_properties['namespaces'] AS $namespace_hierarchy => $namespace_properties ){
			if( $namespace_hierarchy != 'xmlns' ){
				$root->setAttribute( $namespace_hierarchy, $namespace_properties['xml'] );
			}
			if( ! empty( $namespace_properties['xsd'] ) ){
				$xsd_references[] = $namespace_properties['xml'] . ' ' . $namespace_properties['xsd'];
			}
		}
		// set schema xsd locations for namespaces
		$root->setAttributeNS( 'http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', implode( ' ', $xsd_references ) );

    /*  +---------------------------+
        |  NAMESPACE SECTION: BLTI  |
        +---------------------------+  */
		// add basic lti properties
		$blti_namespace = $this->getNamespace( 'blti' );
		if( ! empty( $blti_namespace ) ){
			$blti_properties = $this->getBltiProperties();
			forEach( $blti_properties as $blti_key => $blti_value ){
				$root->appendChild( $doc->createElement( $blti_namespace->name . ':' . $blti_key, $blti_value ) );
			}
		}

    /*  +----------------------------+
        |  NAMESPACE SECTION: LTICM  |
        +----------------------------+  */
		// add lti common messaging properties as blti:extensions
		$lticm_namespace = $this->getNamespace( 'lticm' );
		if( ! empty( $lticm_namespace ) && ! empty( $blti_namespace ) ){
			$lticm_properties = $this->getLticmProperties();
			forEach( $lticm_properties as $platform => $platform_attributes ){
				$platform_element = $doc->createElement( $blti_namespace->name . ':extensions' );
				$root->appendChild( $platform_element );
				$platform_element->setAttribute( 'platform', $platform );
				if( ! empty( $platform_attributes ) ){
					if( ! empty( $platform_attributes['properties'] ) ){
						forEach( $platform_attributes['properties'] as $property_set ){
							$property_element = $doc->createElement( $lticm_namespace->name . ':property' );
							$platform_element->appendChild( $property_element );
							$property_element->setAttribute( 'name', $property_set['name'] );
							$property_element->appendChild( $doc->createTextNode( $property_set['value'] ) );
						}
					}
					if( ! empty( $platform_attributes['options'] ) ){
						$option_element = $doc->createElement( $lticm_namespace->name . ':options' );
						$platform_element->appendChild( $option_element );
						forEach( $platform_attributes['options'] as $options_key => $options_set ){
							if( $options_key == 'properties' && ! empty( $options_set ) ){
								forEach( $options_set as $property_index => $property_set ){
									$property_element = $doc->createElement( $lticm_namespace->name . ':property' );
									$option_element->appendChild( $property_element );
									$property_element->setAttribute( 'name', $property_set['name'] );
									$property_element->appendChild( $doc->createTextNode( $property_set['value'] ) );
								}
							}
							if( $options_key == 'options' && ! empty( $options_set ) ){ // current hierarchy does not require us to go this many levels deep
								$option_subelement = $doc->createElement( $lticm_namespace->name . ':options' );
								$option_element->appendChild( $option_subelement );
								forEach( $options_set as $options_subkey => $options_subset ){
									if( $options_subkey == 'properties' && ! empty( $options_subset ) ){
										forEach( $options_subset as $property_subindex => $property_subset ){
											$property_subelement = $doc->createElement( $lticm_namespace->name . ':property' );
											$option_subelement->appendChild( $property_subelement );
											$property_subelement->setAttribute( 'name', $property_subset['name'] );
											$property_subelement->appendChild( $doc->createTextNode( $property_subset['value'] ) );
										}
									}
								}
							}
						}
					}
				}
			}
		}

    /*  +----------------------------+
        |  NAMESPACE SECTION: LTICP  |
        +----------------------------+  */
		// add lti common profile (vendor) properties
		$lticp_namespace = $this->getNamespace( 'lticp' );
		if( ! empty( $lticp_namespace ) ){
			$vendor = $doc->createElement( 'vendor' );
			$root->appendChild( $vendor );
			// add vendor properties using the common profile dtd
			$lticp_properties = $this->getLticpProperties();
			forEach( $lticp_properties as $lticp_key => $lticp_value ){
				if( is_array( $lticp_value ) ){ // assumes max of two levels
					$attribute = $doc->createElement( $lticp_namespace->name . ':' . $lticp_key );
					$vendor->appendChild( $attribute );
					forEach( $lticp_value AS $child_key => $child_value ){
						$child_attribute = $doc->createElement( $lticp_namespace->name . ':' . $child_key );
						$attribute->appendChild( $child_attribute );
						$child_attribute->appendChild( $doc->createTextNode( $child_value ) );
					}
				}
				else{ // only one level deep
					$attribute = $doc->createElement( $lticp_namespace->name . ':' . $lticp_key );
					$vendor->appendChild( $attribute );
					$attribute->appendChild( $doc->createTextNode( $lticp_value ) );
				}
			}
		}

    /*  +--------------------------------+
        |  LTI OUTCOMES HEADER AND BODY  |
        +--------------------------------+  */
		// add lti outcomes header and body for LTI Basic Outcomes messages
		if( $configuration_properties['root'] == 'imsx_POXEnvelopeRequest' ){
			// recursively append header nodes
			$header_hierarchy = $this->getImsxPoxHeaderProperties();
			$this->buildNode( $doc, $root, $header_hierarchy );
			// recursively append body nodes
			$body_hierarchy = $this->getImsxPoxBodyProperties( $this->_outcomes_action );
			$this->buildNode( $doc, $root, $body_hierarchy );
		}

		// output
		$doc->formatOutput = true;
		$result = $doc->saveXML();
		return $result;
	}

	/**
	 * recursively build xml doc nodes from a hierarchical array
	 * @param DOMDocument $doc
	 * @param mixed &$parent_node
	 * @param array $hierarchy
	 * @return void
	 * */
	public function buildNode( $doc, &$parent_node, $hierarchy ){
		if( is_array( $hierarchy ) ){
			forEach( $hierarchy as $key => $value ){
				$current_node = $doc->createElement( $key );
				$this->buildNode( $doc, $current_node, $value );
				$parent_node->appendChild( $current_node );
			}
		}
		else{
			$current_node = $doc->createTextNode( $hierarchy );
			$parent_node->appendChild( $current_node );
		}
	}

}
