<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages Incoming students synchronisation between fhcomplete and MobilityOnline
 */
class MobilityOnlineIncoming extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
			'index' => 'inout/incoming:rw',
			'syncIncomings' => 'inout/incoming:rw',
			'getIncomingJson' => 'inout/incoming:r',
			'checkMoidsInFhc' => 'inout/incoming:r'
			)
		);

		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->model('organisation/Studiengang_model', 'StudiengangModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MoFhcModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineSyncLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncFromMobilityOnlineLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncIncomingsFromMoLib');
	}

	/**
	 * Index Controller
	 * @return void
	 */
	public function index()
	{
		$this->load->library('WidgetLib');

		$this->StudiensemesterModel->addOrder('start', 'DESC');
		$studiensemesterdata = $this->StudiensemesterModel->load();

		if (isError($studiensemesterdata))
			show_error($studiensemesterdata->retval);

		$currsemdata = $this->StudiensemesterModel->getAktOrNextSemester();

		if (isError($currsemdata))
			show_error($currsemdata->retval);

		$studiengaenge = $this->MoFhcModel->getStudiengaenge();

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnlineIncoming',
			array(
				'semester' => $studiensemesterdata->retval,
				'currsemester' => $currsemdata->retval,
				'studiengaenge' => $studiengaenge->retval
			)
		);
	}

	/**
	 * Syncs incomings (applications) from MobilityOnline to fhcomplete
	 * input: incomingids, studiensemester
	 */
	public function syncIncomings()
	{
		$incomings = $this->input->post('incomings');
		$studiensemester = $this->input->post('studiensemester');

		$incomings = json_decode($incomings, true);

		$syncoutput = $this->syncincomingsfrommolib->startIncomingSync($studiensemester, $incomings);

		$this->outputJsonSuccess($syncoutput);
	}

	/**
	 * Checks for each mobility online application id in an array if the application is saved in FH-Complete
	 * returns array with Mobility Online applicationIds and prestudent_id/null for each
	 */
	public function checkMoidsInFhc()
	{
		$moids = $this->input->post('moids');

		$moidsresult = array();

		foreach ($moids as $moid)
		{
			$moidsresult[$moid] = $this->syncincomingsfrommolib->checkMoIdInFhc($moid);
		}

		$this->outputJsonSuccess($moidsresult);
	}

	/**
	 * Gets incomings for a studiensemester and outputs json
	 */
	public function getIncomingJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$studiengang_kz = $this->input->get('studiengang_kz');
		$incomingdata = $this->syncincomingsfrommolib->getIncoming($studiensemester, $studiengang_kz);

		$this->outputJsonSuccess($incomingdata);
	}
}
