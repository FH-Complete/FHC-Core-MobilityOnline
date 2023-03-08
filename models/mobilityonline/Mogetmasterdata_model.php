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
		return $this->performCall('getCoursesOfSemesterBySearchParameters', $parameters);
	}

	/**
	 * Get study fields of an institution
	 * @param int $institutionid
	 * @return array|null
	 */
	public function getStudyFieldsOfInstitution($institutionid)
	{
		if (!is_array($institutionid))
			$institutionid = array('institutionID' => $institutionid);

		return $this->performCall('getStudyFieldsOfInstitution', $institutionid);
	}

	/**
	 * Get adresses of an institution
	 * @param int $institutionid
	 * @return array|null
	 */
	public function getAddressesOfInstitution($institutionid)
	{
		if (!is_array($institutionid))
			$institutionid = array('institutionID' => $institutionid);

		return $this->performCall('getAddressesOfInstitution', $institutionid);
	}
}
