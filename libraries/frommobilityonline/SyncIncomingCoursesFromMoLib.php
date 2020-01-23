<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for assigning courses to incomings (incoming courses) coming from MobilityOnline in fhcomplete
 */
class SyncIncomingCoursesFromMoLib extends SyncFromMobilityOnlineLib
{
	const MOOBJECTTYPE = 'incomingcourse';

	public function __construct()
	{
		parent::__construct();

		//$this->load->model('crm/Prestudent_model', 'PrestudentModel');
		$this->ci->load->model('crm/prestudentstatus_model', 'PrestudentstatusModel');
		$this->ci->load->model('education/lehrveranstaltung_model', 'LehrveranstaltungModel');
		$this->ci->load->model('education/lehreinheit_model', 'LehreinheitModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MoFhcModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetmasterdata_model', 'MoGetMaModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Molvidzuordnung_model', 'MolvidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Moappidzuordnung_model', 'MoappidzuordnungModel');
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

		$fhccourse = $this->convertToFhcFormat($course, self::MOOBJECTTYPE);

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
	 * Gets incomings with courses for a studiensemester
	 * @param $studiensemester
	 * @return array with prestudents
	 */
	public function getIncomingWithCourses($studiensemester, $studiengang_kz = null)
	{
		$prestudents = array();

		$syncedIncomingIds = $this->ci->MoappidzuordnungModel->load();

		if (hasData($syncedIncomingIds))
		{
			foreach ($syncedIncomingIds->retval as $syncedIncomingId)
			{
				$prestudent = $this->ci->MoFhcModel->getIncomingPrestudent($syncedIncomingId->prestudent_id, $studiengang_kz);

				if (hasData($prestudent))
				{
					$prestudentobj = $prestudent->retval;

					// if semester is not the one in MobilityOnline, check semesters based on stay duration
					if ($studiensemester !== $syncedIncomingId->studiensemester_kurzbz)
					{
						$prestudentstati = $this->ci->PrestudentstatusModel->load(array('prestudent_id' => $syncedIncomingId->prestudent_id));

						$semFound = false;

						if (hasData($prestudentstati))
						{
							foreach (getData($prestudentstati) as $status)
							{
								if ($status->studiensemester_kurzbz === $studiensemester)
								{
									$semFound = true;
									break;
								}
							}
						}

						if (!$semFound)
							continue;
					}

					$courses = $this->ci->MoGetAppModel->getCoursesOfApplication($syncedIncomingId->mo_applicationid);

					$prestudentobj->lvs = array();
					$prestudentobj->nonMoLvs = array();

					if (isset($courses) && is_array($courses))
					{
						foreach ($courses as $course)
						{
							$fhclv = $this->mapMoIncomingCourseToLv($course, $studiensemester, $prestudentobj->uid);

							if (!$course->deleted && isset($fhclv))
								$prestudentobj->lvs[] = $fhclv;
						}
					}

					$additionalCourses = $this->ci->LehrveranstaltungModel->getLvsByStudent($prestudentobj->uid, $studiensemester);

					//additional courses in fhcomplete, but not in MobilityOnline
					if (hasData($additionalCourses))
					{
						foreach ($additionalCourses->retval as $additionalCourse)
						{
							$fhclv = array();

							$found = false;
							foreach ($prestudentobj->lvs as $molv)
							{
								if (isset($molv['lehrveranstaltung']['lehrveranstaltung_id'])
									&& $molv['lehrveranstaltung']['lehrveranstaltung_id'] === $additionalCourse->lehrveranstaltung_id
								)
								{
									$found = true;
									break;
								}
							}
							if (!$found)
							{
								$this->fillFhcCourse($additionalCourse->lehrveranstaltung_id, $prestudentobj->uid, $studiensemester, $fhclv);
								$prestudentobj->nonMoLvs[] = $fhclv;
							}
						}
					}

					//sort courses alphabetically
					usort($prestudentobj->lvs, array($this, '_cmpCourses'));
					usort($prestudentobj->nonMoLvs, array($this, '_cmpCourses'));

					$prestudents[] = $prestudentobj;
				}
			}
		}
		return $prestudents;
	}

	/**
	 * Compares two courses by Bezeichnung in MobilityOnline for sort
	 * @param $a
	 * @param $b
	 * @return int
	 */
	private function _cmpCourses($a, $b)
	{
		if (!isset($a['lehrveranstaltung']['mobezeichnung']) && !isset($b['lehrveranstaltung']['mobezeichnung']))
		{
			return 0;
		}
		elseif (!isset($a['lehrveranstaltung']['mobezeichnung']))
		{
			return -1;
		}
		elseif (!isset($b['lehrveranstaltung']['mobezeichnung']))
		{
			return 1;
		}
		else
		{
			$amobez = strtolower($a['lehrveranstaltung']['mobezeichnung']);
			$bmobez = strtolower($b['lehrveranstaltung']['mobezeichnung']);

			if ($amobez == $bmobez)
				return 0;

			return $amobez > $bmobez ? 1 : -1;
		}
	}
}
