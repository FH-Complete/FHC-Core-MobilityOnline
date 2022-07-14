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
		$this->ci->load->model('crm/student_model', 'StudentModel');
		$this->ci->load->model('person/bankverbindung_model', 'BankverbindungModel');
		$this->ci->load->model('crm/konto_model', 'KontoModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetmasterdata_model', 'MoGetMasterDataModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobisioidzuordnung_model', 'MobisioidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mozahlungidzuordnung_model', 'MozahlungidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobankverbindungidzuordnung_model', 'MobankverbindungidzuordnungModel');
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

				// get files only on sync start to avoid ot of memory error
				$documentTypes = array_keys($this->confmiscvalues['documentstosync']['outgoing']);
				$files = $this->getFiles($appId, $documentTypes);

				if (!isEmptyArray($files))
				{
					$outgoingData['akten'] = $files;
				}

				$ist_double_degree = $outgoingData['bisio_info']['ist_double_degree'];

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

		$fieldMappings = $this->conffieldmappings[self::MOOBJECTTYPE];
		$bisioMappings = $fieldMappings['bisio'];
		$prestudentMappings = $fieldMappings['prestudent'];
		$bisioinfoMappings = $fieldMappings['bisio_info'];
		$adressemappings = $this->conffieldmappings['instaddress']['institution_adresse'];

		$applicationDataElementsByValueType = array(
			// applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
			'comboboxFirstValue' => array(
				$bisioMappings['nation_code'],
				$prestudentMappings['studiensemester_kurzbz'],
				$prestudentMappings['studiengang_kz']
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
		$moInstitutionAddrNation = isset($institutionAddressData) ? $institutionAddressData->{$adressemappings['nation']['name']}->description : null;

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
						if (isset($moAppElementsExtracted->{$configBez}))
							$moAppElementsExtracted->{$configBez} = $fhcNation->nation_code;
					}
				}

				if (isset($institutionAddressData) &&
						($fhcNation->kurztext === $moInstitutionAddrNation || $fhcNation->langtext === $moInstitutionAddrNation
							|| $fhcNation->engltext === $moInstitutionAddrNation))
				{
					$institutionAddressData->{$adressemappings['nation']['name']} = $fhcNation->nation_code;
				}
			}
		}

		$fhcObj = $this->convertToFhcFormat($moAppElementsExtracted, self::MOOBJECTTYPE);
		$fhcAddr = $this->convertToFhcFormat($institutionAddressData, 'instaddress');
		$fhcBankData = $this->convertToFhcFormat($bankData, 'bankdetails');

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
			for ($i = 0; $i < count($payments); $i++)
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

		$fhcObj = array_merge($fhcObj, $fhcAddr, $fhcBankData, array('zahlungen' => $payments));

		return $fhcObj;
	}

	/**
	 * Saves an outgoing
	 * @param int $appId
	 * @param array $outgoing
	 * @param int $bisio_id_existing if bisio id if bisio already exists
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveOutgoing($appId, $outgoing, $bisio_id_existing)
	{
		//$outgoing["akten"][0]["akte"]["file_content"] = "";
		// var_dump($outgoing);
		// die();
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($outgoing, self::MOOBJECTTYPE);

		// check Zahlungen and Akten for errors separately
		$zahlungen = isset($outgoing['zahlungen']) ? $outgoing['zahlungen'] : array();
		$akten = isset($outgoing['akten']) ? $outgoing['akten'] : array();

		foreach ($zahlungen as $zahlung)
		{
			$paymentErrors = $this->fhcObjHasError($zahlung, 'payment');

			if ($paymentErrors->error)
			{
				$errors->error = true;
				$errors->errorMessages = array_merge($errors->errorMessages, $paymentErrors->errorMessages);
			}
		}

		foreach ($akten as $akte)
		{
			$akteErrors = $this->fhcObjHasError($akte, 'file');

			if ($akteErrors->error)
			{
				$errors->error = true;
				$errors->errorMessages = array_merge($errors->errorMessages, $akteErrors->errorMessages);
			}
		}

		if ($errors->error)
		{
			foreach ($errors->errorMessages as $errorMessage)
			{
				$this->addErrorOutput($errorMessage);
			}

			$this->addErrorOutput("Abbruch der Outgoing Speicherung");
			return null;
		}

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
					$this->_saveBankverbindung($bankverbindung, $person['mo_person_id']);
				}

				// Zahlungen
				foreach ($zahlungen as $zahlung)
				{
					$zahlung['konto']['person_id'] = $person_id;
					$zahlung['konto']['studiengang_kz'] = $prestudent['studiengang_kz'];
					$zahlung['konto']['studiensemester_kurzbz'] = $prestudent['studiensemester_kurzbz'];
					$zahlung['konto']['buchungstext'] = $zahlung['buchungsinfo']['mo_zahlungsgrund'];

					$this->_saveZahlung($zahlung);
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
			//'bit_freifeld24' => false, // double degree shouldn't be synced
			'is_storniert' => false // stornierte shouldn't be synced
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
			// get search object for objecttype, with searchparams ($arr) and returning only specified fields (by default)
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

			$institutionAddressesData = array();
			// get gast intitution for adress
			$institution_id = $this->getApplicationDataElement($application, 'elementValue', 'inst_id_gast');
			if (isset($institution_id))
			{
				$institutionAddressesData = $this->ci->MoGetMasterDataModel->getAddressesOfInstitution($institution_id);
			}

			// transform MobilityOnline application to FHC outgoing
			$fhcobj = $this->mapMoAppToOutgoing($application, $institutionAddressesData, $bankData, $nominationData);

			$fhcobj_extended = new StdClass();
			$fhcobj_extended->moid = $appId;

			// check for errors
			$errors = $this->fhcObjHasError($fhcobj, self::MOOBJECTTYPE);
			$fhcobj_extended->error = $errors->error;
			$fhcobj_extended->errorMessages = $errors->errorMessages;

			$ist_double_degree = isset($fhcobj['bisio_info']['ist_double_degree']) && $fhcobj['bisio_info']['ist_double_degree'];

			$found_bisio_id = $this->_checkBisioInFhc($appId);
			$bisio_found = false;

			if (isError($found_bisio_id))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = 'Fehler beim Prüfen von Bisio in FH Complete';
			}
			elseif (hasData($found_bisio_id))
				$bisio_found = true;

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

			// mark as already in fhcomplete if payments synced, bisio is in mapping table, or data is synced when double degree
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
	 * @param int $mo_person_id application id for linking bankverbindung with application in sync table
	 * @return int|null bankverbindung_id of inserted or updated bankverbindung if successful, null otherwise.
	 */
	private function _saveBankverbindung($bankverbindung, $mo_person_id)
	{
		$bankverbindung_id = null;
		$insert = false;
		$update = false;

		// check existent Bankverbindungen
		$this->ci->BankverbindungModel->addSelect('bankverbindung_id, insertvon');
		$this->ci->BankverbindungModel->addOrder('insertamum', 'DESC');
		$this->ci->BankverbindungModel->addLimit(1);

		$bankverbindungRes = $this->ci->BankverbindungModel->loadWhere(array('person_id' => $bankverbindung['person_id']));

		if (isSuccess($bankverbindungRes))
		{
			if (hasData($bankverbindungRes))
			{
				$bankverbindungData = getData($bankverbindungRes)[0];
				$bankverbindung_id = $bankverbindungData->bankverbindung_id;
				$bankverbindung_insertvon = $bankverbindungData->insertvon;

				// check synced Bankverbindungen
				$bankverbindungZuordnungRes = $this->ci->MobankverbindungidzuordnungModel->loadWhere(
					array('bankverbindung_id' => $bankverbindung_id)
				);

				if (isSuccess($bankverbindungZuordnungRes))
				{
					// if already in sync table - update
					if (hasData($bankverbindungZuordnungRes))
					{
						$update = true;
					}
					else
					{
						// not in sync table, existing bankverbindung inserted not by Mobility Online: insert new
						if ($bankverbindung_insertvon !== self::IMPORTUSER)
							$insert = true;
						else // if not in sync table, but inserted by Mobility Online
						{
							// link Bankverbindung
							$bankverbindungZuordnungInsertRes = $this->ci->MobankverbindungidzuordnungModel->insert(
								array(
									'bankverbindung_id' => $bankverbindung_id,
									'mo_person_id' => $mo_person_id
								)
							);

							if (isSuccess($bankverbindungZuordnungInsertRes))
							{
								// and update the linked Bankverbindung with data from mo
								$update = true;
							}
						}
					}
				}
			}
			else // no Bankverbindung exists, add new
				$insert = true;

			if ($insert)
			{
				// new Bankverbindung
				$this->stamp('insert', $bankverbindung);
				$bankverbindungResp = $this->ci->BankverbindungModel->insert($bankverbindung);
				$this->log('insert', $bankverbindungResp, 'bankverbindung');
				$bankverbindung_id = getData($bankverbindungResp);

				// link Bankverbindung
				$bankverbindungZuordnungInsertRes = $this->ci->MobankverbindungidzuordnungModel->insert(
					array(
						'bankverbindung_id' => $bankverbindung_id,
						'mo_person_id' => $mo_person_id
					)
				);
			}
			elseif ($update)
			{
				$this->stamp('update', $bankverbindung);
				$bankverbindungResp = $this->ci->BankverbindungModel->update($bankverbindung_id, $bankverbindung);
				$this->log('update', $bankverbindungResp, 'bankverbindung');
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
				$this->log('update', $kontoResp, 'kontobuchung');
			}
			else
			{
				// new Zahlung
				$this->stamp('insert', $konto);
				$kontoResp = $this->ci->KontoModel->insert($konto);
				$this->log('insert', $kontoResp, 'kontobuchung');

				if (hasData($kontoResp))
				{
					$buchungsnr = getData($kontoResp);

					// insert new mapping into zahlungssynctable
					$this->ci->MozahlungidzuordnungModel->insert(
						array('buchungsnr' => $buchungsnr, 'mo_referenz_nr' => $buchungsinfo['mo_referenz_nr'])
					);
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
		$checkInFhcRes = $this->ci->MozahlungidzuordnungModel->loadWhere(array('mo_referenz_nr' => $mo_referenz_nr));

		if (isError($checkInFhcRes))
			return $checkInFhcRes;

		if (hasData($checkInFhcRes))
		{
			$infhccheck_buchungsnr = getData($checkInFhcRes)[0]->buchungsnr;
		}

		return success($infhccheck_buchungsnr);
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

	/**
	 * Check if bisio for a person is already in fhcomplete by checking sync table.
	 * @param int $person_id
	 * @return object error or success with found ids if in fhcomplete, success with empty array if not in fhcomplete
	 */
	private function _checkBisioInFhcForPerson($person_id)
	{
		$this->ci->MobisioidzuordnungModel->addSelect("bisio_id");
		$this->ci->MobisioidzuordnungModel->addJoin('bis.tbl_bisio', 'bisio_id');
		$this->ci->MobisioidzuordnungModel->addJoin('public.tbl_student', 'student_uid');
		$this->ci->MobisioidzuordnungModel->addJoin('public.tbl_prestudent', 'prestudent_id');
		return $this->ci->MobisioidzuordnungModel->loadWhere(array('person_id' => $person_id));
	}
}
