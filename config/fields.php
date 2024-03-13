<?php
/**
 * Fields for sync.
 * If required fields are not present in the object, sync is not possible and it is an error.
 * type (no type given - assuming string), foreign key references (ref) are also checked
 * "name" is display name for errors
 */

$config['fhcfields']['application']['required'] = array(
	'person' => array(
		'vorname' => array('required' => true),
		'nachname' => array('required' => true),
		'geschlecht' => array('required' => true),
		'staatsbuergerschaft' => array('ref' => 'bis.tbl_nation', 'reffield' => 'nation_code'),
		'anrede' => array(),
		'gebdatum' => array('name' => 'Geburtsdatum', 'type' => 'date'),
		'sprache' => array('ref' => 'public.tbl_sprache'),
		'anmerkung' => array(),
		'foto' => array('type' => 'base64')
	),
	'prestudent' => array(
		'studiengang_kz' => array('required' => true, 'name' => 'Studiengang', 'type' => 'integer', 'ref' => 'public.tbl_studiengang'),
		'zgvnation' => array('ref' => 'bis.tbl_nation', 'reffield' => 'nation_code'),
		'zgvdatum' => array('type' => 'date'),
		'zgvmas_code' => array('name' => 'Zgvmastercode', 'type' => 'integer'),
		'zgvmadatum' => array('name' => 'Zgvmasterdatum', 'type' => 'date'),
		'zgvmanation' => array('name' => 'Zgvmasternation', 'ref' => 'bis.tbl_nation', 'reffield' => 'nation_code')
	),
	'prestudentstatus' => array(
		'studiensemester_kurzbz' => array('required' => true, 'name' => 'Studiensemester', 'ref' => 'public.tbl_studiensemester'),
		'ausbildungssemester' => array('required' => true, 'type' => 'integer'),
		'status_kurzbz' => array('required' => true, 'name' => 'Incomingstatus', 'ref' => 'public.tbl_status')
	),
	'benutzer' => array(),
	'student' => array(
		'semester' => array('required' => true, 'type' => 'integer'),
		'verband' => array('required' => true)
	),
	'studentlehrverband' => array(
		'semester' => array('required' => true, 'type' => 'integer'),
		'verband' => array('required' => true)
	),
	'adresse' => array(
		'nation' => array('required' => true, 'ref' => 'bis.tbl_nation', 'reffield' => 'nation_code'),
		'ort' => array('required' => true),
		'strasse' => array('required' => true),
		'plz' => array('name' => 'Postleitzahl'),
		'gemeinde' => array()
	),
	'kontaktmail' => array(
		'kontakt' => array('required' => true, 'name' => 'E-Mail-Adresse')
	),
	'bisio' => array(
		'von' => array('required' => true, 'name' => 'Aufenthalt von', 'type' => 'date'),
		'bis' => array('required' => true, 'name' => 'Aufenthalt bis', 'type' => 'date'),
		'nation_code' => array('required' => true, 'name' => 'Nation', 'ref' => 'bis.tbl_nation'),
		'mobilitaetsprogramm_code' => array(
			'required' => true,
			'name' => 'Austauschprogramm',
			'type' => 'integer',
			'ref' => 'bis.tbl_mobilitaetsprogramm'
		),
		'herkunftsland_code' => array('required' => true, 'name' => 'Herkunftsland', 'ref' => 'bis.tbl_nation', 'reffield' => 'nation_code'),
	),
	'bisio_zweck' => array(
		'zweck_code' => array('required' => true, 'name' => 'Aufenthaltszweck', 'type' => 'integer', 'ref' => 'bis.tbl_zweck')
	)
);

$config['fhcfields']['application']['optional'] = array(
	'kontaktnotfall' => array(
		'kontakt' => array('name' => 'Notfallkontakt')
	),
	'kontakttel' => array(
		'kontakt' => array('name' => 'Phone number')
	),
	'lichtbild' => array(
		'inhalt' => array('name' => 'Photodokument', 'type' => 'base64')
	),
	'akte' => array(
		'file_content' => array('name' => 'Dokument', 'type' => 'base64Document')
	),
	'studienadresse' => array(
		'nation' => array('required' => true, 'ref' => 'bis.tbl_nation', 'reffield' => 'nation_code'),
		'ort' => array('required' => true),
		'strasse' => array('required' => true),
		'plz' => array('name' => 'Postleitzahl'),
		'gemeinde' => array()
	)
);

$config['fhcfields']['applicationout']['required'] = array(
	'person' => array(
		'vorname' => array('required' => true),
		'nachname' => array('required' => true),
		'mo_person_id' => array('required' => true, 'name' => 'MO Person ID')
	),
	'prestudent' => array(
		'studiengang_kz' => array('required' => true, 'name' => 'Studiengang', 'type' => 'integer', 'ref' => 'public.tbl_studiengang'),
		'studiensemester_kurzbz' => array('required' => true, 'name' => 'Studiensemester', 'ref' => 'public.tbl_studiensemester')
	),
	'bisio' => array(
		'student_uid' => array('required' => true, 'name' => 'Uid', 'ref' => 'public.tbl_student'),
		'von' => array('required' => true, 'name' => 'Aufenthalt von', 'type' => 'date'),
		'bis' => array('required' => true, 'name' => 'Aufenthalt bis', 'type' => 'date'),
		'nation_code' => array('required' => true, 'name' => 'Nation', 'ref' => 'bis.tbl_nation'),
		'herkunftsland_code' => array('required' => true, 'name' => 'Herkunftsland', 'ref' => 'bis.tbl_nation', 'reffield' => 'nation_code'),
		'mobilitaetsprogramm_code' => array(
			'required' => true,
			'name' => 'Austauschprogramm',
			'type' => 'integer',
			'ref' => 'bis.tbl_mobilitaetsprogramm'
		),
		'ects_erworben' => array('name' => 'ECTS erworben', 'type' => 'float'),
		'ects_angerechnet' => array('name' => 'ECTS angerechnet', 'type' => 'float')
	),
	'bisio_zweck' => array(
		'zweck_code' => array('required' => true, 'name' => 'Aufenthaltszweck', 'type' => 'integer', 'ref' => 'bis.tbl_zweck')
	),
	'bisio_aufenthaltfoerderung' => array(
		'aufenthaltfoerderung_code' => array(
			'required' => true,
			'name' => 'Aufenthaltsförderung',
			'type' => 'integer',
			'ref' => 'bis.tbl_bisio_aufenthaltfoerderung')
	),
	'kontaktmail' => array(
		'kontakt' => array('required' => true, 'name' => 'E-Mail-Adresse')
	)
);

$config['fhcfields']['applicationout']['optional'] = array(
	'institution_adresse' => array(
		'ort' => array('name' => 'Nation'),
		'nation' => array('name' => 'Ort')
	),
	'bankverbindung' => array(
		'bic' => array('name' => 'BIC'),
		'iban' => array('name' => 'IBAN')
	)
);

$config['fhcfields']['payment']['required'] = array(
	'konto' => array(
		'betrag' => array('required' => true, 'name' => 'Betrag', 'type' => 'float'),
		'buchungstyp_kurzbz' => array('required' => true, 'name' => 'Buchungstyp', 'ref' => 'public.tbl_buchungstyp'),
		'buchungsdatum' => array('required' => true, 'name' => 'Buchungsdatum', 'type' => 'date')
	)
);

$config['fhcfields']['file']['required'] = array(
	'akte' => array(
		'dokument_kurzbz' => array('required' => true, 'name' => 'Dokumentkurzbezeichnung'),
		'dokument_bezeichnung' => array('required' => true, 'name' => 'Dokumentbezeichnung'),
		'file_content' => array('required' => true, 'name' => 'Dokumentinhalt', 'type' => 'base64Document')
	)
);

$config['fhcfields']['outgoingcourse']['required'] = array(
	'mo_outgoing_lv' => array(
		'mo_lvid' => array('required' => true, 'name' => 'Lehrveranstaltungsid', 'type' => 'integer'),
		'lv_nr_gast' => array('required' => true, 'name' => 'Lehrveranstatlungsnummer Gast'),
		'lv_bez_gast' => array('name' => 'Lehrveranstaltungsbezeichnung Gast'),
		'lv_semesterstunden_gast' => array('name' => 'Lehrveranstaltungssemesterstunden Gast', 'type' => 'float'),
		'ects_punkte_gast' => array('name' => 'ECTS Punkte Gast', 'type' => 'float'),
		'note_local_gast' => array('required' => true, 'name' => 'Note Gast')
	)
);

// aliases: if certain tables should be checked like another table
$config['fhcfields_aliases'] = array(
	'studienadresse' => 'adresse'
);

/**
 * MobilityOnline fields for searching
 */

$applicationSearchFields = array(
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

$config['mofields']['application'] = $applicationSearchFields;
$config['mofields']['applicationout'] = $applicationSearchFields;
$config['mofields']['outgoingcoursesapplication'] = $applicationSearchFields;

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
