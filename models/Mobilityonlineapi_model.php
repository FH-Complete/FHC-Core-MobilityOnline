<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages interaction with Mobility Online API
 */
class Mobilityonlineapi_model extends FHC_Model
{
	private $_mobilityonline_config;
	private $_soapClient;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->_mobilityonline_config = $this->config->item('FHC-Core-MobilityOnline');

		$this->_soapClient = new SoapClient($this->_mobilityonline_config['wsdlurl'].'?wsdl',
			array(
				'soap_version' => $this->_mobilityonline_config['soapversion'],
				'encoding' => $this->_mobilityonline_config['encoding'],
				'uri' => $this->_mobilityonline_config['wsdlurl'].'.'.$this->_mobilityonline_config['endpoint']
			)
		);
	}

	/**
	 * Adds course to MobilityOnline
	 * @param $data coursedata, of type CoursePerSemesterDetails (see wsdl)
	 * @return bool CourseId of course added if successful, false otherwise
	 */
	public function addCoursePerSemester($data)
	{
		$id = $this->_performCall('addCoursePerSemester', array('course' => $data));
		if (isset($id->return) && is_numeric($id->return))
			return $id->return;
		else
			return false;
	}

	/**
	 * Updates course in MobilityOnline
	 * @param $data coursedata, of type CoursePerSemesterDetails (see wsdl)
	 * @return bool true if successful, false otherwise
	 */
	public function updateCoursePerSemester($data)
	{
		$success = $this->_performCall('updateCoursePerSemester', array('course' => $data));
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
		$success = $this->_performCall('removeCoursePerSemesterByCourseID', array('courseID' => $courseid));
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
		$success = $this->_performCall('removeCoursesPerSemesterBySearchParameters', array('semester' => $semester, 'academicYear' => $academicYear));
		if (isset($success->return))
			return $success->return;
		else
			return false;
	}

	/**
	 * Performs generic call of wsdl service
	 * @param $function name of function offered by wsdl service to call
	 * @param $data
	 * @return object returned by called function if successfull call, false otherwise
	 */
	private function _performCall($function, $data)
	{
		$args = array_merge(array('authority' => $this->_mobilityonline_config['authority']), $data);

		try{
			return $this->_soapClient->$function($args);
		}
		catch (SoapFault $e)
		{
			echo "<br />SOAP ERROR:";
			print_r($e->xdebug_message);
			echo "<br />-------------------------------<br />";
		}
		return false;
	}

}
