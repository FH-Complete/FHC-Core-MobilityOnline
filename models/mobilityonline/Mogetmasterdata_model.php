<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functions for MobilityOnline API interaction for GetMasterDataService
 */
class Mogetmasterdata_model extends Mobilityonlineapi_model
{
	protected $name = 'getMasterData';

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
	 * @param array $parameters search parameters
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

	/**
	 * Get study fields of an institution
	 * @param array $parameters search parameters
	 * @return array|null
	 */
	public function getStudyFieldsOfInstitution($institutionid)
	{
		if (!is_array($institutionid))
			$institutionid = array('institutionID' => $institutionid);

		$success = $this->performCall('getStudyFieldsOfInstitution', $institutionid);

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
