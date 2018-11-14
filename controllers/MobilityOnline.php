<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages synchronisation between fhcomplete and Mobility Online
 */
class MobilityOnline extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(array(
			'index'=>'admin:rw',
			'syncLvs'=>'admin:rw',
			'deleteLvs'=>'admin:rw',
			'getLvsJson'=>'admin:rw'
			)
		);

		$this->config->load('extensions/FHC-Core-MobilityOnline/config');

		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/Mobilityonlinedb_model', 'MoDbModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/Mobilityonlineapi_model', 'MoApiModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/Moidzuordnung_model', 'MoidzuordnungModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineLib');
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

		$currsemdata = $this->StudiensemesterModel->getLastOrAktSemester();

		if (isError($currsemdata))
			show_error($currsemdata->retval);

		$lvdata = $this->MoDbModel->getLvs($currsemdata->retval[0]->studiensemester_kurzbz);

		if (isError($lvdata))
			show_error($lvdata->retval);

		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnline',
			array(
				'semester' => $studiensemesterdata->retval,
				'currsemester' => $currsemdata->retval,
				'lvs' => $lvdata->retval
			)
		);
	}

	/**
	 * Syncs Lehrveranstaltungen to MobilityOnline, i.e. adds Lvs from fhcomplete to Mobility Online
	 * and removes Lvs not present in fhcomplete anymore
	 */
	public function syncLvs()
	{
		$studiensemester = $this->input->get('studiensemester');
		$added = $updated = $deleted = 0;

		$coursesPerSemester = array();

		$lvs = $this->MoDbModel->getLvs($studiensemester);

		if (!hasData($lvs))
		{
			echo "No lvs found for sync! aborting.";
			return null;
		}

		$lvcount = count($lvs->retval);

		echo "MOBILITY ONLINE LV SYNC start. $lvcount lvs to sync.";
		echo '<br/>-----------------------------------------------';

		foreach ($lvs->retval as $lv)
		{
			$coursesPerSemester[$lv->lehrveranstaltung_id] = $this->mobilityonlinelib->mapLvToMoLv($lv);
		}

		foreach ($coursesPerSemester as $key => $course)
		{
			$lvid = $key;

			$zuordnung = $this->MoidzuordnungModel->loadWhere(array('lvid' => $lvid, 'studiensemester_kurzbz' => $studiensemester));

			if (hasData($zuordnung))
			{
				echo "<br />lv $lvid - ".$course['courseName']." already exists in Mobility Online - updating";

				$zuordnung = $zuordnung->retval[0];

				$course['courseID'] = $zuordnung->mo_lvid;

				if ($this->MoApiModel->updateCoursePerSemester($course))
				{
					$result = $this->MoidzuordnungModel->update(
						array('lvid' => $zuordnung->lvid, 'studiensemester_kurzbz' => $zuordnung->studiensemester_kurzbz),
						array('updateamum' => 'NOW()')
					);

					if (hasData($result))
					{
						$updated++;
						echo "<br />lv $lvid - " . $course['courseName'] . " successfully updated";
					}
				}
				else
				{
					echo "<br />error when updating lv $lvid - ".$course['courseName'];
				}
			}
			else
			{
				$moid = $this->MoApiModel->addCoursePerSemester($course);

				if (is_numeric($moid))
				{
					$result = $this->MoidzuordnungModel->insert(
						array('lvid' => $lvid, 'mo_lvid' => $moid, 'studiensemester_kurzbz' => $studiensemester, 'insertamum' => 'NOW()')
					);

					if (hasData($result))
					{
						$added++;
						echo "<br />lv $lvid - " . $course['courseName'] . " successfully added";
					}
					else
						echo "<br />mapping entry in db could not be added for course $lvid - ".$course['courseName'];
						//TODO revert add because no mapping in table?
				}
				else
				{
					echo "<br />error when adding lv $lvid - ".$course['courseName'];
				}
			}
		}

		$zuordnungen = $this->MoidzuordnungModel->loadWhere(array('studiensemester_kurzbz' => $studiensemester));

		// if lv not present in fhcomplete anymore, delete from mo.
		foreach ($zuordnungen->retval as $zo)
		{
			$found = false;
			foreach ($lvs->retval as $lv)
			{
				if ($zo->lvid === $lv->lehrveranstaltung_id)
				{
					$found = true;
				}
			}
			if (!$found)
			{
				echo '<br />course with id '.$zo->lvid.' not present in fhcomplete, removing from MobilityOnline';
				$this->MoApiModel->removeCoursePerSemesterByCourseID($zo->mo_lvid);
				$result = $this->MoidzuordnungModel->delete(array('lvid' => $zo->lvid, 'studiensemester_kurzbz' => $zo->studiensemester_kurzbz));
				if (hasData($result))
				{
					$deleted++;
					echo '<br />course with id '.$zo->lvid.' successfully deleted';
				}
			}
		}
		echo '<br />-----------------------------------------------';
		echo "<br />MOBILITY ONLINE LV SYNC FINISHED <br />$added lvs added, $updated lvs updated, $deleted lvs deleted";
	}

	/**
	 * Deletes Lehrveranstaltungen of a given Semester From MobilityOnline
	 * @param $semester
	 */
	public function deleteLvs($semester)
	{
		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		$studienjahrres = $this->StudiensemesterModel->load($semester);

		if (hasData($studienjahrres))
		{
			$lv = $this->mobilityonlinelib->createLv($semester, $studienjahrres->retval[0]->studienjahr_kurzbz);
			$molv = $this->mobilityonlinelib->mapLvToMoLv($lv);
			$zuordnungen = $this->MoidzuordnungModel->loadWhere(array('studiensemester_kurzbz' => $semester));

			if (hasData($zuordnungen))
			{
				if ($this->MoApiModel->removeCoursesPerSemesterBySearchParameters($molv['semester'], $molv['academicYear']))
				{
					foreach ($zuordnungen->retval as $zuordnung)
					{
						$this->MoidzuordnungModel->delete(array('lvid' => $zuordnung->lvid, 'studiensemester_kurzbz' => $semester));
					}
					echo "<br />courses deleted successfully!";
				}
				else
				{
					echo "<br />error when deleting courses for semester $semester";
				}
			}
			else
			{
				echo "<br />No entries in mappingtable found for removing";
			}
		}
	}

	/**
	 * Gets Lehrveranstaltungen which need to be synced to MobilityOnline and outputs as Json
	 */
	public function getLvsJson()
	{
		$studiensemester = $this->input->get('studiensemester');
		$json = null;
		$lvdata = $this->MoDbModel->getLvs($studiensemester);

		if (hasData($lvdata))
			$json = $lvdata;

		$this->output->set_content_type('application/json')->set_output(json_encode($json));
	}
}

