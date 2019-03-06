<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functions for MobilityOnline API interaction for GetMasterDataService
 */
class Mogetmasterdata_model extends Mobilityonlineapi_model
{
	protected $service = 'GetMasterDataService';
	protected $endpoint = 'GetMasterDataServiceHttpsSoap12Endpoint';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->setSoapClient();
	}

	/**
	 * Get courses by search parameters
	 * @param $parameters search parameters
	 * @return array|null
	 */
	public function getCoursesOfSemesterBySearchParameters($parameters)
	{
		$success = $this->performCall('getCoursesOfSemesterBySearchParameters', $parameters);

		if (isset($success->return))
		{
			if (is_array($success->return) || !is_object($success->return))
				return $success->return;
			else
				return array($success->return);
		}
		else
			return null;
	}
}
