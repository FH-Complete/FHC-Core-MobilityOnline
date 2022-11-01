<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for syncing incomings from MobilityOnline to fhcomplete
 */
class SyncOutgoingCoursesFromMoLib extends SyncFromMobilityOnlineLib
{
	const MO_OBJECT_APPLICATION_TYPE = 'outgoingcoursesapplication';
	
	public function __construct()
	{
		parent::__construct();

		$this->moObjectType = 'outgoingcourse';

		$this->ci->load->model('codex/Nation_model', 'NationModel');
		$this->ci->load->model('codex/bisio_model', 'BisioModel');
		$this->ci->load->model('person/person_model', 'PersonModel');
		$this->ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->ci->load->model('crm/student_model', 'StudentModel');
		$this->ci->load->model('person/bankverbindung_model', 'BankverbindungModel');
		$this->ci->load->model('crm/konto_model', 'KontoModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetmasterdata_model', 'MoGetMasterDataModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobisioidzuordnung_model', 'MobisioidzuordnungModel');
		//$this->ci->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MoFhcModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mooutgoinglv_model', 'MoOutgoingLvModel');

		$this->ci->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncOutgoingsFromMoLib');
	}

	/**
	 * Executes sync of incomings for a Studiensemester from MO to FHC. Adds or updates incomings.
	 * @param array $outgoingCourses
	 * @return array syncoutput containing info about failures/success
	 */
	public function startOutgoingCoursesSync($outgoingCourses)
	{
		$results = array('added' => array(), 'updated' => array(), 'errors' => 0, 'syncoutput' => array());
		$studCount = count($outgoingCourses);

		if (!is_array($outgoingCourses) || isEmptyArray($outgoingCourses) || $studCount <= 0)
		{
			$this->addInfoOutput('Keine Outgoings Kurse für Sync gefunden! Abbruch.');
		}
		else
		{
			foreach ($outgoingCourses as $outgoingCourse)
			{
				$outgoingCourseData = $outgoingCourse['data'];
				$appId = $outgoingCourse['moid'];

				$infhccheck_bisio_id = $this->checkMoIdInFhc($appId);

				$outgoing_course_id = $this->saveOutgoingCourse($appId, $outgoingCourseData);

				if (isset($outgoing_course_id))
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
						$outgoingCourseData['person']['vorname'] . " " . $outgoingCourseData['person']['nachname'] . " erfolgreich $actionText");
				}
				else
				{
					$results['errors']++;
					$this->addErrorOutput("Fehler beim Synchronisieren des Studierenden mit applicationid $appId - " .
						$outgoingCourseData['person']['vorname'] . " " . $outgoingCourseData['person']['nachname']);
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
	public function getOutgoingCourses($studiensemester, $studiengang_kz = null)
	{
		$outgoingCourses = array();

		// get application data of Outgoings for semester (and Studiengang)
		$apps = $this->getApplicationBySearchParams($studiensemester, 'OUT', $studiengang_kz, self::MO_OBJECT_APPLICATION_TYPE);

		foreach ($apps as $application)
		{
			$appId = $application->applicationID;

			$coursesData = $this->ci->MoGetAppModel->getCoursesOfApplicationTranscript(39619);


			$fhcobj_extended = new StdClass();
			$fhcobj_extended->moid = $appId;

			// check if bisio already in fhc
			$found_bisio_id = $this->ci->syncoutgoingsfrommolib->checkBisioInFhc($appId);

			$bisio_id = null;
			if (isError($found_bisio_id))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = 'Fehler beim Prüfen von Bisio in FH Complete';
			}
			elseif (!hasData($found_bisio_id))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = 'Outgoing Bewerbung noch nicht in FHC';
			}
			else
			{
				//$fhcobj_extended->bisio_id = getData($found_bisio_id);
				$bisio_id = getData($found_bisio_id);
			}

			// transform MobilityOnline data to FHC outgoing courses
			$fhcobj = $this->mapMoCourseToOutgoingLv($application, $coursesData, $bisio_id);

			// check if the fhc object has errors
			$errors = $this->_outgoingCourseObjHasError($fhcobj);
			$fhcobj_extended->error = $errors->error;
			$fhcobj_extended->errorMessages = $errors->errorMessages;

			// check if courses already in fhc
			$coursesInFhc = true;

			foreach ($fhcobj['kurse'] as $kurs)
			{
				if (!isset($kurs['kursinfo']['infhc']) || $kurs['kursinfo']['infhc'] == false)
				{
					$coursesInFhc = false;
					break;
				}
			}

			// mark as already in fhcomplete if payments synced, bisio is in mapping table, or all data is synced when double degree
			if ($paymentInFhc && hasData($found_bisio_id) && $coursesInFhc)
			{
				$fhcobj_extended->infhc = true;
			}
			//~ elseif ($fhcobj_extended->error === false && !$bisio_found && !$ist_double_degree) // bisios not synced for double degrees
			//~ {
				//~ // check if has not mapped bisios in fhcomplete
				//~ $existingBisiosRes = $this->ci->MoFhcModel->getBisio($fhcobj['bisio']['student_uid']);

				//~ if (isError($existingBisiosRes))
				//~ {
					//~ $fhcobj_extended->error = true;
					//~ $fhcobj_extended->errorMessages[] = 'Fehler beim Prüfen der existierenden Mobilitäten in fhcomplete';
				//~ }

				//~ if (hasData($existingBisiosRes)) // manually select correct bisio if a bisio already exists
				//~ {
					//~ $existingBisios = getData($existingBisiosRes);

					//~ $fhcobj_extended->existingBisios = $existingBisios;
					//~ $fhcobj_extended->error = true;
					//~ $fhcobj_extended->errorMessages[] = 'Mobilität existiert bereits in fhcomplete, zum Verlinken bitte auf Tabellenzeile klicken.';
				//~ }
			//~ }

			$fhcobj_extended->data = $fhcobj;
			$outgoingCourses[] = $fhcobj_extended;
		}

		return $outgoingCourses;
	}

	/**
	 * Converts MobilityOnline application to fhcomplete array (with person, prestudent...)
	 * @param object $moApp MobilityOnline application
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoCourseToOutgoingLv($moApp, $coursesData, $bisio_id)
	{
		$fieldMappings = $this->conffieldmappings[$this->moObjectType];
		//$bisioinfoMappings = $fieldMappings['bisio_info'];

		$applicationDataElementsByValueType = array(
			// applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
			'comboboxFirstValue' => array(
			),
			// applicationDataElements for which comboboxSecondValue is retrieved instead of elementValue
			'comboboxSecondValue' => array(
			),
			// applicationDataElements for which elementValueBoolean is retrieved instead of elementValue
			'elementValueBoolean' => array(
				//~ $bisioinfoMappings['ist_praktikum'],
				//~ $bisioinfoMappings['ist_masterarbeit'],
				//~ $bisioinfoMappings['ist_beihilfe'],
				//~ $bisioinfoMappings['ist_double_degree']
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

		$fhcObj = $this->convertToFhcFormat($moAppElementsExtracted, self::MO_OBJECT_APPLICATION_TYPE);

		var_dump($moAppElementsExtracted);

		// courses
		$fhcCourses = array();
		if (!isEmptyArray($coursesData))
		{
			foreach ($coursesData as $course)
			{
				$fhcCourses[] = $this->convertToFhcFormat($course, $this->moObjectType);
			}
		}

		// check if courses already synced and set flag
		for ($i = 0; $i < count($fhcCourses); $i++)
		{
			// TODO constraint - mo id and fhc bisio id should be unique
			$checkRes = $this->_checkOutgoingCourseInFhc($fhcCourses[$i]['mo_outgoing_lv']['mo_lvid'], $bisio_id);

			if (hasData($checkRes))
			{
				$fhcCourses[$i]['mo_outgoing_lv']['infhc'] = hasData($checkRes);
			}
		}

		$fhcObj = array_merge($fhcObj, array('kurse' => $fhcCourses));

		var_dump($fhcObj);
		die();

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
		//error check for missing data etc.
		$errors = $this->_outgoingObjHasError($outgoing);

		// check Zahlungen and Akten for errors separately
		$zahlungen = isset($outgoing['zahlungen']) ? $outgoing['zahlungen'] : array();
		$akten = isset($outgoing['akten']) ? $outgoing['akten'] : array();

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
					$zahlung['konto']['studiengang_kz'] = $prestudent['studiengang_kz'];
					$zahlung['konto']['studiensemester_kurzbz'] = $prestudent['studiensemester_kurzbz'];
					$zahlung['konto']['buchungstext'] = $zahlung['buchungsinfo']['mo_zahlungsgrund'];

					// TODO studiensemester auch - aber was ist wenn in MO tatsächlich Studiensemester geändert wird? Trotzdem neue Zahlung anlegen?
					$this->_saveZahlung($zahlung, $person_id/*, $prestudent['studiensemester_kurzbz']*/);
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

	// -----------------------------------------------------------------------------------------------------------------
	// Private methods for saving an outgoing

	private function _checkOutgoingCourseInFhc($mo_lvid, $bisio_id)
	{
		$this->ci->MoOutgoingLvModel->addSelect('outgoing_lehrveranstaltung_id');
		$outgoingLvRes = $this->ci->MoOutgoingLvModel->loadWhere(array('mo_lvid' => $mo_lvid, 'bisio_id' => $bisio_id));

		if (isError($outgoingLvRes))
			return $outgoingLvRes;

		$outgoing_lehrveranstaltung_id = null;

		if (hasData($outgoingLvRes))
		{
			$outgoing_lehrveranstaltung_id = getData($outgoingLvRes)[0]->bisio_id;
		}

		return success($outgoing_lehrveranstaltung_id);
	}

	/**
	 * Check if an outgoing object has errors. Checks "sub objects" as well.
	 * @param array $outgoing
	 * @return object error object with messages
	 */
	private function _outgoingObjHasError($outgoing)
	{
		$errorResults = new StdClass();
		$errorResults->error = false;
		$errorResults->errorMessages = array();

		$objToCheck = array(
			$this->moObjectType => array($outgoing),
			'payment' => isset($outgoing['zahlungen']) ? $outgoing['zahlungen'] : array(),
			'file' => isset($outgoing['akten']) ? $outgoing['akten'] : array(),
		);

		foreach ($objToCheck as $objName => $objects)
		{
			foreach ($objects as $object)
			{
				$hasErrorObj = $this->fhcObjHasError($object, $objName);

				if ($hasErrorObj->error)
				{
					$errorResults->error = true;
					$errorResults->errorMessages[] = array_merge($errorResults->errorMessages, $hasErrorObj->errorMessages);
				}
			}
		}

		return $errorResults;
	}
}
