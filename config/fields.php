<?php
/**
 * Fields for sync.
 * If required fields are not present in the object, sync is not possible and it is an error.
 * type (no type given - assuming string), foreign key references (ref) are also checked
 * "name" is display name for errors
 */

$config['fhcfields']['application'] = array(
	'person' => array('vorname' => array('required' => true),
					  'nachname' => array('required' => true),
					  'geschlecht' => array('required' => true),
					  'staatsbuergerschaft' => array('ref' => 'bis.tbl_nation',
													 'reffield' => 'nation_code'),
					  'anrede' => array(),
					  'gebdatum' => array('name' => 'Geburtsdatum',
										  'type' => 'date'),
					  'sprache' => array('ref' => 'public.tbl_sprache'),
					  'anmerkung' => array(),
					  'foto' => array('type' => 'base64')
	),
	'prestudent' => array('studiengang_kz' =>
							  array('required' => true,
							  		'name' => 'Studiengang',
									'type' => 'integer',
									'ref' => 'public.tbl_studiengang'),
						  'zgvnation' =>
							  array('ref' => 'bis.tbl_nation',
									'reffield' => 'nation_code'),
						  'zgvdatum' =>
							  array('type' => 'date'),
						  'zgvmas_code' =>
							  array('name' => 'Zgvmastercode',
							  'type' => 'integer'),
						  'zgvmadatum' => array('name' => 'Zgvmasterdatum',
											'type' => 'date'),
						  'zgvmanation' =>
							  array('name' => 'Zgvmasternation',
									'ref' => 'bis.tbl_nation',
									'reffield' => 'nation_code')
	),
	'prestudentstatus' => array('studiensemester_kurzbz' =>
									array('required' => true,
										  'name' => 'Studiensemester',
										  'ref' => 'public.tbl_studiensemester'),
								'ausbildungssemester' => array('required' => true,
															   'type' => 'integer'),
								'status_kurzbz' =>
									array('required' => true,
										  'name' => 'Incomingstatus',
										  'ref' => 'public.tbl_status')
	),
	'benutzer' => array(),
	'student' => array('semester' => array('required' => true,
										   'type' => 'integer'),
					   'verband' => array('required' => true)
	),
	'studentlehrverband' => array('semester' => array('required' => true,
													  'type' => 'integer'),
								  'verband' => array('required' => true)
	),
	'adresse' => array('nation' => array('required' => true,
										 'ref' => 'bis.tbl_nation',
										 'reffield' => 'nation_code'),
					   'ort' => array('required' => true),
					   'strasse' => array('required' => true),
					   'plz' => array('name' => 'Postleitzahl'),
					   'gemeinde' => array()
	),
	'kontaktmail' => array('kontakt' => array('required' => true,
											  'name' => 'E-Mail-Adresse')
	),
	'kontaktnotfall' => array('kontakt' => array('name' => 'Notfallkontakt')
	),
	'kontakttel' => array('kontakt' => array('name' => 'Phone number')
	),
	'akte' => array('inhalt' => array('name' => 'Photodokument',
									  'type' => 'base64')
	),
	'bisio' => array('von' => array('required' => true,
									'name' => 'Aufenthalt von',
									'type' => 'date'),
					 'bis' => array('required' => true,
									'name' => 'Aufenthalt bis',
					 				'type' => 'date'),
					 'nation_code' => array('required' => true,
					 						'name' => 'Nation',
											'ref' => 'bis.tbl_nation'),
					 'mobilitaetsprogramm_code' =>
						 array('required' => true,
							   'name' => 'Austauschprogramm',
							   'type' => 'integer',
							   'ref' => 'bis.tbl_mobilitaetsprogramm')
	),
	'bisio_zweck' => array('zweck_code' => array('required' => true,
												 'name' => 'Aufenthaltszweck',
												 'type' => 'integer',
												 'ref' => 'bis.tbl_zweck')
	)
);

/**
 * MobilityOnline fields for searching
 */

$config['mofields']['application'] = array(
	'firstName',
	'lastName',
	'secondLastName',
	'birthday',
	'matriculationNumber',
	'email',
	'applicationType',
	'personType',
	'exchangeProgramNumber',
	'academicYearDescription',
	'semesterDescription',
	'studyFieldDescription',
	'login'
);

$config['mofields']['course'] = array(
	'semesterDescription',
	'applicationType',
	'studyArea',
	'studyField',
	'studySubject',
	'courseType',
	'language',
	'studyLevels',
	'courseNumber',
	'courseName'
);
