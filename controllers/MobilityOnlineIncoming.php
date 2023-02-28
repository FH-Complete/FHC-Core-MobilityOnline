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
				'checkMoidsInFhc' => 'inout/incoming:r',
				'getPostMaxSize' => 'inout/incoming:r'
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

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnlineIncoming',
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
	public function syncIncomings()
	{
		$studiensemester = $this->input->post('studiensemester');
		$incomings = $this->input->post('incomings');

		$incomings = json_decode($incomings, true);
		$syncOutput = $this->syncincomingsfrommolib->startIncomingSync($studiensemester, $incomings);

		$this->outputJsonSuccess($syncOutput);
	}

	/**
	 * Gets maximum size of post variable from php.ini so it can be checked before posting incomings.
	 */
	public function getPostMaxSize()
	{
		$maxSizeRes = $this->syncfrommobilityonlinelib->getPostMaxSize();

		$this->outputJsonSuccess($maxSizeRes);
	}

	/**
	 * Checks for each mobility online application id in an array if the application is saved in FH-Complete
	 * returns array with Mobility Online applicationIds and prestudent_id/null for each
	 */
	public function checkMoidsInFhc()
	{
		$moids = $this->input->post('moids');

		$moidsResult = array();
		if (is_array($moids))
		{
			foreach ($moids as $moid)
			{
				$moidsResult[$moid] = $this->syncincomingsfrommolib->checkMoIdInFhc($moid);
			}
		}

		$this->outputJsonSuccess($moidsResult);
	}

	/**
	 * Gets incomings for a studiensemester and a studiengang and outputs json
	 */
	public function getIncomingJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$studiengang_kz = $this->input->get('studiengang_kz');
		$incomingData = $this->syncincomingsfrommolib->getIncoming($studiensemester, $studiengang_kz);

		$this->outputJsonSuccess($incomingData);
	}
}
