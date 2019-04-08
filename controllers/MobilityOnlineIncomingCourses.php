<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages assignment of MobilityOnline courses as Lehreinheiten in fhcomplete
 */
class MobilityOnlineIncomingCourses extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'inout/incoming:rw',
				'getIncomingWithCoursesJson' => 'inout/incoming:r',
				'updateLehreinheitAssignment' => 'inout/incoming:rw',
				'getCourseAssignments' => 'inout/incoming:r'
			)
		);

		$this->config->load('extensions/FHC-Core-MobilityOnline/config');

		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->model('crm/Prestudent_model', 'PrestudentModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MoFhcModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/mappings/Moappidzuordnung_model', 'MoappidzuordnungModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineSyncLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/SyncFromMobilityOnlineLib');
	}

	/**
	 * Index Controller
	 * @return void
	 */
	public function index()
	{
		$this->load->library('WidgetLib');

		$this->StudiensemesterModel->addOrder('start', 'DESC');
		$studiensemesterdata = $this->StudiensemesterModel->load();

		if (isError($studiensemesterdata))
			show_error($studiensemesterdata->retval);

		$currsemdata = $this->StudiensemesterModel->getLastOrAktSemester(0);

		if (isError($currsemdata))
			show_error($currsemdata->retval);

		$prestudents = array();

		if (hasData($currsemdata))
		{
			$prestudents = $this->_getIncomingWithCourses($currsemdata->retval[0]->studiensemester_kurzbz);
		}

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnlineIncomingCourses',
			array(
				'semester' => $studiensemesterdata->retval,
				'currsemester' => $currsemdata->retval,
				'prestudents' => $prestudents
			)
		);
	}

	/**
	 * Gets incomings for a studiensemester and outputs json
	 */
	public function getIncomingWithCoursesJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$incomingdata = $this->_getIncomingWithCourses($studiensemester);

		$this->outputJsonSuccess($incomingdata);
	}

	/**
	 * Changes assignment of multiple students to a Lehreinheit.
	 * Expects array with uid, lehreinheit_id and boolean value (assigned/not assigned) as POST parameter.
	 * Adds and deletes Lehreinheit assignments, where necessary.
	 */
	public function updateLehreinheitAssignment()
	{
		$json = success('Teaching units assignment changed successfully');

		$lehreinheitassignments = $this->input->post('lehreinheitassignments');

		$errors = '';
		$hasError = $changed = false;

		foreach ($lehreinheitassignments as $lehreinheitassignment)
		{
			$grpassignment = $this->LehreinheitgruppeModel->getDirectGroupAssignment(
				$lehreinheitassignment['uid'],
				$lehreinheitassignment['lehreinheit_id']
			);

			if (isSuccess($grpassignment))
			{
				if ($lehreinheitassignment['assigned'] === 'true')
				{
					if (!hasData($grpassignment))
					{
						$changed = true;
						$direktUserAddResult = $this->LehreinheitgruppeModel->direktUserAdd($lehreinheitassignment['uid'], $lehreinheitassignment['lehreinheit_id']);
						if (isError($direktUserAddResult))
						{
							$hasError = true;
							if (!isEmptyString($errors))
								$errors .= '; ';
							$errors .= $direktUserAddResult->retval;
						}
					}
				}
				elseif ($lehreinheitassignment['assigned'] === 'false')
				{
					if (hasData($grpassignment))
					{
						$changed = true;
						$direktUserDeleteResult = $this->LehreinheitgruppeModel->direktUserDelete($lehreinheitassignment['uid'], $lehreinheitassignment['lehreinheit_id']);
						if (isError($direktUserDeleteResult))
						{
							$hasError = true;
							if (!isEmptyString($errors))
								$errors .= '; ';
							$errors .= $direktUserDeleteResult->retval;
						}
					}
				}
			}
		}

		if (!$changed)
			$this->outputJsonSuccess('No teaching unit assignments changed');
		elseif (isSuccess($json) && !$hasError)
		{
			$this->outputJsonSuccess($json->retval);
		}
		else
		{
			$this->outputJsonError($errors);
		}
	}

	/**
	 * Gets direct course assignments for a user and an array of lehreinheitids.
	 * Returns array with lvids and their directly assigned leids
	 */
	public function getCourseAssignments()
	{
		$ledata = $this->input->post('ledata');
		$uid = $this->input->post('uid');

		$result = array();

		if (isset($ledata) && is_array($ledata))
		{
			foreach ($ledata as $le)
			{
				$lv = key($le);
				$le = $le[$lv];
				$groupAssignments = $this->LehreinheitgruppeModel->getDirectGroupAssignment($uid, $le);

				if (!isset($result[$lv]))
					$result[$lv] = array();

				if (hasData($groupAssignments))
				{
					$result[$lv][] = $le;
				}
			}
		}

		$this->outputJsonSuccess($result);
	}

	/**
	 * Gets incomings with courses for a studiensemester
	 * @param $studiensemester
	 * @return array with prestudents
	 */
	private function _getIncomingWithCourses($studiensemester)
	{
		$prestudents = array();

		$this->MoappidzuordnungModel->addSelect('prestudent_id, mo_applicationid');
		$syncedIncomingIds = $this->MoappidzuordnungModel->loadWhere(array('studiensemester_kurzbz' => $studiensemester));

		if (hasData($syncedIncomingIds))
		{
			foreach ($syncedIncomingIds->retval as $syncedIncomingId)
			{
				$prestudent = $this->MoFhcModel->getIncomingPrestudent($syncedIncomingId->prestudent_id);

				if (hasData($prestudent))
				{
					$prestudentobj = $prestudent->retval;

					$courses = $this->MoGetAppModel->getCoursesOfApplication($syncedIncomingId->mo_applicationid);

					$prestudentobj->lvs = array();
					$prestudentobj->nonMoLvs = array();

					if (isset($courses) && is_array($courses))
					{
						foreach ($courses as $course)
						{
							$fhclv = $this->syncfrommobilityonlinelib->mapMoIncomingCourseToLv($course, $studiensemester, $prestudentobj->uid);

							if (!$course->deleted && isset($fhclv))
								$prestudentobj->lvs[] = $fhclv;
						}
					}

					$additionalCourses = $this->LehrveranstaltungModel->getLvsByStudent($prestudentobj->uid, $studiensemester);

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
								$this->syncfrommobilityonlinelib->fillFhcCourse($additionalCourse->lehrveranstaltung_id, $prestudentobj->uid, $studiensemester, $fhclv);
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
