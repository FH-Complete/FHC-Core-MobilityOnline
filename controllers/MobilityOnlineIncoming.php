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
			'index' => 'admin:rw',
			'syncIncomings' => 'admin:rw',
			'getIncomingJson' => 'admin:rw'
			)
		);

		$this->config->load('extensions/FHC-Core-MobilityOnline/config');

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
		$ids = $this->input->post('incomingids[]');
		$studiensemester = $this->input->post('studiensemester');

		if (empty($ids))
		{
			echo "No ids selected for sync! aborting.";
			return null;
		}

		$response = $this->_getIncomingByIds($ids, $studiensemester);

		$studcount = count($response);

		if ($studcount <= 0)
		{
			echo "No incomings found for sync! aborting.";
			return null;
		}

		$added = $updated = 0;

		echo "MOBILITY ONLINE INCOMINGS SYNC start. $studcount incomings to sync.";
		echo '<br/>-----------------------------------------------';

		foreach ($response as $incoming)
		{
			$incomingdata = $incoming->data;
			$appid = $incoming->moid;

			echo "<br />";

			if ($incoming->infhc === true)
			{
				echo "<br />prestudent for applicationid $appid " . $incomingdata['person']['vorname'] . " " . $incomingdata['person']['nachname'] . " already exists in fhcomplete - updating";

				$prestudent_id = $this->syncfrommobilityonlinelib->saveIncoming($incomingdata, $incoming->prestudent_id);

				if (isset($prestudent_id) && is_numeric($prestudent_id))
				{
					$result = $this->MoappidzuordnungModel->update(
						array('mo_applicationid' => $appid, 'studiensemester_kurzbz' => $studiensemester),
						array('updateamum' => 'NOW()')
					);

					if (hasData($result))
					{
						$updated++;
						echo "<br />student for applicationid $appid - " . $incomingdata['person']['vorname'] . " " . $incomingdata['person']['nachname'] . " successfully updated";
					}
				}
				else
				{
					echo "<br />error when updating student for applicationid $appid - " . $incomingdata['person']['vorname'] . " " . $incomingdata['person']['nachname'];
				}
			}
			else
			{
				if ($incoming->inmappingtable === true)
					echo "<br /><br />prestudent for applicationid $appid " . $incomingdata['person']['vorname'] . " " . $incomingdata['person']['nachname'] . " exists in mapping table but not in fhcomplete - adding";

				$prestudent_id = $this->syncfrommobilityonlinelib->saveIncoming($incomingdata);

				if (isset($prestudent_id) && is_numeric($prestudent_id))
				{
					$result = $this->MoappidzuordnungModel->insert(
						array('mo_applicationid' => $appid, 'prestudent_id' => $prestudent_id, 'studiensemester_kurzbz' => $studiensemester, 'insertamum' => 'NOW()')
					);

					if (hasData($result))
					{
						$added++;
						echo "<br />student for applicationid $appid - " . $incomingdata['person']['vorname']." ".$incomingdata['person']['nachname'] . " successfully added";
					}
					else
						echo "<br />mapping entry in db could not be added student for applicationid $appid - " . $incomingdata['person']['vorname']." ".$incomingdata['person']['nachname'];
				}
				else
				{
					echo "<br />error when adding student for applicationid $appid - " . $incomingdata['person']['vorname']." ".$incomingdata['person']['nachname'];
				}
			}
		}
		echo '<br /><br />-----------------------------------------------';
		echo "<br />MOBILITY ONLINE INCOMINGS SYNC FINISHED <br />$added incomings added, $updated incomings updated";
	}

	/**
	 * Gets incomings for a studiensemester and outputs json
	 */
	public function getIncomingJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$json = null;
		$incomingdata = $this->_getIncoming($studiensemester);

		$this->outputJsonSuccess($incomingdata);
	}

	/**
	 * Gets incomings for a studiensemester
	 * @param $studiensemester
	 * @return array|bool array with applications on success, false otherwise
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

		if ($appids === false)
			return false;
		elseif (is_null($appids))
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
		$response = array();

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
			$fhcobj_extended->inmappingtable = false;

			$errors = $this->syncfrommobilityonlinelib->fhcObjHasError($fhcobj, 'application');
			$fhcobj_extended->error = $errors->error;
			$fhcobj_extended->errorMessages = $errors->errorMessages;

			if (hasData($zuordnung))
			{
				$fhcobj_extended->inmappingtable = true;

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
			$response[] = $fhcobj_extended;
		}

		return $response;
	}
}
