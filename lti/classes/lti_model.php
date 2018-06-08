<?php

namespace Lti;

/**
 * Learning Tools Interoperability (LTI) MODEL for Common Cartridge
 * version 1
 * */
class Lti_model
{
	/**
	 * constants
	 * */
	const ROLE_INSTRUCTOR = 'Instructor';
	const ROLE_LEARNER    = 'Learner';
	const ROLE_SYSTEM     = 'sysrole';
	const ROLE_OBSERVER   = 'observer';
	
	/**
	 * properties
	 * */
	protected $_lti_key = false;
	protected $_lti_data = array();
	public $db; // @TODO connect to database
	
/*  +-------------------------------+
    |  STORE LTI DATA IN MEMCACHED  |
    +-------------------------------+  */
	/**
	 * get LTI key
	 * @param array $get_vars
	 * @param array $post_vars
	 * @return mixed
	 * */
	public function getLtiKey( $get_vars=null, $post_vars=null ){
		if( empty( $this->_lti_key ) ){
			// check session for variable
			$this->_lti_key = ! empty( $_SESSION['ltikey'] ) ? $_SESSION['ltikey'] : null ;
			if( empty( $this->_lti_key ) ){
				// check for get variable
				$this->_lti_key = ! empty( $get_vars['ltikey'] ) ? $get_vars['ltikey'] : null ;
				if( empty( $this->_lti_key ) ){
					// check for post variable
					$this->_lti_key = ! empty( $post_vars['ltikey'] ) ? $post_vars['ltikey'] : null ;
					if( empty( $this->_lti_key ) ){
						// create key from LTI post variables
						$messages = array();
						if( $this->isValidLtiRequest( $post_vars, $messages ) ){
							if( ! empty( $post_vars['tool_consumer_instance_guid'] ) && ! empty( $post_vars['user_id'] ) ){
								$this->_lti_key = 'lti_' . $post_vars['tool_consumer_instance_guid'] . '_' . $post_vars['user_id'];
								// refresh the lti_data from the post
								$this->setLtiData( $post_vars );
							}
						}
					}
				}
				// put ltikey into the session if available
				if( ! empty( $this->_lti_key ) ){
					$_SESSION['ltikey'] = $this->_lti_key;
				}
			}
		}
		return $this->_lti_key;
	}
	
	/**
	 * fetch LTI data for the current user
	 * @param array $get_vars
	 * @param array $post_vars
	 * @return array
	 * */
	public function getLtiData( $get_vars=null, $post_vars=null){
		if( empty( $this->_lti_data ) ){
			$lti_key = $this->getLtiKey( $get_vars, $post_vars );
			if( ! empty( $lti_key ) ){
				$memcacher = Memcacher::getInstance();
				$this->_lti_data = $memcacher->memcachedGet( $lti_key );
			}
		}
		return $this->_lti_data;
	}
	
	/**
	 * fetch value from LTI data
	 * @param string $key
	 * @param boolean &$key_found
	 * @return mixed (returns false if key not found)
	 * */
	public function getLtiDataValue( $key, &$key_found=null ){
		$value = false;
		$key_found = false;
		$lti_data = $this->getLtiData();
		if( array_key_exists( $key, $lti_data ) ){
			$key_found = true;
			$value = $lti_data[ $key ];
		}
		else{ // check for lowercase version of the key
			$lower_key = strtolower( $key );
			if( array_key_exists( $lower_key, $lti_data ) ){
				$key_found = true;
				$value = $lti_data[ $lower_key ];
			}
		}
		return $value;
	}
	
	/**
	 * return first existing value from a set of possible data keys
	 * @param array $keys
	 * @return mixed (returns false if none of they keys had values)
	 * */
	public function getFirstLtiDataValueFromPossibleKeys( $keys ){
		$value = false;
		$key_found = false;
		forEach( $keys as $key ){
			$temp_value = $this->getLtiDataValue( $key, $key_found );
			if( $key_found ){
				$value = $temp_value;
				break;
			}
		}
		return $value;
	}
	
	/**
	 * set LTI data for the current user
	 * @param array $lti_data
	 * @return boolean
	 * */
	public function setLtiData( $lti_data ){
		$this->_lti_data = $lti_data;
		$memcacher = Memcacher::getInstance();
		$success = $memcacher->memcachedSet( $lti_data, $this->getLtiKey() );
		return $success;
	}
	
	/**
	 * update LTI data for the current user
	 * @param string $key
	 * @param mixed $value
	 * @return boolean
	 * */
	public function updateLtiData( $key, $value ){
		$lti_data = $this->getLtiData();
		$lti_data[ $key ] = $value;
		$success = $this->setLtiData( $lti_data );
		return $success;
	}
	
	/**
	 * fetch a custom cartridge value from the posted LTI data
	 * @param string $key
	 * @param boolean &$key_found
	 * @return mixed (returns false if key not found)
	 * */
	public function getCustomCartridgeValue( $key, &$key_found=null ){
		$value = false;
		$key_found = false;
		$lti_data = $this->getLtiData();
		// check for variations of the custom cartridge field
		$permutations = array();
		$permutations['original'] = $key;
		$parts = explode( '_', strtolower( $key ) ); // words in field name may be underscore separated
		$permutations['lowercase'] = strtolower( implode('',$parts) );
		$parts = array_map( 'ucfirst', $parts ); // make each part uppercase
		$permutations['snake_case']  = strtolower( implode( '_', $parts ) );
		$permutations['camelCase']   = lcfirst( implode( '', $parts ) );
		$permutations['CapitalCase'] = implode( '', $parts );
		
		$test = false;
		if( $test == true ){
			echo "<pre style='color:red;'>\n";var_dump( $permutations );echo "<br />\n";die(__FILE__.__LINE__);
		}
		
		forEach( $permutations as $style => $field ){
			if( array_key_exists( $field, $lti_data ) ){
				$key_found = true;
				$value = $lti_data[ $field ];
				break;
			}
			$alternative = 'custom_' . $field;
			if( array_key_exists( $alternative, $lti_data ) ){
				$key_found = true;
				$value = $lti_data[ $alternative ];
				break;
			}
		}
		return $value;
	}
	
	/**
	 * Check if the data recieved is a valid LTI request
	 * @param array $post
	 * @param array &$messages
	 * @return boolean 
	 */
	public function isValidLtiRequest( $post, &$messages ) {
		// initial assumption
		$is_valid = true;
		
		// ----------------------------------
		// must have a valid lti message type
		// ----------------------------------
		$lti_message_types = array(
			'basic-lti-launch-request',
			'ContentItemSelectionRequest',
		);
		if( empty( $post['lti_message_type'] ) ){
			$is_valid = false;
			$messages[] = 'missing required value: lti_message_type';
		}
		else{
			if( ! in_array( $post['lti_message_type'], $lti_message_types ) ){
				$is_valid = false;
				$messages[] = 'unknown lti_message_type: '.$post['lti_message_type'];
			}
		}
		// -----------------------------
		// must have a valid lti version
		// -----------------------------
		$lti_versions = array(
			'LTI-1p0',   // http://www.imsglobal.org/lti/blti/bltiv1p0/ltiBLTIimgv1p0.html
			'LTI-1p1',   // http://www.imsglobal.org/LTI/v1p1/ltiIMGv1p1.html
			'LTI-1p1p1', // http://www.imsglobal.org/LTI/v1p1p1/ltiIMGv1p1p1.html
			'LTI-1p2',   // http://www.imsglobal.org/lti/ltiv1p2/ltiIMGv1p2.html
			'LTI-2p0',   // http://www.imsglobal.org/lti/ltiv2p0/ltiIMGv2p0.html
		);
		if( empty( $post['lti_version'] ) ){
			$is_valid = false;
			$messages[] = 'missing required value: lti_version';
		}
		else{
			if( ! in_array( $post['lti_version'], $lti_versions ) ){
				$is_valid = false;
				$messages[] = 'unknown lti_version: '.$post['lti_version'];
			}
			// ----------------------------
			// enforce expected lti version
			// ----------------------------
			$allowed_lti_versions = array(
				'LTI-1p0',   // http://www.imsglobal.org/lti/blti/bltiv1p0/ltiBLTIimgv1p0.html
				'LTI-1p1',   // http://www.imsglobal.org/LTI/v1p1/ltiIMGv1p1.html
				'LTI-1p1p1', // http://www.imsglobal.org/LTI/v1p1p1/ltiIMGv1p1p1.html
				'LTI-1p2',   // http://www.imsglobal.org/lti/ltiv1p2/ltiIMGv1p2.html
			);
			if( ! in_array( $post['lti_version'], $allowed_lti_versions ) ){
				$is_valid = false;
				$messages[] = 'incorrect lti_version: '.$post['lti_version'];
			}
		}
		// ----------------------------------------------
		// enforce requirements based on lti_message_type
		// ----------------------------------------------
		if( isset( $post['lti_message_type'] ) ){
			switch( $post['lti_message_type'] ){
				case 'ContentItemSelectionRequest' :
					if( empty( $post['content_item_return_url'] ) ){
						$is_valid = false;
						$messages[] = 'missing required value: content_item_return_url';
					}
					if( empty( $post['accept_media_types'] ) ){
						$is_valid = false;
						$messages[] = 'missing required value: accept_media_types';
					}
					if( empty( $post['accept_presentation_document_targets'] ) ){
						$is_valid = false;
						$messages[] = 'missing required value: accept_presentation_document_targets';
					}
					break;
				case 'basic-lti-launch-request' :
					if( empty( $post['resource_link_id'] ) ){
						$is_valid = false;
						$messages[] = 'missing required value: resource_link_id';
					}
					break;
			}
		}
		// return boolean
		return $is_valid;
	}
	
	/**
	 * retrieve secret value for a given consumer key
	 * @param string $oauth_consumer_key
	 * @return mixed
	 * */
	public function getSecret( $oauth_consumer_key ){
		$secret = false;
		if( ! empty( $oauth_consumer_key ) ){
			if( ! empty( $db ) ){
				$resource = $this->db->get_where( 'lti_consumer', ['consumer_key' => $oauth_consumer_key] );
				if( $resource && $resource->num_rows() > 0 ){
					$row = $resource->row();
					$secret = $row->secret;
				}
			}
			else{ // hard-coded for demo purposes
				$secret = 'i_am_a_secret';
			}
		}
		return $secret;
	}
	
	/**
	 * get the primary LTI role for the user
	 * @return string
	 * */
	public function getPrimaryLtiRole(){
		$primary_role = '';
		$lti_roles = $this->getLtiDataValue('roles');
		if( ! empty( $lti_roles ) ){
			// Roles come over in a comma-separated string. This just converts it to an array.
			$roles = explode(',', $lti_roles );
			// Check for Instructor and Learner roles.
			switch( true ){
				case in_array( 'Instructor', $roles ) :
				case in_array( 'urn:lti:instrole:ims/lis/Administrator', $roles ) :
				case in_array( 'urn:lti:instrole:ims/lis/Instructor', $roles ) :
				case in_array( 'urn:lti:role:ims/lis/Instructor', $roles ) :
				case in_array( 'urn:lti:sysrole:ims/lis/SysAdmin', $roles ) :
					$primary_role = self::ROLE_INSTRUCTOR; // Instructor
					break;
				case in_array( 'Learner', $roles ) :
				case in_array( 'urn:lti:instrole:ims/lis/Learner', $roles ) :
				case in_array( 'urn:lti:role:ims/lis/Learner', $roles ) :
				case in_array( 'urn:lti:sysrole:ims/lis/User', $roles ) :
				case in_array( 'urn:lti:instrole:ims/lis/Observer', $roles ) :
					$primary_role = self::ROLE_LEARNER; // Learner;
					break;
				case in_array( 'sysrole', $roles ) :
				case in_array( 'urn:lti:sysrole:ims/lis/None', $roles ) :
					$primary_role = self::ROLE_SYSTEM; // sysrole;
					break;
				default :
					$primary_role = '';
					break;
			}
		}
		return $primary_role;
	}
	
	/**
	 * attempt to get a name value for display from lti data
	 * @return string
	 * */
	public function getDisplayName(){
		$display_name = '';
		$lti_name_vars = array( 'lis_person_name_full', 'lis_person_name_given', 'lis_person_name_family' );
		$name_found = false;
		forEach( $lti_name_vars as $check_me ){
			$name = $this->getLtiDataValue( $check_me, $name_found );
			if( $name_found ){
				$display_name = $name;
				break;
			}
		}
		return $display_name;
	}
	
	/**
	 * attempt to get an email value from lti data
	 * @return string
	 * */
	public function getEmail(){
		$email = '';
		$lti_email_vars = array( 'lis_person_contact_email_primary' );
		$email_found = false;
		forEach( $lti_email_vars as $check_me ){
			$temp = $this->getLtiDataValue( $check_me, $email_found );
			if( $email_found ){
				$email = $temp;
				break;
			}
		}
		return $email;
	}

}
