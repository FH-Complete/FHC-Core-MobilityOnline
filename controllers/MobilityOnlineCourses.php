<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages Lehrveranstaltung synchronisation between fhcomplete and Mobility Online
 */
class MobilityOnlineCourses extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
			'index' => 'inout/incoming:rw',
			'syncLvs' => 'inout/incoming:rw',
			'deleteLvs' => 'admin:rw',
			'getLvsJson' => 'inout/incoming:rw'
			)
		);

		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->model('education/lehrveranstaltung_model', 'LehrveranstaltungModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineSyncLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/tomobilityonline/SyncToMobilityOnlineLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/tomobilityonline/SyncCoursesToMoLib');
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

		$currSemData = '';
		$lvData = array();
		if (hasData($currSemData))
		{
			$currSem = getData($currSemData)[0]->studiensemester_kurzbz;
			$lvData = getData($this->LehrveranstaltungModel->getLvsWithIncomingPlaces($currSem));
		}


		if (isError($lvData))
			show_error(getError($lvData));

		$this->load->view(
			'extensions/FHC-Core-MobilityOnline/mobilityOnlineCourses',
			array(
				'semester' => getData($studiensemesterData),
				'currsemester' => $currSem,
				'lvs' => $lvData
			)
		);
	}

	/**
	 * Syncs courses to MobilityOnline, i.e. adds Lvs from fhcomplete to Mobility Online
	 * and removes Lvs not present in fhcomplete anymore
	 */
	public function syncLvs()
	{
		$studiensemester = $this->input->post('studiensemester');

		$results = $this->synccoursestomolib->startCoursesSync($studiensemester);

		$this->outputJsonSuccess($results);
	}

	/**
	 * Deletes courses of a given Semester From MobilityOnline
	 * @param $studiensemester
	 */
	public function deleteLvs($studiensemester)
	{
		$this->synccoursestomolib->startCoursesDeletion($studiensemester);
	}

	/**
	 * Gets courses which need to be synced to MobilityOnline and outputs as Json
	 */
	public function getLvsJson()
	{
		$studiensemester = $this->input->get('studiensemester');

		$lvData = $this->LehrveranstaltungModel->getLvsWithIncomingPlaces($studiensemester);

		if (isSuccess($lvData))
			$this->outputJsonSuccess($lvData->retval);
		else
			$this->outputJsonError("Fehler beim Holen der Kurse");
	}
}
