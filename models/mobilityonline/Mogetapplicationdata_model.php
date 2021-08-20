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
	 * @param array $data
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
	 * @param array $data
	 * @return array|null applications on success, null otherwise
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
	 * Get application ids by search params, additional search params possible (applicationdataelements),
	 * Possible to restrict returned data by search restrictions.
	 * @param array $data
	 * @return array|null
	 */
	public function getSpecifiedApplicationDataBySearchParametersWithFurtherSearchRestrictions($data)
	{
		$success = $this->performCall('getSpecifiedApplicationDataBySearchParametersWithFurtherSearchRestrictions', $data);

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
	 * @param int $appId
	 * @return array application on success, null otherwise
	 */
	public function getApplicationById($appId)
	{
		$success = $this->performCall('getApplicationDataByID', array('applicationID' => $appId));

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
		$test->return->applicationID = $appId;
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
	 * @param array $data
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
	 * @param int $appId
	 * @return array address on success, null otherwise
	 */
	public function getPermanentAddress($appId)
	{
		$success = $this->performCall('getPermanentAddress', array('applicationID' => $appId));

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get current (stiudium) adress of applicant
	 * @param int $appId
	 * @return array address on success, null otherwise
	 */
	public function getCurrentAddress($appId)
	{
		$success = $this->performCall('getCurrentAddress', array('applicationID' => $appId));

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get bank account data of applicant
	 * @param int $appId
	 * @return array bank data on success, null otherwise
	 */
	public function getBankAccountDetails($appId)
	{
		$success = $this->performCall('getBankAccountDetails', array('applicationID' => $appId));

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get Courses an applicant has assigned for
	 * @param int $appId
	 * @return array courses on success, null otherwise
	 */
	public function getCoursesOfApplication($appId)
	{
		$success = $this->performCall('getCoursesOfLearningAgreement', array('applicationID' => $appId));

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
	 * @param int $appId
	 * @param $uploadSettingNumber
	 * @return array files on success, null otherwise
	 */
	public function getFilesOfApplication($appId, $uploadSettingNumber)
	{
		$success = $this->performCall(
			'getFilesOfApplication',
			array(
				'applicationID' => $appId,
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
	 * @param int $appId
	 * @return array files on success, null otherwise
	 */
	public function getAllFilesOfApplication($appId)
	{
		$success = $this->performCall('getAllFilesOfApplication', array('applicationID' => $appId));

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

	public function getProjectDetailsByApplicationID($appId)
	{
		$success = $this->performCall('getProjectDetailsByApplicationID', array('applicationID' => $appId));

		if (isset($success->return))
		{
			return $success->return;
		}
		else
			return null;
	}

	/**
	 * Get nomination data of an application, including project data.
	 * @param int $appId
	 * @return array nomination data on success, null otherwise
	 */
	public function getNominationDataByApplicationID($appId)
	{
		$success = $this->performCall(
			'getNominationDataByApplicationID',
			array(
				'applicationID' => $appId,
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
