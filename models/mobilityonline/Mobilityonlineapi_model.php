<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages interaction with Mobility Online API
 */
class Mobilityonlineapi_model extends FHC_Model
{
	private $_mobilityonline_config;
	private $_soapClient;
	protected $service = '';
	protected $endpoint = '';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->_mobilityonline_config = $this->config->item('FHC-Core-MobilityOnline');
	}

	protected function setSoapClient()
	{
		$this->_soapClient = new SoapClient($this->_mobilityonline_config['wsdlurl'].'/'.$this->service.'?wsdl',
			array(
				'soap_version' => $this->_mobilityonline_config['soapversion'],
				'encoding' => $this->_mobilityonline_config['encoding'],
				'uri' => $this->_mobilityonline_config['wsdlurl'].'/'.$this->service.'.'.$this->endpoint/*,
				'default_socket_timeout' => $this->_mobilityonline_config['default_socket_timeout']*/
			)
		);
	}

	/**
	 * Performs generic call of wsdl service
	 * @param $function name of function offered by wsdl service to call
	 * @param $data
	 * @return object returned by called function if successfull call, false otherwise
	 */
	protected function performCall($function, $data)
	{
		$args = array_merge(array('authority' => $this->_mobilityonline_config['authority']), $data);

		try
		{
			return $this->_soapClient->$function($args);
		}
		catch (SoapFault $e)
		{
			echo "<br />SOAP ERROR:";
			print_r($e);
			echo "<br />-------------------------------<br />";
		}
		return false;
	}
}
