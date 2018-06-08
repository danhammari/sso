<?php

namespace Lti;

/**
 * LTI XML endpoint
 * */
class Lti_xml
{
	/**
	 * properties
	 * */
	protected $_default_configuration = 'commoncartridge';
	public $lti_xml_v1;

	/**
	 * custom constructor
	 * sets header values for xml file
	 * */
	public function __construct() {
		$this->lti_xml_v1 = new \Lti\Lti_xml_v1();
		// setup the headers for the http response
		header("Content-Type: text/xml");
//		header("Content-Disposition: attachment; filename=\"lti_configuration.xml\"");
		header("Pragma: no-cache");
		header("Expires: 0");
	}

	/**
	 * build xml file to describe lti setup
	 * this uses the default configuration "cartridge_basiclti_link"
	 * */
	function handle() {
		switch( $this->_default_configuration ){
			case 'basicltilink'        : $this->basicltilink();        break;
			case 'commoncartridge'     : $this->commoncartridge();     break;
			case 'thincommoncartridge' : $this->thincommoncartridge(); break;
			case 'publication'         : $this->publication();         break;
			default                    : $this->commoncartridge();     break;
		}
	}

	/**
	 * build xml file to describe lti setup
	 * this uses configuration "basic_lti_link"
	 * */
	function basicltilink(){
		// get the xml from the model
		$this->lti_xml_v1->setConfiguration( 'basic_lti_link' );
		$xml = $this->lti_xml_v1->getXml();
		echo $xml;
	}

	/**
	 * build xml file to describe lti setup
	 * this uses the default configuration "common_cartridge"
	 * */
	function commoncartridge(){
		// get the xml from the model
		$this->lti_xml_v1->setConfiguration( 'common_cartridge' );
		$xml = $this->lti_xml_v1->getXml();
		echo $xml;
	}

	/**
	 * build xml file to describe lti setup
	 * this uses configuration "thin_common_cartridge"
	 * */
	function thincommoncartridge() {
		// get the xml from the model
		$this->lti_xml_v1->setConfiguration( 'thin_common_cartridge' );
		$xml = $this->lti_xml_v1->getXml();
		echo $xml;
	}

	/**
	 * build xml file to describe lti setup
	 * this uses configuration "publication"
	 * */
	function publication() {
		// get the xml from the model
		$this->lti_xml_v1->setConfiguration( 'publication' );
		$xml = $this->lti_xml_v1->getXml();
		echo $xml;
	}

}
