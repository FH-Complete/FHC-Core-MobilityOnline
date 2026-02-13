<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/'.EXT_FKT_PATH.'/generateuid.inc.php');
require_once('include/functions.inc.php');
require_once('include/tw/generateZahlungsreferenz.inc.php');

/**
 * Functionality for syncing incomings from MobilityOnline to fhcomplete
 */
class SyncIncomingsFromMoLib extends SyncFromMobilityOnlineLib
{
	public function __construct()
	{
		parent::__construct();

		$this->moObjectType = 'application';

		$this->ci->load->model('person/person_model', 'PersonModel');
		$this->ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->ci->load->model('person/adresse_model', 'AdresseModel');
		$this->ci->load->model('person/kontakt_model', 'KontaktModel');
		$this->ci->load->model('organisation/studiensemester_model', 'StudiensemesterModel');
		$this->ci->load->model('organisation/studiengang_model', 'StudiengangModel');
		$this->ci->load->model('content/dms_model', 'DmsModel');
		$this->ci->load->model('crm/akte_model', 'AkteModel');
		$this->ci->load->model('crm/prestudent_model', 'PrestudentModel');
		$this->ci->load->model('crm/prestudentstatus_model', 'PrestudentstatusModel');
		$this->ci->load->model('crm/student_model', 'StudentModel');
		$this->ci->load->model('crm/konto_model', 'KontoModel');
		$this->ci->load->model('crm/buchungstyp_model', 'BuchungstypModel');
		$this->ci->load->model('education/lehrveranstaltung_model', 'LehrveranstaltungModel');
		$this->ci->load->model('education/lehreinheit_model', 'LehreinheitModel');
		$this->ci->load->model('education/studentlehrverband_model', 'StudentlehrverbandModel');
		$this->ci->load->model('codex/Nation_model', 'NationModel');
		$this->ci->load->model('codex/bisio_model', 'BisioModel');
		$this->ci->load->model('organisation/studienplan_model', 'StudienplanModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Moappidzuordnung_model', 'MoappidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobilityonlinefhc_model', 'MoFhcModel');

		$this->ci->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/FromMobilityOnlineDataConversionLib');
	}

	/**
	 * Executes sync of incomings for a Studiensemester from MO to FHC. Adds or updates incomings.
	 * @param string $studiensemester
	 * @param array $incomings
	 * @return array syncoutput containing info about failures/success
	 */
	public function startIncomingSync($studiensemester, $incomings)
	{
		$results = array('added' => 0, 'updated' => 0, 'errors' => 0, 'syncoutput' => array());
		$studCount = count($incomings);

		if (empty($incomings) || !is_array($incomings) || $studCount <= 0)
		{
			$this->addInfoOutput("Keine Incoming für Sync gefunden! Abbruch.");
		}
		else
		{
			foreach ($incomings as $incoming)
			{
				$incomingData = $incoming['data'];
				$appId = $incoming['moid'];

				// get files only on sync start to avoid ot of memory error
				$documentTypes = array_keys($this->confmiscvalues['documentstosync']['incoming']);
				$files = $this->getFiles($appId, $documentTypes);

				if (isError($files))
				{
					$errorText = getError($files);
					$errorText = is_string($errorText) ? ', ' . $errorText : '';
					$results['errors']++;
					$this->addErrorOutput("Fehler beim Holen der Files des Studierden mit applicationid $appId - " .
						$incomingData['person']['vorname'] . " " . $incomingData['person']['nachname'] . $errorText);
				}

				if (hasData($files))
				{
					$incomingData['akten'] = getData($files);
				}

				$infhccheck_prestudent_id = $this->checkMoIdInFhc($appId);

				if (isset($infhccheck_prestudent_id) && is_numeric($infhccheck_prestudent_id))
				{
					$this->addInfoOutput("Student für applicationid $appId ".$incomingData['person']['vorname'].
						" ".$incomingData['person']['nachname']." existiert bereits in fhcomplete - aktualisieren");

					$prestudent_id = $this->saveIncoming($incomingData, $appId, $infhccheck_prestudent_id);

					if (isset($prestudent_id) && is_numeric($prestudent_id))
					{
						$result = $this->ci->MoappidzuordnungModel->update(
							array('mo_applicationid' => $appId, 'prestudent_id' => $prestudent_id, 'studiensemester_kurzbz' => $studiensemester),
							array('updateamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$results['updated']++;
							$this->addSuccessOutput("student for applicationid $appId - ".
								$incomingData['person']['vorname']." ".$incomingData['person']['nachname']." erfolgreich aktualisiert");
						}
						else
						{
							$results['errors']++;
							$this->addErrorOutput("Fehler beim Aktualisieren der Zuordnung des Studierenden mit applicationid $appId - "
								.$incomingData['person']['vorname']." ".$incomingData['person']['nachname']);
						}
					}
					else
					{
						$results['errors']++;
						$this->addErrorOutput("Fehler beim Update des Studierenden mit applicationid $appId - "
							.$incomingData['person']['vorname']." ".$incomingData['person']['nachname']);
					}
				}
				else
				{
					$prestudent_id = $this->saveIncoming($incomingData, $appId);

					if (isset($prestudent_id) && is_numeric($prestudent_id))
					{
						$result = $this->ci->MoappidzuordnungModel->insert(
							array(
								'mo_applicationid' => $appId,
								'prestudent_id' => $prestudent_id,
								'studiensemester_kurzbz' => $studiensemester,
								'insertamum' => 'NOW()'
							)
						);

						if (hasData($result))
						{
							$results['added']++;
							$this->addSuccessOutput("Student für applicationid $appId - ".
								$incomingData['person']['vorname']." ".$incomingData['person']['nachname']." erfolgreich hinzugefügt");
						}
						else
						{
							$results['errors']++;
							$this->addErrorOutput("Fehler bei Verlinkung in FHC Datenbank für Studierenden mit applicationid $appId - ".
								$incomingData['person']['vorname']." ".$incomingData['person']['nachname']);
						}
					}
					else
					{
						$results['errors']++;
						$this->addErrorOutput("Fehler beim Hinzufügen des Studierden mit applicationid $appId - ".
							$incomingData['person']['vorname']." ".$incomingData['person']['nachname']);
					}
				}
			}
		}

		$results['syncoutput'] = $this->getOutput();
		return $results;
	}

	/**
	 * Gets MobilityOnline incomings for a fhcomplete studiensemester, optionally from a Studiengang.
	 * @param string $studiensemester
	 * @param int $studiengang_kz as in fhc db
	 * @return array with applications
	 */
	public function getIncoming($studiensemester, $studiengang_kz = null)
	{
		$incomings = array();

		// get application data of Incomings for semester (and Studiengang)
		$apps = $this->getApplicationBySearchParams($studiensemester, 'IN', $studiengang_kz);

		foreach ($apps as $application)
		{
			$fhcobj_extended = new StdClass();
			$fhcobj_extended->error = false;
			$fhcobj_extended->errorMessages = array();

			$appId = $application->applicationID;

			// get additional data from Mobility Online for each application
			$addressData = $this->ci->MoGetAppModel->getPermanentAddress($appId);
			if (isError($addressData))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = getError($addressData);
			};
			$addressData = getData($addressData);

			$currAddressData = $this->ci->MoGetAppModel->getCurrentAddress($appId);
			if (isError($currAddressData))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = getError($currAddressData);
			};
			$currAddressData = getData($currAddressData);

			$lichtbildData = $this->ci->MoGetAppModel->getFilesOfApplication($appId, 'PASSFOTO');
			if (isError($lichtbildData))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = getError($lichtbildData);
			}
			$lichtbildData = getData($lichtbildData);

			// transform MobilityOnline application to FHC incoming
			$fhcObj = $this->mapMoAppToIncoming($application, $addressData, $currAddressData, $lichtbildData);

			// courses
			$fhcObj['mocourses'] = array();
			$courses = $this->ci->MoGetAppModel->getCoursesOfApplication($appId);

			if (isError($courses))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = getError($courses);
			};

			if (hasData($courses))
			{
				$coursesData = getData($courses);
				foreach ($coursesData as $course)
				{
					if (!$course->deleted)
					{
						$courseData = new stdClass();
						$courseData->number = $course->hostCourseNumber;
						$courseData->name = $course->hostCourseName;
						$fhcObj['mocourses'][] = $courseData;
					}
				}
			}

			$fhcobj_extended->moid = $appId;
			$fhcobj_extended->infhc = false;

			// check if the fhc object has errors
			$errors = $this->fhcObjHasError($fhcObj, $this->moObjectType);
			$fhcobj_extended->error = $fhcobj_extended->error || $errors->error;
			$fhcobj_extended->errorMessages = array_merge($fhcobj_extended->errorMessages, $errors->errorMessages);

			$found_prestudent_id = $this->checkMoIdInFhc($appId);

			// mark as already in fhcomplete if prestudent is in mapping table
			if (isset($found_prestudent_id) && is_numeric($found_prestudent_id))
			{
				$fhcobj_extended->infhc = true;
				$fhcobj_extended->prestudent_id = $found_prestudent_id;
			}

			$fhcobj_extended->data = $fhcObj;
			$incomings[] = $fhcobj_extended;
		}

		return $incomings;
	}

	/**
	 * Converts MobilityOnline application to fhcomplete array (with person, prestudent...)
	 * @param object $moApp MobilityOnline application
	 * @param object $moAddr MobilityOnline adress of application
	 * @param array $photo of applicant
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoAppToIncoming($moApp, $moAddr = null, $currAddr = null, $photo = null)
	{
		$fieldMappings = $this->conffieldmappings[$this->moObjectType];
		$personMappings = $fieldMappings['person'];
		$prestudentMappings = $fieldMappings['prestudent'];
		$prestudentstatusMappings = $fieldMappings['prestudentstatus'];
		$adresseMappings = $this->conffieldmappings['address']['adresse'];

		$lichtbildMappings = $this->conffieldmappings['photo']['lichtbild'];
		$bisioMappings = $fieldMappings['bisio'];

		$applicationDataElementsByValueType = array(
			// applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
			'comboboxFirstValue' => array(
				$personMappings['staatsbuergerschaft'], $personMappings['geburtsnation'], $personMappings['sprache'], $prestudentstatusMappings['studiensemester_kurzbz'],
				$prestudentMappings['zgvnation'], $prestudentMappings['zgvmanation'],
				$bisioMappings['nation_code'], $bisioMappings['herkunftsland_code']
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

		// small Lichtbild
		if (!isEmptyArray($photo))
		{
			$moAppElementsExtracted->{$lichtbildMappings['inhalt']} = $photo[0]->{$lichtbildMappings['inhalt']};
		}

		$fhcObj = $this->convertToFhcFormat($moAppElementsExtracted, $this->moObjectType);

		// add all Studiensemester for Prestudentstatus

		// get all semesters from MO Semesterfield
		$studiensemester_start = $fhcObj['prestudentstatus']['studiensemester_kurzbz'];
		$allSemesters = array($studiensemester_start);
		$moStudjahr = $this->ci->tomobilityonlinedataconversionlib->mapSemesterToMoStudienjahr($studiensemester_start);

		// WS and SS if Studienjahr given in MO
		if ($moAppElementsExtracted->{$prestudentstatusMappings['studiensemester_kurzbz']} === $moStudjahr)
		{
			$allSemesters =
				array_unique(
					array_merge(
						$allSemesters,
						$this->ci->frommobilityonlinedataconversionlib->mapMoStudienjahrToSemester($moStudjahr)
					)
				);
		}

		// get start of Studiensemester for getting semester by date
		$this->ci->StudiensemesterModel->addSelect('start');
		$semStart = $fhcObj['bisio']['von'];
		$semStartRes = $this->ci->StudiensemesterModel->loadWhere(array('studiensemester_kurzbz' => $studiensemester_start));

		if (hasData($semStartRes))
		{
			$semStart = getData($semStartRes)[0]->start;
		}

		// add Studiensemester for each semester in the span of first semester start - stay end date
		$studiensemesterRes = $this->ci->StudiensemesterModel->getByDateRange($semStart, $fhcObj['bisio']['bis']);

		if (hasData($studiensemesterRes))
		{
			foreach (getData($studiensemesterRes) as $semester)
			{
				$studiensemester_kurzbz = $semester->studiensemester_kurzbz;
				if (!in_array($studiensemester_kurzbz, $allSemesters))
					$allSemesters[] = $studiensemester_kurzbz;
			}
		}

		$fhcObj['all_studiensemester_kurzbz'] = $allSemesters;

		// add last MO pipeline status
		$fhcObj['pipelineStatus'] = 'not set';
		$fhcObj['pipelineStatusDescription'] = 'no Status set';

		$pipelinestatus = $fieldMappings['status_info'];

		// status is sorted, latest one last
		foreach ($pipelinestatus as $status)
		{
			foreach ($moAppElementsExtracted->nonUsedApplicationDataElements as $element)
			{
				if (isset($element->elementName) && $element->elementName === $status
					&& isset($element->elementValueBoolean) && $element->elementValueBoolean === true)
				{
					$fhcObj['pipelineStatus'] = $element->elementName;
					$fhcObj['pipelineStatusDescription'] = $element->elementDescription;
				}
			}
		}

		// remove original applicationDataElements
		unset($moAppElementsExtracted->applicationDataElements);
		unset($moAppElementsExtracted->nonUsedApplicationDataElements);

		// lichtbild
		$fhcLichtbild = array();
		if (isset($photo[0]))
			$fhcLichtbild = $this->convertToFhcFormat($photo[0], 'photo');

		// adresses
		$fhcAddr = $this->convertToFhcFormat($moAddr, 'address');
		$fhcCurrAddr = $this->convertToFhcFormat($currAddr, 'curraddress');

		$fhcObj = array_merge($fhcObj, $fhcLichtbild, $fhcAddr, $fhcCurrAddr);

		//~ // courses
		//~ $fhcObj['mocourses'] = array();
		//~ $courses = $this->ci->MoGetAppModel->getCoursesOfApplication($moAppElementsExtracted->applicationID);

		//~ if (hasData($courses))
		//~ {
			//~ foreach ($courses as $course)
			//~ {
				//~ if (!$course->deleted)
				//~ {
					//~ $courseData = new stdClass();
					//~ $courseData->number = $course->hostCourseNumber;
					//~ $courseData->name = $course->hostCourseName;
					//~ $fhcObj['mocourses'][] = $courseData;
				//~ }
			//~ }
		//~ }

		return $fhcObj;
	}

	/**
	 * Saves an incoming (pre-)student, i.e. adds him or updates it if prestudent_id is set
	 * @param array $incoming
	 * @param int $appId MobilityOnline application Id
	 * @param int $prestudent_id
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveIncoming($incoming, $appId, $prestudent_id = null)
	{
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($incoming, $this->moObjectType);

		if ($errors->error)
		{
			$this->addErrorOutput("Fehler");
			foreach ($errors->errorMessages as $errorMessage)
			{
				$this->addErrorOutput($errorMessage);
			}

			$this->addErrorOutput("Abbruch bei Speicherung des Incomings");
			return null;
		}

		$person = $incoming['person'];
		$prestudent = $incoming['prestudent'];
		$prestudentstatus = $incoming['prestudentstatus'];
		$benutzer = $incoming['benutzer'];
		$student = $incoming['student'];
		$studentlehrverband = $incoming['studentlehrverband'];
		$adresse = $incoming['adresse'];
		$kontaktmail = $incoming['kontaktmail'];
		$bisio = $incoming['bisio'];
		$bisio_zweck = $incoming['bisio_zweck'];
		$konto = $incoming['konto'];

		// optional fields
		$lichtbild = isset($incoming['lichtbild']) ? $incoming['lichtbild'] : array();
		$akten = isset($incoming['akten']) ? $incoming['akten'] : array();
		$kontaktnotfall = isset($incoming['kontaktnotfall']) ? $incoming['kontaktnotfall'] : array();
		$kontakttel = isset($incoming['kontakttel']) ? $incoming['kontakttel'] : array();
		$studienadresse = isset($incoming['studienadresse']) ? $incoming['studienadresse'] : array();

		$studiensemester = $prestudentstatus['studiensemester_kurzbz'];

		// all semesters, one for each prestudentstatus
		$studiensemarr = $incoming['all_studiensemester_kurzbz'];

		// Start DB transaction
		$this->ci->db->trans_begin();

		$person_id = $this->_savePerson($prestudent_id, $person);

		if (isset($person_id) && is_numeric($person_id))
		{
			// adresse
			$this->_saveAdresse($person_id, $adresse);
			$this->_saveAdresse($person_id, $studienadresse);

			// kontakt
			$kontakte = array(
				array(
					'kontaktobj' => $kontaktmail,
					'table' => 'mailkontakt'
				),
				array(
					'kontaktobj' => $kontakttel,
					'table' => 'telefonkontakt'
				),
				array(
					'kontaktobj' => $kontaktnotfall,
					'table' => 'notfallkontakt'
				)
			);

			foreach ($kontakte as $kontakt)
			{
				$this->_saveKontakt($person_id, $kontakt['kontaktobj'], $kontakt['table']);
			}

			// lichtbild - akte
			$this->_saveLichtbild($person_id, $lichtbild);

			// prestudent
			$prestudent['person_id'] = $person_id;
			$prestudent_id_res = $this->_savePrestudent($prestudent_id, $prestudent);

			if (isset($prestudent_id_res) && is_numeric($prestudent_id_res))
			{
				// prestudentstatus
				$prestudent['prestudent_id'] = $prestudentstatus['prestudent_id'] = $prestudent_id_res;

				$this->_savePrestudentStatus($studiensemarr, $prestudent, $prestudentstatus);

				// benutzer
				$matrikelnr = $this->ci->StudentModel->generateMatrikelnummer($prestudent['studiengang_kz'], $studiensemester);
				$benutzerrespuid = $this->_saveBenutzer($matrikelnr, $prestudent, $benutzer);

				if (!isEmptyString($benutzerrespuid))
				{
					$studentuidresp = $this->_saveStudent($benutzerrespuid, $matrikelnr, $prestudent, $student);

					if (!isEmptyString($studentuidresp))
					{
						// studentlehrverband
						$studentlehrverband['student_uid'] = $benutzerrespuid;
						$studentlehrverband['studiengang_kz'] = $prestudent['studiengang_kz'];
						$studentlehrverband['semester'] = $prestudentstatus['ausbildungssemester'];

						$this->_saveStudentlehrverband($studiensemarr, $studentlehrverband);

						// bisio
						$bisio['student_uid'] = $benutzerrespuid;
						$bisio_id = $this->_saveBisio($appId, $bisio);
						$bisio_zweck['bisio_id'] = $bisio_id;
						$bisio_zweckresult = $this->ci->MoFhcModel->saveBisioZweck($bisio_zweck);
						if (hasData($bisio_zweckresult))
							$this->log('insert', $bisio_zweckresult, 'bisio_zweck');
					}

					// Buchungen
					if (count($studiensemarr) > 0)
					{
						$konto['person_id'] = $person_id;
						$this->stamp('insert', $konto);
						foreach ($studiensemarr as $studiensem)
						{
							$konto['studiensemester_kurzbz'] = $studiensem;
							$this->_saveBuchungen($konto);
						}
					}
				}

				// save documents
				foreach ($akten as $akte)
				{
					$this->saveAkte($person_id, $akte['akte'], $prestudent_id_res);
				}
			}
		}

		// Transaction complete!
		$this->ci->db->trans_complete();

		// Check if everything went ok during the transaction
		if ($this->ci->db->trans_status() === false)
		{
			$this->addInfoOutput("Rollback...");
			$this->ci->db->trans_rollback();
			return null;
		}
		else
		{
			$this->ci->db->trans_commit();
			return $prestudent_id_res;
		}
	}

	/**
	 * Checks for a mobility online application id whether the application is saved in FH-Complete
	 * returns prestudent_id if in FHC, null otherwise
	 * @param int $moId
	 * @return int|null
	 */
	public function checkMoIdInFhc($moId)
	{
		$this->ci->PrestudentModel->addSelect('prestudent_id');
		$appIdZuordnung = $this->ci->MoappidzuordnungModel->loadWhere(array('mo_applicationid' => $moId));
		if (hasData($appIdZuordnung))
		{
			$prestudent_id = getData($appIdZuordnung)[0]->prestudent_id;
			$prestudent = $this->ci->PrestudentModel->load($prestudent_id);
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

	// -----------------------------------------------------------------------------------------------------------------
	// Private methods for saving an incoming

	/**
	 * Inserts person or updates an existing one, if prestudent for person already exists.
	 * @param int $prestudent_id
	 * @param array $person
	 * @return int|null person_id of inserted/updated person
	 */
	private function _savePerson($prestudent_id, $person)
	{
		$person_id = null;
		$prestudentCheckResp = isset($prestudent_id) && is_numeric($prestudent_id) ? $this->ci->PrestudentModel->load($prestudent_id) : null;

		$update = hasData($prestudentCheckResp);

		// update if prestudent already exists, insert otherwise
		if ($update)
		{
			$person_id = getData($prestudentCheckResp)[0]->person_id;
			$this->stamp('update', $person);
			$personResponse = $this->ci->PersonModel->update($person_id, $person);
			$this->log('update', $personResponse, 'person');
		}
		else
		{
			$this->stamp('insert', $person);
			$personResponse = $this->ci->PersonModel->insert($person);
			if (isSuccess($personResponse))
			{
				$person_id = getData($personResponse);
			}
			$this->log('insert', $personResponse, 'person');
		}

		return $person_id;
	}

	/**
	 * Inserts Adresse for a person.
	 * @param int $person_id
	 * @param array $adresse
	 * @return int|null adresse_id of inserted adresse, null if adresse already exists
	 */
	private function _saveAdresse($person_id, $adresse)
	{
		if (isEmptyArray($adresse))
			return null;

		// use Ort if there is no Gemeinde
		if ((!isset($adresse['gemeinde']) || isEmptyString($adresse['gemeinde'])) && isset($adresse['ort'])) $adresse['gemeinde'] = $adresse['ort'];

		$adresse_id = null;
		// insert if there is no adress with same heimatadresse / zustelladresse values
		$heimatAddrResp = $this->ci->AdresseModel->loadWhere(array(
				'person_id' => $person_id,
				'heimatadresse' => $adresse['heimatadresse'],
				'zustelladresse' => $adresse['zustelladresse']
			));

		if (isSuccess($heimatAddrResp) && !hasData($heimatAddrResp))
		{
			$adresse['person_id'] = $person_id;
			$this->stamp('insert', $adresse);
			$addrResp = $this->ci->AdresseModel->insert($adresse);
			$adresse_id = getData($addrResp);
			$this->log('insert', $addrResp, 'adresse');
		}

		return $adresse_id;
	}

	/**
	 * Inserts Kontakt for a person.
	 * @param int $person_id
	 * @param array $kontakt
	 * @param string $table for log
	 * @return int|null kontakt_id of inserted kontakt, null if Kontakt with given type already exists
	 */
	private function _saveKontakt($person_id, $kontakt, $table)
	{
		$kontakt_id = null;

		if (isset($kontakt['kontakttyp']) && !isEmptyString($kontakt['kontakttyp']))
		{
			$kontaktResp = $this->ci->KontaktModel->loadWhere(array('person_id' => $person_id, 'kontakttyp' => $kontakt['kontakttyp']));

			if (!empty($kontakt['kontakt']))
			{
				$kontaktFound = false;
				if (hasData($kontaktResp))
				{
					foreach (getData($kontaktResp) as $ktkt)
					{
						if ($ktkt->kontakt === $kontakt['kontakt'])
						{
							$kontaktFound = true;
							break;
						}
					}
				}

				if (isSuccess($kontaktResp) && !$kontaktFound)
				{
					$kontakt['person_id'] = $person_id;
					$this->stamp('insert', $kontakt);
					$kontaktResp = $kontaktinsresp = $this->ci->KontaktModel->insert($kontakt);
					$kontakt_id = getData($kontaktResp);
					$this->log('insert', $kontaktinsresp, $table);
				}
			}
		}

		return $kontakt_id;
	}

	/**
	 * Inserts a Lichtbild (picture) of a person as an akte.
	 * @param int $person_id
	 * @param array $akte
	 * @return int|null akte_id of inserted akte, null if Akte with given dokument_kurzbz already exists
	 */
	private function _saveLichtbild($person_id, $akte)
	{
		$akte_id = null;

		if (isset($akte['dokument_kurzbz']) && !isEmptyString($akte['dokument_kurzbz']) && isset($akte['bezeichnung']))
		{
			$aktecheckResp = $this->ci->AkteModel->loadWhere(array('person_id' => $person_id, 'dokument_kurzbz' => $akte['dokument_kurzbz']));

			if (isSuccess($aktecheckResp))
			{
				if (hasData($aktecheckResp))
				{
					if ($this->debugmode)
					{
						$this->addInfoOutput('Lichtbild existiert bereits, akte_id '.getData($aktecheckResp)[0]->akte_id);
					}
				}
				else
				{
					$akte['person_id'] = $person_id;
					// prepend file name to title ending
					$akte['titel'] = $akte['bezeichnung'].'_'.$person_id.$akte['titel'];
					$this->stamp('insert', $akte);
					$akteResp = $this->ci->AkteModel->insert($akte);
					$akte_id = getData($akteResp);
					$this->log('insert', $akteResp, $akte['bezeichnung']);
				}
			}
		}

		return $akte_id;
	}

	/**
	 * Inserts prestudent or updates an existing one.
	 * @param int $prestudent_id
	 * @param array $prestudent
	 * @return int|null prestudent_id of inserted or updated prestudent if successful, null otherwise.
 	 */
	private function _savePrestudent($prestudent_id, $prestudent)
	{
		$prestudent_id_response = null;
		$prestudentCheckResp = isset($prestudent_id) && is_numeric($prestudent_id) ? $this->ci->PrestudentModel->load($prestudent_id) : null;

		$update = hasData($prestudentCheckResp);

		if ($update)
		{
			$this->stamp('update', $prestudent);
			$prestudentResponse = $this->ci->PrestudentModel->update($prestudent_id, $prestudent);
			$this->log('update', $prestudentResponse, 'prestudent');
		}
		else
		{
			$this->stamp('insert', $prestudent);
			$prestudentResponse = $this->ci->PrestudentModel->insert($prestudent);
			$this->log('insert', $prestudentResponse, 'prestudent');
		}
		$prestudent_id_response = getData($prestudentResponse);

		return $prestudent_id_response;
	}

	/**
	 * Inserts prestudentstatus for each given Studiensemester.
	 * @param string $studiensemarr all semester, for which a prestudentstatus entry should be generated
	 * @param array $prestudent
	 * @param array $prestudentstatus
	 * @return array containing inserted prestudentstatus primary keys
	 */
	private function _savePrestudentStatus($studiensemarr, $prestudent, $prestudentstatus)
	{
		$saved = array();

		foreach ($studiensemarr as $semester)
		{
			$lastStatus = $this->ci->PrestudentstatusModel->getLastStatus($prestudentstatus['prestudent_id'], $semester);

			// if there is no latest status
			if (isSuccess($lastStatus) && (!hasData($lastStatus) || getData($lastStatus)[0]->status_kurzbz !== 'Incoming'))
			{
				if (isset($prestudent['studiengang_kz']))
				{
					// get Studiengang data
					$this->ci->StudiengangModel->addSelect('orgform_kurzbz, typ');
					$studiengangResponse = $this->ci->StudiengangModel->load($prestudent['studiengang_kz']);

					if (hasData($studiengangResponse))
					{
						$studiengang = getData($studiengangResponse)[0];

						// check if there is a Studienplan with orgform from priorities (prioirities defined in config)
						if (isset($this->confmiscvalues['orgform_priorities']) && !isEmptyArray($this->confmiscvalues['orgform_priorities']))
						{
							$studienplaeneResponse = $this->ci->StudienplanModel->getStudienplaeneBySemester(
								$prestudent['studiengang_kz'],
								$semester
							);

							if (hasData($studienplaeneResponse))
							{
								$studienplaene = getData($studienplaeneResponse);

								// set orgform from config (sorted by priority) if found in status
								foreach ($this->confmiscvalues['orgform_priorities'] as $orgform_kurzbz)
								{
									foreach ($studienplaene as $studienplan)
									{
										if ($orgform_kurzbz == $studienplan->orgform_kurzbz)
										{
											$prestudentstatus['orgform_kurzbz'] = $studienplan->orgform_kurzbz;
											break 2;
										}
									}
								}
							}
						}

						if (!isset($prestudentstatus['orgform_kurzbz']))
						{
							// fallback: if no Studienplan found for orgform, use studiengangtyp fallback from config
							if (isset($this->confmiscvalues['orgform_studiengangtyp_fallback'][$studiengang->typ]))
							{
								$prestudentstatus['orgform_kurzbz'] = $this->confmiscvalues['orgform_studiengangtyp_fallback'][$studiengang->typ];
							}
							// second fallback: if no Orgform for Studiengangtyp, use the highest priority Orgform from config
							elseif (isset($this->confmiscvalues['orgform_priorities'][0]))
							{
								$prestudentstatus['orgform_kurzbz'] = $this->confmiscvalues['orgform_priorities'][0];
							}
						}
					}
				}

				$prestudentstatus['studiensemester_kurzbz'] = $semester;
				$prestudentstatus['datum'] = date('Y-m-d', time());
				$this->stamp('insert', $prestudentstatus);
				$prestudentstatusResponse = $this->ci->PrestudentstatusModel->insert($prestudentstatus);
				if (hasData($prestudentstatusResponse))
					$saved[] = getData($prestudentstatusResponse);
				$this->log('insert', $prestudentstatusResponse, 'prestudentstatus');
			}
		}

		return $saved;
	}

	/**
	 * Inserts benutzer and generates uid and activation key, if no benutzer already exists for given prestudent.
	 * @param string $matrikelnr for uid generation
	 * @param array $prestudent
	 * @param array $benutzer
	 * @return string|null benutzer_uid of inserted benutzer if successful, null otherwise
	 */
	private function _saveBenutzer($matrikelnr, $prestudent, $benutzer)
	{
		$benutzerresp_uid = null;

		$this->ci->StudentModel->addOrder('insertamum');
		$benutzerstudCheckResp = $this->ci->StudentModel->loadWhere(array('prestudent_id' => $prestudent['prestudent_id']));

		if (isSuccess($benutzerstudCheckResp))
		{
			if (hasData($benutzerstudCheckResp))
			{
				$benutzer['uid'] = getData($benutzerstudCheckResp)[0]->student_uid;
				$benutzerresp_uid = $benutzer['uid'];

				if ($this->debugmode)
				{
					$this->addInfoOutput("benutzer for student ".$prestudent['prestudent_id'] ." already exists, uid " .$benutzer['uid']);
				}
			}
			else
			{
				$benutzer['person_id'] = $prestudent['person_id'];
				$jahr = mb_substr($matrikelnr, 0, 2);
				$stg = mb_substr($matrikelnr, 3, 4);

				$stgres = $this->ci->StudiengangModel->load($stg);

				if (hasData($stgres))
				{
					$stg_bez = getData($stgres)[0]->kurzbz;
					$stg_typ = getData($stgres)[0]->typ;
					$benutzer['uid'] = generateUID($stg_bez, $jahr, $stg_typ, $matrikelnr);

					//check for existing benutzer
					$benutzerCheckResp = $this->ci->BenutzerModel->loadWhere(array('uid' => $benutzer['uid']));

					if (hasData($benutzerCheckResp))
					{
						$this->addInfoOutput("benutzer mit uid ".$benutzer['uid']." existiert bereits");
						$benutzerresp_uid = $benutzer['uid'];
					}
					elseif (isSuccess($benutzerCheckResp))
					{
						$benutzer['aktivierungscode'] = generateActivationKey();
						$this->stamp('insert', $benutzer);
						$benutzerInsCheckResp = $this->ci->BenutzerModel->insert($benutzer);
						if (hasData($benutzerInsCheckResp))
							$benutzerresp_uid = getData($benutzerInsCheckResp)['uid'];

						$this->log('insert', $benutzerInsCheckResp, 'benutzer');
					}
				}
			}
		}

		return $benutzerresp_uid;
	}

	/**
	 * Inserts student or updates an existing one.
	 * @param string $student_uid of existing benutzer for the student
	 * @param string $matrikelnr
	 * @param array $prestudent for retrieving prestudent_id and studiengang_kz for student
	 * @param array $student
	 * @return string|null student_uid of inserted/updated student if successful, null otherwise
	 */
	private function _saveStudent($student_uid, $matrikelnr, $prestudent, $student)
	{
		$studentresp_uid = null;

		$student['prestudent_id'] = $prestudent['prestudent_id'];
		$student['studiengang_kz'] = $prestudent['studiengang_kz'];

		$studentCheckResp = $this->ci->StudentModel->loadWhere(array('student_uid' => $student_uid));

		if (isSuccess($studentCheckResp))
		{
			if (hasData($studentCheckResp))
			{
				$this->stamp('update', $student);
				$studentResponse = $this->ci->StudentModel->update(array('student_uid' => $student_uid), $student);
				$this->log('update', $studentResponse, 'student');
			}
			else
			{
				$student['matrikelnr'] = $matrikelnr;
				$this->stamp('insert', $student);
				$student['student_uid'] = $student_uid;
				$studentResponse = $this->ci->StudentModel->insert($student);
				$this->log('insert', $studentResponse, 'student');
			}

			$studentresp_uid = getData($studentResponse)['student_uid'];
		}

		return $studentresp_uid;
	}

	/**
	 * Inserts studentlehrverband or updates an existing one for all given Studiensemester.
	 * @param array $studiensemarr all semester, for which a studentlehrverband entry should be generated
	 * @param array $studentlehrverband
	 * @return array containing inserted/updated studentlehrverband primary keys
	 */
	private function _saveStudentlehrverband($studiensemarr, $studentlehrverband)
	{
		$studentlehrverbandPk = array();

		if (is_array($studiensemarr))
		{
			foreach ($studiensemarr as $semester)
			{
				$studentlehrverband['studiensemester_kurzbz'] = $semester;
				$studenlehrverbandCheckResp = $this->ci->StudentlehrverbandModel->load(
					array(
						'student_uid' => $studentlehrverband['student_uid'],
						'studiensemester_kurzbz' => $studentlehrverband['studiensemester_kurzbz']
					)
				);
				if (isSuccess($studenlehrverbandCheckResp))
				{
					if (hasData($studenlehrverbandCheckResp))
					{
						$this->stamp('update', $studentlehrverband);
						$studentlehrverbandResponse = $this->ci->StudentlehrverbandModel->update(
							array(
								'student_uid' => $studentlehrverband['student_uid'],
								'studiensemester_kurzbz' => $studentlehrverband['studiensemester_kurzbz']
							),
							$studentlehrverband
						);
						$this->log('update', $studentlehrverbandResponse, 'studentlehrverband');
					}
					else
					{
						$this->stamp('insert', $studentlehrverband);
						$studentlehrverbandResponse = $this->ci->StudentlehrverbandModel->insert($studentlehrverband);
						$this->log('insert', $studentlehrverbandResponse, 'studentlehrverband');
					}
					$studentlehrverbandPk = getData($studentlehrverbandResponse);
				}
			}
		}

		return $studentlehrverbandPk;
	}

	/**
	 * Inserts bisio for a student or updates an existing one.
	 * @param int appId MobilityOnline appliation Id
	 * @param array $bisio
	 * @return int|null bisio_id of inserted or updated bisio if successful, null otherwise.
	 */
	private function _saveBisio($appId, $bisio)
	{
		$bisioRespId = null;

		// get stay for the student
		$this->ci->BisioModel->addOrder('von', 'DESC');
		$this->ci->BisioModel->addOrder('insertamum', 'DESC');
		$bisiocheckResp = $this->ci->BisioModel->loadWhere(array('student_uid' => $bisio['student_uid']));

		if (isSuccess($bisiocheckResp))
		{
			// if there are bisios for the student id
			if (hasData($bisiocheckResp))
			{
				$bisio_id = null;
				// if there is a linked bisio, update it
				$bisioIdRes = $this->checkBisioInFhc($appId);

				if (hasData($bisioIdRes))
				{
					$bisio_id = getData($bisioIdRes);
				}
				elseif (count($bisiocheckResp) == 1) // otherwise update if only one entry
				{
					$bisio_id = getData($bisiocheckResp)[0]->bisio_id;
				}

				if (is_numeric($bisio_id))
				{
					$this->stamp('update', $bisio);
					$bisioResult = $this->ci->BisioModel->update($bisio_id, $bisio);
					$this->log('update', $bisioResult, 'bisio');
				}
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

			if (isset($bisioResult)) $bisioRespId = getData($bisioResult);
		}

		return $bisioRespId;
	}

	/**
	 * Inserts Kontobuchungen, adds data like Betrag, Buchungsverweis, Zahlungsreferenz.
	 * Saves Gegenbuchung if Buchungsbetrag is 0.
	 * Inserts all Buchungstypen present in the buchungstyp_kurzbz property array, if not already existing.
	 * @param array $konto
	 * @return array containing inserted buchungsnr
	 */
	private function _saveBuchungen($konto)
	{
		$inserted_buchungen = array();

		if (isset($konto['buchungstyp_kurzbz']) && !isEmptyArray($konto['buchungstyp_kurzbz']) &&
			isset($konto['studiengang_kz']) && !isEmptyString($konto['studiengang_kz']))
		{
			$kontoToInsert = $konto;
			foreach ($konto['buchungstyp_kurzbz'] as $buchungstyp_kurzbz)
			{
				$buchungstypRes = $this->ci->BuchungstypModel->load($buchungstyp_kurzbz);

				if (hasData($buchungstypRes))
				{
					$checkbuchungRes = $this->ci->KontoModel->loadWhere(
						array(
							'buchungstyp_kurzbz' => $buchungstyp_kurzbz,
							'studiensemester_kurzbz' => $konto['studiensemester_kurzbz'],
							'person_id' => $konto['person_id'],
							'studiengang_kz' => $konto['studiengang_kz']
						)
					);

					if (isSuccess($checkbuchungRes) && !hasData($checkbuchungRes))
					{
						$buchungstyp = getData($buchungstypRes)[0];
						$kontoToInsert['buchungstyp_kurzbz'] = $buchungstyp_kurzbz;

						if (isset($konto['betrag'][$buchungstyp_kurzbz]))
							$kontoToInsert['betrag'] = $konto['betrag'][$buchungstyp_kurzbz];
						else
							$kontoToInsert['betrag'] = $buchungstyp->standardbetrag;

						if (isset($konto['buchungstext'][$buchungstyp_kurzbz]))
							$kontoToInsert['buchungstext'] = $konto['buchungstext'][$buchungstyp_kurzbz];
						else
							$kontoToInsert['buchungstext'] = '';

						$kontoToInsert['buchungsdatum'] = date('Y-m-d');

						$kontoInsertRes = $this->ci->KontoModel->insert($kontoToInsert);
						$this->log('insert', $kontoInsertRes, 'konto');

						if (hasData($kontoInsertRes))
						{
							$kontoInsertId = getData($kontoInsertRes);
							// Zahlungsreferenz generieren
							$zahlungsref = generateZahlungsreferenz($konto['studiengang_kz'], $kontoInsertId);

							$zahlungsrefres = $this->ci->KontoModel->update($kontoInsertId, array('zahlungsreferenz' => $zahlungsref));

							if (hasData($zahlungsrefres) && isset($konto['betrag'][$buchungstyp_kurzbz])
								&& $konto['betrag'][$buchungstyp_kurzbz] == 0)
							{
								// Gegenbuchung wenn 0 Betrag
								$gegenbuchung = $kontoToInsert;
								$gegenbuchung['mahnspanne'] = 0;
								$gegenbuchung['buchungsnr_verweis'] = $kontoInsertId;
								$gegenbuchung['zahlungsreferenz'] = $zahlungsref;
								$this->ci->KontoModel->insert($gegenbuchung);

								$inserted_buchungen[] = $kontoInsertId;
							}
						}
					}
				}
			}
		}

		return $inserted_buchungen;
	}
}
