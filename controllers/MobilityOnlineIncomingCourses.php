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
				'getFhcCourses' => 'inout/incoming:r'
			)
		);

		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineSyncLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncFromMobilityOnlineLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/frommobilityonline/SyncIncomingCoursesFromMoLib');
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
			show_error(getError($studiensemesterdata));

		$currsemdata = $this->StudiensemesterModel->getAktOrNextSemester();

		if (isError($currsemdata))
			show_error(getError($currsemdata));

		$studiengaenge = $this->MoFhcModel->getStudiengaenge();

		if (isError($studiengaenge))
			show_error(getError($studiengaenge));

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnlineIncomingCourses',
			array(
				'semester' => getData($studiensemesterdata),
				'currsemester' => getData($currsemdata),
				'studiengaenge' => getData($studiengaenge)
			)
		);
	}

	/**
	 * Gets incomings for a studiensemester and outputs json
	 */
	public function getIncomingWithCoursesJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$studiengang_kz = $this->input->get('studiengang_kz');
		$incomingdata = $this->syncincomingcoursesfrommolib->getIncomingWithCourses($studiensemester, $studiengang_kz);

		$this->outputJsonSuccess($incomingdata);
	}

	/**
	 * Changes assignment of multiple students to a Lehreinheit.
	 * Expects array with uid, lehreinheit_id and boolean value (assigned/not assigned) as POST parameter.
	 * Adds and deletes Lehreinheit assignments, where necessary.
	 */
	public function updateLehreinheitAssignment()
	{
		$json = success('Änderung der Lehreinheitszuweisung erfolgreich durchgeführt');

		$lehreinheitassignments = $this->input->post('lehreinheitassignments');

		$errors = '';
		$hasError = $changed = false;

		foreach ($lehreinheitassignments as $lehreinheitAssignment)
		{
			$grpAssignment = $this->LehreinheitgruppeModel->getDirectGroupAssignment(
				$lehreinheitAssignment['uid'],
				$lehreinheitAssignment['lehreinheit_id']
			);

			if (isSuccess($grpAssignment))
			{
				if ($lehreinheitAssignment['assigned'] === 'true')
				{
					if (!hasData($grpAssignment))
					{
						$changed = true;
						$direktUserAddResult = $this->LehreinheitgruppeModel->direktUserAdd($lehreinheitAssignment['uid'], $lehreinheitAssignment['lehreinheit_id']);
						if (isError($direktUserAddResult))
						{
							$hasError = true;
							if (!isEmptyString($errors))
								$errors .= '; ';
							$errors .= getError($direktUserAddResult);
						}
					}
				}
				elseif ($lehreinheitAssignment['assigned'] === 'false')
				{
					if (hasData($grpAssignment))
					{
						$changed = true;
						$direktUserDeleteResult = $this->LehreinheitgruppeModel->direktUserDelete($lehreinheitAssignment['uid'], $lehreinheitAssignment['lehreinheit_id']);
						if (isError($direktUserDeleteResult))
						{
							$hasError = true;
							if (!isEmptyString($errors))
								$errors .= '; ';
							$errors .= getError($direktUserDeleteResult);
						}
					}
				}
			}
		}

		if (!$changed)
			$this->outputJsonSuccess('Keine Lehreinheitszuweisungen geändert');
		elseif (isSuccess($json) && !$hasError)
		{
			$this->outputJsonSuccess(getData($json));
		}
		else
		{
			$this->outputJsonError($errors);
		}
	}

	/**
	 * Gets fhcomplete courses (with lehreinheiten)
	 * for a certain user in a studiensemester for certain lvids.
	 */
	public function getFhcCourses()
	{
		$lvIds = $this->input->post('lvids');
		$uid = $this->input->post('uid');
		$studiensemester = $this->input->post('studiensemester');
		$fhcCourses = array();

		if (!isset($studiensemester) || !isset($uid))
			$this->outputJsonError("Parameter fehlen");

		if (isset($lvIds) && is_array($lvIds))
		{
			foreach ($lvIds as $lvid)
			{
				$fhcLv = array();
				$this->syncincomingcoursesfrommolib->fillFhcCourse($lvid, $uid, $studiensemester, $fhcLv);
				$fhcCourses[] = $fhcLv;
			}
		}

		$this->outputJsonSuccess($fhcCourses);
	}
}
