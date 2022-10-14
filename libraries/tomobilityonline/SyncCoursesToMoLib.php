<?php

/**
 * Functionality for syncing courses to MobilityOnline
 */
class SyncCoursesToMoLib extends SyncToMobilityOnlineLib
{
	const MOOBJECTTYPE = 'course';

	public function __construct()
	{
		parent::__construct();
		$this->ci->load->model('education/lehrveranstaltung_model', 'LehrveranstaltungModel');
		$this->ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mosetmasterdata_model', 'MoSetMaModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Molvidzuordnung_model', 'MolvidzuordnungModel');
	}

	/**
	 * Executes sync of courses for a Studiensemester from FHC to MO.
	 * Adds, updates or deletes (if not in FHC anymore) courses.
	 * @param string $studiensemester
	 * @return array containing syncoutput, errors, numer of added, updated, deleted courses
	 */
	public function startCoursesSync($studiensemester)
	{
		$fieldmappings = $this->ci->config->item('fieldmappings');
		$courseName = $fieldmappings[self::MOOBJECTTYPE]['lv_bezeichnung'];

		$results = array('added' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => 0, 'syncoutput' => '');
		$lvs = $this->ci->LehrveranstaltungModel->getLvsWithIncomingPlaces($studiensemester);

		if (!hasData($lvs))
		{
			$results['syncoutput'] .= "Keine Lvs für Sync gefunden! Abbruch.";
		}
		else
		{
			$lvcount = count($lvs->retval);

			$results['syncoutput'] .= "<div class='text-center'>MOBILITY ONLINE KURSSYNCHRONISIERUNG START. $lvcount Lvs zum Synchronisieren.";
			$results['syncoutput'] .= '<br/>-----------------------------------------------</div>';
			$results['syncoutput'] .= '<div class="lvsyncoutputtext">';

			$first = true;
			foreach ($lvs->retval as $lv)
			{
				$course = $this->mapLvToMoLv($lv);
				$lvid = $lv->lehrveranstaltung_id;

				$zuordnung = $this->ci->MolvidzuordnungModel->loadWhere(
					array(
						'lehrveranstaltung_id' => $lvid,
						'studiensemester_kurzbz' => $studiensemester
					)
				);

				if ($first)
					$results['syncoutput'] .= "<br />";
				$first = false;

				if (hasData($zuordnung))
				{
					$results['syncoutput'] .= "<p>lv $lvid - ".$course[$courseName]." existiert bereits in Mobility Online - aktualisieren";

					$zuordnung = $zuordnung->retval[0];

					$course['courseID'] = $zuordnung->mo_lvid;

					if ($this->ci->MoSetMaModel->updateCoursePerSemester($course))
					{
						$result = $this->ci->MolvidzuordnungModel->update(
							array(
								'lehrveranstaltung_id' => $zuordnung->lehrveranstaltung_id,
								'studiensemester_kurzbz' => $zuordnung->studiensemester_kurzbz
							),
							array('updateamum' => 'NOW()')
						);

						if (hasData($result))
						{
							$results['updated']++;
							$results['syncoutput'] .= "<br /><i class='fa fa-check text-success'></i> Lv $lvid - ".$course[$courseName]." erfolgreich aktualisiert</p>";
						}
					}
					else
					{
						$results['syncoutput'] .= "<br /><span class='text-danger'><i class='fa fa-times'></i> Fehler beim Aktualisieren von Lv $lvid - ".$course[$courseName]."</span></p>";
						$results['errors']++;
					}
				}
				else
				{
					$moid = $this->ci->MoSetMaModel->addCoursePerSemester($course);

					if (is_numeric($moid))
					{
						$result = $this->ci->MolvidzuordnungModel->insert(
							array(
								'lehrveranstaltung_id' => $lvid,
								'mo_lvid' => $moid,
								'studiensemester_kurzbz' => $studiensemester,
								'insertamum' => 'NOW()'
							)
						);

						if (hasData($result))
						{
							$results['added']++;
							$results['syncoutput'] .= "<p><i class='fa fa-check text-success'></i> Lv $lvid - ".$course[$courseName]." erfolgreich hinzugefügt</p>";
						}
						else
							$results['syncoutput'] .= "<p><span class='text-danger'><i class='fa fa-times'></i> Verlinkung konnte für Kurs $lvid - ".$course[$courseName]." nicht hinzugefügt werden</span></p>";
					}
					else
					{
						$results['syncoutput'] .= "<p><span class='text-danger'><i class='fa fa-times text-danger'></i> Fehler beim Hinzufügen der lv $lvid - ".$course[$courseName]."</span></p>";
						$results['errors']++;
					}
				}
			}

			$zuordnungen = $this->ci->MolvidzuordnungModel->loadWhere(array('studiensemester_kurzbz' => $studiensemester));

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
					$results['syncoutput'] .= '<p>Kurs mit Id '.$zo->lehrveranstaltung_id.' ist nicht in fhcomplete, wird von Mobility Online entfernt';
					$this->ci->MoSetMaModel->removeCoursePerSemesterByCourseID($zo->mo_lvid);
					$result = $this->ci->MolvidzuordnungModel->delete(
						array(
							'lehrveranstaltung_id' => $zo->lehrveranstaltung_id,
							'studiensemester_kurzbz' => $zo->studiensemester_kurzbz
						)
					);

					if (hasData($result))
					{
						$results['deleted']++;
						$results['syncoutput'] .= "<br /><i class='fa fa-check text-success'></i> Kurs mit Id ".$zo->lehrveranstaltung_id." erfolgreich gelöscht";
					}
					$results['syncoutput'] .= "</p>";
				}
			}
			$results['syncoutput'] .= '</div>';
			$results['syncoutput'] .= '<div class="text-center">-----------------------------------------------';
			$results['syncoutput'] .= "<br />MOBILITY ONLINE KURSSYNCHRONISERUNG ENDE <br />".$results['added']." hinzugefügt, ".
			$results['updated']." aktualisiert, ".$results['deleted']." gelöscht, ".$results['errors']." Fehler</div>";
		}

		return $results;
	}

	/**
	 * Initialises deletion of MO courses for a Studiensemester.
	 * @param string $studiensemester
	 */
	public function startCoursesDeletion($studiensemester)
	{
		$studienjahrRes = $this->ci->StudiensemesterModel->load($studiensemester);

		if (hasData($studienjahrRes))
		{
			$zuordnungen = $this->ci->MolvidzuordnungModel->loadWhere(array('studiensemester_kurzbz' => $studiensemester));

			if (hasData($zuordnungen))
			{
				$moSemester = $this->mapSemesterToMo($studiensemester);
				$moStudienjahr = $this->mapStudienjahrToMo($studienjahrRes->retval[0]->studienjahr_kurzbz);

				if ($this->ci->MoSetMaModel->removeCoursesPerSemesterBySearchParameters($moSemester, $moStudienjahr))
				{
					foreach ($zuordnungen->retval as $zuordnung)
					{
						$this->ci->MolvidzuordnungModel->delete(
							array(
								'lehrveranstaltung_id' => $zuordnung->lehrveranstaltung_id,
								'studiensemester_kurzbz' => $studiensemester
							)
						);
					}
					echo "<br />Kurse erfolgreich gelöscht!";
				}
				else
				{
					echo "<br />Fehler beim Löschen der Kurse für Studiensemester $studiensemester";
				}
			}
			else
			{
				echo "<br />Keine Einträge zum Löschen in Verlinkungstabelle gefunden";
			}
		}
	}

	/**
	 * Maps fhcomplete Lehrveranstaltung to course in MobilityOnline
	 * @param object $lv Lehrveranstaltung from fhcomplete
	 * @return array course to be passed to MobilityOnline
	 */
	public function mapLvToMoLv($lv)
	{
		$moLv = $this->convertToMoFormat($lv, self::MOOBJECTTYPE);

		/* lv structure in mobility online
		 * array(
				'courseId' => 1114,
				'courseNumber' => 1113,
				'courseName' => 'testkurs',
				'applicationType' => 'IN',
				'academicYear' => array('description' => '2018/2019'),
				'semester' => 'Sommersemester 2019',
				'studyArea' => array('description' => 'FHTW Studiengänge'),
				'studyField' => array('description' => 'Wirtschaftsinformatik Master'),
				'courseType' => array('number' => 'LV'),
				'language' => array('number' => 'de'),
				'numberOfLessons' => 2,
				'ectsCredits' => 2,
				'freePlaces' => 4,
				'studyLevels' => 'Bachelor'
			 //'maxParticipants' => 5, */

		$moLv['courseNumber'] = $lv->lehrveranstaltung_id.'_'.$lv->orgform_kurzbz.'_'.$lv->semester.'sem';
		$moLv['linkEctsDescription'] = CIS_ROOT.'addons/lvinfo/cis/view.php?lehrveranstaltung_id='.$lv->lehrveranstaltung_id.'&studiensemester_kurzbz='.$lv->studiensemester_kurzbz;

		return $moLv;
	}
}
