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
				'index' => 'admin:rw',
				'getIncomingCoursesJson' => 'admin:r',
				'changeLehreinheitAssignment' => 'admin:rw'
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
	public function getIncomingCoursesJson()
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
	public function changeLehreinheitAssignment()
	{
		$json = success('Teaching units assignment changed successfully');

		$lehreinheitassignments = $this->input->post('lehreinheitassignments');

		$changed = false;

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
						$direktUserAddResult = $this->LehreinheitgruppeModel->direktUserAdd($lehreinheitassignment['uid'], $lehreinheitassignment['lehreinheit_id']);
						if (isSuccess($direktUserAddResult))
							$changed = true;
						else
							$json = $direktUserAddResult;
					}
				}
				elseif ($lehreinheitassignment['assigned'] === 'false')
				{
					if (hasData($grpassignment))
					{
						$direktUserDeleteResult = $this->LehreinheitgruppeModel->direktUserDelete($lehreinheitassignment['uid'], $lehreinheitassignment['lehreinheit_id']);
						if (isSuccess($direktUserDeleteResult))
							$changed = true;
						else
							$json = $direktUserDeleteResult;
					}
				}
			}
		}

		if (!$changed)
			$json = success('No teaching unit assignments changed');

		if (isSuccess($json))
		{
			$this->outputJsonSuccess($json->retval);
		}
		else
		{
			$this->outputJsonError($json->retval);
		}
	}

	/**
	 * Gets incomings with coursesfor a studiensemester
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
			$this->load->model('person/Kontakt_model', 'KontaktModel');

			foreach ($syncedIncomingIds->retval as $syncedIncomingId)
			{
				$this->PrestudentModel->addSelect('prestudent_id, person_id, vorname, nachname, uid');
				$this->PrestudentModel->addJoin('public.tbl_person', 'person_id');
				$this->PrestudentModel->addJoin('public.tbl_benutzer', 'person_id');
				$prestudent = $this->PrestudentModel->load($syncedIncomingId->prestudent_id);

				if (hasData($prestudent))
				{
					$prestudent = $prestudent->retval[0];

					$this->KontaktModel->addLimit(1);
					$kontakt = $this->KontaktModel->loadWhere(array('person_id' => $prestudent->person_id, 'kontakttyp' => 'email', 'zustellung' => true));
					if (hasData($kontakt))
					{
						$prestudentobj = new StdClass();

						$courses = $this->MoGetAppModel->getCoursesOfApplication($syncedIncomingId->mo_applicationid);

						$prestudentobj->lvs = array();
						$prestudentobj->nonMoLvs = array();

						foreach ($courses as $course)
						{
							$fhclv = $this->syncfrommobilityonlinelib->mapMoIncomingCourseToLv($course, $studiensemester, $prestudent->uid);
							if (isset($fhclv))
								$prestudentobj->lvs[] = $fhclv;
						}

						$additionalCourses = $this->LehrveranstaltungModel->getLvsByStudent($prestudent->uid, $studiensemester);

						foreach ($additionalCourses->retval as $additionalCourse)
						{
							$fhclv = array();

							$found = false;
							foreach ($prestudentobj->lvs as $molv)
							{
								if (isset($molv['lehrveranstaltung']['lehrveranstaltung_id'])
									&& $molv['lehrveranstaltung']['lehrveranstaltung_id'] === $additionalCourse->lehrveranstaltung_id)
								{
									$found = true;
									break;
								}
							}
							if (!$found)
							{
								$this->syncfrommobilityonlinelib->fillFhcCourse($additionalCourse->lehrveranstaltung_id, $prestudent->uid, $studiensemester, $fhclv);
								$prestudentobj->nonMoLvs[] = $fhclv;
							}
						}

						$prestudentobj->prestudent_id = $prestudent->prestudent_id;
						$prestudentobj->vorname = $prestudent->vorname;
						$prestudentobj->nachname = $prestudent->nachname;
						$prestudentobj->uid = $prestudent->uid;
						$prestudentobj->email = $kontakt->retval[0]->kontakt;
						$prestudentobj->studiensemester_kurzbz = $studiensemester;

						$prestudents[] = $prestudentobj;
					}
				}
			}
		}
		return $prestudents;
	}
}
