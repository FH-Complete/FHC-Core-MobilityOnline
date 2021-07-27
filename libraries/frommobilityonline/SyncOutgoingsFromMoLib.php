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
		$this->ci->load->model('person/person_model', 'PersonModel');
		$this->ci->load->model('person/bankverbindung_model', 'BankverbindungModel');
		$this->ci->load->model('crm/konto_model', 'KontoModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobisioidzuordnung_model', 'MobisioidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mozahlungidzuordnung_model', 'MozahlungidzuordnungModel');
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
	public function mapMoAppToOutgoing($moapp, $bankdata = null, $nominationData = null)
	{
		$fieldMappings = $this->conffieldmappings[self::MOOBJECTTYPE_OUT];
		$bisioMappings = $fieldMappings['bisio'];
		$prestudentMappings = $fieldMappings['prestudent'];
		$bisioinfoMappings = $fieldMappings['bisio_info'];

		// applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
		$comboboxValueFields = array($bisioMappings['nation_code'], $prestudentMappings['studiensemester_kurzbz'],
			$prestudentMappings['studiengang_kz']);

		// applicationDataElements for which comboboxSecondValue is retrieved instead of elementValue
		$comboboxSecondValueFields = array($bisioMappings['universitaet']);

		// applicationDataElements for which comboboxSecondValue is retrieved instead of elementValue
		$elementvalueBooleanFields = array($bisioinfoMappings['ist_double_degree'], $bisioinfoMappings['ist_praktikum'],
			$bisioinfoMappings['ist_masterarbeit'], $bisioinfoMappings['ist_beihilfe']);

		foreach ($fieldMappings as $fhctable)
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
							if (in_array($element->elementName, $comboboxValueFields) && isset($element->comboboxFirstValue))
							{
								$moapp->$value = $element->comboboxFirstValue;
							}
							elseif (in_array($element->elementName, $comboboxSecondValueFields) && isset($element->comboboxSecondValue))
							{
								$moapp->$value = $element->comboboxSecondValue;
							}
							elseif (in_array($element->elementName, $elementvalueBooleanFields) && isset($element->elementValueBoolean))
							{
								$moapp->$value = $element->elementValueBoolean;
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
		$moBisionation = $moapp->{$bisioMappings['nation_code']};

		$moNations = array(
			$bisioMappings['nation_code'] => $moBisionation
		);

		$fhcnations = $this->ci->NationModel->load();

		if (hasData($fhcnations))
		{
			foreach ($fhcnations->retval as $fhcNation)
			{
				// trying to get nations by bezeichnung
				foreach ($moNations as $configBez => $mooNation)
				{
					if ($fhcNation->kurztext === $mooNation || $fhcNation->langtext === $mooNation || $fhcNation->engltext === $mooNation)
					{
						if (isset($moapp->{$configBez}))
							$moapp->{$configBez} = $fhcNation->nation_code;
					}
				}
			}
		}

		$fhcobj = $this->convertToFhcFormat($moapp, self::MOOBJECTTYPE_OUT);
		$fhcbankdata = $this->convertToFhcFormat($bankdata, 'bankdetails');

		// payments
		$payments = array();
		$paymentObjectName = 'payment';
		if (isset($nominationData->project->payments))
		{
			if (is_array($nominationData->project->payments))
			{
				foreach ($nominationData->project->payments as $payment)
				{
					$payments[] = $this->convertToFhcFormat($payment, $paymentObjectName);
				}
			}
			else
				$payments[] = $this->convertToFhcFormat($nominationData->project->payments, $paymentObjectName);

			// check if payments already synced and set flag
			for($i = 0; $i < count($payments); $i++)
			{
				$referenzNrRes = $this->_checkPaymentInFhc($payments[$i]['buchungsinfo']['mo_referenz_nr']);

				if (isSuccess($referenzNrRes))
				{
					if (hasData($referenzNrRes))
					{
						$payments[$i]['buchungsinfo']['infhc'] = true;
					}
					else
						$payments[$i]['buchungsinfo']['infhc'] = false;
				}
			}
		}

		$fhcobj = array_merge($fhcobj, $fhcbankdata, array('zahlungen' => $payments));

		return $fhcobj;
	}

	/**
	 * Saves an outgoing
	 * @param $appid
	 * @param $outgoing
	 * @param $bisio_id
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveOutgoing($appid, $outgoing, $bisio_id_existing)
	{
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($outgoing, self::MOOBJECTTYPE_OUT);

		// check Zahlungen for errors separately
		$zahlungen = $outgoing['zahlungen'];

		foreach ($zahlungen as $zahlung)
		{
			$paymentErrors = $this->fhcObjHasError($zahlung, 'payment');

			if ($paymentErrors->error)
			{
				$errors->error = true;
				$errors->errorMessages = array_merge($errors->errorMessages, $paymentErrors->errorMessages);
			}
		}

		if ($errors->error)
		{
			foreach ($errors->errorMessages as $errorMessage)
			{
				$this->addErrorOutput($errorMessage);
			}

			$this->addErrorOutput("aborting outgoing save");
			return null;
		}

		$prestudent = $outgoing['prestudent'];
		$bisio = $outgoing['bisio'];
		$bisio_zweck = $outgoing['bisio_zweck'];
		$bisio_aufenthaltfoerderung = $outgoing['bisio_aufenthaltfoerderung'];

		if (isset($outgoing['bisio_info']))
			$bisio_info = $outgoing['bisio_info'];

		// Start DB transaction
		$this->ci->db->trans_begin();

		// get person_id
		$personRes = $this->ci->PersonModel->getByUid($bisio['student_uid']);

		if (hasData($personRes))
		{
			$person_id = getData($personRes)[0]->person_id;

			// bisio
			$bisio_id = $this->_saveBisio($appid, $bisio_id_existing, $bisio);

			if (isset($bisio_id))
			{
				// bisio Zweck

				$zweckCodesToSync = array();

				// if praktikum flag is set -> only one Zweck "Studium und Praktikum"
				if (isset($bisio_info['ist_praktikum']) && $bisio_info['ist_praktikum'] === true)
				{
					$bisio_zweck = $outgoing['bisio_zweck_studium_praktikum'];
				}

				// insert primary Zweck
				$bisio_zweck['bisio_id'] = $bisio_id;
				$zweckCodesToSync[] = $bisio_zweck['zweck_code'];

				$bisio_zweckresult = $this->ci->MoFhcModel->saveBisioZweck($bisio_zweck);

				if (hasData($bisio_zweckresult))
				{
					$this->log('insert', $bisio_zweckresult, 'bisio_zweck');
				}

				// if student writes Masterarbeit as well, insert secondary Diplom-/Masterarbeit bzw. Dissertation Zweck
				if (isset($bisio_info['ist_masterarbeit']) && $bisio_info['ist_masterarbeit'] === true)
				{
					$bisio_zweck = $outgoing['bisio_zweck_masterarbeit'];
					$bisio_zweck['bisio_id'] = $bisio_id;

					$zweckCodesToSync[] = $bisio_zweck['zweck_code'];

					$bisio_zweckresult = $this->ci->MoFhcModel->saveBisioZweck($bisio_zweck);

					if (hasData($bisio_zweckresult))
					{
						$this->log('insert', $bisio_zweckresult, 'bisio_zweck');
					}
				}

				// delete any Zweck which was not inserted/updated
				$this->ci->MoFhcModel->deleteBisioZweck($bisio_id, $zweckCodesToSync);

				// bisio Aufenthaltsförderung
				$aufenthaltfoerderungCodesToSync = array();

				$bisio_aufenthaltfoerderung['bisio_id'] = $bisio_id;

				$aufenthaltfoerderungCodesToSync[] = $bisio_aufenthaltfoerderung['aufenthaltfoerderung_code'];

				$bisio_aufenthaltfoerderungresult = $this->ci->MoFhcModel->saveBisioAufenthaltfoerderung($bisio_aufenthaltfoerderung);

				if (hasData($bisio_aufenthaltfoerderungresult))
				{
					$this->log('insert', $bisio_aufenthaltfoerderungresult, 'bisio_aufenthaltfoerderung');
				}


				// add additional aufenthaltfoerderung if Beihilfe from Bund
				if (isset($bisio_info['ist_beihilfe']) && $bisio_info['ist_beihilfe'] === true)
				{
					$bisio_aufenthaltfoerderung = $outgoing['bisio_aufenthaltfoerderung_beihilfe'];
					$bisio_aufenthaltfoerderung['bisio_id'] = $bisio_id;

					$aufenthaltfoerderungCodesToSync[] = $bisio_aufenthaltfoerderung['aufenthaltfoerderung_code'];

					$bisio_aufenthaltfoerderungresult = $this->ci->MoFhcModel->saveBisioAufenthaltfoerderung($bisio_aufenthaltfoerderung);

					if (hasData($bisio_aufenthaltfoerderungresult))
					{
						$this->log('insert', $bisio_aufenthaltfoerderungresult, 'bisio_aufenthaltfoerderung');
					}
				}

				// delete any Aufenthaltförderung code which was not inserted/updated
				$this->ci->MoFhcModel->deleteBisioAufenthaltfoerderung($bisio_id, $aufenthaltfoerderungCodesToSync);

				// Bankverbindung
				if (isset($outgoing['bankverbindung']['iban']) && !isEmptyString($outgoing['bankverbindung']['iban']))
				{
					$bankverbindung = $outgoing['bankverbindung'];
					$bankverbindung['person_id'] = $person_id;
					$bankverbindung_id = $this->_saveBankverbindung($bankverbindung, $bisio_id_existing);
				}

				// Zahlungen
				foreach ($zahlungen as $zahlung)
				{
					$zahlung['konto']['person_id'] = $person_id;
					$zahlung['konto']['studiengang_kz'] = $prestudent['studiengang_kz'];
					$zahlung['konto']['studiensemester_kurzbz'] = $prestudent['studiensemester_kurzbz'];
					$zahlung['konto']['buchungstext'] = 'Outgoingzuschuss '.$zahlung['buchungsinfo']['mo_referenz_nr'].' '.$zahlung['buchungsinfo']['mo_zahlungsgrund'];

					$this->_saveZahlung($zahlung);
				}
			}
		}

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

		foreach ($appids as $appid)
		{
			$application = $this->ci->MoGetAppModel->getApplicationById($appid);
			$bankdata = $this->ci->MoGetAppModel->getBankAccountDetails($appid);
			$nominationData = $this->ci->MoGetAppModel->getNominationDataByApplicationID($appid);

			$fhcobj = $this->mapMoAppToOutgoing($application, $bankdata, $nominationData);

			// if double degree - ignore application
			if (isset($fhcobj['bisio_info']['ist_double_degree']) && $fhcobj['bisio_info']['ist_double_degree'] === true)
				continue;

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
	 * Inserts bankverbindung for a student or updates an existing one.
	 * @param $bankverbindung
	 * @param $bisio_id_existing for check if it is a new save
	 * @return int|null bankverbindung_id of inserted or updated bankverbindung if successful, null otherwise.
	 */
	private function _saveBankverbindung($bankverbindung, $bisio_id_existing)
	{
		$bankverbindung_id = null;

		// check existent Bankverbindungen
		$this->ci->BankverbindungModel->addSelect('bankverbindung_id');
		$this->ci->BankverbindungModel->addOrder('insertamum');
		$this->ci->BankverbindungModel->addLimit(1);

		$bankverbindungRes = $this->ci->BankverbindungModel->loadWhere(array('person_id' => $bankverbindung['person_id']));

		if (isSuccess($bankverbindungRes))
		{
			if (hasData($bankverbindungRes) && isset($bisio_id_existing))
			{
				// Bankverbindung already exists and it's not first insert - update
				$bankverbindung_id = getData($bankverbindungRes)[0]->bankverbindung_id;
				$this->stamp('update', $bisio);
				$bankverbindungresp = $this->ci->BankverbindungModel->update($bankverbindung_id, $bankverbindung);
				$this->log('update', $bankverbindungresp, 'bankverbindung');
			}
			else
			{
				// new Bankverbindung
				$this->stamp('insert', $bankverbindung);
				$bankverbindungresp = $this->ci->BankverbindungModel->insert($bankverbindung);
				$this->log('insert', $bankverbindungresp, 'bankverbindung');
				$bankverbindung_id = getData($bankverbindungresp)[0];
			}
		}

		return $bankverbindung_id;
	}

	/**
	 * Inserts Zahlung (konto) for a student or updates an existing one.
	 * @param $zahlung
	 * @return int|null buchungsnr of inserted or updated konto Buchung if successful, null otherwise.
	 */
	private function _saveZahlung($zahlung)
	{
		$buchungsnr = null;

		$buchungsinfo = $zahlung['buchungsinfo'];
		$konto = $zahlung['konto'];

		// check existent Zahlungen
		$zlgZuordnungRes = $this->ci->MozahlungidzuordnungModel->loadWhere(array('mo_referenz_nr' => $buchungsinfo['mo_referenz_nr']));

		if (isSuccess($zlgZuordnungRes))
		{
			if (hasData($zlgZuordnungRes))
			{
				// Zahlung already exists - update
				$buchungsnr = getData($zlgZuordnungRes)[0]->buchungsnr;
				$this->stamp('update', $konto);
				$kontoResp = $this->ci->KontoModel->update($buchungsnr, $konto);
				$this->log('update', $kontoResp, 'konto');
			}
			else
			{
				// new Zahlung
				$konto['buchungsdatum'] = date('Y-m-d');
				$this->stamp('insert', $konto);
				$kontoResp = $this->ci->KontoModel->insert($konto);
				$this->log('insert', $kontoResp, 'konto');

				if (hasData($kontoResp))
				{
					$buchungsnr = getData($kontoResp);

					// insert new mapping into zahlungssynctable
					$this->ci->MozahlungidzuordnungModel->insert(array('buchungsnr' => $buchungsnr, 'mo_referenz_nr' => $buchungsinfo['mo_referenz_nr']));
				}
			}
		}

		return $buchungsnr;
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

	/**
	 * Check if payment is already in fhcomplete by checking sync table.
	 * @param $mo_referenz_nr
	 * @return object error or success with found buchungsnr if in fhcomplete, success with null if not in fhcomplete
	 */
	private function _checkPaymentInFhc($mo_referenz_nr)
	{
		$infhccheck_buchungsnr = null;
		$this->ci->MozahlungidzuordnungModel->addSelect('buchungsnr');
		$moReferenzNrRes = $this->ci->MozahlungidzuordnungModel->loadWhere(array('mo_referenz_nr' => $mo_referenz_nr));

		if (isError($moReferenzNrRes))
			return $moReferenzNrRes;

		if (hasData($moReferenzNrRes))
		{
			$infhccheck_buchungsnr = getData($moReferenzNrRes)[0]->buchungsnr;
		}

		return success($infhccheck_buchungsnr);
	}
}
