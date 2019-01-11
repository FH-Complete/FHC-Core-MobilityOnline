<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for syncing fhcomplete objects to MobilityOnline
 */
class SyncToMobilityOnlineLib extends MobilityOnlineSyncLib
{
	/**
	 * SyncToMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Maps fhcomplete Lehrveranstaltung to course in MobilityOnline
	 * @param $lv Lehrveranstaltung from fhcomplete
	 * @return array course to be passed to MobilityOnline
	 */
	public function mapLvToMoLv($lv)
	{
		$moLv = $this->convertToMoFormat($lv, 'course');

		//var_dump($moLv);

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
