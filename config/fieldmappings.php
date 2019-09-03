<?php
/**
 * config file containing mapping of fieldnames from fhcomplete and mobility online
 * array structure:
 * ['fieldmappings']['mobilityonlineobject']['fhctable'] = array('fhcfieldname' => 'mobilityonlinefieldname')
 */

$config['fieldmappings']['application']['person'] = array(
	'vorname' => 'firstName',
	'nachname' => 'lastName',
	'staatsbuergerschaft' => 'lcd_id_nat',
	'geschlecht' => 'bew_geschlecht',
	'anrede' => 'bew_geschlecht',
	'gebdatum' => 'bew_geb_datum',
	'geburtsnation' => 'bew_geb_ort',
	'sprache' => 'spr_id_komm',
	'foto' => 'file',
	'anmerkung' => 'bew_txt_gruende'
);

$config['fieldmappings']['application']['prestudent'] = array(
	'studiengang_kz' => 'studr_id',
	'zgvnation' => 'lcd_id_bereits',
	'zgvdatum' => 'varchar_freifeld1',
	'zgvmas_code' => 'int_freifeld1',
	'zgvmadatum' => 'varchar_freifeld2',
	'zgvmanation' => 'lcd_id_bereits_2'
);

$config['fieldmappings']['application']['prestudentstatus'] = array(
	'studiensemester_kurzbz' => 'sem_id'
);

$config['fieldmappings']['application']['akte'] = array(
	'inhalt' => 'file'
);

$config['fieldmappings']['application']['bisio'] = array(
	'von' => 'bew_dat_von',
	'bis' => 'bew_dat_bis',
	'universitaet' => 'inst_id_heim_name',
	'nation_code' => 'lcd_id_gast',
	'mobilitaetsprogramm_code' => 'aust_prog_id'
);

$config['fieldmappings']['address']['adresse'] = array(
	'strasse' => 'street',
	'plz' => 'postCode',
	'ort' => 'city',
	'gemeinde' => 'additionalAddressInformation',
	//if data is returned as array, type is name of field where value is stored
	'nation' => array('name' => 'country', 'type' => 'description')
);

$config['fieldmappings']['address']['kontakttel'] = array(
	'kontakt' => 'telNumber'
);

$config['fieldmappings']['application']['kontaktmail'] = array(
	'kontakt' => 'email'
);

$config['fieldmappings']['application']['kontaktnotfall'] = array(
	'kontakt' => 'bew_tel_nr_kontakt'
);

$config['fieldmappings']['application']['studiengang'] = array(
	'typ' => 'stud_niveau_id'
);

$config['fieldmappings']['application']['konto'] = array(
	'buchungstyp_kurzbz' => 'aust_prog_id',
	'betrag' => 'aust_prog_id',
	'buchungstext' => 'aust_prog_id',
	'studiengang_kz' => 'studr_id'
);;

$config['fieldmappings']['incomingcourse']['lehrveranstaltung'] = array(
	'mobezeichnung' => 'hostCourseName',
);

$config['fieldmappings']['incomingcourse']['mostudiengang'] = array(
	'bezeichnung' => 'studyFieldDescription'
);

$config['fieldmappings']['course'] = array(
	'lv_bezeichnung' => 'courseName',
	'studienjahr_kurzbz' => array('name' => 'academicYear', 'type' => 'description'),
	'studiensemester_kurzbz' => 'semester',
	'semester' => 'semesterNr',
	'studiengang_kuerzel' => array('name' => 'studyField', 'type' => 'number'),
	'lehrform_kurzbz' => array('name' => 'courseType', 'type' => 'number', 'default' => 'LV'),
	'locale' => array('name' => 'language', 'type' => 'number'),
	'sws' => 'numberOfLessons',
	'ects' => 'ectsCredits',
	'incoming' => 'freePlaces',
	'typ' => array('name' => 'studyLevels', 'type' => 'number')
);
