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
	const MOOBJECTTYPE = 'application';

	public function __construct()
	{
		parent::__construct();

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
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Moappidzuordnung_model', 'MoappidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobilityonlinefhc_model', 'MoFhcModel');
	}

	/**
	 * Executes sync of incomings for a Studiensemester from MO to FHC. Adds or updates incomings.
	 * @param $studiensemester
	 * @param $incomings
	 * @return array syncoutput containing info about failures/success
	 */
	public function startIncomingSync($studiensemester, $incomings)
	{
		$results = array('added' => 0, 'updated' => 0, 'errors' => 0, 'syncoutput' => array());
		$studcount = count($incomings);

		if (empty($incomings) || !is_array($incomings) || $studcount <= 0)
		{
			$this->addInfoOutput("Keine Incoming für Sync gefunden! Abbruch.");
		}
		else
		{
			foreach ($incomings as $incoming)
			{
				$incomingdata = $incoming['data'];
				$appid = $incoming['moid'];

				$infhccheck_prestudent_id = $this->checkMoIdInFhc($appid);

				if (isset($infhccheck_prestudent_id) && is_numeric($infhccheck_prestudent_id))
				{
					$this->addInfoOutput("Student für applicationid $appid ".$incomingdata['person']['vorname'].
						" ".$incomingdata['person']['nachname']." existiert bereits in fhcomplete - aktualisieren");

					$prestudent_id = $this->saveIncoming($incomingdata, $infhccheck_prestudent_id);

					if (isset($prestudent_id) && is_numeric($prestudent_id))
					{
						$result = $this->ci->MoappidzuordnungModel->update(
							array('mo_applicationid' => $appid, 'prestudent_id' => $prestudent_id, 'studiensemester_kurzbz' => $studiensemester),
							array('updateamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$results['updated']++;
							$this->addSuccessOutput("student for applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']." erfolgreich aktualisiert");
						}
					}
					else
					{
						$results['errors']++;
						$this->addErrorOutput("Fehler beim Update des Studierenden mit applicationid $appid - "
							.$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']);
					}
				}
				else
				{
					$prestudent_id = $this->saveIncoming($incomingdata);

					if (isset($prestudent_id) && is_numeric($prestudent_id))
					{
						$result = $this->ci->MoappidzuordnungModel->insert(
							array('mo_applicationid' => $appid, 'prestudent_id' => $prestudent_id, 'studiensemester_kurzbz' => $studiensemester, 'insertamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$results['added']++;
							$this->addSuccessOutput("Student für applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']." erfolgreich hinzugefügt");
						}
						else
						{
							$results['errors']++;
							$this->addErrorOutput("Fehler bei Verlinkung in FHC Datenbank für Studierenden mit applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']);
						}
					}
					else
					{
						$results['errors']++;
						$this->addErrorOutput("Fehler beim Hinzufügen des Studierden mit applicationid $appid - ".
							$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']);
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
	 * @param $moaddr MobilityOnline adress of application
	 * @param $photo of applicant
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoAppToIncoming($moapp, $moaddr = null, $curraddr = null, $photo = null)
	{
		$fieldmappings = $this->conffieldmappings[self::MOOBJECTTYPE];
		$personmappings = $fieldmappings['person'];
		$prestudentmappings = $fieldmappings['prestudent'];
		$prestudentstatusmappings = $fieldmappings['prestudentstatus'];
		$adressemappings = $this->conffieldmappings['address']['adresse'];

		$aktemappings = $fieldmappings['akte'];
		$bisiomappings = $fieldmappings['bisio'];

		//applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
		$comboboxvaluefields = array($personmappings['staatsbuergerschaft'], $personmappings['sprache'], $prestudentstatusmappings['studiensemester_kurzbz'],
									 $prestudentmappings['studiengang_kz'], $prestudentmappings['zgvnation'], $prestudentmappings['zgvmanation'],
									 $bisiomappings['nation_code']);

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
		$monation = $moapp->{$personmappings['staatsbuergerschaft']};
		$mobisionation = $moapp->{$bisiomappings['nation_code']};
		$moaddrnation = isset($moaddr) ? $moaddr->{$adressemappings['nation']['name']}->description : null;
		$curraddrnation = isset($curraddr) ? $curraddr->{$adressemappings['nation']['name']}->description : null;

		$mozgvnation = isset($prestudentmappings['zgvnation']) && isset($moapp->{$prestudentmappings['zgvnation']}) ? $moapp->{$prestudentmappings['zgvnation']} : null;
		$mozgvmanation = isset($prestudentmappings['zgvmanation']) && isset($moapp->{$prestudentmappings['zgvmanation']}) ? $moapp->{$prestudentmappings['zgvmanation']} : null;

		$monations = array(
			$personmappings['staatsbuergerschaft'] => $monation,
			$bisiomappings['nation_code'] => $mobisionation,
			$prestudentmappings['zgvnation'] => $mozgvnation,
			$prestudentmappings['zgvmanation'] => $mozgvmanation
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

				if ($fhcnation->kurztext === $moaddrnation || $fhcnation->langtext === $moaddrnation || $fhcnation->engltext === $moaddrnation)
				{
					$moaddr->{$adressemappings['nation']['name']} = $fhcnation->nation_code;
				}

				if ($fhcnation->kurztext === $curraddrnation || $fhcnation->langtext === $curraddrnation || $fhcnation->engltext === $curraddrnation)
				{
					$curraddr->{$adressemappings['nation']['name']} = $fhcnation->nation_code;
				}
			}
		}

		// Lichtbild
		if ($photo)
		{
			$moapp->{$aktemappings['inhalt']} = $photo[0]->{$aktemappings['inhalt']};
		}

		$fhcobj = $this->convertToFhcFormat($moapp, self::MOOBJECTTYPE);

		// add all Studiensemester for Prestudentstatus

		// get all semesters from MO Semesterfield
		$allsemesters = array($fhcobj['prestudentstatus']['studiensemester_kurzbz']);
		$mostudjahr = $this->mapSemesterToMoStudienjahr($fhcobj['prestudentstatus']['studiensemester_kurzbz']);

		// WS and SS if Studienjahr given in MO
		if ($moapp->{$prestudentstatusmappings['studiensemester_kurzbz']} === $mostudjahr)
		{
			$allsemesters = array_unique(array_merge($allsemesters, $this->mapMoStudienjahrToSemester($mostudjahr)));
		}

		// add Studiensemester for each semester in the stay time span of von - bis date
		$studiensemesterres = $this->ci->StudiensemesterModel->getByDate($fhcobj['bisio']['von'], $fhcobj['bisio']['bis']);

		if (hasData($studiensemesterres))
		{
			foreach ($studiensemesterres->retval as $semester)
			{
				$studiensemester_kurzbz = $semester->studiensemester_kurzbz;
				if (!in_array($studiensemester_kurzbz, $allsemesters))
					$allsemesters[] = $studiensemester_kurzbz;
			}
		}

		$fhcobj['all_studiensemester_kurzbz'] = $allsemesters;

		// add last MO pipeline status
		$fhcobj['pipelineStatus'] = 'not set';
		$fhcobj['pipelineStatusDescription'] = 'no Status set';

		$pipelinestati = $fieldmappings['status_info'];

		foreach ($pipelinestati as $pipelinestatus)
		{
			foreach ($moapp->nonUsedApplicationDataElements as $element)
			{
				if (isset($element->elementName) && $element->elementName === $pipelinestatus
					&& isset($element->elementValueBoolean) && $element->elementValueBoolean === true)
				{
					$fhcobj['pipelineStatus'] = $element->elementName;
					$fhcobj['pipelineStatusDescription'] = $element->elementDescription;
				}
			}
		}

		$fhcaddr = $this->convertToFhcFormat($moaddr, 'address');
		$fhccurraddr = $this->convertToFhcFormat($curraddr, 'curraddress');

		$fhcobj = array_merge($fhcobj, $fhcaddr, $fhccurraddr);

		// courses
		$fhcobj['mocourses'] = array();
		$courses = $this->ci->MoGetAppModel->getCoursesOfApplication($moapp->applicationID);

		if (is_array($courses))
		{
			foreach ($courses as $course)
			{
				if (!$course->deleted)
				{
					$coursedata = new stdClass();
					$coursedata->number = $course->hostCourseNumber;
					$coursedata->name = $course->hostCourseName;
					$fhcobj['mocourses'][] = $coursedata;
				}
			}
		}

		return $fhcobj;
	}

	/**
	 * Saves an incoming (pre-)student, i.e. adds him or updates it if prestudent_id is set
	 * @param $incoming
	 * @param $prestudent_id
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveIncoming($incoming, $prestudent_id = null)
	{
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($incoming, self::MOOBJECTTYPE);

		if ($errors->error)
		{
			$this->addErrorOutput("ERROR! ");
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
		$akte = isset($incoming['akte']) ? $incoming['akte'] : array();
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
			$this->_saveLichtbild($person_id, $akte);

			// prestudent
			$prestudent['person_id'] = $person_id;
			$prestudent_id_res = $this->_savePrestudent($prestudent_id, $prestudent);

			if (isset($prestudent_id_res) && is_numeric($prestudent_id_res))
			{
				// prestudentstatus
				$prestudent['prestudent_id'] = $prestudentstatus['prestudent_id'] = $prestudent_id_res;

				$this->_savePrestudentStatus($studiensemarr, $prestudentstatus);

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
						$bisio_id = $this->_saveBisio($bisio);
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
	 * Gets MobilityOnline incomings for a fhcomplete studiensemester, optionally from a Studiengang.
	 * @param $studiensemester
	 * @param $studiengang_kz as in fhc db
	 * @return array with applications
	 */
	public function getIncoming($studiensemester, $studiengang_kz = null)
	{
		$studiensemestermo = $this->mapSemesterToMo($studiensemester);
		$semestersforsearch = array($studiensemestermo);
		$searcharrays = array();
		$apps = array();

		$stgvaluemappings = $this->valuemappings['frommo']['studiengang_kz'];
		$mostgname = $this->conffieldmappings['incomingcourse']['mostudiengang']['bezeichnung'];

		// searchobject to search incomings
		$searcharray = array(
			'applicationType' => 'IN',
			'personType' => 'S',
			'furtherSearchRestrictions' => array('is_storniert' => false)
		);

		// Also search for Incomings who have entered Studienjahr as their Semester
		$studienjahrsemestermo = $this->mapSemesterToMoStudienjahr($studiensemester);
		if (isset($studienjahrsemestermo))
			$semestersforsearch[] = $studienjahrsemestermo;

		foreach ($semestersforsearch as $semesterforsearch)
		{
			$searcharray['semesterDescription'] = $semesterforsearch;

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

		foreach ($searcharrays as $sarr)
		{
			$searchObj = $this->getSearchObj(
				self::MOOBJECTTYPE,
				$sarr
			);

			$semApps = $this->ci->MoGetAppModel->getSpecifiedApplicationDataBySearchParametersWithFurtherSearchRestrictions($searchObj);

			if (!isEmptyArray($semApps))
				$apps = array_merge($apps, $semApps);
		}

		return $this->_getIncomingExtended($apps);
	}

	/**
	 * Checks for a mobility online application id whether the application is saved in FH-Complete
	 * returns prestudent_id if in FHC, null otherwise
	 * @param $moid
	 * @return number|null
	 */
	public function checkMoIdInFhc($moid)
	{
		$this->ci->PrestudentModel->addSelect('prestudent_id');
		$appidzuordnung = $this->ci->MoappidzuordnungModel->loadWhere(array('mo_applicationid' => $moid));
		if (hasData($appidzuordnung))
		{
			$prestudent_id = $appidzuordnung->retval[0]->prestudent_id;
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

	/**
	 * Gets incomings (applications) by appids
	 * also checks if incomings already are in fhcomplete
	 * (prestudent_id in tbl_mo_appidzuordnung table and tbl_prestudent)
	 * @param $appids
	 * @param $studiensemester for check if in mapping table
	 * @return array with applications
	 */
	private function _getIncomingExtended($apps)
	{
		$incomings = array();

		foreach ($apps as $application)
		{
			$appid = $application->applicationID;

			$address = $this->ci->MoGetAppModel->getPermanentAddress($appid);
			$currAddress = $this->ci->MoGetAppModel->getCurrentAddress($appid);

			$lichtbild = $this->ci->MoGetAppModel->getFilesOfApplication($appid, 'PASSFOTO');

			$fhcobj = $this->mapMoAppToIncoming($application, $address, $currAddress, $lichtbild);

			$fhcobj_extended = new StdClass();
			$fhcobj_extended->moid = $appid;
			$fhcobj_extended->infhc = false;

			$errors = $this->fhcObjHasError($fhcobj, self::MOOBJECTTYPE);
			$fhcobj_extended->error = $errors->error;
			$fhcobj_extended->errorMessages = $errors->errorMessages;

			$found_prestudent_id = $this->checkMoIdInFhc($appid);

			// mark as already in fhcomplete if prestudent is in mapping table
			if (isset($found_prestudent_id) && is_numeric($found_prestudent_id))
			{
				$fhcobj_extended->infhc = true;
				$fhcobj_extended->prestudent_id = $found_prestudent_id;
			}

			$fhcobj_extended->data = $fhcobj;
			$incomings[] = $fhcobj_extended;
		}

		return $incomings;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Private methods for saving an incoming

	/**
	 * Inserts person or updates an existing one, if prestudent for person already exists.
	 * @param $prestudent_id
	 * @param $person
	 * @return int|null person_id of inserted/updated person
	 */
	private function _savePerson($prestudent_id, $person)
	{
		$person_id = null;
		$prestudentcheckresp = isset($prestudent_id) && is_numeric($prestudent_id) ? $this->ci->PrestudentModel->load($prestudent_id) : null;

		$update = hasData($prestudentcheckresp);

		// update if prestudent already exists, insert otherwise
		if ($update)
		{
			$person_id = $prestudentcheckresp->retval[0]->person_id;
			$this->stamp('update', $person);
			$personresponse = $this->ci->PersonModel->update($person_id, $person);
			$this->log('update', $personresponse, 'person');
		}
		else
		{
			$this->stamp('insert', $person);
			$personresponse = $this->ci->PersonModel->insert($person);
			if (isSuccess($personresponse))
			{
				$person_id = $personresponse->retval;
			}
			$this->log('insert', $personresponse, 'person');
		}

		return $person_id;
	}

	/**
	 * Inserts Adresse for a person.
	 * @param $person_id
	 * @param $adresse
	 * @return int|null adresse_id of inserted adresse, null if adresse already exists
	 */
	private function _saveAdresse($person_id, $adresse)
	{
		if (isEmptyArray($adresse))
			return null;

		$adresse_id = null;
		// insert if there is no adress with same heimatadresse / zustelladresse values
		$heimataddrresp = $this->ci->AdresseModel->loadWhere(array(
				'person_id' => $person_id,
				'heimatadresse' => $adresse['heimatadresse'],
				'zustelladresse' => $adresse['zustelladresse']
			));

		if (isSuccess($heimataddrresp) && !hasData($heimataddrresp))
		{
			$adresse['person_id'] = $person_id;
			$this->stamp('insert', $adresse);
			$addrresp = $this->ci->AdresseModel->insert($adresse);
			$adresse_id = $addrresp->retval;
			$this->log('insert', $addrresp, 'adresse');
		}

		return $adresse_id;
	}

	/**
	 * Inserts Kontakt for a person.
	 * @param $person_id
	 * @param $kontakt
	 * @param $table
	 * @return int|null kontakt_id of inserted kontakt, null if Kontakt with given type already exists
	 */
	private function _saveKontakt($person_id, $kontakt, $table)
	{
		$kontakt_id = null;

		if (isset($kontakt['kontakttyp']) && !isEmptyString($kontakt['kontakttyp']))
		{
			$kontaktresp = $this->ci->KontaktModel->loadWhere(array('person_id' => $person_id, 'kontakttyp' => $kontakt['kontakttyp']));

			if (!empty($kontakt['kontakt']))
			{
				$kontaktfound = false;
				if (hasData($kontaktresp))
				{
					foreach ($kontaktresp->retval as $ktkt)
					{
						if ($ktkt->kontakt === $kontakt['kontakt'])
						{
							$kontaktfound = true;
							break;
						}
					}
				}

				if (isSuccess($kontaktresp) && !$kontaktfound)
				{
					$kontakt['person_id'] = $person_id;
					$this->stamp('insert', $kontakt);
					$kontaktresp = $kontaktinsresp = $this->ci->KontaktModel->insert($kontakt);
					$kontakt_id = $kontaktresp->retval;
					$this->log('insert', $kontaktinsresp, $table);
				}
			}
		}

		return $kontakt_id;
	}

	/**
	 * Inserts a Lichtbild (picture) of a person as an akte.
	 * @param $person_id
	 * @param $akte
	 * @return int|null akte_id of inserted akte, null if Akte with given dokument_kurzbz already exists
	 */
	private function _saveLichtbild($person_id, $akte)
	{
		$akte_id = null;

		if (isset($akte['dokument_kurzbz']) && !isEmptyString($akte['dokument_kurzbz']))
		{
			$aktecheckresp = $this->ci->AkteModel->loadWhere(array('person_id' => $person_id, 'dokument_kurzbz' => $akte['dokument_kurzbz']));

			if (isSuccess($aktecheckresp))
			{
				if (hasData($aktecheckresp))
				{
					if ($this->debugmode)
					{
						$this->addInfoOutput('Lichtbild existiert bereits, akte_id '.$aktecheckresp->retval[0]->akte_id);
					}
				}
				else
				{
					$akte['person_id'] = $person_id;
					$akte['titel'] = 'Lichtbild_'.$person_id;
					$this->stamp('insert', $akte);
					$akteresp = $this->ci->AkteModel->insert($akte);
					$akte_id = $akteresp->retval;
					$this->log('insert', $akteresp, 'akte');
				}
			}
		}

		return $akte_id;
	}

	/**
	 * Inserts prestudent or updates an existing one.
	 * @param $prestudent_id
	 * @param $prestudent
	 * @return int|null prestudent_id of inserted or updated prestudent if successful, null otherwise.
 	 */
	private function _savePrestudent($prestudent_id, $prestudent)
	{
		$prestudent_id_response = null;
		$prestudentcheckresp = isset($prestudent_id) && is_numeric($prestudent_id) ? $this->ci->PrestudentModel->load($prestudent_id) : null;

		$update = hasData($prestudentcheckresp);

		if ($update)
		{
			$this->stamp('update', $prestudent);
			$prestudentresponse = $this->ci->PrestudentModel->update($prestudent_id, $prestudent);
			$this->log('update', $prestudentresponse, 'prestudent');
		}
		else
		{
			$this->stamp('insert', $prestudent);
			$prestudentresponse = $this->ci->PrestudentModel->insert($prestudent);
			$this->log('insert', $prestudentresponse, 'prestudent');
		}
		$prestudent_id_response = $prestudentresponse->retval;

		return $prestudent_id_response;
	}

	/**
	 * Inserts prestudentstatus for each given Studiensemester.
	 * @param $studiensemarr all semester, for which a prestudentstatus entry should be generated
	 * @param $prestudentstatus
	 * @return array containing inserted prestudentstatus primary keys
	 */
	private function _savePrestudentStatus($studiensemarr, $prestudentstatus)
	{
		$saved = array();

		foreach ($studiensemarr as $semester)
		{
			$lastStatus = $this->ci->PrestudentstatusModel->getLastStatus($prestudentstatus['prestudent_id'], $semester);
			if (isSuccess($lastStatus) && (!hasData($lastStatus) || $lastStatus->retval[0]->status_kurzbz !== 'Incoming'))
			{
				$prestudentstatus['studiensemester_kurzbz'] = $semester;
				$prestudentstatus['datum'] = date('Y-m-d', time());
				$this->stamp('insert', $prestudentstatus);
				$prestudentstatusresponse = $this->ci->PrestudentstatusModel->insert($prestudentstatus);
				if (hasData($prestudentstatusresponse))
					$saved[] = $prestudentstatusresponse->retval;
				$this->log('insert', $prestudentstatusresponse, 'prestudentstatus');
			}
		}

		return $saved;
	}

	/**
	 * Inserts benutzer and generates uid and activation key, if no benutzer already exists for given prestudent.
	 * @param $matrikelnr for uid generation
	 * @param $prestudent
	 * @param $benutzer
	 * @return string|null benutzer_uid of inserted benutzer if successful, null otherwise
	 */
	private function _saveBenutzer($matrikelnr, $prestudent, $benutzer)
	{
		$benutzerresp_uid = null;

		$this->ci->StudentModel->addOrder('insertamum');
		$benutzerstudcheckresp = $this->ci->StudentModel->loadWhere(array('prestudent_id' => $prestudent['prestudent_id']));

		if (isSuccess($benutzerstudcheckresp))
		{
			if (hasData($benutzerstudcheckresp))
			{
				$benutzer['uid'] = $benutzerstudcheckresp->retval[0]->student_uid;
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
					$stg_bez = $stgres->retval[0]->kurzbz;
					$stg_typ = $stgres->retval[0]->typ;
					$benutzer['uid'] = generateUID($stg_bez, $jahr, $stg_typ, $matrikelnr);

					//check for existing benutzer
					$benutzercheckresp = $this->ci->BenutzerModel->loadWhere(array('uid' => $benutzer['uid']));

					if (hasData($benutzercheckresp))
					{
						$this->addInfoOutput("benutzer mit uid ".$benutzer['uid']." existiert bereits");
						$benutzerresp_uid = $benutzer['uid'];
					}
					elseif (isSuccess($benutzercheckresp))
					{
						$benutzer['aktivierungscode'] = generateActivationKey();
						$this->stamp('insert', $benutzer);
						$benutzerinscheckresp = $this->ci->BenutzerModel->insert($benutzer);
						if (hasData($benutzerinscheckresp))
							$benutzerresp_uid = $benutzerinscheckresp->retval['uid'];

						$this->log('insert', $benutzerinscheckresp, 'benutzer');
					}
				}
			}
		}

		return $benutzerresp_uid;
	}

	/**
	 * Inserts student or updates an existing one.
	 * @param $student_uid of existing benutzer for the student
	 * @param $matrikelnr
	 * @param $prestudent for retrieving prestudent_id and studiengang_kz for student
	 * @param $student
	 * @return string|null student_uid of inserted/updated student if successful, null otherwise
	 */
	private function _saveStudent($student_uid, $matrikelnr, $prestudent, $student)
	{
		$studentresp_uid = null;

		$student['prestudent_id'] = $prestudent['prestudent_id'];
		$student['studiengang_kz'] = $prestudent['studiengang_kz'];

		$studentcheckresp = $this->ci->StudentModel->loadWhere(array('student_uid' => $student_uid));

		if (isSuccess($studentcheckresp))
		{
			if (hasData($studentcheckresp))
			{
				$this->stamp('update', $student);
				$studentresponse = $this->ci->StudentModel->update(array('student_uid' => $student_uid), $student);
				$this->log('update', $studentresponse, 'student');
			}
			else
			{
				$student['matrikelnr'] = $matrikelnr;
				$this->stamp('insert', $student);
				$student['student_uid'] = $student_uid;
				$studentresponse = $this->ci->StudentModel->insert($student);
				$this->log('insert', $studentresponse, 'student');
			}

			$studentresp_uid = $studentresponse->retval['student_uid'];
		}

		return $studentresp_uid;
	}

	/**
	 * Inserts studentlehrverband or updates an existing one for all given Studiensemester.
	 * @param $studiensemarr all semester, for which a studentlehrverband entry should be generated
	 * @param $studentlehrverband
	 * @return array containing inserted/updated studentlehrverband primary keys
	 */
	private function _saveStudentlehrverband($studiensemarr, $studentlehrverband)
	{
		$studentlehrverbandpk = array();

		if (is_array($studiensemarr))
		{
			foreach ($studiensemarr as $semester)
			{
				$studentlehrverband['studiensemester_kurzbz'] = $semester;
				$studenlehrverbandcheckresp = $this->ci->StudentlehrverbandModel->load(array('student_uid' => $studentlehrverband['student_uid'], 'studiensemester_kurzbz' => $studentlehrverband['studiensemester_kurzbz']));
				if (isSuccess($studenlehrverbandcheckresp))
				{
					if (hasData($studenlehrverbandcheckresp))
					{
						$this->stamp('update', $studentlehrverband);
						$studentlehrverbandresponse = $this->ci->StudentlehrverbandModel->update(array('student_uid' => $studentlehrverband['student_uid'], 'studiensemester_kurzbz' => $studentlehrverband['studiensemester_kurzbz']), $studentlehrverband);
						$this->log('update', $studentlehrverbandresponse, 'studentlehrverband');
					}
					else
					{
						$this->stamp('insert', $studentlehrverband);
						$studentlehrverbandresponse = $this->ci->StudentlehrverbandModel->insert($studentlehrverband);
						$this->log('insert', $studentlehrverbandresponse, 'studentlehrverband');
					}
					$studentlehrverbandpk = $studentlehrverbandresponse->retval;
				}
			}
		}

		return $studentlehrverbandpk;
	}

	/**
	 * Inserts bisio for a student or updates an existing one.
	 * @param $bisio
	 * @return int|null bisio_id of inserted or updated bisio if successful, null otherwise.
	 */
	private function _saveBisio($bisio)
	{
		$bisiorespid = null;

		$bisiocheckresp = $this->ci->BisioModel->loadWhere(array('student_uid' => $bisio['student_uid']));

		if (isSuccess($bisiocheckresp))
		{
			if (hasData($bisiocheckresp))
			{
				$this->stamp('update', $bisio);
				$bisioresult = $this->ci->BisioModel->update($bisiocheckresp->retval[0]->bisio_id, $bisio);
				$this->log('update', $bisioresult, 'bisio');
			}
			else
			{
				$this->stamp('insert', $bisio);
				$bisioresult = $this->ci->BisioModel->insert($bisio);
				$this->log('insert', $bisioresult, 'bisio');
			}

			$bisiorespid = $bisioresult->retval;
		}

		return $bisiorespid;
	}

	/**
	 * Inserts Kontobuchungen, adds data like Betrag, Buchungsverweis, Zahlungsreferenz.
	 * Saves Gegenbuchung if Buchungsbetrag is 0.
	 * Inserts all Buchungstypen present in the buchungstyp_kurzbz property array, if not already existing.
	 * @param $konto
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
				$buchungstypres = $this->ci->BuchungstypModel->load($buchungstyp_kurzbz);

				if (hasData($buchungstypres))
				{
					$checkbuchungres = $this->ci->KontoModel->loadWhere(
						array(
							'buchungstyp_kurzbz' => $buchungstyp_kurzbz,
							'studiensemester_kurzbz' => $konto['studiensemester_kurzbz'],
							'person_id' => $konto['person_id'],
							'studiengang_kz' => $konto['studiengang_kz']
						)
					);

					if (isSuccess($checkbuchungres) && !hasData($checkbuchungres))
					{
						$buchungstyp = $buchungstypres->retval[0];
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

						$kontoinsertres = $this->ci->KontoModel->insert($kontoToInsert);
						$this->log('insert', $kontoinsertres, 'konto');

						if (hasData($kontoinsertres))
						{
							$kontoinsertid = $kontoinsertres->retval;
							// Zahlungsreferenz generieren
							$zahlungsref = generateZahlungsreferenz($konto['studiengang_kz'], $kontoinsertid);

							$zahlungsrefres = $this->ci->KontoModel->update($kontoinsertid, array('zahlungsreferenz' => $zahlungsref));

							if (hasData($zahlungsrefres) && isset($konto['betrag'][$buchungstyp_kurzbz])
								&& $konto['betrag'][$buchungstyp_kurzbz] == 0)
							{
								// Gegenbuchung wenn 0 Betrag
								$gegenbuchung = $kontoToInsert;
								$gegenbuchung['mahnspanne'] = 0;
								$gegenbuchung['buchungsnr_verweis'] = $kontoinsertid;
								$gegenbuchung['zahlungsreferenz'] = $zahlungsref;
								$this->ci->KontoModel->insert($gegenbuchung);

								$inserted_buchungen[] = $kontoinsertid;
							}
						}
					}
				}
			}
		}

		return $inserted_buchungen;
	}
}
