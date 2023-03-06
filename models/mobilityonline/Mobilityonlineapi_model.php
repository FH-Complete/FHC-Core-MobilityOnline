<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages interaction with Mobility Online API
 */
class Mobilityonlineapi_model extends CI_Model
{
	protected $name = '';
	private $_mobilityonline_config;
	private $_soapClient;

	const WSDL = 'wsdl';
	const DATETIME_NAMESPACE = 'http://xsd.types.databinding.axis2.apache.org/xsd';
	const ERROR_STR = '%s: %s'; // Error message format

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->config->load('extensions/FHC-Core-MobilityOnline/config');
		$this->_mobilityonline_config = $this->config->item('FHC-Core-MobilityOnline');
	}

	/**
	 * Establisches connection with soap client
	 */
	protected function setSoapClient()
	{
		try
		{
			$this->_soapClient = new SoapClient(
				$this->_mobilityonline_config['wsdlurl'].'/'.
				$this->_mobilityonline_config['services'][$this->name]['service'].'?'.self::WSDL,
				array(
					'soap_version' => $this->_mobilityonline_config['soapversion'],
					'encoding' => $this->_mobilityonline_config['encoding'],
					'uri' => $this->_mobilityonline_config['wsdlurl'].'/'.
						$this->_mobilityonline_config['services'][$this->name]['service'].'.'.
						$this->_mobilityonline_config['services'][$this->name]['endpoint'],
					'typemap' => array( // set typemappings for correct serialization of dates
						array(
							// type namespaces have to match those declared in the WSDL
							'type_ns' => self::DATETIME_NAMESPACE,
							'type_name' => 'DateTime',
							'from_xml' => array($this, 'datetimeFromXml') // callback for transformation of date to string
						),
    				),
					'features' => SOAP_SINGLE_ELEMENT_ARRAYS // elements appearning once are placed in array, so access is consistent
					/*'default_socket_timeout' => $this->_mobilityonline_config['default_socket_timeout']*/
				)
			);
		}
		catch (SoapFault $e)
		{
			return;
		}
	}

	/**
	 * Performs generic call of wsdl service
	 * @param string $function name of function offered by wsdl service to call
	 * @param array $data data to pass
	 * @return object returned by called function if successful call, null otherwise
	 */
	protected function performCall($function, $data)
	{
		$args = array_merge(array('authority' => $this->_mobilityonline_config['authority']), $data);

		if (!isset($this->_soapClient)) return error('SOAP client not initialized');

		if (isEmptyString($function)) return error('No function name passed');

		try
		{
			$soapRes = $this->_soapClient->{$function}($args);

			if (property_exists($soapRes, 'return'))
				return success($soapRes->return);
			else
				return error('Invalid return data');
		}
		catch (SoapFault $sf)
		{
			//error_log($e->getMessage());
			return error('SOAP error '.sprintf(self::ERROR_STR, $sf->getCode(), $sf->getMessage()));
		}
		catch (Exception $e)
		{
			//error_log($e->getMessage());
			return error('Error '.sprintf(self::ERROR_STR, $e->getCode(), $e->getMessage()));
		}
	}

	/**
	 * XML callback function for converting DateTime into php object.
	 * @param string $xml the xml date element string
	 * @return string|null the date
	 */
	public function datetimeFromXml($xml)
	{
		$dateTimeXmlObj = simplexml_load_string($xml);

		// first element is date string, or false if null
		$dateTime = reset($dateTimeXmlObj);

		return $dateTime ? $dateTime : null;
	}
}
