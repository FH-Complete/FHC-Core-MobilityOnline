<?php
/**
 * Fields required for sync.
 * If those fields are not present in the named object, sync is not possible and it is an error.
 */

$config['requiredfields']['application'] = array(
	'person' => array('vorname', 'nachname', 'geschlecht'),
	'prestudent' => array('studiengang_kz'),
	'prestudentstatus' => array('studiensemester_kurzbz', 'ausbildungssemester', 'status_kurzbz'),
	'benutzer' => array(),
	'student' => array('semester', 'verband'),
	'studentlehrverband' => array('semester', 'verband'),
	'adresse' => array('nation', 'ort', 'strasse'),
	'kontaktmail' => array('kontakt'),
	'bisio' => array('von', 'bis', 'nation_code', 'mobilitaetsprogramm_code', 'zweck_code')
);

