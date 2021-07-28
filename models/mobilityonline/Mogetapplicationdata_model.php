<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functions for MobilityOnline API interaction for GetApplicationDataService
 */
class Mogetapplicationdata_model extends Mobilityonlineapi_model
{
	protected $name = 'getApplicationData';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->setSoapClient();
	}

	/**
	 * Get applications by search params, e.g. studiensemester
	 * @param $data
	 * @return array applications on success, null otherwise
	 */
	public function getApplications($data)
	{
		/* search params structure:
		$data = array();
		$data["lastName"]=NULL;
		$data["secondLastName"]=NULL;
		$data["firstName"]=NULL;
		$data["birthday"]=NULL;
		$data["matriculationNumber"]=NULL;
		$data["email"]=NULL;
		$data["applicationType"]=$application_type;
		$data["personType"]="S";
		$data["exchangeProgramNumber"]=NULL;
		$data["academicYearDescription"]=NULL;
		$data["semesterDescription"]=$semester;
		$data["studyFieldDescription"]=NULL;
		$data["login"]=NULL;
		*/

		$success = $this->performCall('getApplicationDataBySearchParameters', $data);

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
	 * Get application ids by search params, additional search params possible (applicationdataelements)
	 * @param $data
	 * @return array applications on success, null otherwise
	 */
	public function getApplicationIdsWithFurtherSearchRestrictions($data)
	{
		$success = $this->performCall('getApplicationIdsBySearchParametersWithFurtherSearchRestrictions', $data);

		if (isset($success->return))
		{
			if (is_array($success->return) || (!is_object($success->return) && !is_integer($success->return)))
				return $success->return;
			else
				return array($success->return);
		}
		else
			return null;
	}

	/**
	 * Get application by applicationid
	 * @param $appid
	 * @return array application on success, null otherwise
	 */
	public function getApplicationById($appid)
	{
		$success = $this->performCall('getApplicationDataByID', array('applicationID' => $appid));

		/*
		structure of application object:
		$test = new stdClass();
		$test->return = new stdClass();
		$appdataelements = array();
		$dateel = new stdClass();
		$datael->elementName = 'kz_bew_art';
		$datael->elementValue = 'IN';
		$datael->comboboxFirstValue = null;

		$appdataelements[] = $dateel;
		$test->return->applicationDataElements = $appdataelements;
		$test->return->applicationID = $appid;
		$test->return->email = '';
		$test->return->firstName= '';
		$test->return->lastName= '';*/

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get application ids by search params, e.g. studiensemester
	 * @param $data
	 * @return array application ids on success, null otherwise
	 */
	public function getApplicationIds($data)
	{
		$success = $this->performCall('getApplicationIdsBySearchParameters', $data);

		if (isset($success->return))
		{
			if (is_array($success->return) || !is_numeric($success->return))
				return $success->return;
			else
				return array($success->return);
		}
		else
			return null;
	}

	/**
	 * Get permanent (home) adress of applicant
	 * @param $appid
	 * @return array address on success, null otherwise
	 */
	public function getPermanentAddress($appid)
	{
		$success = $this->performCall('getPermanentAddress', array('applicationID' => $appid));

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get current (stiudium) adress of applicant
	 * @param $appid
	 * @return array address on success, null otherwise
	 */
	public function getCurrentAddress($appid)
	{
		$success = $this->performCall('getCurrentAddress', array('applicationID' => $appid));

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get bank account data of applicant
	 * @param $appid
	 * @return array bank data on success, null otherwise
	 */
	public function getBankAccountDetails($appid)
	{
		$success = $this->performCall('getBankAccountDetails', array('applicationID' => $appid));

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get Courses an applicant has assigned for
	 * @param $appid
	 * @return array courses on success, null otherwise
	 */
	public function getCoursesOfApplication($appid)
	{
		$success = $this->performCall('getCoursesOfLearningAgreement', array('applicationID' => $appid));

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
	 * Gets files associated with an application, for a certain upload setting
	 * @param $appid
	 * @param $uploadSettingNumber
	 * @return array files on success, null otherwise
	 */
	public function getFilesOfApplication($appid, $uploadSettingNumber)
	{
		$success = $this->performCall(
			'getFilesOfApplication',
			array(
				'applicationID' => $appid,
				'uploadSettingNumber' => $uploadSettingNumber
			)
		);

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
	 * Get all files of an application
	 * @param $appid
	 * @return array files on success, null otherwise
	 */
	public function getAllFilesOfApplication($appid)
	{
		$success = $this->performCall('getAllFilesOfApplication', array('applicationID' => $appid));

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

	public function getProjectDetailsByApplicationID($appid)
	{
		$success = $this->performCall('getProjectDetailsByApplicationID', array('applicationID' => $appid));

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get nomination data of an application, including project data.
	 * @param $appid
	 * @return array nomination data on success, null otherwise
	 */
	public function getNominationDataByApplicationID($appid)
	{
		$success = $this->performCall(
			'getNominationDataByApplicationID',
			array(
				'applicationID' => $appid,
				'withProjectData' => true
			)
		);

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}
}
