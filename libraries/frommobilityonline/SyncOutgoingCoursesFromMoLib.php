<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for syncing outgoing courses from MobilityOnline to fhcomplete
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
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobisioidzuordnung_model', 'MobisioidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mooutgoinglv_model', 'MoOutgoingLvModel');

		$this->ci->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncOutgoingsFromMoLib');
	}

	/**
	 * Executes sync of outgoing courses for a Studiensemester from MO to FHC. Adds or updates outgoing courses.
	 * @param array $outgoingCourses
	 * @return array syncoutput containing info about failures/success
	 */
	public function startOutgoingCoursesSync($outgoingCourses)
	{
		$results = array('added' => array(), 'updated' => array(), 'errors' => 0, 'syncoutput' => array());

		if (!is_array($outgoingCourses) || isEmptyArray($outgoingCourses))
		{
			$this->addInfoOutput('Keine Outgoing Kurse für Sync gefunden! Abbruch.');
		}
		else
		{
			foreach ($outgoingCourses as $outgoingCourse)
			{
				$mo_outgoing_lv = $outgoingCourse['mo_outgoing_lv'];
				$mo_lvid = $mo_outgoing_lv['mo_lvid'];
				$bisio_id = $mo_outgoing_lv['bisio_id'];

				$infhccheck_mo_lvid = $this->_checkOutgoingCourseInFhc($mo_lvid, $bisio_id);

				$outgoing_course_id = $this->saveOutgoingCourse($outgoingCourse);

				if (isSuccess($outgoing_course_id))
				{
					if (hasData($infhccheck_mo_lvid))
					{
						$results['updated'][] = $mo_lvid;
						$actionText = 'aktualisiert';
					}
					else
					{
						$results['added'][] = $mo_lvid;
						$actionText = 'hinzugefügt';
					}

					$this->addSuccessOutput("Kurs mit Id $mo_lvid - " . $mo_outgoing_lv['lv_bez_gast'] . " erfolgreich $actionText");
				}
				else
				{
					$results['errors']++;
					$this->addErrorOutput("Fehler beim Synchronisieren des Kurses mit Id $mo_lvid - " .
						$mo_outgoing_lv['lv_bez_gast']);
				}
			}
		}

		$results['syncoutput'] = $this->getOutput();
		return $results;
	}

	/**
	 * Gets MobilityOnline outgoings for a fhcomplete studiensemester.
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

			$fhcobj_extended = new StdClass();
			$fhcobj_extended->moid = $appId;

			// TODO: take course from transcript or learning agreement?
			//$coursesData = $this->ci->MoGetAppModel->getCoursesOfApplication($appId);
			$coursesData = $this->ci->MoGetAppModel->getCoursesOfApplicationTranscript($appId);

			if (isError($coursesData))
			{
				$fhcobj_extended->error = true;
				$fhcobj_extended->errorMessages[] = 'Fehler beim Holen der Kursdaten';
			}
			$coursesData = getData($coursesData);

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
			for ($i = 0; $i < count($fhcobj['kurse']); $i++)
			{
				$kurs = $fhcobj['kurse'][$i];
				$hasErrorObj = $this->fhcObjHasError($kurs, $this->moObjectType);

				if ($hasErrorObj->error)
				{
					$fhcobj['kurse'][$i]['kursinfo']['error'] = true;
					$fhcobj['kurse'][$i]['kursinfo']['errorMessages'] = $hasErrorObj->errorMessages;
				}

				if (!isset($kurs['kursinfo']['infhc']) || $kurs['kursinfo']['infhc'] == false)
				{
					$coursesInFhc = false;
				}
			}

			// check if courses already in fhc
			$coursesInFhc = true;

			// mark as already in fhcomplete if courses synced
			if (hasData($found_bisio_id) && $coursesInFhc)
			{
				$fhcobj_extended->infhc = true;
			}

			$fhcobj_extended->data = $fhcobj;
			$outgoingCourses[] = $fhcobj_extended;
		}

		return $outgoingCourses;
	}

	/**
	 * Converts MobilityOnline course to fhcomplete array (with person, prestudent...)
	 * @param object $moApp MobilityOnline application
	 * @return array with fhcomplete table arrays
	 */
	public function mapMoCourseToOutgoingLv($moApp, $coursesData, $bisio_id)
	{
		$fieldMappings = $this->conffieldmappings[$this->moObjectType];

		$moAppElementsExtracted = $moApp;

		// retrieve correct value from MO for each fieldmapping
		foreach ($fieldMappings as $fhcTable)
		{
			foreach ($fhcTable as $elementName)
			{
				$valueType = 'elementValue';

				$found = false;
				$appDataValue = $this->getApplicationDataElement($moApp, $valueType, $elementName, $found);

				if ($found === true)
					$moAppElementsExtracted->$elementName = $appDataValue;
			}
		}

		// remove original applicationDataElements
		unset($moAppElementsExtracted->applicationDataElements);

		$fhcObj = $this->convertToFhcFormat($moAppElementsExtracted, self::MO_OBJECT_APPLICATION_TYPE);

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

			$fhcCourses[$i]['kursinfo']['infhc'] = hasData($checkRes);

			// add bisio_id
			$fhcCourses[$i]['mo_outgoing_lv']['bisio_id'] = $bisio_id;
		}

		$fhcObj = array_merge($fhcObj, array('kurse' => $fhcCourses));

		return $fhcObj;
	}

	/**
	 * Saves (inserts or updates) an outgoing course.
	 * @param int $appId
	 * @param array $outgoing
	 * @param int $bisio_id_existing if bisio id if bisio already exists
	 * @return string prestudent_id of saved prestudent
	 */
	public function saveOutgoingCourse($outgoingCourse)
	{
		//error check for missing data etc.
		$errors = $this->fhcObjHasError($outgoingCourse, $this->moObjectType);

		if ($errors->error)
		{
			foreach ($errors->errorMessages as $errorMessage)
			{
				$this->addErrorOutput($errorMessage);
			}

			$this->addErrorOutput("Abbruch der Outgoing Kursspeicherung");
			return null;
		}

		$mo_outgoing_lv = $outgoingCourse['mo_outgoing_lv'];
		$mo_lvid = $mo_outgoing_lv['mo_lvid'];
		$bisio_id = $mo_outgoing_lv['bisio_id'];

		// Start DB transaction
		//$this->ci->db->trans_begin();

		// check if outgoing course is already in fhcomplete
		$checkRes = $this->_checkOutgoingCourseInFhc($mo_lvid, $bisio_id);

		if (isSuccess($checkRes))
		{
			// if in fhc, update
			if (hasData($checkRes))
			{
				// unset id for update
				unset($mo_outgoing_lv['mo_lvid']);
				$this->stamp('update', $mo_outgoing_lv);
				return $this->ci->MoOutgoingLvModel->update(array('mo_lvid' => $mo_lvid), $mo_outgoing_lv);
			}
			else
			{
				// otherwise insert
				$this->stamp('insert', $mo_outgoing_lv);
				return $this->ci->MoOutgoingLvModel->insert($mo_outgoing_lv);
			}
		}

		return null;


		// Transaction complete!
		//$this->ci->db->trans_complete();

		// Check if everything went ok during the transaction
		//~ if ($this->ci->db->trans_status() === false)
		//~ {
			//~ $this->addInfoOutput("rolling back...");
			//~ $this->ci->db->trans_rollback();
			//~ return null;
		//~ }
		//~ else
		//~ {
			//~ $this->ci->db->trans_commit();
			//~ return $bisio['student_uid'];
		//~ }
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Private methods for saving an outgoing

	/**
	 * Checks if an outgoing course in already in fhcomplete.
	 * @param int $mo_lvid
	 * @param int $bisio_id
	 */
	private function _checkOutgoingCourseInFhc($mo_lvid, $bisio_id)
	{
		$this->ci->MoOutgoingLvModel->addSelect('outgoing_lehrveranstaltung_id');
		$outgoingLvRes = $this->ci->MoOutgoingLvModel->loadWhere(array('mo_lvid' => $mo_lvid, 'bisio_id' => $bisio_id));

		if (isError($outgoingLvRes))
			return $outgoingLvRes;

		$outgoing_lehrveranstaltung_id = null;

		if (hasData($outgoingLvRes))
		{
			$outgoing_lehrveranstaltung_id = getData($outgoingLvRes)[0]->outgoing_lehrveranstaltung_id;
		}

		return success($outgoing_lehrveranstaltung_id);
	}
}
