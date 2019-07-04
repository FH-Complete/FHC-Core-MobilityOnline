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
			show_error($studiensemesterdata->retval);

		$currsemdata = $this->StudiensemesterModel->getLastOrAktSemester(0);

		if (isError($currsemdata))
			show_error($currsemdata->retval);

		$prestudents = array();

		if (hasData($currsemdata))
		{
			$prestudents = $this->syncincomingcoursesfrommolib->getIncomingWithCourses($currsemdata->retval[0]->studiensemester_kurzbz);
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
		$incomingdata = $this->syncincomingcoursesfrommolib->getIncomingWithCourses($studiensemester);

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
	 * Gets fhcomplete courses (with lehreinheiten)
	 * for a certain user in a studiensemester for certain lvids.
	 */
	public function getFhcCourses()
	{
		$lvids = $this->input->post('lvids');
		$uid = $this->input->post('uid');
		$studiensemester = $this->input->post('studiensemester');
		$fhccourses = array();

		if (!isset($studiensemester) || !isset($uid))
			$this->outputJsonError("Parameters missing");

		if (isset($lvids) && is_array($lvids))
		{
			foreach ($lvids as $lvid)
			{
				$fhclv = array();
				$this->syncincomingcoursesfrommolib->fillFhcCourse($lvid, $uid, $studiensemester, $fhclv);
				$fhccourses[] = $fhclv;
			}
		}

		$this->outputJsonSuccess($fhccourses);
	}
}
