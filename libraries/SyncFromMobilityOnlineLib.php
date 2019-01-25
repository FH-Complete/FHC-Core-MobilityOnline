<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/'.EXT_FKT_PATH.'/generateuid.inc.php');
require_once('include/functions.inc.php');

/**
 * Functionality for syncing MobilityOnline objects to fhcomplete
 */
class SyncFromMobilityOnlineLib extends MobilityOnlineSyncLib
{
	// user saved in db insertvon, updatevon fields
	const IMPORTUSER = 'mo_import';
	private $_pipelinestati = array(
		'is_mail_best_bew',
		'is_registriert',
		'is_mail_best_reg',
		'is_pers_daten_erf',
		'is_abgeschlossen'
	);


	/**
	 * SyncFromMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Converts MobilityOnline application to fhcomplete array (with person, prestudent...)
	 * @param $moapp MobilityOnline application
	 * @param $moaddr MobilityOnline adress of application
	 * @param $photo of applicant
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoAppToIncoming($moapp, $moaddr, $photo)
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
									 $prestudentmappings['studiengang_kz'], $prestudentmappings['zgvmas_code'], /*$prestudentmappings['zgvnation'],*/ $prestudentmappings['zgvmanation'],
									 $bisiomappings['mobilitaetsprogramm_code'], $bisiomappings['nation_code']);

		foreach ($fieldmappings as $fhctable)
		{
			foreach ($fhctable as $value)
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

		// Nation
		$monation = $moapp->{$personmappings['staatsbuergerschaft']};
		$moaddrnation = isset($moaddr) ? $moaddr->{$adressemappings['nation']}->description : null;
		$mozgvnation = isset($prestudentmappings['zgvnation']) && isset($moapp->{$prestudentmappings['zgvnation']}) ? $moapp->{$prestudentmappings['zgvnation']} : null;
		$mozgvmanation = isset($prestudentmappings['zgvmanation']) && isset($moapp->{$prestudentmappings['zgvmanation']}) ? $moapp->{$prestudentmappings['zgvmanation']} : null;
		$this->ci->load->model('codex/Nation_model', 'NationModel');

		$fhcnations = $this->ci->NationModel->load();

		if (hasData($fhcnations))
		{
			foreach ($fhcnations->retval as $fhcnation)
			{
				// try to get nation by bezeichnung
				if ($fhcnation->kurztext === $monation || $fhcnation->langtext === $monation || $fhcnation->engltext === $monation)
				{
					$moapp->{$personmappings['staatsbuergerschaft']} = $fhcnation->nation_code;
					$moapp->{$bisiomappings['nation_code']} = $fhcnation->nation_code;
				}

				if ($fhcnation->kurztext === $moaddrnation || $fhcnation->langtext === $moaddrnation || $fhcnation->engltext === $moaddrnation)
				{
					$moaddr->{$adressemappings['nation']} = $fhcnation->nation_code;
				}

				if ($fhcnation->kurztext === $mozgvnation || $fhcnation->langtext === $mozgvnation || $fhcnation->engltext === $mozgvnation)
				{
					if (isset($moapp->{$prestudentmappings['zgvnation']}))
						$moapp->{$prestudentmappings['zgvnation']} = $fhcnation->nation_code;
				}

				if ($fhcnation->kurztext === $mozgvmanation || $fhcnation->langtext === $mozgvmanation || $fhcnation->engltext === $mozgvmanation)
				{
					if (isset($moapp->{$prestudentmappings['zgvmanation']}))
						$moapp->{$prestudentmappings['zgvmanation']} = $fhcnation->nation_code;
				}
			}
		}

		// Lichtbild
		if ($photo)
		{
			$moapp->{$aktemappings['inhalt']} = base64_encode($photo[0]->{$aktemappings['inhalt']});
		}

		// Studiengang
		/*$mostg = $moapp->{$fieldmappings['studiengang_kz']};
		$this->ci->load->model('organisation/Studiengang_model', 'StudiengangModel');

		$fhcstudiengaenge = $this->ci->StudiengangModel->load();

		if (hasData($fhcstudiengaenge))
		{
			foreach ($fhcstudiengaenge->retval as $fhcstg)
			{
				// try to get nation by text
				if ($fhcstg->bezeichnung === $mostg)
				{
					$moapp->{$fieldmappings['studiengang_kz']} = $fhcstg->studiengang_kz;
					break;
				}
			}
		}*/

		// Sprache
		/*		$mosprache = $person['sprache'];
				$this->ci->load->model('system/Sprache_model', 'SpracheModel');

				$fhcsprachen = $this->ci->SpracheModel->load();

				if (hasData($fhcsprachen))
				{
					foreach ($fhcsprachen->retval as $fhcsprache)
					{
						// try to get nation by text
						if ($fhcsprache->sprache === $mosprache)
						{
							break;
						}
					}
				}*/

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

		return $fhcobj;
	}

	/**
	 * Gets object for searching an application
	 * containing studiensemester and applicationtype, for which data is needed
	 * @param $studiensemester
	 * @param $applicationtype
	 * @return array
	 */
	public function getSearchAppObj($studiensemester = null, $applicationtype = null)
	{
		$appobj = array();

		$fields = $this->conffields['application'];

		foreach ($fields as $field)
		{
			$appobj[$field] = null;
		}

		$appobj['applicationType'] = $applicationtype;
		$appobj['semesterDescription'] = $studiensemester;
		$appobj['personType'] = 'S';

		return $appobj;
	}

	/**
	 * Saves an incoming (pre-)student, i.e. adds him or updates it if prestudent_id is set
	 * @param $incoming
	 * @param $prestudent_id
	 * @return null
	 */
	public function saveIncoming($incoming, $prestudent_id = null)
	{
		$this->ci->load->model('person/person_model', 'PersonModel');
		$this->ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->ci->load->model('person/adresse_model', 'AdresseModel');
		$this->ci->load->model('person/kontakt_model', 'KontaktModel');
		$this->ci->load->model('content/dms_model', 'DmsModel');
		$this->ci->load->model('crm/akte_model', 'AkteModel');
		$this->ci->load->model('crm/prestudent_model', 'PrestudentModel');
		$this->ci->load->model('crm/prestudentstatus_model', 'PrestudentstatusModel');
		$this->ci->load->model('crm/student_model', 'StudentModel');
		$this->ci->load->model('education/studentlehrverband_model', 'StudentlehrverbandModel');
		$this->ci->load->model('codex/bisio_model', 'BisioModel');

		//error check for missing data etc.
		$errors = $this->fhcObjHasError($incoming, 'application');

		if ($errors->error)
		{
			echo "<br />ERROR! ";
			foreach ($errors->errorMessages as $errorMessage)
			{
				echo "$errorMessage";
			}

			echo "<br />aborting incoming save";
		}

/*		$tables = array('person' => array(),
						'prestudent',
						'prestudentstatus' => array(''),
						'benutzer',
						'student',
						'studentlehrverband',
						'akte',
						'adresse',
						'kontaktmail',
						'kontaktnotfall',
						'bisio');

		foreach ($tables as $table)
		{
			if (!array_key_exists($table, $incoming))
			{
				echo "<br />incoming data missing: $table, aborting incoming save";
				return false;
			}
		}*/

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
						echo '<br />Lichtbild already exists, akte_id ' . $aktecheckresp->retval[0]->akte_id;
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

			$prestudent_id = isset($prestudentresponse->retval) ? $prestudentresponse->retval : null;
			if (isset($prestudent_id) && is_numeric($prestudent_id))
			{
				// prestudentstatus
				$prestudentstatus['prestudent_id'] = $prestudent_id;

				$studiensemarr = array($studiensemester);

				// add prestudentstatus for semester saved in MO
				$this->ci->load->model('organisation/studiensemester_model', 'StudiensemesterModel');
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
					$lastStatus = $this->ci->PrestudentstatusModel->getLastStatus($prestudent_id, $semester);
					if (isSuccess($lastStatus) && (!hasData($lastStatus) || $lastStatus->retval[0]->status_kurzbz !== 'Incoming'))
					{
						$prestudentstatus['studiensemester_kurzbz'] = $semester;
						$prestudentstatus['datum'] = date('Y-m-d', time());
						$this->_stamp('insert', $prestudentstatus);
						$prestudentstatusresponse = $this->ci->PrestudentstatusModel->insert($prestudentstatus);
						$this->_log('insert', $prestudentstatusresponse, 'prestudentstatus');
					}
				}

				/*				$this->ci->load->model('organisation/studienplan_model', 'StudienplanModel');

								$prestudentstatus['orgform_kurzbz'] = null;
								$prestudentstatus['studienplan_id'] = null;

								$studienplaene = $this->StudienplanModel->getStudienplaeneBySemester($prestudent['studiengang_kz'], $prestudentstatus['studiensemester_kurzbz'], $prestudentstatus['ausbildungssemester'], 'VZ');

								if (hasData($studienplaene))
								{
									$prestudentstatus['studienplan_id'] = $studienplaene->retval[0]->studienplan_id;
									$prestudentstatus['orgform_kurzbz'] = 'VZ';
								}
								else
								{
									$studienplaene = $this->StudienplanModel->getStudienplaeneBySemester($prestudent['studiengang_kz'], $prestudentstatus['studiensemester_kurzbz'], $prestudentstatus['ausbildungssemester'], 'BB');
									if (hasData($studienplaene))
									{
										$prestudentstatus['studienplan_id'] = $studienplaene->retval[0]->studienplan_id;
										$prestudentstatus['orgform_kurzbz'] = 'BB';
									}
								}*/
			}

			// benutzer
			$matrikelnr = $this->ci->StudentModel->generateMatrikelnummer($prestudent['studiengang_kz'], $studiensemester);
			$this->ci->StudentModel->addOrder('insertamum');
			$benutzerstudcheckresp = $this->ci->StudentModel->loadWhere(array('prestudent_id' => $prestudent_id));
			$benutzercheckresp = success('success');

			if (isSuccess($benutzerstudcheckresp))
			{
				if (hasData($benutzerstudcheckresp))
				{
					$benutzer['uid'] = $benutzerstudcheckresp->retval[0]->student_uid;
					echo "<br />benutzer for student $prestudent_id already exists, uid ". $benutzer['uid'];
				}
				else
				{
					$benutzer['person_id'] = $person_id;
					$jahr = mb_substr($matrikelnr, 0, 2);
					$stg = mb_substr($matrikelnr, 3, 4);

					$this->ci->load->model('organisation/studiengang_model', 'StudiengangModel');

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
							echo "<br />benutzer with uid ".$benutzer['uid']." already exists";
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
				&& isset($prestudent_id) && is_numeric($prestudent_id))
			{
				// student
				$student['student_uid'] = $benutzer['uid'];
				$student['prestudent_id'] = $prestudent_id;
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
			echo "rolling back...";
			$this->ci->db->trans_rollback();
			return null;
		}
		else
		{
			$this->ci->db->trans_commit();
			return $prestudent_id;
		}
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
				foreach ($fields as $field)
				{
					if (!isset($fhcobj[$table][$field]) || (!is_numeric($fhcobj[$table][$field]) && isEmptyString($fhcobj[$table][$field])))
					{
						$hasError->errorMessages[] = "$table: $field missing or has no match";
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
		if (isSuccess($response))
		{
			if (is_array($response->retval))
				$id = implode('; ', $response->retval);
			else
				$id = $response->retval;

			echo "<br />$table $modtype successfull, id ".$id;
		}
		else
		{
			echo "<br />$table $modtype error";
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
