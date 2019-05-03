<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages Lehrveranstaltung synchronisation between fhcomplete and Mobility Online
 */
class MobilityOnlineCourses extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
			'index' => 'inout/incoming:rw',
			'syncLvs' => 'inout/incoming:rw',
			'deleteLvs' => 'admin:rw',
			'getLvsJson' => 'inout/incoming:rw'
			)
		);

		$this->config->load('extensions/FHC-Core-MobilityOnline/config');

		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->model('education/lehrveranstaltung_model', 'LehrveranstaltungModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mosetmasterdata_model', 'MoSetMaModel');
		$this->load->model('extensions/FHC-Core-MobilityOnline/mappings/Molvidzuordnung_model', 'MolvidzuordnungModel');
		$this->load->library('extensions/FHC-Core-MobilityOnline/MobilityOnlineSyncLib');
		$this->load->library('extensions/FHC-Core-MobilityOnline/SyncToMobilityOnlineLib');
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

		$lvdata = $this->LehrveranstaltungModel->getLvsWithIncomingPlaces($currsemdata->retval[0]->studiensemester_kurzbz);

		if (isError($lvdata))
			show_error($lvdata->retval);

		$this->load->view(
			'extensions/FHC-Core-MobilityOnline/mobilityOnlineCourses',
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
		$fieldmappings = $this->config->item('fieldmappings');
		$coursename = $fieldmappings['course']['lv_bezeichnung'];

		$studiensemester = $this->input->post('studiensemester');
		$results = array('added' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => 0, 'syncoutput' => '');

		$lvs = $this->LehrveranstaltungModel->getLvsWithIncomingPlaces($studiensemester);

		if (!hasData($lvs))
		{
			$results['syncoutput'] .= "No lvs found for sync! Aborting.";
		}
		else
		{
			$lvcount = count($lvs->retval);

			$results['syncoutput'] .= "<div class='text-center'>MOBILITY ONLINE COURSES SYNC start. $lvcount lvs to sync.";
			$results['syncoutput'] .= '<br/>-----------------------------------------------</div>';
			$results['syncoutput'] .= '<div class="lvsyncoutputtext">';

			$first = true;
			foreach ($lvs->retval as $lv)
			{
				$course = $this->synctomobilityonlinelib->mapLvToMoLv($lv);
				$lvid = $lv->lehrveranstaltung_id;

				$zuordnung = $this->MolvidzuordnungModel->loadWhere(array('lehrveranstaltung_id' => $lvid, 'studiensemester_kurzbz' => $studiensemester));

				if ($first)
					$results['syncoutput'] .= "<br />";
				$first = false;

				if (hasData($zuordnung))
				{
					$results['syncoutput'] .= "<p>lv $lvid - ".$course[$coursename]." already exists in Mobility Online - updating";

					$zuordnung = $zuordnung->retval[0];

					$course['courseID'] = $zuordnung->mo_lvid;

					if ($this->MoSetMaModel->updateCoursePerSemester($course))
					{
						$result = $this->MolvidzuordnungModel->update(
							array('lehrveranstaltung_id' => $zuordnung->lehrveranstaltung_id, 'studiensemester_kurzbz' => $zuordnung->studiensemester_kurzbz),
							array('updateamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$results['updated']++;
							$results['syncoutput'] .= "<br /><i class='fa fa-check text-success'></i> lv $lvid - ".$course[$coursename]." successfully updated</p>";
						}
					}
					else
					{
						$results['syncoutput'] .= "<br /><span class='text-danger'><i class='fa fa-times'></i> error when updating lv $lvid - ".$course[$coursename]."</span></p>";
						$results['errors']++;
					}
				}
				else
				{
					$moid = $this->MoSetMaModel->addCoursePerSemester($course);

					if (is_numeric($moid))
					{
						$result = $this->MolvidzuordnungModel->insert(
							array('lehrveranstaltung_id' => $lvid, 'mo_lvid' => $moid, 'studiensemester_kurzbz' => $studiensemester, 'insertamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$results['added']++;
							$results['syncoutput'] .= "<p><i class='fa fa-check text-success'></i> lv $lvid - ".$course[$coursename]." successfully added</p>";
						}
						else
							$results['syncoutput'] .= "<p><span class='text-danger'><i class='fa fa-times'></i> mapping entry in db could not be added for course $lvid - ".$course[$coursename]."</span></p>";
					}
					else
					{
						$results['syncoutput'] .= "<p><span class='text-danger'><i class='fa fa-times text-danger'></i> error when adding lv $lvid - ".$course[$coursename]."</span></p>";
						$results['errors']++;
					}
				}
			}

			$zuordnungen = $this->MolvidzuordnungModel->loadWhere(array('studiensemester_kurzbz' => $studiensemester));

			// if lv not present in fhcomplete anymore, delete from mo.
			foreach ($zuordnungen->retval as $zo)
			{
				$found = false;
				foreach ($lvs->retval as $lv)
				{
					if ($zo->lehrveranstaltung_id === $lv->lehrveranstaltung_id)
					{
						$found = true;
					}
				}
				if (!$found)
				{
					$results['syncoutput'] .= '<p>course with id '.$zo->lehrveranstaltung_id.' not present in fhcomplete, removing from MobilityOnline';
					$this->MoSetMaModel->removeCoursePerSemesterByCourseID($zo->mo_lvid);
					$result = $this->MolvidzuordnungModel->delete(array('lehrveranstaltung_id' => $zo->lehrveranstaltung_id, 'studiensemester_kurzbz' => $zo->studiensemester_kurzbz));
					if (hasData($result))
					{
						$results['deleted']++;
						$results['syncoutput'] .= "<br /><i class='fa fa-check text-success'></i> course with id ".$zo->lehrveranstaltung_id." successfully deleted";
					}
					$results['syncoutput'] .= "</p>";
				}
			}
			$results['syncoutput'] .= '</div>';
			$results['syncoutput'] .= '<div class="text-center">-----------------------------------------------';
			$results['syncoutput'] .= "<br />MOBILITY ONLINE COURSES SYNC FINISHED <br />".$results['added']." added, "
				.$results['updated']." updated, ".$results['deleted']." deleted, ".$results['errors']." errors</div>";
		}

		$this->outputJsonSuccess($results);
	}

	/**
	 * Deletes Lehrveranstaltungen of a given Semester From MobilityOnline
	 * @param $semester
	 */
	public function deleteLvs($semester)
	{
		$studienjahrres = $this->StudiensemesterModel->load($semester);

		if (hasData($studienjahrres))
		{
			$zuordnungen = $this->MolvidzuordnungModel->loadWhere(array('studiensemester_kurzbz' => $semester));

			if (hasData($zuordnungen))
			{
				$mosemester = $this->mobilityonlinesynclib->mapSemesterToMo($semester);
				$mostudienjahr = $this->mobilityonlinesynclib->mapStudienjahrToMo($studienjahrres->retval[0]->studienjahr_kurzbz);

				if ($this->MoSetMaModel->removeCoursesPerSemesterBySearchParameters($mosemester, $mostudienjahr))
				{
					foreach ($zuordnungen->retval as $zuordnung)
					{
						$this->MolvidzuordnungModel->delete(array('lehrveranstaltung_id' => $zuordnung->lehrveranstaltung_id, 'studiensemester_kurzbz' => $semester));
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

		$lvdata = $this->LehrveranstaltungModel->getLvsWithIncomingPlaces($studiensemester);

		if (isSuccess($lvdata))
			$this->outputJsonSuccess($lvdata->retval);
		else
			$this->outputJsonError("Error when getting courses");
	}
}
