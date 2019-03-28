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
		$this->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MoFhcModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/mappings/Moappidzuordnung_model', 'MoappidzuordnungModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineSyncLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/SyncFromMobilityOnlineLib');
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

		$currsemdata = $this->StudiensemesterModel->getLastOrAktSemester(0);

		if (isError($currsemdata))
			show_error($currsemdata->retval);

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnlineIncoming',
			array(
				'semester' => $studiensemesterdata->retval,
				'currsemester' => $currsemdata->retval
			)
		);
	}

	/**
	 * Syncs incomings (applications) from MobilityOnline to fhcomplete
	 * input: incomingids, studiensemester
	 */
	public function syncIncomings()
	{
		$syncoutput = '';
		$incomings = $this->input->post('incomings');
		$studiensemester = $this->input->post('studiensemester');

		$incomings = json_decode($incomings, true);

		$studcount = count($incomings);

		if (empty($incomings) || !is_array($incomings) || $studcount <= 0)
		{
			$syncoutput .= "No incomings found for sync! aborting.";
		}
		else
		{
			$added = $updated = 0;

			$syncoutput .= "<div class='text-center'>MOBILITY ONLINE INCOMINGS SYNC start. $studcount incomings to sync.";
			$syncoutput .= '<br/>-----------------------------------------------</div><div class="incomingsyncoutputtext">';

			$first = true;
			foreach ($incomings as $incoming)
			{
				$incomingdata = $incoming['data'];
				$appid = $incoming['moid'];

				if (!$first)
					$syncoutput .= "<br />";
				$first = false;

				$infhccheck_prestudent_id = $this->_checkMoIdInFhc($appid);

				if (isset($infhccheck_prestudent_id) && is_numeric($infhccheck_prestudent_id))
				{
					$syncoutput .= "<br />prestudent ".("for applicationid $appid ").$incomingdata['person']['vorname'].
						" ".$incomingdata['person']['nachname']." already exists in fhcomplete - updating";

					$prestudent_id = $this->syncfrommobilityonlinelib->saveIncoming($incomingdata, $infhccheck_prestudent_id);

					$saveIncomingOutput = $this->syncfrommobilityonlinelib->getOutput();

					$syncoutput .= $saveIncomingOutput;

					if (isset($prestudent_id) && is_numeric($prestudent_id))
					{
						$result = $this->MoappidzuordnungModel->update(
							array('mo_applicationid' => $appid, 'prestudent_id' => $prestudent_id, 'studiensemester_kurzbz' => $studiensemester),
							array('updateamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$updated++;
							$syncoutput .= "<br /><i class='fa fa-check text-success'></i> student for applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']." successfully updated";
						}
					}
					else
					{
						$syncoutput .= "<br /><span class='text-danger'><i class='fa fa-times'></i> error when updating student for applicationid $appid - "
							.$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']."</span>";
					}
				}
				else
				{
					$prestudent_id = $this->syncfrommobilityonlinelib->saveIncoming($incomingdata);

					$saveIncomingOutput = $this->syncfrommobilityonlinelib->getOutput();

					$syncoutput .= $saveIncomingOutput;

					if (isset($prestudent_id) && is_numeric($prestudent_id))
					{
						$result = $this->MoappidzuordnungModel->insert(
							array('mo_applicationid' => $appid, 'prestudent_id' => $prestudent_id, 'studiensemester_kurzbz' => $studiensemester, 'insertamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$added++;
							$syncoutput .= "<br /><i class='fa fa-check text-success'></i> student for applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']." successfully added";
						}
						else
							$syncoutput .= "<br /><span class='text-danger'><i class='fa fa-times'></i> mapping entry in db could not be added student for applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']."</span>";
					}
					else
					{
						$syncoutput .= "<br /><span class='text-danger'><i class='fa fa-times'></i> error when adding student for applicationid $appid - ".
							$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']."</span>";
					}
				}
			}
			$syncoutput .= "</div><div class='text-center'><br />-----------------------------------------------";
			$syncoutput .= "<br />MOBILITY ONLINE INCOMINGS SYNC FINISHED <br />$added incomings added, $updated incomings updated</div>";

		}
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
			$moidsresult[$moid] = $this->_checkMoIdInFhc($moid);
		}

		$this->outputJsonSuccess($moidsresult);
	}

	/**
	 * Gets incomings for a studiensemester and outputs json
	 */
	public function getIncomingJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$incomingdata = $this->_getIncoming($studiensemester);

		$this->outputJsonSuccess($incomingdata);
	}

	/**
	 * Gets incomings for a studiensemester
	 * @param $studiensemester
	 * @return array with applications
	 */
	private function _getIncoming($studiensemester)
	{
		$studiensemestermo = $this->mobilityonlinesynclib->mapSemesterToMo($studiensemester);

		$appobj = $this->syncfrommobilityonlinelib->getSearchObj(
			'application',
			array('semesterDescription' => $studiensemestermo,
				  'applicationType' => 'IN',
				  'personType' => 'S')
		);

		$appids = $this->MoGetAppModel->getApplicationIds($appobj);

		if (!isset($appids))
		{
			return array();
		}

		return $this->_getIncomingByIds($appids, $studiensemester);
	}

	/**
	 * Gets incomings (applications) by appids
	 * also checks if incomings already in fhcomplete
	 * (prestudent_id in tbl_mo_appidzuordnung table and tbl_prestudent)
	 * @param $appids
	 * @param $studiensemester for check if in mapping table
	 * @return array with applications
	 */
	private function _getIncomingByIds($appids, $studiensemester)
	{
		$incomings = array();

		foreach ($appids as $appid)
		{
			$application = $this->MoGetAppModel->getApplicationById($appid);
			$address = $this->MoGetAppModel->getPermanentAddress($appid);
			$lichtbild = $this->MoGetAppModel->getFilesOfApplication($appid, 'PASSFOTO');

			$fhcobj = $this->syncfrommobilityonlinelib->mapMoAppToIncoming($application, $address, $lichtbild);

			$zuordnung = $this->MoappidzuordnungModel->loadWhere(array('mo_applicationid' => $appid, 'studiensemester_kurzbz' => $studiensemester));

			$fhcobj_extended = new StdClass();
			$fhcobj_extended->moid = $appid;

			$fhcobj_extended->infhc = false;

			$errors = $this->syncfrommobilityonlinelib->fhcObjHasError($fhcobj, 'application');
			$fhcobj_extended->error = $errors->error;
			$fhcobj_extended->errorMessages = $errors->errorMessages;

			if (hasData($zuordnung))
			{
				$prestudent_id = $zuordnung->retval[0]->prestudent_id;

				$this->load->model('crm/Prestudent_model', 'PrestudentModel');
				$prestudent_res = $this->PrestudentModel->load($prestudent_id);

				if (hasData($prestudent_res))
				{
					$fhcobj_extended->infhc = true;
					$fhcobj_extended->prestudent_id = $prestudent_id;
				}
			}

			$fhcobj_extended->data = $fhcobj;
			$incomings[] = $fhcobj_extended;
		}

		return $incomings;
	}

	/**
	 * Checks for a mobility online application id in an array if the application is saved in FH-Complete
	 * returns prestudent_id if in FHC, null otherwise
	 * @param $moid
	 * @return number|null
	 */
	private function _checkMoIdInFhc($moid)
	{
		$this->PrestudentModel->addSelect('prestudent_id');
		$appidzuordnung = $this->MoappidzuordnungModel->loadWhere(array('mo_applicationid' => $moid));
		if (hasData($appidzuordnung))
		{
			$prestudent_id = $appidzuordnung->retval[0]->prestudent_id;
			$prestudent = $this->PrestudentModel->load($prestudent_id);
			if (hasData($prestudent))
			{
				 return $prestudent_id;
			}
			else
			{
				return null;
			}
		}
		else
		{
			return null;
		}
	}
}
