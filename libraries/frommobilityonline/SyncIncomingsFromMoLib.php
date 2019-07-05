<?php

/**
 * Functionality for syncing incomings from MobilityOnline to fhcomplete
 */
class SyncIncomingsFromMoLib extends SyncFromMobilityOnlineLib
{
	const MOOBJECTTYPE = 'application';

	// stati in application cycle, for displaying last status, in chronological order
	private $_pipelinestati = array(
		'is_mail_best_bew',
		'is_registriert',
		'is_mail_best_reg',
		'is_pers_daten_erf',
		'is_abgeschlossen'
	);

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
		$this->ci->load->model('education/lehrveranstaltung_model', 'LehrveranstaltungModel');
		$this->ci->load->model('education/lehreinheit_model', 'LehreinheitModel');
		$this->ci->load->model('education/studentlehrverband_model', 'StudentlehrverbandModel');
		$this->ci->load->model('codex/Nation_model', 'NationModel');
		$this->ci->load->model('codex/bisio_model', 'BisioModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Moappidzuordnung_model', 'MoappidzuordnungModel');
	}

	/**
	 * Executes sync of incomings for a Studiensemester from MO to FHC. Adds or updates incomings.
	 * @param $studiensemester
	 * @param $incomings
	 * @return string syncoutput containing info about failures/success
	 */
	public function startIncomingSync($studiensemester, $incomings)
	{
		$syncoutput = '';
		$studcount = count($incomings);

		if (empty($incomings) || !is_array($incomings) || $studcount <= 0)
		{
			$syncoutput .= "<div class='text-center'>No incomings found for sync! aborting.</div>";
		}
		else
		{
			$added = $updated = 0;

			$syncoutput .= "<div class='text-center'>MOBILITY ONLINE INCOMINGS SYNC start. $studcount incomings to sync.";
			$syncoutput .= '<br/>-----------------------------------------------</div><div class="incomingsyncoutputtext">';

			$first = true;
			foreach ($incomings as $incoming)
			{
				$incomingdata = $incoming['data'];
				$appid = $incoming['moid'];

				if (!$first)
					$syncoutput .= "<br />";
				$first = false;

				$infhccheck_prestudent_id = $this->checkMoIdInFhc($appid);

				if (isset($infhccheck_prestudent_id) && is_numeric($infhccheck_prestudent_id))
				{
					$syncoutput .= "<br />prestudent ".("for applicationid $appid ").$incomingdata['person']['vorname'].
						" ".$incomingdata['person']['nachname']." already exists in fhcomplete - updating";

					$prestudent_id = $this->saveIncoming($incomingdata, $infhccheck_prestudent_id);

					$saveIncomingOutput = $this->getOutput();

					$syncoutput .= $saveIncomingOutput;

					if (isset($prestudent_id) && is_numeric($prestudent_id))
					{
						$result = $this->ci->MoappidzuordnungModel->update(
							array('mo_applicationid' => $appid, 'prestudent_id' => $prestudent_id, 'studiensemester_kurzbz' => $studiensemester),
							array('updateamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$updated++;
							$syncoutput .= "<br /><i class='fa fa-check text-success'></i> student for applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']." successfully updated";
						}
					}
					else
					{
						$syncoutput .= "<br /><span class='text-danger'><i class='fa fa-times'></i> error when updating student for applicationid $appid - "
							.$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']."</span>";
					}
				}
				else
				{
					$prestudent_id = $this->saveIncoming($incomingdata);

					$saveIncomingOutput = $this->getOutput();

					$syncoutput .= $saveIncomingOutput;

					if (isset($prestudent_id) && is_numeric($prestudent_id))
					{
						$result = $this->ci->MoappidzuordnungModel->insert(
							array('mo_applicationid' => $appid, 'prestudent_id' => $prestudent_id, 'studiensemester_kurzbz' => $studiensemester, 'insertamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$added++;
							$syncoutput .= "<br /><i class='fa fa-check text-success'></i> student for applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']." successfully added";
						}
						else
							$syncoutput .= "<br /><span class='text-danger'><i class='fa fa-times'></i> mapping entry in db could not be added student for applicationid $appid - ".
								$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']."</span>";
					}
					else
					{
						$syncoutput .= "<br /><span class='text-danger'><i class='fa fa-times'></i> error when adding student for applicationid $appid - ".
							$incomingdata['person']['vorname']." ".$incomingdata['person']['nachname']."</span>";
					}
				}
			}
			$syncoutput .= "</div><div class='text-center'><br />-----------------------------------------------";
			$syncoutput .= "<br />MOBILITY ONLINE INCOMINGS SYNC FINISHED <br />$added added, $updated updated</div>";
		}
		return $syncoutput;
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
		$fieldmappings = $this->conffieldmappings[self::MOOBJECTTYPE];
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
		$moaddrnation = isset($moaddr) ? $moaddr->{$adressemappings['nation']['name']}->description : null;
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
			}
		}

		// Lichtbild
		if ($photo)
		{
			$moapp->{$aktemappings['inhalt']} = base64_encode($photo[0]->{$aktemappings['inhalt']});
		}

		$fhcobj = $this->convertToFhcFormat($moapp, self::MOOBJECTTYPE);

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
		$this->output = '';
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($incoming, self::MOOBJECTTYPE);

		if ($errors->error)
		{
			$this->output .= "<br />ERROR! ";
			foreach ($errors->errorMessages as $errorMessage)
			{
				$this->output .= "$errorMessage";
			}

			$this->output .= "<br />aborting incoming save";
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

		if (isset($person_id) && is_numeric($person_id))
		{
			// adresse

			// insert if there is no Heimatadresse
			$heimataddrresp = $this->ci->AdresseModel->loadWhere(array('person_id' => $person_id, 'heimatadresse' => true));

			if (isSuccess($heimataddrresp) && !hasData($heimataddrresp))
			{
				$adresse['person_id'] = $person_id;
				$this->stamp('insert', $adresse);
				$addrresp = $this->ci->AdresseModel->insert($adresse);
				$this->log('insert', $addrresp, 'adresse');
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
				$this->stamp('insert', $kontaktmail);
				$kontaktinsresp = $this->ci->KontaktModel->insert($kontaktmail);
				$this->log('insert', $kontaktinsresp, 'mailkontakt');
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
					$this->stamp('insert', $kontakttel);
					$kontaktinsresp = $this->ci->KontaktModel->insert($kontakttel);
					$this->log('insert', $kontaktinsresp, 'telefonkontakt');
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
					$this->stamp('insert', $kontaktnotfall);
					$kontaktinsresp = $this->ci->KontaktModel->insert($kontaktnotfall);
					$this->log('insert', $kontaktinsresp, 'notfallkontakt');
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
						if ($this->debugmode)
						{
							$this->output .= '<br />lichtbild already exists, akte_id ' .$aktecheckresp->retval[0]->akte_id;
						}
					}
					else
					{
						$akte['person_id'] = $person_id;
						$akte['titel'] = 'Lichtbild_' . $person_id;
						$this->stamp('insert', $akte);
						$akteresp = $this->ci->AkteModel->insert($akte);
						$this->log('insert', $akteresp, 'akte');
					}
				}
			}

			// prestudent
			$prestudent['person_id'] = $person_id;
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
						$this->stamp('insert', $prestudentstatus);
						$prestudentstatusresponse = $this->ci->PrestudentstatusModel->insert($prestudentstatus);
						$this->log('insert', $prestudentstatusresponse, 'prestudentstatus');
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
					if ($this->debugmode)
					{
						$this->output .= "<br />benutzer for student $prestudent_id_res already exists, uid " .$benutzer['uid'];
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
							$this->output .= "<br />benutzer with uid ".$benutzer['uid']." already exists";
						}
						elseif (isSuccess($benutzercheckresp))
						{
							$benutzer['aktivierungscode'] = generateActivationKey();
							$this->stamp('insert', $benutzer);
							$benutzerinscheckresp = $this->ci->BenutzerModel->insert($benutzer);
							$this->log('insert', $benutzerinscheckresp, 'benutzer');
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
						$this->stamp('update', $student);
						$studentresponse = $this->ci->StudentModel->update(array($student['student_uid']), $student);
						$this->log('update', $studentresponse, 'student');
					}
					else
					{
						$student['matrikelnr'] = $matrikelnr;
						$this->stamp('insert', $student);
						$studentresponse = $this->ci->StudentModel->insert($student);
						$this->log('insert', $studentresponse, 'student');
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
				}
			}
		}

		// Transaction complete!
		$this->ci->db->trans_complete();

		// Check if everything went ok during the transaction
		if ($this->ci->db->trans_status() === false)
		{
			$this->output .= "rolling back...";
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
	 * Gets incomings for a studiensemester
	 * @param $studiensemester
	 * @return array with applications
	 */
	public function getIncoming($studiensemester)
	{
		$studiensemestermo = $this->mapSemesterToMo($studiensemester);
		$semestersforsearch = array($studiensemestermo);
		$appids = array();

		// if Wintersemester, also search for Incomings who have entered Studienjahr as their Semester
		$studienjahrsemestermo = $this->mapSemesterToMoStudienjahr($studiensemester);
		if (isset($studienjahrsemestermo))
			$semestersforsearch[] = $studienjahrsemestermo;

		foreach ($semestersforsearch as $semesterforsearch)
		{
			$appobj = $this->getSearchObj(
				self::MOOBJECTTYPE,
				array('semesterDescription' => $semesterforsearch,
					  'applicationType' => 'IN',
					  'personType' => 'S')
			);

			$semappids = $this->ci->MoGetAppModel->getApplicationIds($appobj);

			if (isset($semappids) && is_array($semappids))
				$appids = array_merge($appids, $semappids);
		}

		return $this->_getIncomingByIds($appids, $studiensemester);
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
	 * also checks if incomings already in fhcomplete
	 * (prestudent_id in tbl_mo_appidzuordnung table and tbl_prestudent)
	 * @param $appids
	 * @param $studiensemester for check if in mapping table
	 * @return array with applications
	 */
	private function _getIncomingByIds($appids, $studiensemester)
	{
		$incomings = array();

		foreach ($appids as $appid)
		{
			$application = $this->ci->MoGetAppModel->getApplicationById($appid);
			$address = $this->ci->MoGetAppModel->getPermanentAddress($appid);
			$lichtbild = $this->ci->MoGetAppModel->getFilesOfApplication($appid, 'PASSFOTO');

			$fhcobj = $this->mapMoAppToIncoming($application, $address, $lichtbild);

			$zuordnung = $this->ci->MoappidzuordnungModel->loadWhere(
				array(
					'mo_applicationid' => $appid,
					'studiensemester_kurzbz' => $studiensemester)
			);

			$fhcobj_extended = new StdClass();
			$fhcobj_extended->moid = $appid;

			$fhcobj_extended->infhc = false;

			$errors = $this->fhcObjHasError($fhcobj, self::MOOBJECTTYPE);
			$fhcobj_extended->error = $errors->error;
			$fhcobj_extended->errorMessages = $errors->errorMessages;

			if (hasData($zuordnung))
			{
				$prestudent_id = $zuordnung->retval[0]->prestudent_id;

				$prestudent_res = $this->ci->PrestudentModel->load($prestudent_id);

				if (hasData($prestudent_res))
				{
					$fhcobj_extended->infhc = true;
					$fhcobj_extended->prestudent_id = $prestudent_id;
				}
			}

			$fhcobj_extended->data = $fhcobj;
			$incomings[] = $fhcobj_extended;
		}

		return $incomings;
	}
}
