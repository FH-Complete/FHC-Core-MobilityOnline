<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for syncing incomings from MobilityOnline to fhcomplete
 */
class SyncOutgoingsFromMoLib extends SyncFromMobilityOnlineLib
{
	const MOOBJECTTYPE = 'applicationout';

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
	 * @param string $studiensemester
	 * @param array $outgoings
	 * @return array syncoutput containing info about failures/success
	 */
	public function startOutgoingSync($studiensemester, $outgoings)
	{
		$results = array('added' => array(), 'updated' => array(), 'errors' => 0, 'syncoutput' => array());
		$studCount = count($outgoings);

		if (empty($outgoings) || !is_array($outgoings) || $studCount <= 0)
		{
			$this->addInfoOutput('Keine Outgoings für Sync gefunden! Abbruch.');
		}
		else
		{
			foreach ($outgoings as $outgoing)
			{
				$outgoingData = $outgoing['data'];
				$appId = $outgoing['moid'];

				$infhccheck_bisio_id = null;
				$bisioIdRes = $this->_checkBisioInFhc($appId);

				if (isError($bisioIdRes))
				{
					$results['errors']++;
					$this->addErrorOutput("Fehler beim Verlinken des Studierden mit applicationid $appId - " .
						$outgoingData['person']['vorname'] . " " . $outgoingData['person']['nachname']);
				}
				else
				{
					// if linked in sync table, update, otherwise insert
					if (hasData($bisioIdRes))
					{
						$infhccheck_bisio_id = getData($bisioIdRes);
					}

					$student_uid = $this->saveOutgoing($appId, $outgoingData, $infhccheck_bisio_id);

					if (isset($student_uid))
					{
						if (isset($infhccheck_bisio_id))
						{
							$results['updated'][] = $appId;
							$actionText = 'aktualisiert';
						}
						else
						{
							$results['added'][] = $appId;
							$actionText = 'hinzugefügt';
						}

						$this->addSuccessOutput("Student mit applicationid $appId - " .
							$outgoingData['person']['vorname'] . " " . $outgoingData['person']['nachname'] . " erfolgreich $actionText");
					}
					else
					{
						$results['errors']++;
						$this->addErrorOutput("Fehler beim Synchronisieren des Studierenden mit applicationid $appId - " .
							$outgoingData['person']['vorname'] . " " . $outgoingData['person']['nachname']);
					}
				}
			}
		}

		$results['syncoutput'] = $this->getOutput();
		return $results;
	}

	/**
	 * Converts MobilityOnline application to fhcomplete array (with person, prestudent...)
	 * @param object $moApp MobilityOnline application
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoAppToOutgoing($moApp, $bankdata = null, $nominationData = null)
	{
		$fieldMappings = $this->conffieldmappings[self::MOOBJECTTYPE];
		$bisioMappings = $fieldMappings['bisio'];
		$prestudentMappings = $fieldMappings['prestudent'];
		$bisioinfoMappings = $fieldMappings['bisio_info'];

		// applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
		$comboboxValueFields = array($bisioMappings['nation_code'], $prestudentMappings['studiensemester_kurzbz'],
			$prestudentMappings['studiengang_kz']);

		// applicationDataElements for which comboboxSecondValue is retrieved instead of elementValue
		$comboboxSecondValueFields = array($bisioMappings['universitaet']);

		// applicationDataElements for which comboboxSecondValue is retrieved instead of elementValue
		$elementvalueBooleanFields = array($bisioinfoMappings['ist_praktikum'],
			$bisioinfoMappings['ist_masterarbeit'], $bisioinfoMappings['ist_beihilfe']);

		foreach ($fieldMappings as $fhcTable)
		{
			foreach ($fhcTable as $value)
			{
				if (isset($moApp->applicationDataElements))
				{
					// find mobility online application data fields
					foreach ($moApp->applicationDataElements as $element)
					{
						if ($element->elementName === $value)
						{
							if (in_array($element->elementName, $comboboxValueFields) && isset($element->comboboxFirstValue))
							{
								$moApp->$value = $element->comboboxFirstValue;
							}
							elseif (in_array($element->elementName, $comboboxSecondValueFields) && isset($element->comboboxSecondValue))
							{
								$moApp->$value = $element->comboboxSecondValue;
							}
							elseif (in_array($element->elementName, $elementvalueBooleanFields) && isset($element->elementValueBoolean))
							{
								$moApp->$value = $element->elementValueBoolean;
							}
							else
							{
								$moApp->$value = $element->elementValue;
							}
						}
					}
				}
			}
		}

		// Nation
		$moBisionation = $moApp->{$bisioMappings['nation_code']};

		$moNations = array(
			$bisioMappings['nation_code'] => $moBisionation
		);

		$fhcNations = $this->ci->NationModel->load();

		if (hasData($fhcNations))
		{
			foreach ($fhcNations->retval as $fhcNation)
			{
				// trying to get nations by bezeichnung
				foreach ($moNations as $configBez => $mooNation)
				{
					if ($fhcNation->kurztext === $mooNation || $fhcNation->langtext === $mooNation || $fhcNation->engltext === $mooNation)
					{
						if (isset($moApp->{$configBez}))
							$moApp->{$configBez} = $fhcNation->nation_code;
					}
				}
			}
		}

		$fhcObj = $this->convertToFhcFormat($moApp, self::MOOBJECTTYPE);
		$fhcBankData = $this->convertToFhcFormat($bankdata, 'bankdetails');

		// payments
		$payments = array();
		$paymentObjectName = 'payment';
		if (isset($nominationData->project->payments))
		{
			// payment can be object is single or array if multiple
			if (is_array($nominationData->project->payments))
			{
				foreach ($nominationData->project->payments as $payment)
				{
					$fhcPayment = $this->convertToFhcFormat($payment, $paymentObjectName);
					if ($fhcPayment['buchungsinfo']['angewiesen'] === true) // sync only if authorized
						$payments[] = $fhcPayment;
				}
			}
			else
			{
				$fhcPayment = $this->convertToFhcFormat($nominationData->project->payments, $paymentObjectName);
				if ($fhcPayment['buchungsinfo']['angewiesen'] === true) // sync only if authorized
					$payments[] = $fhcPayment;
			}

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

		$fhcObj = array_merge($fhcObj, $fhcBankData, array('zahlungen' => $payments));

		return $fhcObj;
	}

	/**
	 * Saves an outgoing
	 * @param int $appId
	 * @param array $outgoing
	 * @param int $bisio_id
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveOutgoing($appId, $outgoing, $bisio_id_existing)
	{
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($outgoing, self::MOOBJECTTYPE);

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

			$this->addErrorOutput("Abbruch des Outgoing Speicherung");
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
			$bisio_id = $this->_saveBisio($appId, $bisio_id_existing, $bisio);

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
	 * @param string $studiensemester
	 * @param int $studiengang_kz as in fhc db
	 * @return array with applications
	 */
	public function getOutgoing($studiensemester, $studiengang_kz = null)
	{
		$studiensemesterMo = $this->mapSemesterToMo($studiensemester);
		$semestersForSearch = array($studiensemesterMo);
		$searchArrays = array();
		$apps = array();

		$stgValuemappings = $this->valuemappings['frommo']['studiengang_kz'];
		$moStgName = $this->conffieldmappings['incomingcourse']['mostudiengang']['bezeichnung'];

		// searchobject to search outgoings
		$searchArray = array(
			'applicationType' => 'OUT',
			'personType' => 'S',
			'furtherSearchRestrictions' => array()
		);

		$applicationDataSearchFlags = array(
			'bit_freifeld24' => false, // double degree shouldn't be synced
			'is_storniert' => false
		);

		foreach ($applicationDataSearchFlags as $flagName => $flagValue)
		{
			$flagObj = new stdClass();
			$flagObj->elementName = $flagName;
			$flagObj->elementValueBoolean = $flagValue;
			$flagObj->elementType = 'boolean';
			$searchArray['furtherSearchRestrictions'][] = $flagObj;
		}

		// Also search for Outgoings who have entered Studienjahr as their Semester
		$studienjahrSemesterMo = $this->mapSemesterToMoStudienjahr($studiensemester);
		if (isset($studienjahrSemesterMo))
			$semestersForSearch[] = $studienjahrSemesterMo;

		foreach ($semestersForSearch as $semesterForSearch)
		{
			$searchArray['semesterDescription'] = $semesterForSearch;

			if (isset($studiengang_kz) && is_numeric($studiengang_kz))
			{
				foreach ($stgValuemappings as $mobez => $stg_kz)
				{
					if ($stg_kz === (int)$studiengang_kz)
					{
						$searchArray[$moStgName] = $mobez;
						$searchArrays[] = $searchArray;
					}
				}
			}
			else
			{
				$searchArrays[] = $searchArray;
			}
		}

		foreach ($searchArrays as $sarr)
		{
			$searchObj = $this->getSearchObj(
				self::MOOBJECTTYPE,
				$sarr
			);


			$semApps = $this->ci->MoGetAppModel->getSpecifiedApplicationDataBySearchParametersWithFurtherSearchRestrictions($searchObj);

			if (!isEmptyArray($semApps))
				$apps = array_merge($apps, $semApps);
		}

		return $this->_getOutgoingExtended($apps);
	}

	/**
	 * Links a MO application with a bisio in fhcomplete.
	 * @param int $moId
	 * @param int $bisio_id
	 * @return object
	 */
	public function linkBisio($moId, $bisio_id)
	{
		return $this->ci->MobisioidzuordnungModel->insert(array('bisio_id' => $bisio_id, 'mo_applicationid' => $moId));
	}

	/**
	 * Gets outgoings (applications) with additional data
	 * @param array $apps
	 * @param string $studiensemester for check if in mapping table
	 * @return array with applications
	 */
	private function _getOutgoingExtended($apps)
	{
		$outgoings = array();

		foreach ($apps as $application)
		{
			$appId = $application->applicationID;
			$bankData = $this->ci->MoGetAppModel->getBankAccountDetails($appId);
			$nominationData = $this->ci->MoGetAppModel->getNominationDataByApplicationID($appId);

			$fhcobj = $this->mapMoAppToOutgoing($application, $bankData, $nominationData);

			$fhcobj_extended = new StdClass();
			$fhcobj_extended->moid = $appId;

			$errors = $this->fhcObjHasError($fhcobj, self::MOOBJECTTYPE);
			$fhcobj_extended->error = $errors->error;
			$fhcobj_extended->errorMessages = $errors->errorMessages;

			$found_bisio_id = $this->_checkBisioInFhc($appId);

			if (isError($found_bisio_id))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = 'Fehler beim verlinken der bisio_id mit der appid';
			}

			// mark as already in fhcomplete if bisio is in mapping table
			if (hasData($found_bisio_id))
			{
				$fhcobj_extended->infhc = true;
			}
			elseif ($fhcobj_extended->error === false)
			{
				// check if has not mapped bisios in fhcomplete
				$existingBisiosRes = $this->ci->MoFhcModel->getBisio($fhcobj['bisio']['student_uid']);

				if (isError($existingBisiosRes))
				{
					$fhcobj_extended->error = true;
					$fhcobj_extended->errorMessages[] = 'Fehler beim Prüfen der existierenden Mobilitäten in fhcomplete';
				}

				if (hasData($existingBisiosRes))
				{
					$existingBisios = getData($existingBisiosRes);

					$fhcobj_extended->existingBisios = $existingBisios;
					$fhcobj_extended->error = true;
					$fhcobj_extended->errorMessages[] = 'Mobilität existiert bereits in fhcomplete, zum Verlinken bitte auf Tabellenzeile klicken.';
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
	 * @param int $appId
	 * @param int $bisio_id
	 * @param array $bisio
	 * @return int|null bisio_id of inserted or updated bisio if successful, null otherwise.
	 */
	private function _saveBisio($appId, $bisio_id, $bisio)
	{
		// if linked in sync table, update, otherwise insert
		if (isset($bisio_id))
		{
			$this->stamp('update', $bisio);
			$bisioResult = $this->ci->BisioModel->update($bisio_id, $bisio);
			$this->log('update', $bisioResult, 'bisio');
		}
		else
		{
			$this->stamp('insert', $bisio);
			$bisioResult = $this->ci->BisioModel->insert($bisio);
			$this->log('insert', $bisioResult, 'bisio');

			if (hasData($bisioResult))
			{
				$bisio_id = getData($bisioResult);

				// link new bisio to mo bisio
				$this->ci->MobisioidzuordnungModel->insert(array('bisio_id' => $bisio_id, 'mo_applicationid' => $appId));
			}
		}

		return $bisio_id;
	}

	/**
	 * Inserts bankverbindung for a student or updates an existing one.
	 * @param array $bankverbindung
	 * @param int $bisio_id_existing for check if it is a new save
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
				$bankverbindungResp = $this->ci->BankverbindungModel->update($bankverbindung_id, $bankverbindung);
				$this->log('update', $bankverbindungResp, 'bankverbindung');
			}
			else
			{
				// new Bankverbindung
				$this->stamp('insert', $bankverbindung);
				$bankverbindungResp = $this->ci->BankverbindungModel->insert($bankverbindung);
				$this->log('insert', $bankverbindungResp, 'bankverbindung');
				$bankverbindung_id = getData($bankverbindungResp)[0];
			}
		}

		return $bankverbindung_id;
	}

	/**
	 * Inserts Zahlung (konto) for a student or updates an existing one.
	 * @param array $zahlung
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
	 * @param int $appId
	 * @return object error or success with found id if in fhcomplete, success with null if not in fhcomplete
	 */
	private function _checkBisioInFhc($appId)
	{
		$infhccheck_bisio_id = null;
		$this->ci->MobisioidzuordnungModel->addSelect('bisio_id');
		$bisioIdRes = $this->ci->MobisioidzuordnungModel->loadWhere(array('mo_applicationid' => $appId));

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
	 * @param string $mo_referenz_nr
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
