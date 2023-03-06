<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functions for MobilityOnline API interaction for SetMasterDataService
 */
class Mosetmasterdata_model extends Mobilityonlineapi_model
{
	protected $name = 'setMasterData';

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
	 * @param array $data coursedata, of type CoursePerSemesterDetails (see wsdl)
	 * @return mixed CourseId of course added if successful, null otherwise
	 */
	public function addCoursePerSemester($data)
	{
		return $this->performCall('addCoursePerSemester', array('course' => $data));
	}

	/**
	 * Updates course in MobilityOnline
	 * @param array $data coursedata, of type CoursePerSemesterDetails (see wsdl)
	 * @return bool true if successful, false otherwise
	 */
	public function updateCoursePerSemester($data)
	{
		return $this->performCall('updateCoursePerSemester', array('course' => $data));
	}

	/**
	 * Removes course in MobilityOnline
	 * @param int $courseId id of course to remove
	 * @return bool true if successful, false otherwise
	 */
	public function removeCoursePerSemesterByCourseID($courseId)
	{
		return $this->performCall('removeCoursePerSemesterByCourseID', array('courseID' => $courseId));
	}

	/**
	 * Removes courses in MobilityOnline
	 * @param string $semester Studiensemester of courses to delete
	 * (format e.g. "Sommersemester 2018" or "Wintersemester 2018/19"
	 * @param string $academicYear Studienjahr of courses to delete, e.g. "2018/2019"
	 * @return bool true if successful, false otherwise
	 */
	public function removeCoursesPerSemesterBySearchParameters($semester, $academicYear)
	{
		return $this->performCall(
			'removeCoursesPerSemesterBySearchParameters',
			array('semester' => $semester, 'academicYear' => array('description' => $academicYear))
		);
	}
}
