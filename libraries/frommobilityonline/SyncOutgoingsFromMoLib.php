<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for syncing incomings from MobilityOnline to fhcomplete
 */
class SyncOutgoingsFromMoLib extends SyncFromMobilityOnlineLib
{
	public function __construct()
	{
		parent::__construct();

		$this->moObjectType = 'applicationout';

		$this->ci->load->model('codex/Nation_model', 'NationModel');
		$this->ci->load->model('codex/bisio_model', 'BisioModel');
		$this->ci->load->model('person/person_model', 'PersonModel');
		$this->ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->ci->load->model('crm/student_model', 'StudentModel');
		$this->ci->load->model('crm/konto_model', 'KontoModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetmasterdata_model', 'MoGetMasterDataModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobilityonlinefhc_model', 'MoFhcModel');
	}

	/**
	 * Executes sync of outgoings for a Studiensemester from MO to FHC. Adds or updates outgoings.
	 * @param array $outgoings
	 * @return array syncoutput containing info about failures/success
	 */
	public function startOutgoingSync($outgoings)
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

				// get files only on sync start to avoid ot of memory error
				$documentTypes = array_keys($this->confmiscvalues['documentstosync']['outgoing']);
				$files = $this->getFiles($appId, $documentTypes);

				if (isError($files))
				{
					$errorText = getError($files);
					$errorText = is_string($errorText) ? ', ' . $errorText : '';
					$results['errors']++;
					$this->addErrorOutput("Fehler beim Holen der Files des Studierden mit applicationid $appId - " .
						$outgoingData['person']['vorname'] . " " . $outgoingData['person']['nachname'] . $errorText);
				}

				if (hasData($files))
				{
					$outgoingData['akten'] = getData($files);
				}

				$ist_double_degree = $outgoingData['bisio_info']['ist_double_degree'];

				$infhccheck_bisio_id = null;
				$bisioIdRes = $this->checkBisioInFhc($appId);

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
						if (isset($infhccheck_bisio_id) || $ist_double_degree) // double degree: only bankverbindung and payments are aktualisiert
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
	 * Gets MobilityOnline outgoings for a fhcomplete studiensemester
	 * @param string $studiensemester
	 * @param int $studiengang_kz as in fhc db
	 * @return array with applications
	 */
	public function getOutgoing($studiensemester, $studiengang_kz = null)
	{
		$outgoings = array();

		// get application data of Outgoings for semester (and Studiengang)
		$apps = $this->getApplicationBySearchParams($studiensemester, 'OUT', $studiengang_kz);

		foreach ($apps as $application)
		{
			$fhcobj_extended = new StdClass();
			$fhcobj_extended->error = false;
			$fhcobj_extended->errorMessages = array();
			$appId = $application->applicationID;

			// get additional data from Mobility Online for each application

			// get bank account data
			$bankData = $this->ci->MoGetAppModel->getBankAccountDetails($appId);

			if (isError($bankData))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = getError($bankData);
			}
			$bankData = getData($bankData);

			// nomination data for payments
			$nominationData = $this->ci->MoGetAppModel->getNominationDataByApplicationID($appId);

			// do not include payment errors for now, as error is returned when there are empty payments

			//~ if (isError($nominationData))
			//~ {
				//~ $fhcobj_extended->error = true;
				//~ $fhcobj_extended->errorMessages[] = 'Fehler beim Holen der Zahlungen: '.getError($nominationData);
			//~ }

			$nominationData = getData($nominationData);

			$institutionAddressesData = array();
			// get gast intitution for adress
			$institution_id = $this->getApplicationDataElement($application, 'elementValue', 'inst_id_gast');
			if (isset($institution_id))
			{
				$institutionAddressesData = $this->ci->MoGetMasterDataModel->getAddressesOfInstitution($institution_id);

				if (isError($institutionAddressesData))
				{
					$fhcobj_extended->error = true;
					$fhcobj_extended->errorMessages[] = 'Fehler beim Holen der Institutionsadresse: '.getError($institutionAddressesData);
				}
				$institutionAddressesData = getData($institutionAddressesData);
			}

			// transform MobilityOnline application to FHC outgoing
			$fhcobj = $this->mapMoAppToOutgoing($application, $institutionAddressesData, $bankData, $nominationData);

			$fhcobj_extended->moid = $appId;

			// check if the fhc object has errors
			$errors = $this->applicationObjHasError($fhcobj);
			$fhcobj_extended->error = $fhcobj_extended->error || $errors->error;
			$fhcobj_extended->errorMessages = array_merge($fhcobj_extended->errorMessages, $errors->errorMessages);

			$ist_double_degree = isset($fhcobj['bisio_info']['ist_double_degree']) && $fhcobj['bisio_info']['ist_double_degree'];

			// check if bisio already in fhc
			$found_bisio_id = $this->checkBisioInFhc($appId);
			$bisio_found = false;

			if (isError($found_bisio_id))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = 'Fehler beim Prüfen von Bisio in FH Complete';
			}
			elseif (hasData($found_bisio_id))
				$bisio_found = true;

			// check if payment already in fhc
			$paymentInFhc = true;

			foreach ($fhcobj['zahlungen'] as $zlg)
			{
				if (!isset($zlg['buchungsinfo']['infhc']) || $zlg['buchungsinfo']['infhc'] == false)
				{
					$paymentInFhc = false;
					break;
				}
			}

			//check if bankverbindung already in fhc
			$bankverbindungInFhc = !isset($fhcobj['bankverbindung']) || isEmptyArray($fhcobj['bankverbindung']);
			$bankverbindungInFhcRes = $this->_checkBankverbindungInFhc($fhcobj['person']['mo_person_id']);

			if (isSuccess($bankverbindungInFhcRes))
			{
				if (hasData($bankverbindungInFhcRes))
				{
					$bankverbindungInFhc = true;
				}
			}

			// mark as already in fhcomplete if payments synced, bisio is in mapping table, or all data is synced when double degree
			if ($paymentInFhc && (hasData($found_bisio_id) || ($ist_double_degree && $bankverbindungInFhc)))
			{
				$fhcobj_extended->infhc = true;
			}
			elseif ($fhcobj_extended->error === false && !$bisio_found && !$ist_double_degree) // bisios not synced for double degrees
			{
				// check if has not mapped bisios in fhcomplete
				$existingBisiosRes = $this->ci->MoFhcModel->getBisio($fhcobj['bisio']['student_uid']);

				if (isError($existingBisiosRes))
				{
					$fhcobj_extended->error = true;
					$fhcobj_extended->errorMessages[] = 'Fehler beim Prüfen der existierenden Mobilitäten in fhcomplete';
				}

				if (hasData($existingBisiosRes)) // manually select correct bisio if a bisio already exists
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

	/**
	 * Converts MobilityOnline application to fhcomplete array (with person, prestudent...)
	 * @param object $moApp MobilityOnline application
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoAppToOutgoing($moApp, $institutionAddressesData = null, $bankData = null, $nominationData = null)
	{
		// get correct institutionAddress - first active Hauptadresse
		$institutionAddressData = null;
		if (isset($institutionAddressesData) && is_array($institutionAddressesData))
		{
			foreach ($institutionAddressesData as $institutionAddress)
			{
				if ($institutionAddress->typeOfAddress == 'Hauptadresse' && $institutionAddress->active == true)
				{
					$institutionAddressData = $institutionAddress;
					break;
				}
			}
		}

		$fieldMappings = $this->conffieldmappings[$this->moObjectType];
		$bisioMappings = $fieldMappings['bisio'];
		$prestudentMappings = $fieldMappings['prestudent'];
		$bisioinfoMappings = $fieldMappings['bisio_info'];
		$adresseMappings = $this->conffieldmappings['instaddress']['institution_adresse'];

		$applicationDataElementsByValueType = array(
			// applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
			'comboboxFirstValue' => array(
				$bisioMappings['nation_code'],
				$bisioMappings['herkunftsland_code'],
				$prestudentMappings['studiensemester_kurzbz']
			),
			// applicationDataElements for which comboboxSecondValue is retrieved instead of elementValue
			'comboboxSecondValue' => array(
				$bisioMappings['universitaet']
			),
			// applicationDataElements for which elementValueBoolean is retrieved instead of elementValue
			'elementValueBoolean' => array(
				$bisioinfoMappings['ist_praktikum'],
				$bisioinfoMappings['ist_masterarbeit'],
				$bisioinfoMappings['ist_beihilfe'],
				$bisioinfoMappings['ist_double_degree']
			)
		);

		$moAppElementsExtracted = $moApp;

		// retrieve correct value from MO for each fieldmapping
		foreach ($fieldMappings as $fhcTable)
		{
			foreach ($fhcTable as $elementName)
			{
				$valueType = 'elementValue';
				foreach ($applicationDataElementsByValueType as $valueTypeKey => $elementNameValues)
				{
					if (in_array($elementName, $elementNameValues))
					{
						$valueType = $valueTypeKey;
						break;
					}
				}

				$found = false;
				$appDataValue = $this->getApplicationDataElement($moApp, $valueType, $elementName, $found);

				if ($found === true)
					$moAppElementsExtracted->$elementName = $appDataValue;
			}
		}

		// remove original applicationDataElements
		unset($moAppElementsExtracted->applicationDataElements);

		// Nation
		$moBisionation = $moAppElementsExtracted->{$bisioMappings['nation_code']};
		$moBisioHerkunftnation = $moAppElementsExtracted->{$bisioMappings['herkunftsland_code']};
		$moInstitutionAddrNation = isset($institutionAddressData) ? $institutionAddressData->{$adresseMappings['nation']['name']}->description : null;

		$moNations = array(
			$bisioMappings['nation_code'] => $moBisionation,
			$bisioMappings['herkunftsland_code'] => $moBisioHerkunftnation
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
						if (isset($moAppElementsExtracted->{$configBez}))
							$moAppElementsExtracted->{$configBez} = $fhcNation->nation_code;
					}
				}

				if (isset($institutionAddressData) &&
						($fhcNation->kurztext === $moInstitutionAddrNation || $fhcNation->langtext === $moInstitutionAddrNation
							|| $fhcNation->engltext === $moInstitutionAddrNation))
				{
					$institutionAddressData->{$adresseMappings['nation']['name']} = $fhcNation->nation_code;
				}
			}
		}

		$fhcObj = $this->convertToFhcFormat($moAppElementsExtracted, $this->moObjectType);
		$fhcAddr = $this->convertToFhcFormat($institutionAddressData, 'instaddress');
		$fhcBankData = $this->convertToFhcFormat($bankData, 'bankdetails');

		// payments
		$payments = $this->getPaymentsFromNominationData($fhcObj['bisio']['student_uid'], $nominationData);

		$fhcObj = array_merge($fhcObj, $fhcAddr, $fhcBankData, array('zahlungen' => $payments));

		return $fhcObj;
	}

	/**
	 * Saves an outgoing.
	 * @param int $appId
	 * @param array $outgoing
	 * @param int $bisio_id_existing bisio id if bisio already exists
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveOutgoing($appId, $outgoing, $bisio_id_existing)
	{
		//error check for missing data etc.
		$errors = $this->applicationObjHasError($outgoing);

		if ($errors->error)
		{
			foreach ($errors->errorMessages as $errorMessage)
			{
				$this->addErrorOutput($errorMessage);
			}

			$this->addErrorOutput("Abbruch der Outgoing Speicherung");
			return null;
		}

		// get Zahlungen and Akten
		$zahlungen = isset($outgoing['zahlungen']) ? $outgoing['zahlungen'] : array();
		$akten = isset($outgoing['akten']) ? $outgoing['akten'] : array();

		$person = $outgoing['person'];
		$prestudent = $outgoing['prestudent'];
		$bisio = $outgoing['bisio'];
		$bisio_zweck = $outgoing['bisio_zweck'];
		$bisio_aufenthaltfoerderung = $outgoing['bisio_aufenthaltfoerderung'];

		// optional fields

		if (isset($outgoing['institution_adresse'])) // instituion adress is optional
		{
			$bisio_adresse = $outgoing['institution_adresse'];

			// get Bezeichnung of Nation by code
			$nationRes = $this->ci->NationModel->loadWhere(array('nation_code' => $bisio_adresse['nation']));

			if (hasData($nationRes))
			{
				// set bisio ort from and Nation from institution address
				$bisio['ort'] = $bisio_adresse['ort'] . ', ' . getData($nationRes)[0]->langtext;
			}
		}

		if (isset($outgoing['bisio_info']))
			$bisio_info = $outgoing['bisio_info'];

		// Start DB transaction
		$this->ci->db->trans_begin();

		// get person_id
		$personRes = $this->ci->PersonModel->getByUid($bisio['student_uid']);
		// get prestudent_id
		$this->ci->StudentModel->addSelect('prestudent_id');
		$prestudentRes = $this->ci->StudentModel->loadWhere(array('student_uid' => $bisio['student_uid']));

		if (hasData($personRes) && hasData($prestudentRes))
		{
			$person_id = getData($personRes)[0]->person_id;
			$prestudent_id = getData($prestudentRes)[0]->prestudent_id;
			$ist_double_degree = (isset($bisio_info['ist_double_degree']) && $bisio_info['ist_double_degree'] === true);

			if (!$ist_double_degree) // for double degree students, only payments are transferred, no bisio
			{
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
				}
			}

			// if bisio is set or is double degree student
			if (isset($bisio_id) || $ist_double_degree)
			{
				// Bankverbindung
				if (isset($outgoing['bankverbindung']['iban']) && !isEmptyString($outgoing['bankverbindung']['iban']))
				{
					$bankverbindung = $outgoing['bankverbindung'];
					$bankverbindung['person_id'] = $person_id;
					$this->saveBankverbindung($bankverbindung, $person['mo_person_id']);
				}

				// Zahlungen
				foreach ($zahlungen as $zahlung)
				{
					$zahlung['konto']['studiengang_kz'] = $prestudent['studiengang_kz'];
					$zahlung['konto']['studiensemester_kurzbz'] = $prestudent['studiensemester_kurzbz'];
					$zahlung['konto']['buchungstext'] = $zahlung['buchungsinfo']['mo_zahlungsgrund'];

					// TODO studiensemester auch zur Identifikation der Zahlung
					// - aber was ist wenn in MO tatsächlich Studiensemester geändert wird? Trotzdem neue Zahlung anlegen?
					$this->saveZahlung($zahlung, $person_id/*, $prestudent['studiensemester_kurzbz']*/);
				}

				// Documents
				// save documents
				foreach ($akten as $akte)
				{
					$this->saveAkte($person_id, $akte['akte'], $prestudent_id);
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
	 * Links a MO application with a bisio in fhcomplete.
	 * @param int $moId
	 * @param int $bisio_id
	 * @return object
	 */
	public function linkBisio($moId, $bisio_id)
	{
		return $this->ci->MobisioidzuordnungModel->insert(array('bisio_id' => $bisio_id, 'mo_applicationid' => $moId));
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
	 * Check if payment is already in fhcomplete by checking sync table.
	 * @param int $mo_person_id person id in mobility online
	 * @return object error or success with found buchungsnr if in fhcomplete, success with null if not in fhcomplete
	 */
	private function _checkBankverbindungInFhc($mo_person_id)
	{
		$infhccheck_bankverbindung_id = null;
		$this->ci->MobankverbindungidzuordnungModel->addSelect('bankverbindung_id');
		$checkInFhcRes = $this->ci->MobankverbindungidzuordnungModel->loadWhere(array('mo_person_id' => $mo_person_id));

		if (isError($checkInFhcRes))
			return $checkInFhcRes;

		if (hasData($checkInFhcRes))
		{
			$infhccheck_bankverbindung_id = getData($checkInFhcRes)[0]->bankverbindung_id;
		}

		return success($infhccheck_bankverbindung_id);
	}
}
