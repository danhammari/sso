<?php

namespace Lti;

require_once( getcwd() . '/LTI/OAuth.php');

/**
 * Learning Tools Interoperability (LTI) Outcomes Model
 * version 1
 * */
class Lti_outcomes extends MY_Model
{
	/**
	 * constants
	 * */

	/**
	 * properties
	 * */
	public $lti_model;
	public $lti_xml_v1;
	public $db; // @TODO connect to db

	/**
	 * constructor
	 * */
	public function __construct() {
		parent::__construct();
		$this->lti_model = new \Lti\Lti_model();
		$this->lti_xml_v1 = new \Lti\Lti_xml_v1);
	}

/*  +-----------------------------+
    |  STORE LTI OUTCOMES VALUES  |
    +-----------------------------+  */
	/**
	 * save the outcomes service details for the requested resource
	 * @return boolean
	 * */
	public function saveLtiOutcomesValues(){
		$result = false;
		// store the LIS Outcomes Details affiliated with the given resource link for this user
		$oauth_consumer_key      = $this->lti_model->getLtiDataValue( 'oauth_consumer_key' );
		$user_id                 = $this->lti_model->getLtiDataValue( 'user_id' );
		$resource_link_id        = $this->lti_model->getLtiDataValue( 'resource_link_id' );
		$lis_result_sourcedid    = $this->lti_model->getLtiDataValue( 'lis_result_sourcedid' );
		$lis_outcome_service_url = $this->lti_model->getLtiDataValue( 'lis_outcome_service_url' );
		if( ! empty( $this->db ) && ! empty( $lis_result_sourcedid ) && ! empty( $lis_outcome_service_url ) ){
			// upsert the LTI Outcomes Service details
			$existing_row = $this->getLtiOutcomesValues( $oauth_consumer_key, $user_id, $resource_link_id );
			if( ! empty( $this->db ) && ! empty( $existing_row->lis_result_sourcedid ) ){
				// update
				$this->db->where( 'consumer_key',     $oauth_consumer_key );
				$this->db->where( 'user_id',          $user_id );
				$this->db->where( 'resource_link_id', $resource_link_id );
				$updates = array(
					'lis_result_sourcedid'    => $lis_result_sourcedid,
					'lis_outcome_service_url' => $lis_outcome_service_url,
				);
				$result = $this->db->update( 'lti_outcomes', $updates );
			}
			else{
				// insert
				$inserts = array(
					'consumer_key'            => $oauth_consumer_key,
					'user_id'                 => $user_id,
					'resource_link_id'        => $resource_link_id,
					'lis_result_sourcedid'    => $lis_result_sourcedid,
					'lis_outcome_service_url' => $lis_outcome_service_url,
				);
				$result = $this->db->insert( 'lti_outcomes', $inserts );
			}
		}
		return $result;
	}

/*  +--------------------------------+
    |  RETRIEVE LTI OUTCOMES VALUES  |
    +--------------------------------+  */
	/**
	 * get the outcomes service details for the requested resource
	 * @param string $oauth_consumer_key (optional)
	 * @param string $user_id (optional)
	 * @param string $resource_link_id (optional)
	 * @return \stdClass
	 * */
	public function getLtiOutcomesValues( $oauth_consumer_key=null, $user_id=null, $resource_link_id=null ){
		if( empty( $this->db ) ){
			return (object)[];
		}
		if( empty( $oauth_consumer_key ) ){
			$oauth_consumer_key = $this->lti_model->getLtiData( 'oauth_consumer_key' );
		}
		if( empty( $user_id ) ){
			$user_id = $this->lti_model->getLtiData( 'user_id' );
		}
		if( empty( $resource_link_id ) ){
			$resource_link_id = $this->lti_model->getLtiData( 'resource_link_id' );
		}
		$columns = array(
			'consumer_key',
			'user_id',
			'resource_link_id',
			'lis_result_sourcedid',
			'lis_outcome_service_url',
		);
		$result = new \stdClass();
		forEach( $columns as $column ){
			$result->$column = null;
		}
		if( ! empty( $oauth_consumer_key ) && ! empty( $user_id ) && ! empty( $resource_link_id ) ){
			try{
				$wheres = array(
					'consumer_key'     => $oauth_consumer_key,
					'user_id'          => $user_id,
					'resource_link_id' => $resource_link_id,
				);
				$resource = $this->db->get_where( 'lti_outcomes', $wheres );
				if( $resource && $resource->num_rows() > 0 ){
					forEach( $columns as $column ){
						if( isset( $row->$column ) ){
							$result->$column = $row->$column;
						}
					}
				}
			}
			catch( \Exception $exception ){
				// create the lti_outcomes table if it does not yet exist
				$success = $this->createLtiOutcomesTable();
			}
		}
		return $result;
	}

/*  +---------------------------------------------+
    |  CREATE TABLE TO STORE LTI OUTCOMES VALUES  |
    +---------------------------------------------+  */
	/**
	 * create the lti_outcomes table
	 * @return boolean
	 * */
	public function createLtiOutcomesTable(){
		$success = false;
		$query = <<<QUERY_CREATE_LTI_OUTCOMES_TABLE
CREATE TABLE public.lti_outcomes
(
  consumer_key character varying(100) NOT NULL,
  user_id character varying(100) NOT NULL,
  resource_link_id character varying(100) NOT NULL,
  lis_result_sourcedid character varying(100),
  lis_outcome_service_url character varying(150),
  CONSTRAINT lti_outcomes_pkey PRIMARY KEY ( consumer_key, user_id, resource_link_id )
)
WITH (
  OIDS=FALSE
);
QUERY_CREATE_LTI_OUTCOMES_TABLE;
		try{
			$success = $this->db->query( $query );
		}
		catch( Exception $e ){
			error_log( $e->getMessage() );
		}
		return $success;
	}

/*  +------------------------------------+
    |  OUTCOMES SERVICE: REPLACE RESULT  |
    +------------------------------------+  */
	/**
	 * send the replaceResultRequest to the Tool Consumer
	 * @param string $score
	 * @param string $test (optional) [request|response]
	 * @return boolean
	 * */
	public function replaceResultRequest( $score, $test=null ){
		$success = false;
		$lti_outcome_values = $this->getLtiOutcomesValues();
		if( ! empty( $test ) || ( ! empty( $lti_outcome_values->lis_result_sourcedid ) && ! empty( $lti_outcome_values->lis_outcome_service_url ) ) ){
			// build xml document for replace result action
			$this->lti_xml_v1->setConfiguration(    'lti_basic_outcomes' );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_message_identifier', uniqid() );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_sourced_id', $lti_outcome_values->lis_result_sourcedid );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_score',      $score );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_action',     'outcomes_action_replace' );
			$xml_request = $this->lti_xml_v1->getXml();

			// test to display xml request document
			if( $test == 'request' ){
				header( 'Content-Type: application/xml' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				echo $xml_request;
				die();
			}

			$xml_response = $this->sendOutcomesRequest( $xml_request, $lti_outcome_values->lis_outcome_service_url );

			// test to display xml response document
			if( $test == 'response' ){
				header( 'Content-Type: application/xml' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				echo $xml_response;
				die();
			}
		}
		return $success;
	}

/*  +---------------------------------+
    |  OUTCOMES SERVICE: READ RESULT  |
    +---------------------------------+  */
	/**
	 * send the readResultRequest to the Tool Consumer to get a stored value
	 * @param string $test (optional) [request|response]
	 * @return string
	 * */
	public function readResultRequest( $test=null ){
		$success = false;
		$lti_outcome_values = $this->getLtiOutcomesValues();
		if( ! empty( $test ) || ( ! empty( $lti_outcome_values->lis_result_sourcedid ) && ! empty( $lti_outcome_values->lis_outcome_service_url ) ) ){
			// build xml document for replace result action
			$this->lti_xml_v1->setConfiguration(    'lti_basic_outcomes' );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_message_identifier', uniqid() );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_sourced_id', $lti_outcome_values->lis_result_sourcedid );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_action',     'outcomes_action_read' );
			$xml_request = $this->lti_xml_v1->getXml();

			// test to display xml request document
			if( $test == 'request' ){
				header( 'Content-Type: application/xml' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				echo $xml_request;
				die();
			}

			$xml_response = $this->sendOutcomesRequest( $xml_request, $lti_outcome_values->lis_outcome_service_url );

			// test to display xml response document
			if( $test == 'response' ){
				header( 'Content-Type: application/xml' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				echo $xml_response;
				die();
			}
		}
		return $success;
	}

/*  +-----------------------------------+
    |  OUTCOMES SERVICE: DELETE RESULT  |
    +-----------------------------------+  */
	/**
	 * send the deleteResultRequest to the Tool Consumer to erase a stored value
	 * @param string $test (optional) [request|response]
	 * @return boolean
	 * */
	public function deleteResultRequest( $test=null ){
		$success = false;
		$lti_outcome_values = $this->getLtiOutcomesValues();
		if( ! empty( $test ) || ( ! empty( $lti_outcome_values->lis_result_sourcedid ) && ! empty( $lti_outcome_values->lis_outcome_service_url ) ) ){
			// build xml document for replace result action
			$this->lti_xml_v1->setConfiguration(    'lti_basic_outcomes' );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_message_identifier', uniqid());
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_sourced_id', $lti_outcome_values->lis_result_sourcedid );
			$this->lti_xml_v1->setOutcomesProperty( 'outcomes_property_action',     'outcomes_action_delete' );
			$xml_request = $this->lti_xml_v1->getXml();

			// test to display xml request document
			if( $test == 'request' ){
				header( 'Content-Type: application/xml' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				echo $xml_request;
				die();
			}

			$xml_response = $this->sendOutcomesRequest( $xml_request, $lti_outcome_values->lis_outcome_service_url);

			// test to display xml response document
			if( $test == 'response' ){
				header( 'Content-Type: application/xml' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				echo $xml_response;
				die();
			}
		}
		return $success;
	}

	/**
	 * send xml request via post with oauth1 signature
	 * @param string $xml_request
	 * @param string $lis_outcome_service_url
	 * @return string
	 * */
	public function sendOutcomesRequest( $xml_request, $lis_outcome_service_url ){
		$xml_response = '';
		// oauth1 requires messages of type application/xml to get hash of body separately
		$body_hash = base64_encode( sha1( $xml_request, true ) );
		// send xml document to lis_outcome_service_url via LTI protocol (OAuth1)
		$oauth_consumer_key = $this->lti_model->getLtiDataValue( 'oauth_consumer_key' );
		$oauth_secret = $this->lti_model->getSecret( $oauth_consumer_key );
		$consumer = new \OAuthConsumer( $oauth_consumer_key, $oauth_secret );
		$token = new \OAuthToken( $oauth_consumer_key, $oauth_secret );
		// create an oauth request
		$oauth_request = OAuthRequest::from_consumer_and_token( $consumer, $token, 'POST', $lis_outcome_service_url, array( 'oauth_body_hash' => $body_hash ) );
		$method = new \OAuthSignatureMethod_HMAC_SHA1();
		$oauth_request->sign_request( $method, $consumer, $token );
		$header = $oauth_request->to_header() . "\r\nContent-Type: application/xml\r\n";

		// try to send directly to remote server socket via fput command
		$xml_response = $this->sendXmlOverSocket( $lis_outcome_service_url, $xml_request, $header );
		if( $xml_response !== false && strlen( $xml_response ) > 0){
			return $xml_response;
		}

		// try to send via stream
		$params = array(
			'http' => array(
				'method'  => 'POST',
				'content' => $xml_request,
				'header'  => $header,
			)
		);
		$ctx = stream_context_create( $params );
		try {
			$fp = @fopen( $lis_outcome_service_url, 'r', false, $ctx );
		}
		catch( \Exception $e ){
			$fp = false;
		}
		if( $fp ){
			$xml_response = @stream_get_contents( $fp );
		}
		else { // try to send via curl
			$headers = explode( "\r\n", $header );
			$xml_response = $this->sendXmlOverPost( $lis_outcome_service_url, $xml_request, $headers );
		}
		return $xml_response;
	}

	/**
	 * send xml using fputs
	 * From: http://php.net/manual/en/function.file-get-contents.php
	 * */
	public function sendXmlOverSocket( $endpoint, $data, $moreheaders=false ){
		if( empty( $endpoint ) ){
			return false;
		}
		$url = parse_url( $endpoint );
		if( ! isset( $url['port'] ) ){
			if( $url['scheme'] == 'http' ){
				$url['port']=80;
			}
			else if( $url['scheme'] == 'https' ){
				$url['port']=443;
			}
		}
		$url['query'] = isset( $url['query'] ) ? $url['query'] : '';
		$hostport = ':' . $url['port'];
		if( $url['scheme'] == 'http'  && $hostport == ':80'  ) $hostport = '';
		if( $url['scheme'] == 'https' && $hostport == ':443' ) $hostport = '';
		$url['protocol'] = $url['scheme'].'://';
		$eol="\r\n";
		$uri = "/";
		if( isset(  $url['path']    )     ) $uri  = $url['path'];
		if( strlen( $url['query']   ) > 0 ) $uri .= '?' . $url['query'];
		if( isset( $url['fragment'] ) ){
			if( strlen( $url['fragment']) > 0 ) $uri .= '#' . $url['fragment'];
		}
		$headers = 
			"POST "     . $uri." HTTP/1.0" . $eol .
			"Host: "    . $url['host'] . $hostport . $eol .
			"Referer: " . $url['protocol'] . $url['host'] . $url['path'] . $eol .
			"Content-Length: " . strlen($data) . $eol;
		if( is_string( $moreheaders ) ) $headers .= $moreheaders;
		$len = strlen( $headers );
		if( substr( $headers, $len-2 ) != $eol ) {
			$headers .= $eol;
		}
		$headers .= $eol.$data;
		// echo("\n"); echo($headers); echo("\n");
		// echo("PORT=".$url['port']);
		try {
			$fp = fsockopen( $url['host'], $url['port'], $errno, $errstr, 30 );
			if( $fp ){
				fputs( $fp, $headers );
				$result = '';
				while( ! feof( $fp ) ){
					$result .= fgets( $fp, 128 );
				}
				fclose( $fp );
				//removes headers
				$pattern="/^.*\r\n\r\n/s";
				$result=preg_replace( $pattern,'',$result );
				return $result;
			}
		}
		catch( Exception $e ){
			return false;
		}
		return false;
	}

	/**
	 * try to send xml_request via CURL POST
	 * @param string $url
	 * @param string $xml
	 * @param string $header
	 * @return string
	 * */
	public function sendXmlOverPost( $url, $xml, $header ){
		if( ! function_exists('curl_init') ){
			return false;
		}
		$ch = curl_init();
		// For xml, change the content-type.
		curl_setopt( $ch, CURLOPT_URL,            $url    );
		curl_setopt( $ch, CURLOPT_HTTPHEADER,     $header );
		curl_setopt( $ch, CURLOPT_POST,           1       );
		curl_setopt( $ch, CURLOPT_POSTFIELDS,     $xml    );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1       ); // ask for results to be returned
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0       );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0       );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
	}

}
