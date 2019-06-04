<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/'.EXT_FKT_PATH.'/generateuid.inc.php');
require_once('include/functions.inc.php');

/**
 * Functionality for syncing MobilityOnline objects to fhcomplete
 */
class SyncFromMobilityOnlineLib extends MobilityOnlineSyncLib
{
	private $_mobilityonline_config;
	private $_debugmode = false;
	// user saved in db insertvon, updatevon fields
	const IMPORTUSER = 'mo_import';
	// stati in application cycle, for displaying last status, in chronological order
	private $_pipelinestati = array(
		'is_mail_best_bew',
		'is_registriert',
		'is_mail_best_reg',
		'is_pers_daten_erf',
		'is_abgeschlossen'
	);
	private $_output = '';

	/**
	 * SyncFromMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_mobilityonline_config = $this->ci->config->item('FHC-Core-MobilityOnline');
		$this->_debugmode = isset($this->_mobilityonline_config['debugmode']) &&
			$this->_mobilityonline_config['debugmode'] === true;

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
		$this->ci->load->model('education/lehrveranstaltung_model', 'LehrveranstaltungModel');
		$this->ci->load->model('education/lehreinheit_model', 'LehreinheitModel');
		$this->ci->load->model('education/studentlehrverband_model', 'StudentlehrverbandModel');
		$this->ci->load->model('codex/Nation_model', 'NationModel');
		$this->ci->load->model('codex/bisio_model', 'BisioModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetmasterdata_model', 'MoGetMaModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Molvidzuordnung_model', 'MolvidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MobilityonlinefhcModel');
	}

	/**
	 * Converts MobilityOnline application to fhcomplete array (with person, prestudent...)
	 * @param $moapp MobilityOnline application
	 * @param $moaddr MobilityOnline adress of application
	 * @param $photo of applicant
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoAppToIncoming($moapp, $moaddr = null, $photo = null)
	{
		$fieldmappings = $this->conffieldmappings['application'];
		$personmappings = $fieldmappings['person'];
		$prestudentmappings = $fieldmappings['prestudent'];
		$prestudentstatusmappings = $fieldmappings['prestudentstatus'];
		$adressemappings = $this->conffieldmappings['address']['adresse'];

		$aktemappings = $fieldmappings['akte'];
		$bisiomappings = $fieldmappings['bisio'];

		//applicationDataElements for which comboboxFirstValue is retrieved instead of elementValue
		$comboboxvaluefields = array($personmappings['staatsbuergerschaft'], $personmappings['sprache'], $prestudentstatusmappings['studiensemester_kurzbz'],
									 $prestudentmappings['studiengang_kz'], $prestudentmappings['zgvmas_code'], $prestudentmappings['zgvnation'], $prestudentmappings['zgvmanation'],
									 $bisiomappings['mobilitaetsprogramm_code'], $bisiomappings['nation_code']);

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
		$moaddrnation = isset($moaddr) ? $moaddr->{$adressemappings['nation']}->description : null;
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
					$moaddr->{$adressemappings['nation']} = $fhcnation->nation_code;
				}
			}
		}

		// Lichtbild
		if ($photo)
		{
			$moapp->{$aktemappings['inhalt']} = base64_encode($photo[0]->{$aktemappings['inhalt']});
		}

		$fhcobj = $this->convertToFhcFormat($moapp, 'application');

		$fhcobj['pipelineStatus'] = 'not set';
		$fhcobj['pipelineStatusDescription'] = 'no Status set';

		// add last status
		for ($i = count($this->_pipelinestati) - 1; $i >= 0; $i--)
		{
			foreach ($moapp->nonUsedApplicationDataElements as $element)
			{
				if ($element->elementName === $this->_pipelinestati[$i] && $element->elementValueBoolean === true)
				{
						$fhcobj['pipelineStatus'] = $element->elementName;
						$fhcobj['pipelineStatusDescription'] = $element->elementDescription;
						break 2;
				}
			}
		}

		$fhcaddr = $this->convertToFhcFormat($moaddr, 'address');

		$fhcobj = array_merge($fhcobj, $fhcaddr);

		// courses
		$fhcobj['mocourses'] = array();
		$courses = $this->ci->MoGetAppModel->getCoursesOfApplication($moapp->applicationID);

		if (is_array($courses))
		{
			foreach ($courses as $course)
			{
				$coursedata = new stdClass();
				$coursedata->number = $course->hostCourseNumber;
				$coursedata->name = $course->hostCourseName;
				$fhcobj['mocourses'][] = $coursedata;
			}
		}

		return $fhcobj;
	}

	/**
	 * Converts MobilityOnline course to fhcomplete course
	 * Finds course in synctable and loads them from fhcomplete
	 * @param $course
	 * @param $studiensemester
	 * @param $uid
	 * @return array
	 */
	public function mapMoIncomingCourseToLv($course, $studiensemester, $uid)
	{
		$studiensemestermo = $this->mapSemesterToMo($studiensemester);

		$searchparams = array('semesterDescription' => $studiensemestermo,
							  'applicationType' => 'IN',
							  'courseNumber' => $course->hostCourseNumber
								);

		$searchobj = $this->getSearchObj('course', $searchparams);

		// search for course to get courseID
		$mocourses = $this->ci->MoGetMaModel->getCoursesOfSemesterBySearchParameters($searchobj);

		$fhccourse = $this->convertToFhcFormat($course, 'incomingcourse');

		if (is_array($mocourses))
		{
			foreach ($mocourses as $mocourse)
			{
				$mocourseid = $mocourse->courseID;

				$lvidzuordnung = $this->ci->MolvidzuordnungModel->loadWhere(array('studiensemester_kurzbz' => $studiensemester, 'mo_lvid' => $mocourseid));

				if (hasData($lvidzuordnung))
				{
					$this->fillFhcCourse($lvidzuordnung->retval[0]->lehrveranstaltung_id, $uid, $studiensemester, $fhccourse);
				}
			}
		}

		return $fhccourse;
	}

	/**
	 * Fills fhccourse with necessary data before displaying, adds Lehreinheiten to the course.
	 * @param $lehrveranstaltung_id
	 * @param $uid for getting group assignments or lehreinheiten
	 * @param $studiensemester_kurzbz
	 * @param $fhccourse to be filled
	 */
	public function fillFhcCourse($lehrveranstaltung_id, $uid, $studiensemester_kurzbz, &$fhccourse)
	{
		$this->ci->LehrveranstaltungModel->addSelect('lehrveranstaltung_id, tbl_lehrveranstaltung.bezeichnung AS lvbezeichnung, incoming');

		$lvresult = $this->ci->LehrveranstaltungModel->loadWhere(
			array(
				'tbl_lehrveranstaltung.lehrveranstaltung_id' => $lehrveranstaltung_id
			)
		);

		if (hasData($lvresult))
		{
			$lv = $lvresult->retval[0];
			$fhccourse['lehrveranstaltung']['lehrveranstaltung_id'] = $lv->lehrveranstaltung_id;
			$fhccourse['lehrveranstaltung']['fhcbezeichnung'] = $lv->lvbezeichnung;
			$fhccourse['lehrveranstaltung']['incomingplaetze'] = $lv->incoming;

			//get studiengäng(e) and semester for LV
			$this->ci->LehrveranstaltungModel->addSelect('tbl_studiengang.studiengang_kz, tbl_studiengang.typ, tbl_studiengang.kurzbz AS studiengang_kurzbz, tbl_studiengang.bezeichnung, tbl_studienplan_lehrveranstaltung.semester');
			$this->ci->LehrveranstaltungModel->addJoin('lehre.tbl_studienplan_lehrveranstaltung', 'lehrveranstaltung_id', 'LEFT');
			$this->ci->LehrveranstaltungModel->addJoin('lehre.tbl_studienplan', 'studienplan_id', 'LEFT');
			$this->ci->LehrveranstaltungModel->addJoin('lehre.tbl_studienplan_semester', 'studienplan_id', 'LEFT');
			$this->ci->LehrveranstaltungModel->addJoin('lehre.tbl_studienordnung', 'studienordnung_id', 'LEFT');
			$this->ci->LehrveranstaltungModel->addJoin('public.tbl_studiengang', 'tbl_studienordnung.studiengang_kz = tbl_studiengang.studiengang_kz', 'LEFT');

			$lvdataresult = $this->ci->LehrveranstaltungModel->loadWhere(
				array(
					'tbl_lehrveranstaltung.lehrveranstaltung_id' => $lehrveranstaltung_id,
					'tbl_studienplan_semester.studiensemester_kurzbz' => $studiensemester_kurzbz
				)
			);

			$fhccourse['studiengaenge'] = array();
			$fhccourse['ausbildungssemester'] = array();

			if (hasData($lvdataresult))
			{
				foreach ($lvdataresult->retval as $lvdata)
				{
					$found = false;
					foreach ($fhccourse['studiengaenge'] as $studiengangobj)
					{
						if ($studiengangobj->studiengang_kz == $lvdata->studiengang_kz)
						{
							$found = true;
							break;
						}
					}

					if (!$found)
					{
						$studiengang = new StdClass();
						$studiengang->studiengang_kz = $lvdata->studiengang_kz;
						$studiengang->kuerzel = mb_strtoupper($lvdata->typ . $lvdata->studiengang_kurzbz);
						$studiengang->bezeichnung = $lvdata->bezeichnung;
						$fhccourse['studiengaenge'][] = $studiengang;
					}

					$found = false;
					foreach ($fhccourse['ausbildungssemester'] as $ausbildungssemobj)
					{
						if ($ausbildungssemobj == $lvdata->semester)
						{
							$found = true;
							break;
						}
					}

					if (!$found)
					{
						$fhccourse['ausbildungssemester'][] = $lvdata->semester;
					}
				}
			}

			//get Lehreinheiten, number of students, directly assigned for Lv
			if (isset($fhccourse['lehrveranstaltung']['lehrveranstaltung_id']) &&
				is_numeric($fhccourse['lehrveranstaltung']['lehrveranstaltung_id']))
			{
				$fhccourse['lehreinheiten'] = $this->ci->LehreinheitModel->getLesForLv($fhccourse['lehrveranstaltung']['lehrveranstaltung_id'], $studiensemester_kurzbz, false);

				$anz_incomings = 0;

				$incoming_prestudent_ids = array();

				foreach ($fhccourse['lehreinheiten'] as $lehreinheit)
				{
					$lehreinheit->directlyAssigned = false;

					$students = $this->ci->LehreinheitModel->getStudenten($lehreinheit->lehreinheit_id);

					$anz_teilnehmer = 0;

					if (isSuccess($students))
					{
						$anz_teilnehmer = count($students->retval);

						foreach ($students->retval as $student)
						{
							if (!in_array($student->prestudent_id, $incoming_prestudent_ids))
							{
								$lastStatus = $this->ci->PrestudentstatusModel->getLastStatus($student->prestudent_id, $studiensemester_kurzbz, 'Incoming');

								if (hasData($lastStatus))
								{
									$incoming_prestudent_ids[] = $student->prestudent_id;
									$anz_incomings++;
								}
							}
						}
					}

					$lehreinheit->anz_teilnehmer = $anz_teilnehmer;

					$directlyassigned = $this->ci->LehreinheitgruppeModel->getDirectGroupAssignment($uid, $lehreinheit->lehreinheit_id);

					if (hasData($directlyassigned))
						$lehreinheit->directlyAssigned = true;
				}

				$fhccourse['lehrveranstaltung']['anz_incomings'] = $anz_incomings;
			}
		}
	}

	/**
	 * Saves an incoming (pre-)student, i.e. adds him or updates it if prestudent_id is set
	 * @param $incoming
	 * @param $prestudent_id
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveIncoming($incoming, $prestudent_id = null)
	{
		$this->_output = '';
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($incoming, 'application');

		if ($errors->error)
		{
			$this->_output .= "<br />ERROR! ";
			foreach ($errors->errorMessages as $errorMessage)
			{
				$this->_output .= "$errorMessage";
			}

			$this->_output .= "<br />aborting incoming save";
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

		// optional
		$akte = isset($incoming['akte']) ? $incoming['akte'] : array();
		$kontaktnotfall = isset($incoming['kontaktnotfall']) ? $incoming['kontaktnotfall'] : array();
		$kontakttel = isset($incoming['kontakttel']) ? $incoming['kontakttel'] : array();

		$studiensemester = $prestudentstatus['studiensemester_kurzbz'];

		$prestudentstatus['studiensemester_kurzbz'] = $studiensemester;

		// Start DB transaction
		$this->ci->db->trans_begin();

		$prestudentcheckresp = isset($prestudent_id) && is_numeric($prestudent_id) ? $this->ci->PrestudentModel->load($prestudent_id) : null;

		$update = hasData($prestudentcheckresp);

		// person
		// update if prestudent already exists, insert otherwise
		if ($update)
		{
			$person_id = $prestudentcheckresp->retval[0]->person_id;
			$this->_stamp('update', $person);
			$personresponse = $this->ci->PersonModel->update($person_id, $person);
			$this->_log('update', $personresponse, 'person');
		}
		else
		{
			$this->_stamp('insert', $person);
			$personresponse = $this->ci->PersonModel->insert($person);
			if (isSuccess($personresponse))
			{
				$person_id = $personresponse->retval;
			}
			$this->_log('insert', $personresponse, 'person');
		}

		if (isset($person_id) && is_numeric($person_id))
		{
			// adresse

			// insert if there is no Heimatadresse
			$heimataddrresp = $this->ci->AdresseModel->loadWhere(array('person_id' => $person_id, 'heimatadresse' => true));

			if (isSuccess($heimataddrresp) && !hasData($heimataddrresp))
			{
				$adresse['person_id'] = $person_id;
				$this->_stamp('insert', $adresse);
				$addrresp = $this->ci->AdresseModel->insert($adresse);
				$this->_log('insert', $addrresp, 'adresse');
			}
			// kontakt
			$kontaktmailresp = $this->ci->KontaktModel->loadWhere(array('person_id' => $person_id, 'kontakttyp' => $kontaktmail['kontakttyp']));

			$mailfound = false;
			if (hasData($kontaktmailresp))
			{
				foreach ($kontaktmailresp->retval as $kontakt)
				{
					if ($kontakt->kontakt === $kontaktmail['kontakt'])
					{
						$mailfound = true;
						break;
					}
				}
			}

			if (isSuccess($kontaktmailresp) && !$mailfound)
			{
				$kontaktmail['person_id'] = $person_id;
				$this->_stamp('insert', $kontaktmail);
				$kontaktinsresp = $this->ci->KontaktModel->insert($kontaktmail);
				$this->_log('insert', $kontaktinsresp, 'mailkontakt');
			}

			$kontakttelresp = $this->ci->KontaktModel->loadWhere(array('person_id' => $person_id, 'kontakttyp' => $kontakttel['kontakttyp']));

			if (!empty($kontakttel['kontakt']))
			{
				$telfound = false;
				if (hasData($kontakttelresp))
				{
					foreach ($kontakttelresp->retval as $kontakt)
					{
						if ($kontakt->kontakt === $kontakttel['kontakt'])
						{
							$telfound = true;
							break;
						}
					}
				}

				if (isSuccess($kontakttelresp) && !$telfound)
				{
					$kontakttel['person_id'] = $person_id;
					$this->_stamp('insert', $kontakttel);
					$kontaktinsresp = $this->ci->KontaktModel->insert($kontakttel);
					$this->_log('insert', $kontaktinsresp, 'telefonkontakt');
				}
			}

			$kontaktnfresp = $this->ci->KontaktModel->loadWhere(array('person_id' => $person_id, 'kontakttyp' => $kontaktnotfall['kontakttyp']));

			if (!empty($kontaktnotfall['kontakt']))
			{
				$nfkfound = false;
				if (hasData($kontaktnfresp))
				{
					foreach ($kontaktnfresp->retval as $kontakt)
					{
						if ($kontakt->kontakt === $kontaktnotfall['kontakt'])
						{
							$nfkfound = true;
							break;
						}
					}
				}

				if (isSuccess($kontaktnfresp) && !$nfkfound)
				{
					$kontaktnotfall['person_id'] = $person_id;
					$this->_stamp('insert', $kontaktnotfall);
					$kontaktinsresp = $this->ci->KontaktModel->insert($kontaktnotfall);
					$this->_log('insert', $kontaktinsresp, 'notfallkontakt');
				}
			}

			if (isset($akte['dokument_kurzbz']))
			{
				// lichtbild - akte
				$aktecheckresp = $this->ci->AkteModel->loadWhere(array('person_id' => $person_id, 'dokument_kurzbz' => $akte['dokument_kurzbz']));

				if (isSuccess($aktecheckresp))
				{
					if (hasData($aktecheckresp))
					{
						if ($this->_debugmode)
						{
							$this->_output .= '<br />lichtbild already exists, akte_id ' .$aktecheckresp->retval[0]->akte_id;
						}
					}
					else
					{
						$akte['person_id'] = $person_id;
						$akte['titel'] = 'Lichtbild_' . $person_id;
						$this->_stamp('insert', $akte);
						$akteresp = $this->ci->AkteModel->insert($akte);
						$this->_log('insert', $akteresp, 'akte');
					}
				}
			}

			// prestudent
			$prestudent['person_id'] = $person_id;
			if ($update)
			{
				$this->_stamp('update', $prestudent);
				$prestudentresponse = $this->ci->PrestudentModel->update($prestudent_id, $prestudent);
				$this->_log('update', $prestudentresponse, 'prestudent');
			}
			else
			{
				$this->_stamp('insert', $prestudent);
				$prestudentresponse = $this->ci->PrestudentModel->insert($prestudent);
				$this->_log('insert', $prestudentresponse, 'prestudent');
			}

			$prestudent_id_res = isset($prestudentresponse->retval) ? $prestudentresponse->retval : null;
			if (isset($prestudent_id_res) && is_numeric($prestudent_id_res))
			{
				// prestudentstatus
				$prestudentstatus['prestudent_id'] = $prestudent_id_res;

				$studiensemarr = array($studiensemester);

				// add prestudentstatus for semester saved in MO
				$studiensemesterres = $this->ci->StudiensemesterModel->getByDate($incoming['bisio']['von'], $incoming['bisio']['bis']);

				// add prestudentstatus for each semester in the time span of von - bis date
				if (hasData($studiensemesterres))
				{
					foreach ($studiensemesterres->retval as $semester)
					{
						$studiensemester_kurzbz = $semester->studiensemester_kurzbz;
						if (!in_array($studiensemester_kurzbz, $studiensemarr))
							$studiensemarr[] = $studiensemester_kurzbz;
					}
				}

				foreach ($studiensemarr as $semester)
				{
					$lastStatus = $this->ci->PrestudentstatusModel->getLastStatus($prestudent_id_res, $semester);
					if (isSuccess($lastStatus) && (!hasData($lastStatus) || $lastStatus->retval[0]->status_kurzbz !== 'Incoming'))
					{
						$prestudentstatus['studiensemester_kurzbz'] = $semester;
						$prestudentstatus['datum'] = date('Y-m-d', time());
						$this->_stamp('insert', $prestudentstatus);
						$prestudentstatusresponse = $this->ci->PrestudentstatusModel->insert($prestudentstatus);
						$this->_log('insert', $prestudentstatusresponse, 'prestudentstatus');
					}
				}
			}

			// benutzer
			$matrikelnr = $this->ci->StudentModel->generateMatrikelnummer($prestudent['studiengang_kz'], $studiensemester);
			$this->ci->StudentModel->addOrder('insertamum');
			$benutzerstudcheckresp = $this->ci->StudentModel->loadWhere(array('prestudent_id' => $prestudent_id_res));
			$benutzercheckresp = success('success');

			if (isSuccess($benutzerstudcheckresp))
			{
				if (hasData($benutzerstudcheckresp))
				{
					$benutzer['uid'] = $benutzerstudcheckresp->retval[0]->student_uid;
					if ($this->_debugmode)
					{
						$this->_output .= "<br />benutzer for student $prestudent_id_res already exists, uid " .$benutzer['uid'];
					}
				}
				else
				{
					$benutzer['person_id'] = $person_id;
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
							$this->_output .= "<br />benutzer with uid ".$benutzer['uid']." already exists";
						}
						elseif (isSuccess($benutzercheckresp))
						{
							$benutzer['aktivierungscode'] = generateActivationKey();
							$this->_stamp('insert', $benutzer);
							$benutzerinscheckresp = $this->ci->BenutzerModel->insert($benutzer);
							$this->_log('insert', $benutzerinscheckresp, 'benutzer');
						}
					}
				}
			}

			if (isSuccess($benutzerstudcheckresp) && isSuccess($benutzercheckresp)
				&& isset($prestudent_id_res) && is_numeric($prestudent_id_res))
			{
				// student
				$student['student_uid'] = $benutzer['uid'];
				$student['prestudent_id'] = $prestudent_id_res;
				$student['studiengang_kz'] = $prestudent['studiengang_kz'];

				$studentcheckresp = $this->ci->StudentModel->load(array($student['student_uid']));

				if (isSuccess($studentcheckresp))
				{
					if (hasData($studentcheckresp))
					{
						$this->_stamp('update', $student);
						$studentresponse = $this->ci->StudentModel->update(array($student['student_uid']), $student);
						$this->_log('update', $studentresponse, 'student');
					}
					else
					{
						$student['matrikelnr'] = $matrikelnr;
						$this->_stamp('insert', $student);
						$studentresponse = $this->ci->StudentModel->insert($student);
						$this->_log('insert', $studentresponse, 'student');
					}
				}

				if (isSuccess($studentresponse))
				{
					// studentlehrverband
					$studentlehrverband['student_uid'] = $benutzer['uid'];
					$studentlehrverband['studiengang_kz'] = $prestudent['studiengang_kz'];
					$studentlehrverband['semester'] = $prestudentstatus['ausbildungssemester'];

					if (hasData($studiensemesterres))
					{
						foreach ($studiensemarr as $semester)
						{
							$studentlehrverband['studiensemester_kurzbz'] = $semester;
							$studenlehrverbandcheckresp = $this->ci->StudentlehrverbandModel->load(array('student_uid' => $studentlehrverband['student_uid'], 'studiensemester_kurzbz' => $studentlehrverband['studiensemester_kurzbz']));
							if (isSuccess($studenlehrverbandcheckresp))
							{
								if (hasData($studenlehrverbandcheckresp))
								{
									$this->_stamp('update', $studentlehrverband);
									$studentlehrverbandresponse = $this->ci->StudentlehrverbandModel->update(array('student_uid' => $studentlehrverband['student_uid'], 'studiensemester_kurzbz' => $studentlehrverband['studiensemester_kurzbz']), $studentlehrverband);
									$this->_log('update', $studentlehrverbandresponse, 'studentlehrverband');
								}
								else
								{
									$this->_stamp('insert', $studentlehrverband);
									$studentlehrverbandresponse = $this->ci->StudentlehrverbandModel->insert($studentlehrverband);
									$this->_log('insert', $studentlehrverbandresponse, 'studentlehrverband');
								}
							}
						}
					}
				}

				// bisio
				$bisio['student_uid'] = $benutzer['uid'];

				$bisiocheckresp = $this->ci->BisioModel->loadWhere(array('student_uid' => $bisio['student_uid']));

				if (isSuccess($bisiocheckresp))
				{
					if (hasData($bisiocheckresp))
					{
						$this->_stamp('update', $bisio);
						$bisioresult = $this->ci->BisioModel->update($bisiocheckresp->retval[0]->bisio_id, $bisio);
						$this->_log('update', $bisioresult, 'bisio');
					}
					else
					{
						$this->_stamp('insert', $bisio);
						$bisioresult = $this->ci->BisioModel->insert($bisio);
						$this->_log('insert', $bisioresult, 'bisio');
					}
				}
			}
		}

		// Transaction complete!
		$this->ci->db->trans_complete();

		// Check if everything went ok during the transaction
		if ($this->ci->db->trans_status() === false)
		{
			$this->_output .= "rolling back...";
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
	 * Gets sync output string
	 * @return string
	 */
	public function getOutput()
	{
		return $this->_output;
	}

	/**
	 * Gets object for searching an Object in MobilityOnline API
	 * @param $objtype Type of object to search.
	 * @param $searchparams Fields with values to search for.
	 * @return array the object containing search parameters.
	 */
	public function getSearchObj($objtype, $searchparams)
	{
		$searchobj = array();

		$fields = $this->conffields[$objtype];

		foreach ($fields as $field)
		{
			$searchobj[$field] = null;
		}

		if (is_array($searchparams))
		{
			foreach ($searchparams as $paramname => $param)
			{
				$searchobj[$paramname] = $param;
			}
		}

		return $searchobj;
	}

	/**
	 * Checks if fhcomplete object has errors, like missing fields,
	 * so it cannot be inserted in db
	 * @param $fhcobj
	 * @param $objtype
	 * @return StdClass object with properties bollean for has Error and array with errormessages
	 */
	public function fhcObjHasError($fhcobj, $objtype)
	{
		$hasError = new StdClass();
		$hasError->error = false;
		$hasError->errorMessages = array();
		$requiredfields = $this->requiredfields[$objtype];

		foreach ($requiredfields as $table => $fields)
		{
			if (array_key_exists($table, $fhcobj))
			{
				foreach ($fields as $field => $params)
				{
					$haserror = false;

					if (isset($fhcobj[$table][$field]))
					{
						$value = $fhcobj[$table][$field];
						if (!is_numeric($value) && isEmptyString($value))
							$haserror = true;
					}
					else
						$haserror = true;

					if ($haserror)
					{
						$fieldname = isset($params['name']) ? $params['name'] : ucfirst($field);

						$hasError->errorMessages[] = ucfirst($table).": $fieldname missing or has no match";
						$hasError->error = true;
					}
				}
			}
			else
			{
				$hasError->errorMessages[] = "data missing: $table";
				$hasError->error = true;
			}
		}

		return $hasError;
	}

	/**
	 * Outputs success or error of a db action
	 * @param $modtype insert, update,...
	 * @param $response of db action
	 * @param $table database table
	 */
	private function _log($modtype, $response, $table)
	{
		if ($this->_debugmode)
		{
			if (isSuccess($response))
			{
				if (is_array($response->retval))
					$id = implode('; ', $response->retval);
				else
					$id = $response->retval;

				$this->_output .= "<br />$table $modtype successful, id " . $id;
			}
			else
			{
				$this->_output .= "<br />$table $modtype error";
			}
		}
	}

	/**
	 * Sets timestamp and importuser for insert/update
	 * @param $modtype
	 * @param $arr
	 */
	private function _stamp($modtype, &$arr)
	{
		$idx = $modtype.'amum';
		$arr[$idx] = date('Y-m-d H:i:s', time());
		$idx = $modtype.'von';
		$arr[$idx] = self::IMPORTUSER;
	}
}
