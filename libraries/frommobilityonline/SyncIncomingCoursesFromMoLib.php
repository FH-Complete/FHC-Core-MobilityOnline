<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for assigning courses to incomings (incoming courses) coming from MobilityOnline in fhcomplete
 */
class SyncIncomingCoursesFromMoLib extends SyncFromMobilityOnlineLib
{
	public function __construct()
	{
		parent::__construct();

		$this->moObjectType = 'incomingcourse';

		// load models
		$this->ci->load->model('crm/prestudentstatus_model', 'PrestudentstatusModel');
		$this->ci->load->model('education/lehrveranstaltung_model', 'LehrveranstaltungModel');
		$this->ci->load->model('education/lehreinheit_model', 'LehreinheitModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MoFhcModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetmasterdata_model', 'MoGetMaModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Molvidzuordnung_model', 'MolvidzuordnungModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Moappidzuordnung_model', 'MoappidzuordnungModel');

		// load libraries
		$this->ci->load->library('extensions/FHC-Core-MobilityOnline/tomobilityonline/ToMobilityOnlineDataConversionLib');
	}

	/**
	 * Gets incomings with courses for a studiensemester.
	 * @param string $studiensemester
	 * @param string $studiengang_kz
	 * @return array with prestudents
	 */
	public function getIncomingWithCourses($studiensemester, $studiengang_kz = null)
	{
		$prestudents = array();

		// get already synced students by loading ids from synctable
		$whereParams = array('tbl_prestudentstatus.studiensemester_kurzbz' => $studiensemester);

		if (isset($studiengang_kz)) $whereParams['tbl_prestudent.studiengang_kz'] = $studiengang_kz;
		$this->ci->MoappidzuordnungModel->addDistinct();
		$this->ci->MoappidzuordnungModel->addSelect(
			'tbl_mo_appidzuordnung.prestudent_id, tbl_mo_appidzuordnung.mo_applicationid, tbl_mo_appidzuordnung.studiensemester_kurzbz'
		);
		$this->ci->MoappidzuordnungModel->addJoin('public.tbl_prestudent', 'prestudent_id');
		$this->ci->MoappidzuordnungModel->addJoin('public.tbl_prestudentstatus', 'prestudent_id');
		$syncedIncomingIds = $this->ci->MoappidzuordnungModel->loadWhere($whereParams);

		if (hasData($syncedIncomingIds))
		{
			foreach (getData($syncedIncomingIds) as $syncedIncomingId)
			{
				$prestudent = $this->ci->MoFhcModel->getIncomingPrestudent($syncedIncomingId->prestudent_id, $studiengang_kz);

				if (hasData($prestudent))
				{
					$prestudentObj = getData($prestudent);

					// if semester is not the one in MobilityOnline, check semesters based on stay duration
					if ($studiensemester !== $syncedIncomingId->studiensemester_kurzbz)
					{
						$prestudentStatus = $this->ci->PrestudentstatusModel->load(array('prestudent_id' => $syncedIncomingId->prestudent_id));

						$semFound = false;

						if (hasData($prestudentStatus))
						{
							foreach (getData($prestudentStatus) as $status)
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

					$prestudentObj->lvs = array();
					$prestudentObj->nonMoLvs = array();

					if (hasData($courses))
					{
						$coursesData = getData($courses);
						$prestudentObj->anzahlMoLvs = count($coursesData);

						foreach ($coursesData as $course)
						{
							$fhcCourse = $this->convertToFhcFormat($course, $this->moObjectType);

							$lvidzuordnung = $this->ci->MolvidzuordnungModel->loadWhere(
								array(
									'studiensemester_kurzbz' => $studiensemester,
									'lehrveranstaltung_id' => $fhcCourse['lehrveranstaltung']['lehrveranstaltung_id_mo']
								)
							);

							if (hasData($lvidzuordnung))
							{
								$this->fillFhcCourse(
									$fhcCourse['lehrveranstaltung']['lehrveranstaltung_id_mo'],
									$prestudentObj->uid,
									$studiensemester,
									$fhcCourse
								);
								$this->fillFhcCourseWithAssignmentInformation(
									$fhcCourse['lehrveranstaltung']['lehrveranstaltung_id'],
									$prestudentObj->uid,
									$studiensemester,
									$fhcCourse
								);
							}
							if (!$course->deleted && isset($fhcCourse))
								$prestudentObj->lvs[] = $fhcCourse;
						}
					}

					$additionalCourses = $this->ci->LehrveranstaltungModel->getLvsByStudent($prestudentObj->uid, $studiensemester);

					//additional courses in fhcomplete, but not in MobilityOnline
					if (hasData($additionalCourses))
					{
						foreach (getData($additionalCourses) as $additionalCourse)
						{
							$fhcCourse = array();

							$found = false;
							foreach ($prestudentObj->lvs as $molv)
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
								$this->fillFhcCourse($additionalCourse->lehrveranstaltung_id, $prestudentObj->uid, $studiensemester, $fhcCourse);
								$this->fillFhcCourseWithAssignmentInformation(
									$additionalCourse->lehrveranstaltung_id,
									$prestudentObj->uid,
									$studiensemester,
									$fhcCourse
								);
								$prestudentObj->nonMoLvs[] = $fhcCourse;
							}
						}
					}

					//sort courses alphabetically
					usort($prestudentObj->lvs, array($this, '_cmpCourses'));
					usort($prestudentObj->nonMoLvs, array($this, '_cmpCourses'));

					$prestudents[] = $prestudentObj;
				}
			}
		}
		return $prestudents;
	}

	/**
	 * Gets all courses of an incoming
	 * @param lv_kuerzel to identify
	 * @return object success or error
	 */
	public function getCoursesForIncoming($lv_kuerzel, $studiensemester, $uid)
	{
		$studiensemestermo = $this->ci->tomobilityonlinedataconversionlib->mapSemesterToMo($studiensemester);

		$courses = array('moLvs' => array(), 'nonMoLvs' => array());

		if (is_array($lv_kuerzel))
		{
			foreach ($lv_kuerzel as $kuerzel)
			{
				$fhcCourse = null;

				$searchparams = array('semesterDescription' => $studiensemestermo, 'applicationType' => 'IN', 'courseNumber' => $kuerzel);

				$searchobj = $this->getSearchObj(
					'course',
					$searchparams,
					false
				);

				// search for course to get courseID
				$mocourses = $this->ci->MoGetMaModel->getCoursesOfSemesterBySearchParameters($searchobj);

				if (hasData($mocourses))
				{
					$mocoursesData = getData($mocourses);
					foreach ($mocoursesData as $mocourse)
					{
						$fhcCourse = $this->convertToFhcFormat($mocourse, 'incomingcourseSearched');
						$mocourseid = $mocourse->courseID;

						$lvidzuordnung = $this->ci->MolvidzuordnungModel->loadWhere(
							array(
								'studiensemester_kurzbz' => $studiensemester,
								'mo_lvid' => $mocourseid
							)
						);

						if (hasData($lvidzuordnung))
						{
							$this->fillFhcCourse(getData($lvidzuordnung)[0]->lehrveranstaltung_id, $uid, $studiensemester, $fhcCourse);
							$this->fillFhcCourseWithLehreinheitData(getData($lvidzuordnung)[0]->lehrveranstaltung_id, $uid, $studiensemester, $fhcCourse);
						}
					}
				}

				if (isset($fhcCourse))  $courses['moLvs'][] = $fhcCourse;
			}
		}

		$additionalCourses = $this->ci->LehrveranstaltungModel->getLvsByStudent($uid, $studiensemester);

		//additional courses in fhcomplete, but not in MobilityOnline
		if (hasData($additionalCourses))
		{
			foreach (getData($additionalCourses) as $additionalCourse)
			{
				$fhcCourse = array();

				$found = false;
				foreach ($courses['moLvs'] as $molv)
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
					$this->fillFhcCourse($additionalCourse->lehrveranstaltung_id, $uid, $studiensemester, $fhcCourse);
					$this->fillFhcCourseWithLehreinheitData($additionalCourse->lehrveranstaltung_id, $uid, $studiensemester, $fhcCourse);
					$courses['nonMoLvs'][] = $fhcCourse;
				}
			}
		}

		return $courses;
	}

	/**
	 * Fills fhccourse with necessary data before displaying, adds Lehreinheiten to the course.
	 * @param int $lehrveranstaltung_id
	 * @param string $uid for getting group assignments or lehreinheiten
	 * @param string $studiensemester_kurzbz
	 * @param array $fhcCourse to be filled
	 */
	public function fillFhcCourse($lehrveranstaltung_id, $uid, $studiensemester_kurzbz, &$fhcCourse)
	{
		$this->ci->LehrveranstaltungModel->addSelect('lehrveranstaltung_id, tbl_lehrveranstaltung.bezeichnung AS lvbezeichnung, incoming');

		$lvResult = $this->ci->LehrveranstaltungModel->loadWhere(
			array(
				'tbl_lehrveranstaltung.lehrveranstaltung_id' => $lehrveranstaltung_id
			)
		);

		if (hasData($lvResult))
		{
			$lv = getData($lvResult)[0];
			$fhcCourse['lehrveranstaltung']['lehrveranstaltung_id'] = $lv->lehrveranstaltung_id;
			$fhcCourse['lehrveranstaltung']['fhcbezeichnung'] = $lv->lvbezeichnung;
			$fhcCourse['lehrveranstaltung']['incomingplaetze'] = $lv->incoming;

			//get studiengäng(e) and semester for LV
			$this->ci->LehrveranstaltungModel->addSelect('tbl_studiengang.studiengang_kz, tbl_studiengang.typ, tbl_studiengang.kurzbz AS studiengang_kurzbz, tbl_studiengang.bezeichnung, tbl_studienplan_lehrveranstaltung.semester');
			$this->ci->LehrveranstaltungModel->addJoin('lehre.tbl_studienplan_lehrveranstaltung', 'lehrveranstaltung_id', 'LEFT');
			$this->ci->LehrveranstaltungModel->addJoin('lehre.tbl_studienplan', 'studienplan_id', 'LEFT');
			$this->ci->LehrveranstaltungModel->addJoin('lehre.tbl_studienplan_semester', 'studienplan_id', 'LEFT');
			$this->ci->LehrveranstaltungModel->addJoin('lehre.tbl_studienordnung', 'studienordnung_id', 'LEFT');
			$this->ci->LehrveranstaltungModel->addJoin('public.tbl_studiengang', 'tbl_studienordnung.studiengang_kz = tbl_studiengang.studiengang_kz', 'LEFT');

			$lvDataResult = $this->ci->LehrveranstaltungModel->loadWhere(
				array(
					'tbl_lehrveranstaltung.lehrveranstaltung_id' => $lehrveranstaltung_id,
					'tbl_studienplan_semester.studiensemester_kurzbz' => $studiensemester_kurzbz
				)
			);

			$fhcCourse['studiengaenge'] = array();
			$fhcCourse['ausbildungssemester'] = array();

			if (hasData($lvDataResult))
			{
				foreach (getData($lvDataResult) as $lvData)
				{
					$found = false;
					foreach ($fhcCourse['studiengaenge'] as $studiengangObj)
					{
						if ($studiengangObj->studiengang_kz == $lvData->studiengang_kz)
						{
							$found = true;
							break;
						}
					}

					if (!$found)
					{
						$studiengang = new StdClass();
						$studiengang->studiengang_kz = $lvData->studiengang_kz;
						$studiengang->kuerzel = mb_strtoupper($lvData->typ . $lvData->studiengang_kurzbz);
						$studiengang->bezeichnung = $lvData->bezeichnung;
						$fhcCourse['studiengaenge'][] = $studiengang;
					}

					$found = false;
					foreach ($fhcCourse['ausbildungssemester'] as $ausbildungsSemObj)
					{
						if ($ausbildungsSemObj == $lvData->semester)
						{
							$found = true;
							break;
						}
					}

					if (!$found)
					{
						$fhcCourse['ausbildungssemester'][] = $lvData->semester;
					}
				}
			}
		}
	}

	/**
	 * Fills fhcomplete course with course assignment information (wether incoming course is already assigned in fhc).
	 * @param $lehrveranstaltung_id
	 * @param $$uid of student
	 * @param $studiensemester_kurzbz
	 * @param $fhcCourse to be filled with information
	 */
	public function fillFhcCourseWithAssignmentInformation($lehrveranstaltung_id, $uid, $studiensemester_kurzbz, &$fhcCourse)
	{
		//get Lehreinheiten, number of students, directly assigned for Lv
		if (isset($lehrveranstaltung_id) && is_numeric($lehrveranstaltung_id))
		{
			$this->ci->LehreinheitModel->addSelect('lehreinheit_id');
			$les = $this->ci->LehreinheitModel->loadWhere(
				array(
					'lehrveranstaltung_id' => $lehrveranstaltung_id,
					'studiensemester_kurzbz' => $studiensemester_kurzbz
				)
			);

			if (!hasData($les)) return;

			$fhcCourse['lehreinheiten'] = getData($les);

			$incoming_prestudent_ids = array();

			foreach ($fhcCourse['lehreinheiten'] as $lehreinheit)
			{
				$lehreinheit->directlyAssigned = false;

				$directlyAssigned = $this->ci->LehreinheitgruppeModel->getDirectGroupAssignment($uid, $lehreinheit->lehreinheit_id);

				if (hasData($directlyAssigned))
					$lehreinheit->directlyAssigned = true;
			}
		}
	}

	/**
	 * Fills a course array with Lehreinheit data.
	 * @param $lehrveranstaltung_id in fh complete
	 * @param $uid for group assignment
	 * @param $studiensemester_kurzbz
	 * @param $fhcCourse course to be filled
	 */
	public function fillFhcCourseWithLehreinheitData($lehrveranstaltung_id, $uid, $studiensemester_kurzbz, &$fhcCourse)
	{
		//get Lehreinheiten, number of students, directly assigned for Lv
		if (isset($lehrveranstaltung_id) && is_numeric($lehrveranstaltung_id))
		{
			$fhcCourse['lehreinheiten'] = $this->ci->LehreinheitModel->getLesForLv(
				$lehrveranstaltung_id,
				$studiensemester_kurzbz
				//false
			);

			$anz_incomings = 0;

			$incoming_prestudent_ids = array();

			foreach ($fhcCourse['lehreinheiten'] as $lehreinheit)
			{
				$lehreinheit->directlyAssigned = false;

				$students = $this->ci->LehreinheitModel->getStudenten($lehreinheit->lehreinheit_id);

				$anz_teilnehmer = 0;

				if (hasData($students))
				{
					$anz_teilnehmer = count(getData($students));

					foreach (getData($students) as $student)
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

				$directlyAssigned = $this->ci->LehreinheitgruppeModel->getDirectGroupAssignment($uid, $lehreinheit->lehreinheit_id);

				if (hasData($directlyAssigned))
					$lehreinheit->directlyAssigned = true;
			}

			$fhcCourse['lehrveranstaltung']['anz_incomings'] = $anz_incomings;
		}
	}

	/**
	 * Compares two courses by Bezeichnung in MobilityOnline for sort.
	 * @param array $a frist course
	 * @param array $b second course
	 * @return int 1 if $a comes after $b, 0 when equal, -1 otherwiese
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
			$aMoBez = strtolower($a['lehrveranstaltung']['mobezeichnung']);
			$bMoBez = strtolower($b['lehrveranstaltung']['mobezeichnung']);

			if ($aMoBez == $bMoBez)
				return 0;

			return $aMoBez > $bMoBez ? 1 : -1;
		}
	}
}
