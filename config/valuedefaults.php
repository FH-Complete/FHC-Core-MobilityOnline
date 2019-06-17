<?php
/**
 * Contains value defaults for fhcomplete and MobilityOnline fields that have no valuemapping
 */

$config['fhcdefaults']['application']['prestudent'] = array(
	'bismelden' => true,
	'aufmerksamdurch_kurzbz' => 'k.A.',
	'reihungstestangetreten' => false,
	/*'berufstaetigkeit_code' => 0,*/
	'zgv_code' => 5,
	'gsstudientyp_kurzbz' => 'Intern'
);

$config['fhcdefaults']['application']['prestudentstatus'] = array(
	'ausbildungssemester' => 0,
	'status_kurzbz' => 'Incoming'
);

$config['fhcdefaults']['application']['benutzer'] = array(
	'aktiv' => true
);

$config['fhcdefaults']['application']['student'] = array(
	'semester' => 0,
	'verband' => 'I',
	'gruppe' => ''
);

$config['fhcdefaults']['application']['studentlehrverband'] = array(
	'semester' => 0,
	'verband' => 'I',
	'gruppe' => ''
);

$config['fhcdefaults']['application']['akte'] = array(
	'dokument_kurzbz' => 'Lichtbil',
	'mimetype' => 'image/jpeg',
	'gedruckt' => false,
	'bezeichnung' => 'Lichtbild',
	'nachgereicht' => false
);

$config['fhcdefaults']['address']['adresse'] = array(
	'typ' => 'h',
	'heimatadresse' => true,
	'zustelladresse' => true
);

$config['fhcdefaults']['address']['kontakttel'] = array(
	'kontakttyp' => 'telefon'
);

$config['fhcdefaults']['application']['kontaktmail'] = array(
	'kontakttyp' => 'email',
	'zustellung' => true
);

$config['fhcdefaults']['application']['kontaktnotfall'] = array(
	'kontakttyp' => 'notfallkontakt',
	'zustellung' => false
);

$config['fhcdefaults']['application']['bisio'] = array(
	'zweck_code' => '1'
);

$config['modefaults']['course']['lehrveranstaltung'] = array(
	'applicationType' => 'IN',
	'studyArea' => array('description' => 'FHTW Studiengänge')
);
