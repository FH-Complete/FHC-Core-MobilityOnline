<?php
/**
 * Fields required for sync.
 * If those fields are not present in the named object, sync is not possible and it is an error.
 */

$config['requiredfields']['application'] = array(
	'person' => array('vorname' => array(),
					  'nachname' => array(),
					  'geschlecht' => array()
	),
	'prestudent' => array('studiengang_kz' =>
							  array('name' => 'Studiengang')
	),
	'prestudentstatus' => array('studiensemester_kurzbz' =>
									array('name' => 'Studiensemester'),
								'ausbildungssemester' => array(),
								'status_kurzbz' =>
									array('name' => 'Incomingstatus')
	),
	'benutzer' => array(),
	'student' => array('semester' => array(),
					   'verband' => array()
	),
	'studentlehrverband' => array('semester' => array(),
								  'verband' => array()
	),
	'adresse' => array('nation' => array(),
					   'ort' => array(),
					   'strasse' => array()
	),
	'kontaktmail' => array('kontakt' => array('name' => 'E-Mail-Adresse')
	),
	'bisio' => array('von' => array('name' => 'Aufenthalt von'),
					 'bis' => array('name' => 'Aufenthalt bis'),
					 'nation_code' => array('name' => 'Nation'),
					 'mobilitaetsprogramm_code' =>
						 array('name' => 'Austauschprogramm'),
					 'zweck_code' =>
						 array('name' => 'Aufenthaltszweck')
	)
);
