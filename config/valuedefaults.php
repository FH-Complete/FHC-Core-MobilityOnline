<?php
/**
 * Contains value defaults for autofill of fhcomplete and MobilityOnline fields that have no valuemapping
 */

$config['fhcdefaults']['application']['prestudent'] = array(
	'bismelden' => true,
	'aufmerksamdurch_kurzbz' => 'k.A.',
	'reihungstestangetreten' => false,
	/*'berufstaetigkeit_code' => 0,*/
	'zgv_code' => 5,
	'gsstudientyp_kurzbz' => 'Intern',
	'foerderrelevant' => false
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

$config['fhcdefaults']['application']['konto'] = array(
	'mahnspanne' => 30
);

$config['fhcdefaults']['photo']['lichtbild'] = array(
	'dokument_kurzbz' => 'Lichtbil',
	'gedruckt' => false,
	'bezeichnung' => 'Lichtbild',
	'nachgereicht' => false
);

$config['fhcdefaults']['address']['adresse'] = array(
	'typ' => 'h',
	'heimatadresse' => true,
	'zustelladresse' => true
);

$config['fhcdefaults']['curraddress']['studienadresse'] = array(
	'typ' => 'h',
	'heimatadresse' => false,
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

$config['fhcdefaults']['application']['bisio_zweck'] = array(
	'zweck_code' => '1'
);

$config['fhcdefaults']['file']['akte'] = array(
	'gedruckt' => false,
	'nachgereicht' => false
);

$config['fhcdefaults']['applicationout']['bisio_zweck_studium_praktikum'] = array(
	'zweck_code' => '3' // Studium und Praktikum
);

$config['fhcdefaults']['applicationout']['bisio_zweck_masterarbeit'] = array(
	'zweck_code' => '4'  // Diplom-/Masterarbeit bzw. Dissertation
);

$config['fhcdefaults']['applicationout']['bisio_aufenthaltfoerderung_beihilfe'] = array(
	'aufenthaltfoerderung_code' => 2  // Beihilfe von Bund, Land, Gemeinde
);

$config['fhcdefaults']['applicationout']['institution_adresse'] = array(
	'ort' => null,
	'nation' => null
);

$config['fhcdefaults']['bankdetails']['bankverbindung'] = array(
	'typ' => 'p',
	'verrechnung' => true
);

$config['fhcdefaults']['payment']['konto'] = array(
	'buchungstyp_kurzbz' => 'ZuschussIO'
);

$config['modefaults']['course']['lehrveranstaltung'] = array(
	'applicationType' => 'IN',
	'studyArea' => array('description' => 'FHTW Studiengänge')
);
