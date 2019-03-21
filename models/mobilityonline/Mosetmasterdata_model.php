<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functions for MobilityOnline API interaction for SetMasterDataService
 */
class Mosetmasterdata_model extends Mobilityonlineapi_model
{
	protected $service = 'SetMasterDataService';
	protected $endpoint = 'SetMasterDataServiceHttpsSoap12Endpoint';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->setSoapClient();
	}

	/**
	 * Adds course to MobilityOnline
	 * @param $data coursedata, of type CoursePerSemesterDetails (see wsdl)
	 * @return mixed CourseId of course added if successful, null otherwise
	 */
	public function addCoursePerSemester($data)
	{
		$id = $this->performCall('addCoursePerSemester', array('course' => $data));
		if (isset($id->return) && is_numeric($id->return))
			return $id->return;
		else
			return null;
	}

	/**
	 * Updates course in MobilityOnline
	 * @param $data coursedata, of type CoursePerSemesterDetails (see wsdl)
	 * @return bool true if successful, false otherwise
	 */
	public function updateCoursePerSemester($data)
	{
		$success = $this->performCall('updateCoursePerSemester', array('course' => $data));
		if (isset($success->return))
			return $success->return;
		else
			return false;
	}

	/**
	 * Removes course in MobilityOnline
	 * @param $courseid id of course to remove
	 * @return bool true if successful, false otherwise
	 */
	public function removeCoursePerSemesterByCourseID($courseid)
	{
		$success = $this->performCall('removeCoursePerSemesterByCourseID', array('courseID' => $courseid));
		if (isset($success->return))
			return $success->return;
		else
			return false;
	}

	/**
	 * Removes courses in MobilityOnline
	 * @param $semester Studiensemester of courses to delete
	 * (format e.g. "Sommersemester 2018" or "Wintersemester 2018/19"
	 * @param $academicYear Studienjahr of courses to delete, e.g. "2018/2019"
	 * @return bool true if successful, false otherwise
	 */
	public function removeCoursesPerSemesterBySearchParameters($semester, $academicYear)
	{
		$success = $this->performCall('removeCoursesPerSemesterBySearchParameters', array('semester' => $semester, 'academicYear' => array('description' => $academicYear)));
		if (isset($success->return))
			return $success->return;
		else
			return false;
	}

}
