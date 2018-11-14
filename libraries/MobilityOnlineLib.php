<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides any functionality needed for Mobility Online sync not directly related to API or fcomplete database
 */
class MobilityOnlineLib
{
	/**
	 * Maps fhcomplete Lehrveranstaltung to Course in MobilityOnline
	 * @param $lv Lehrveranstaltung from fhcomplete
	 * @return array course to be passed to MobilityOnline
	 */
	public function mapLvToMoLv($lv)
	{
		$temp = $lv;

		//string replacements
		$replacementsarr = array(
			'studiensemester_kurzbz' => array(
				'WS\d{4}' => 'Wintersemester '
					.substr($lv->studiensemester_kurzbz, -4, 4).'/'
					.(substr($lv->studiensemester_kurzbz, -4, 4) + 1),
				'SS' => 'Sommersemester '
			),
			'typ' => array(
				'b' => 'B',
				'm' => 'M'
			),
			'studienjahr_kurzbz' => array(
				$lv->studienjahr_kurzbz =>
				substr($lv->studienjahr_kurzbz, 0, strpos($lv->studienjahr_kurzbz, '/')).'/'.
				(substr($lv->studienjahr_kurzbz, 0, strpos($lv->studienjahr_kurzbz, '/'))+1)
			),
			'lehrform_kurzbz' => array(
					'^ILV.*$' => 'LV',
					'^SE.*$' => 'SE',
					'^VO.*$' => 'VO',
					'^(?!(ILV|SE|VO)).*$' => 'LV'
			)
		);

		foreach ($temp as $prop => $value)
		{
			foreach ($replacementsarr as $key => $entry)
			{
				if ($key === $prop)
				{
					foreach ($entry as $toreplace => $replacement)
					{
						$toreplace = '/'.str_replace('/', '\/', $toreplace).'/';
						$temp->$prop = preg_replace($toreplace, $replacement, $temp->$prop);
					}
				}
			}
		}

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
				'freePlaces' => 4
			 //'maxParticipants' => 5, */


		$moLv = array();

		//$moLv['courseID'] = $temp->lehrveranstaltung_id;
		//$moLv['courseNumber'] = isset($temp->lvnr) ? $temp->lvnr : $temp->lehrveranstaltung_id;
		$moLv['courseNumber'] = $temp->lehrveranstaltung_id.'_'.$temp->orgform_kurzbz.'_'.$temp->semester.'sem';
		$moLv['courseName'] = $temp->bezeichnung;
		$moLv['applicationType'] = 'IN';
		$moLv['academicYear'] = array('description' => $temp->studienjahr_kurzbz);
		$moLv['semester'] = $temp->studiensemester_kurzbz;
		$moLv['semesterNr'] = $temp->semester;
		$moLv['studyArea'] = array('description' => 'FHTW Studiengänge');
		$moLv['studyField'] = array('number' => $temp->studiengang_kuerzel);
		$moLv['courseType'] =  array('number' =>$temp->lehrform_kurzbz);
		$moLv['language'] = array('number' => $temp->sprachkuerzel);
		$moLv['numberOfLessons'] = $temp->sws;
		$moLv['ectsCredits'] = $temp->ects;
		$moLv['freePlaces'] = $temp->incoming;
		$moLv['studyLevels'] = array('number' => $temp->typ);
		$moLv['linkEctsDescription'] = CIS_ROOT.'cis/private/lehre/ects/preview.php?lv='.$temp->lehrveranstaltung_id;

		return $moLv;
	}

	/**
	 * Creates an empty fhcomplete Lv with given Semester and Studienjahr
	 * @param $semester
	 * @param $studienjahr
	 * @return StdClass
	 */
	public function createLv($semester, $studienjahr)
	{
		$lv = new StdClass();
		$lv->lehrveranstaltung_id = 0;
		$lv->bezeichnung = '';
		$lv->studienjahr_kurzbz = $studienjahr;
		$lv->studiensemester_kurzbz = $semester;
		$lv->semester = 0;
		$lv->studiengang_kuerzel = '';
		$lv->lehrform_kurzbz = '';
		$lv->sprachkuerzel = '';
		$lv->sws = 0;
		$lv->ects = 0;
		$lv->incoming = 0;
		$lv->typ = '';
		$lv->orgform_kurzbz = '';

		return $lv;
	}
}
