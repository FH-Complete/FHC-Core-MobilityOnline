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
	 * @return void |null
	 */
	protected function setSoapClient()
	{
		try
		{
			$this->_soapClient = new SoapClient($this->_mobilityonline_config['wsdlurl'].'/'.
				$this->_mobilityonline_config['services'][$this->name]['service'].'?'.self::WSDL,
				array(
					'soap_version' => $this->_mobilityonline_config['soapversion'],
					'encoding' => $this->_mobilityonline_config['encoding'],
					'uri' => $this->_mobilityonline_config['wsdlurl'].'/'.
						$this->_mobilityonline_config['services'][$this->name]['service'].'.'.
						$this->_mobilityonline_config['services'][$this->name]['endpoint']/*,
					'default_socket_timeout' => $this->_mobilityonline_config['default_socket_timeout']*/
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
	 * @param $function name of function offered by wsdl service to call
	 * @param $data
	 * @return object returned by called function if successful call, null otherwise
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
			//error_log($e->getMessage());
			return null;
		}
	}
}
