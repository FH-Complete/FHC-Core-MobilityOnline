<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages Outgoing students synchronisation between fhcomplete and MobilityOnline
 */
class MobilityOnlineOutgoingCourses extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'inout/outgoing:rw',
				'syncOutgoingCourses' => 'inout/outgoing:rw',
				'getOutgoingCoursesJson' => 'inout/outgoing:r'
			)
		);

		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->model('organisation/Studiengang_model', 'StudiengangModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MoFhcModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineSyncLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncFromMobilityOnlineLib');
		//$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncOutgoingsFromMoLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncOutgoingCoursesFromMoLib');
	}

	/**
	 * Index Controller
	 * @return void
	 */
	public function index()
	{
		$this->load->library('WidgetLib');

		$this->StudiensemesterModel->addOrder('start', 'DESC');
		$studiensemesterData = $this->StudiensemesterModel->load();

		if (isError($studiensemesterData))
			show_error(getError($studiensemesterData));

		$currSemData = $this->StudiensemesterModel->getAktOrNextSemester();

		if (isError($currSemData))
			show_error(getError($currSemData));

		$studiengaenge = $this->MoFhcModel->getStudiengaenge();

		if (isError($studiengaenge))
			show_error(getError($studiengaenge));

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnlineOutgoingCourses',
			array(
				'semester' => getData($studiensemesterData),
				'currsemester' => getData($currSemData),
				'studiengaenge' => getData($studiengaenge)
			)
		);
	}

	/**
	 * Syncs incomings (applications) from MobilityOnline to fhcomplete
	 * input: incomingids, studiensemester
	 */
	public function syncOutgoingCourses()
	{
		$studiensemester = $this->input->post('studiensemester');
		$outgoingCourses = $this->input->post('outgoingCourses');

		$outgoingCourses = json_decode($outgoingCourses, true);
		$syncOutput = $this->syncoutgoingcoursesfrommolib->startOutgoingCoursesSync($outgoingCourses);

		$this->outputJsonSuccess($syncOutput);
	}

	/**
	 * Gets outgoing courses for a studiensemester and a studiengang and outputs json
	 */
	public function getOutgoingCoursesJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$studiengang_kz = $this->input->get('studiengang_kz');
		$studiengang_kz = 256;

		$outgoingCoursesData = $this->syncoutgoingcoursesfrommolib->getOutgoingCourses($studiensemester, $studiengang_kz);

		var_dump($outgoingCoursesData);
		die();

		$this->outputJsonSuccess($outgoingCoursesData);
	}
}
