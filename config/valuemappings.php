<?php
/**
 * Contains value mappings between fhcomplete and MobilityOnline
 * array structure depending on sync direction:
 * ['tomo']['fhcvaluefieldname'] = array('fhcvalue' => 'mobilityonlinevalue')
 * or ['frommo'] ['fhcvaluefieldname'] = array('mobilityonlinevalue' => 'fhcvalue')
 */

$config['valuemappings']['tomo']['locale'] = array(
	'de-AT' => 'de',
	'en-US' => 'en'
);

$config['valuemappings']['tomo']['typ'] = array(
	'b' => array('B','M'),
	'm' => 'M',
	'e' => array('B', 'M')
);

$nations = array(
	'Bosnien Herzegowina' => 'BSH',
	'Canada' => 'CDN',
	'China' => 'CHF',
	'Elfenbeinküste' => 'CI',
	'Großbritannien und Nordirland' => 'GB',
	'Kirgistan' => 'KRG',
	'Korea' => 'ROK',
	'Malaysien' => 'MAL',
	'Mexico' => 'MEX',
	'Moldawien' => 'MLD',
	'Nicaragua' => 'NIC',
	'Russian Federation' => 'RSF',
	'Saudiarabien' => 'SA',
	'Südkorea' => 'ROK',
	'Taiwan' => 'RC',
	'Tansania' => 'EAT',
	'Tschechische Republik' => 'TCH',
	'United States of America' => 'USA',
	'Vereinigte Arabische Emirate' => 'VE',
	'Sonstige' => 'XXX',
	'andere' => 'XXX'
);

$studiengaenge = array(
	'13996' => 585, // AI Engineering
	'7068' => 227, // Biomedical Engineering
	'6712' => 10006, // Campus International
	'12268' => 854, // Data Science
	'7030' => 254, // Elektronik
	'7031' => 255, // Elektronik/Wirtschaft
	'7035' => 297, // Embedded Systems
	'7049' => 578, // Erneuerbare Urbane Energiesysteme Master
	'7050' => 585, // Game Engineering und Simulation
	'7042' => 329, // Gesundheits- und Rehabilitationstechnik
	'7041' => 327, // Human Factors and Sports Engineering
	'7033' => 257, // Informatik/Computer Science
	'7034' => 258, // Informations- und Kommunikationssysteme
	'13995' => 298, // Internet of Things und intelligente Systeme
	'7040' => 303, // IT-Security
	'7039' => 301, // Innovations- und Technologiemanagement
	'7046' => 334, // Integrative Stadtentwicklung-Smart City
	'7370' => 335, // Internationales Wirtschaftsingenieurwesen
	'7047' => 336, // Internationales Wirtschaftsingenieurwesen Master
	'7372' => 779, // Maschinenbau
	'7053' => 804, // Maschinenbau Master
	'7043' => 330, // Mechatronik/Robotik
	'7373' => 331, // Mechatronik/Robotik Master
	'7029' => 228, // Medical Engineering & eHealth
	'7052' => 768, // Smart Homes und Assistive Technologien
	'7037' => 299, // Software Engineering
	'7369' => 328, // Sports Technology Master
	'7044' => 332, // Technisches Umweltmanagement und Ökotoxikologie
	'7036' => 298, // Telekommunikation und Internettechnologien (alt)
	'7051' => 692, // Tissue Engineering and Regenerative Medicine
	'7048' => 476, // Urbane Erneuerbare Energietechnologien
	'7032' => 256, // Wirtschaftsinformatik
	'7371' => 302 // Wirtschaftsinformatik Master
);

$mobilitaetsprogramme = array(
	'681' => 7, // Erasmus (Mundus)
	'682' => 202, // Free Mover - selbst
	'685' => 7, // Erasmus (Praktikum)
	'688' => 7, // Erasmus (Semester)
	'830' => 201, // Exchange Semester (with Bilateral Agreement) - FH-Mob
	'946' => 18, // Marshall Plan Scholarship - Marshall
	'1037' => 14, // Auslandspraktikum (ohne Zuschuss)
	'1151' => 7, // Erasmus (Kurzzeitmobilität, BIP)
	'1158' => 202, // Kurzzeitmobilität (Sommer-/ Winterschule, Studienreise, Exkursion etc.) - selbst
	'1224' => 7 // virtueller Austausch
	/*	'Erasmus SMS' => 7,
	'Erasmus SMP' => 7,*/
	//'Erasmus (Studies)' => 7,
);

$bisioZwecke = array(
	'1114418' => '1', // Studium
	'1114419' => '2', // Praktikum
	'1114420' => '3', // Studium und Praktikum
	'1114421' => '4', // Diplom-/Masterarbeit bzw. Dissertation
	// '1114422' => '7', TODO: Kurzzeitmobilität

	// if zweck code is defined by Mobilitaetsprogramm
	'681' => '1', // Erasmus (Mundus) - Studium
	'682' => '1', // Free Mover - Studium
	'685' => '2', // Erasmus (Praktikum) - Praktikum
	'688' => '1', // Erasmus (Semester) - Studium
	'830' => '1', // Exchange Semester (with Bilateral Agreement) - Studium
	'946' => '4', // Marshall Plan Scholarship
	'1037' => '2', // Auslandspraktikum (ohne Zuschuss)
	'1151' => '1', // Kurzzeitmobilität BIP
	'1158' => '1', // Kurzzeitmobilität (Sommer-/ Winterschule, Studienreise, Exkursion etc.)
	'1224' => '1' // Virtueller Austausch - Studium
);

$config['valuemappings']['frommo']['application']['bisio']['nation_code'] = $nations;
$config['valuemappings']['frommo']['applicationout']['bisio']['nation_code'] = $nations;

$config['valuemappings']['frommo']['address']['adresse']['nation'] = $nations;
$config['valuemappings']['frommo']['curraddress']['studienadresse']['nation'] = $nations;
$config['valuemappings']['frommo']['instaddress']['institution_adresse']['nation'] = $nations;

$config['valuemappings']['frommo']['application']['person']['staatsbuergerschaft'] = $nations;

$config['valuemappings']['frommo']['application']['bisio']['herkunftsland_code'] = $nations;
$config['valuemappings']['frommo']['applicationout']['bisio']['herkunftsland_code'] = $nations;

$config['valuemappings']['frommo']['application']['person']['sprache'] = array(
	'Englisch' => 'English',
	'Deutsch' => 'German'
);

$config['valuemappings']['frommo']['application']['person']['geschlecht'] = array(
	'M' => 'm',
	'W' => 'w'
);

$config['valuemappings']['frommo']['application']['person']['anrede'] = array(
	'M' => 'Herr',
	'W' => 'Frau',
	'm' => 'Herr',
	'w' => 'Frau'
);

$config['valuemappings']['frommo']['application']['prestudent']['studiengang_kz'] = $studiengaenge;
$config['valuemappings']['frommo']['applicationout']['prestudent']['studiengang_kz'] = $studiengaenge;
$config['valuemappings']['frommo']['application']['konto']['studiengang_kz'] = $studiengaenge;

$config['valuemappings']['frommo']['application']['prestudent']['zgvnation'] = $nations;

$config['valuemappings']['frommo']['application']['prestudent']['zgvmas_code'] = array(
	'929710' => 1, // FH-Bachelor (I)
	'929711' => 2, // FH-Bachelor (A)
	'929712' => 3, // postsek.Inland
	'929713' => 4, // postsek.Ausland
	'929714' => 5, // Uni-Bachelor (I)
	'929715' => 6, // Uni-Bachelor (A)
	'929716' => 7, // FH (I)
	'929717' => 8, // FH (A)
	'929718' => 9, // Uni (I)
	'929719' => 10, // Uni (A)
	'929720' => 11 // Sonstige
);

$config['valuemappings']['frommo']['application']['prestudent']['zgvmanation'] = $nations;

$config['valuemappings']['frommo']['application']['bisio']['mobilitaetsprogramm_code'] = $mobilitaetsprogramme;
$config['valuemappings']['frommo']['applicationout']['bisio']['mobilitaetsprogramm_code'] = $mobilitaetsprogramme;

$config['valuemappings']['frommo']['application']['bisio_zweck']['zweck_code'] = $bisioZwecke;

$config['valuemappings']['frommo']['application']['konto']['buchungstyp_kurzbz'] = array(
	'681' => array('OEH', 'Studiengebuehr'), // Buchungstyp depends on mobilitaetsprogramm_code
	'682' => array('OEH', 'Studiengebuehr'),
	'685' => array('OEH', 'Studiengebuehr'),
	'688' => array('OEH', 'Studiengebuehr'),
	'830' => array('OEH', 'Studiengebuehr'),
	'946' => array('OEH', 'Studiengebuehr')
);

// if Betrag is not set here, default from tbl_buchungstyp is used
$config['valuemappings']['frommo']['application']['konto']['betrag'] = array(
	'681' => array('Studiengebuehr' => 0.00),// Betrag depends on mobilitaetsprogramm_code
	'682' => array('Studiengebuehr' => 0.00),
	'685' => array('Studiengebuehr' => 0.00),
	'688' => array('Studiengebuehr' => 0.00),
	'830' => array('Studiengebuehr' => 0.00),
	'946' => array('Studiengebuehr' => 0.00)
);

$config['valuemappings']['frommo']['applicationout']['bisio_aufenthaltfoerderung']['aufenthaltfoerderung_code'] = array(
	'1100668' => 1, // EU-Förderung
	'1100669' => 2, // Beihilfe von Bund, Land, Gemeinde
	'1100670' => 3, // Förderung durch Universität/Hochschule
	'1100671' => 4, // andere Förderung
	'1100672' => 5 // keine Förderung
);

$config['valuemappings']['frommo']['applicationout']['bisio_zweck']['zweck_code'] = $bisioZwecke;

$defaultbuchungen = array('OEH' => 'ÖH-Beitrag STG Semester', 'Studiengebuehr' => 'Studienbeitrag_Incoming');
$config['valuemappings']['frommo']['application']['konto']['buchungstext'] = array(
	'681' => $defaultbuchungen,
	'682' => $defaultbuchungen,
	'685' => $defaultbuchungen,
	'688' => $defaultbuchungen,
	'830' => $defaultbuchungen,
	'946' => $defaultbuchungen
	/**'682' => array('OEH' => 'ÖH-Beitrag STG Semester',
						'Studiengebuehr' => 'Studienbeitrag STG Semester - Freemover',
						'Unkostenbeitrag' => 'Unkostenbeitrag STG Semester')**/
);

$config['valuemappings']['frommo']['file']['akte']['dokument_kurzbz'] = array(
	'PASS_COPY' => 'identity',
	'GRANT_AGREE_SIGNED_FH' => 'GrantAgr'
);

$config['valuemappings']['frommo']['file']['akte']['dokument_bezeichnung'] = array(
	'PASS_COPY' => 'Identitätsnachweis',
	'GRANT_AGREE_SIGNED_FH' => 'Grant Agreement'
);
