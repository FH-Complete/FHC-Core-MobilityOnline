<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages Outgoing students synchronisation between fhcomplete and MobilityOnline
 */
class MobilityOnlineOutgoing extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'inout/outgoing:rw',
				'syncOutgoings' => 'inout/outgoing:rw',
				'getOutgoingJson' => 'inout/outgoing:r',
				'getPostMaxSize' => 'inout/outgoing:r',
				'linkBisio' => 'inout/outgoing:r'
			)
		);

		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->model('organisation/Studiengang_model', 'StudiengangModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MoFhcModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineSyncLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncFromMobilityOnlineLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncOutgoingsFromMoLib');
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

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnlineOutgoing',
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
	public function syncOutgoings()
	{
		$studiensemester = $this->input->post('studiensemester');
		$outgoings = $this->input->post('outgoings');

		$outgoings = json_decode($outgoings, true);
		$syncOutput = $this->syncoutgoingsfrommolib->startOutgoingSync($outgoings);

		$this->outputJsonSuccess($syncOutput);
	}

	/**
	 * Gets incomings for a studiensemester and a studiengang and outputs json
	 */
	public function getOutgoingJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$studiengang_kz = $this->input->get('studiengang_kz');

		$outgoingData = $this->syncoutgoingsfrommolib->getOutgoing($studiensemester, $studiengang_kz);

		$this->outputJsonSuccess($outgoingData);
	}

	/**
	 * Links a FHC Mobilität (bisio) with a Mobility Online application.
	 */
	public function linkBisio()
	{
		$moid = $this->input->post('moid');
		$bisio_id = $this->input->post('bisio_id');

		$linkBisioRes = $this->syncoutgoingsfrommolib->linkBisio($moid, $bisio_id);

		if (hasData($linkBisioRes))
			$this->outputJsonSuccess(getData($linkBisioRes));
		else
			$this->outputJsonError('Fehler beim Verlinken des Outgoing');
	}
}
