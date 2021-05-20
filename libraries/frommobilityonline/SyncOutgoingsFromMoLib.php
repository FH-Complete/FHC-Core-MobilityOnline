<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for syncing incomings from MobilityOnline to fhcomplete
 */
class SyncOutgoingsFromMoLib extends SyncFromMobilityOnlineLib
{
	const MOOBJECTTYPE = 'application';
	const MOOBJECTTYPE_OUT = 'applicationout';

	public function __construct()
	{
		parent::__construct();

		$this->ci->load->model('codex/Nation_model', 'NationModel');
		$this->ci->load->model('codex/bisio_model', 'BisioModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobisioidzuordnung_model', 'MobisioidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobilityonlinefhc_model', 'MoFhcModel');
	}

	/**
	 * Executes sync of incomings for a Studiensemester from MO to FHC. Adds or updates incomings.
	 * @param $studiensemester
	 * @param $outgoings
	 * @return array syncoutput containing info about failures/success
	 */
	public function startOutgoingSync($studiensemester, $outgoings)
	{
		$results = array('added' => array(), 'updated' => array(), 'errors' => 0, 'syncoutput' => array());
		$studcount = count($outgoings);

		if (empty($outgoings) || !is_array($outgoings) || $studcount <= 0)
		{
			$this->addInfoOutput('No outgoings found for sync! aborting.');
		}
		else
		{
			foreach ($outgoings as $outgoing)
			{
				$outgoingdata = $outgoing['data'];
				$appid = $outgoing['moid'];

				$infhccheck_bisio_id = null;
				$bisioIdRes = $this->_checkBisioInFhc($appid);

				if (isError($bisioIdRes))
				{
					$results['errors']++;
					$this->addErrorOutput("error when linking student for applicationid $appid - " .
						$outgoingdata['person']['vorname'] . " " . $outgoingdata['person']['nachname']);
				}
				else
				{
					// if linked in sync table, update, otherwise insert
					if (hasData($bisioIdRes))
					{
						$infhccheck_bisio_id = getData($bisioIdRes);
					}

					$student_uid = $this->saveOutgoing($appid, $outgoingdata, $infhccheck_bisio_id);

					if (isset($student_uid))
					{
						if (isset($infhccheck_bisio_id))
						{
							$results['updated'][] = $appid;
							$actiontext = 'updated';
						}
						else
						{
							$results['added'][] = $appid;
							$actiontext = 'added';
						}

						$this->addSuccessOutput("student for applicationid $appid - " .
							$outgoingdata['person']['vorname'] . " " . $outgoingdata['person']['nachname'] . " successfully $actiontext");
					}
					else
					{
						$results['errors']++;
						$this->addErrorOutput("error when syncing student for applicationid $appid - " .
							$outgoingdata['person']['vorname'] . " " . $outgoingdata['person']['nachname']);
					}
				}
			}
		}

		$results['syncoutput'] = $this->getOutput();
		return $results;
	}

	/**
	 * Converts MobilityOnline application to fhcomplete array (with person, prestudent...)
	 * @param $moapp MobilityOnline application
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoAppToOutgoing($moapp)
	{
		$fieldmappings = $this->conffieldmappings[self::MOOBJECTTYPE_OUT];
		$bisiomappings = $fieldmappings['bisio'];

		// applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
		$comboboxvaluefields = array($bisiomappings['nation_code']);

		// applicationDataElements for which comboboxSecondValue is retrieved instead of elementValue
		$comboboxsecondvaluefields = array($bisiomappings['universitaet']);

		foreach ($fieldmappings as $fhctable)
		{
			foreach ($fhctable as $value)
			{
				if (isset($moapp->applicationDataElements))
				{
					// find mobility online application data fields
					foreach ($moapp->applicationDataElements as $element)
					{
						if ($element->elementName === $value)
						{
							if (in_array($element->elementName, $comboboxvaluefields) && isset($element->comboboxFirstValue))
							{
								$moapp->$value = $element->comboboxFirstValue;
							}
							elseif (in_array($element->elementName, $comboboxsecondvaluefields) && isset($element->comboboxSecondValue))
							{
								$moapp->$value = $element->comboboxSecondValue;
							}
							else
							{
								$moapp->$value = $element->elementValue;
							}
						}
					}
				}
			}
		}

		// Nation
		$mobisionation = $moapp->{$bisiomappings['nation_code']};


		$monations = array(
			$bisiomappings['nation_code'] => $mobisionation
		);

		$fhcnations = $this->ci->NationModel->load();

		if (hasData($fhcnations))
		{
			foreach ($fhcnations->retval as $fhcnation)
			{
				// trying to get nations by bezeichnung
				foreach ($monations as $configbez => $moonation)
				{
					if ($fhcnation->kurztext === $moonation || $fhcnation->langtext === $moonation || $fhcnation->engltext === $moonation)
					{
						if (isset($moapp->{$configbez}))
							$moapp->{$configbez} = $fhcnation->nation_code;
					}
				}
			}
		}

		$fhcobj = $this->convertToFhcFormat($moapp, self::MOOBJECTTYPE_OUT);

		return $fhcobj;
	}

	/**
	 * Saves an outgoing
	 * @param $appid
	 * @param $outgoing
	 * @param $bisio_id
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveOutgoing($appid, $outgoing, $bisio_id)
	{
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($outgoing, self::MOOBJECTTYPE_OUT);

		if ($errors->error)
		{
			$this->addErrorOutput("ERROR! ");
			foreach ($errors->errorMessages as $errorMessage)
			{
				$this->addErrorOutput($errorMessage);
			}

			$this->addErrorOutput("aborting outgoing save");
			return null;
		}

		$bisio = $outgoing['bisio'];
		$bisio_zweck = $outgoing['bisio_zweck'];

/*		$studiensemester = $prestudentstatus['studiensemester_kurzbz'];

		// all semesters, one for each prestudentstatus
		$studiensemarr = $outgoing['all_studiensemester_kurzbz'];*/

		// Start DB transaction
		$this->ci->db->trans_begin();

		// bisio
		$bisio_id = $this->_saveBisio($appid, $bisio_id, $bisio);
		$bisio_zweck['bisio_id'] = $bisio_id;
		$bisio_zweckresult = $this->ci->MoFhcModel->saveBisioZweck($bisio_zweck);
		if (hasData($bisio_zweckresult))
			$this->log('insert', $bisio_zweckresult, 'bisio_zweck');

		// Transaction complete!
		$this->ci->db->trans_complete();

		// Check if everything went ok during the transaction
		if ($this->ci->db->trans_status() === false)
		{
			$this->addInfoOutput("rolling back...");
			$this->ci->db->trans_rollback();
			return null;
		}
		else
		{
			$this->ci->db->trans_commit();
			return $bisio['student_uid'];
		}
	}

	/**
	 * Gets MobilityOnline outgoings for a fhcomplete studiensemester
	 * @param $studiensemester
	 * @param $studiengang_kz as in fhc db
	 * @return array with applications
	 */
	public function getOutgoing($studiensemester, $studiengang_kz = null)
	{
		$studiensemestermo = $this->mapSemesterToMo($studiensemester);
		$semestersforsearch = array($studiensemestermo);
		$searcharrays = array();
		$appids = array();

		$stgvaluemappings = $this->valuemappings['frommo']['studiengang_kz'];
		$mostgname = $this->conffieldmappings['incomingcourse']['mostudiengang']['bezeichnung'];

		// Also search for Outgoings who have entered Studienjahr as their Semester
		$studienjahrsemestermo = $this->mapSemesterToMoStudienjahr($studiensemester);
		if (isset($studienjahrsemestermo))
			$semestersforsearch[] = $studienjahrsemestermo;

		foreach ($semestersforsearch as $semesterforsearch)
		{
			$searcharray = array('semesterDescription' => $semesterforsearch,
							   'applicationType' => 'OUT',
							   'personType' => 'S');

			if (isset($studiengang_kz) && is_numeric($studiengang_kz))
			{
				foreach ($stgvaluemappings as $mobez => $stg_kz)
				{
					if ($stg_kz === (int)$studiengang_kz)
					{
						$searcharray[$mostgname] = $mobez;
						$searcharrays[] = $searcharray;
					}
				}
			}
			else
			{
				$searcharrays[] = $searcharray;
			}
		}

		foreach ($searcharrays as $searcharray)
		{
			$appobj = $this->getSearchObj(
				self::MOOBJECTTYPE,
				$searcharray
			);

			$semappids = $this->ci->MoGetAppModel->getApplicationIds($appobj);

			if (!isEmptyArray($semappids))
				$appids = array_merge($appids, $semappids);
		}

		return $this->_getOutgoingByIds($appids);
	}

	public function linkBisio($moid, $bisio_id)
	{
		return $this->ci->MobisioidzuordnungModel->insert(array('bisio_id' => $bisio_id, 'mo_applicationid' => $moid));
	}

	/**
	 * Gets outgoings (applications) by appids
	 * also checks if incomings already are in fhcomplete
	 * (prestudent_id in tbl_mo_appidzuordnung table and tbl_prestudent)
	 * @param $appids
	 * @param $studiensemester for check if in mapping table
	 * @return array with applications
	 */
	private function _getOutgoingByIds($appids)
	{
		$outgoings = array();

		$cnt = 1;

		foreach ($appids as $appid)
		{
			if ($cnt > 5)
				break;
			$cnt++;
			$application = $this->ci->MoGetAppModel->getApplicationById($appid);

			$fhcobj = $this->mapMoAppToOutgoing($application);

			$fhcobj_extended = new StdClass();
			$fhcobj_extended->moid = $appid;

			$errors = $this->fhcObjHasError($fhcobj, self::MOOBJECTTYPE_OUT);
			$fhcobj_extended->error = $errors->error;
			$fhcobj_extended->errorMessages = $errors->errorMessages;

			$found_bisio_id = $this->_checkBisioInFhc($appid);

			if (isError($found_bisio_id))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = 'Error when linking bisio_id to appid';
			}

			// mark as already in fhcomplete if bisio is in mapping table
			if (hasData($found_bisio_id))
			{
				$fhcobj_extended->infhc = true;
				//$fhcobj_extended->prestudent_id = $found_bisio_id;
			}
			elseif ($fhcobj_extended->error === false)
			{
				// check if has not mapped bisios in fhcomplete
				$existingBisiosRes = $this->ci->MoFhcModel->getBisio($fhcobj['bisio']['student_uid']);

				if (isError($existingBisiosRes))
				{
					$fhcobj_extended->error = true;
					$fhcobj_extended->errorMessages[] = 'Error when checking existing mobilities in fhcomplete';
				}

				if (hasData($existingBisiosRes))
				{
					$existingBisios = getData($existingBisiosRes);

					$fhcobj_extended->existingBisios = $existingBisios;
					$fhcobj_extended->error = true;
					$fhcobj_extended->errorMessages[] = 'Mobility already existing in fhcomplete, please link correct mobility by clicking on row.';
				}
			}

			$fhcobj_extended->data = $fhcobj;
			$outgoings[] = $fhcobj_extended;
		}

		return $outgoings;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Private methods for saving an outgoing

	/**
	 * Inserts bisio for a student or updates an existing one.
	 * @param $appid
	 * @param $bisio_id
	 * @param $bisio
	 * @return int|null bisio_id of inserted or updated bisio if successful, null otherwise.
	 */
	private function _saveBisio($appid, $bisio_id, $bisio)
	{
		// if linked in sync table, update, otherwise insert
		if (isset($bisio_id))
		{
			$this->stamp('update', $bisio);
			$bisioresult = $this->ci->BisioModel->update($bisio_id, $bisio);
			$this->log('update', $bisioresult, 'bisio');
		}
		else
		{
			$this->stamp('insert', $bisio);
			$bisioresult = $this->ci->BisioModel->insert($bisio);
			$this->log('insert', $bisioresult, 'bisio');

			if (hasData($bisioresult))
			{
				$bisio_id = getData($bisioresult);

				// link new bisio to mo bisio
				$this->ci->MobisioidzuordnungModel->insert(array('bisio_id' => $bisio_id, 'mo_applicationid' => $appid));
			}
		}

		return $bisio_id;
	}

	/**
	 * Check if bisio is already in fhcomplete by checking sync table.
	 * @param $appid
	 * @return object error or success with found id if in fhcomplete, success with null if not in fhcomplete
	 */
	private function _checkBisioInFhc($appid)
	{
		$infhccheck_bisio_id = null;
		$this->ci->MobisioidzuordnungModel->addSelect('bisio_id');
		$bisioIdRes = $this->ci->MobisioidzuordnungModel->loadWhere(array('mo_applicationid' => $appid));

		if (isError($bisioIdRes))
			return $bisioIdRes;

		if (hasData($bisioIdRes))
		{
			$infhccheck_bisio_id = getData($bisioIdRes)[0]->bisio_id;
		}

		return success($infhccheck_bisio_id);
	}
}
