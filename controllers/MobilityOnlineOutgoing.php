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
				//'checkMoidsInFhc' => 'inout/outgoing:r',
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
		$studiensemesterdata = $this->StudiensemesterModel->load();

		if (isError($studiensemesterdata))
			show_error($studiensemesterdata->retval);

		$currsemdata = $this->StudiensemesterModel->getAktOrNextSemester();

		if (isError($currsemdata))
			show_error($currsemdata->retval);

		$studiengaenge = $this->MoFhcModel->getStudiengaenge();

		if (isError($studiengaenge))
			show_error($studiengaenge->retval);

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnlineOutgoing',
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
	public function syncOutgoings()
	{
		$studiensemester = $this->input->post('studiensemester');
		$outgoings = $this->input->post('outgoings');

		$outgoings = json_decode($outgoings, true);
		$syncoutput = $this->syncoutgoingsfrommolib->startOutgoingSync($studiensemester, $outgoings);

		$this->outputJsonSuccess($syncoutput);
	}

	/**
	 * Gets incomings for a studiensemester and a studiengang and outputs json
	 */
	public function getOutgoingJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$studiengang_kz = $this->input->get('studiengang_kz');

		$outgoingdata = $this->syncoutgoingsfrommolib->getOutgoing($studiensemester, $studiengang_kz);

		$this->outputJsonSuccess($outgoingdata);
	}

	public function linkBisio()
	{
		$moid = $this->input->post('moid');
		$bisio_id = $this->input->post('bisio_id');

		$linkbisiores = $this->syncoutgoingsfrommolib->linkBisio($moid, $bisio_id);

		if (hasData($linkbisiores))
			$this->outputJsonSuccess(getData($linkbisiores));
		else
			$this->outputJsonError('Error when linking outgoing');
	}
}
