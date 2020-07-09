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
	'b' => 'B',
	'm' => 'M',
	'e' => array('B', 'M')
);

$nations = array(
	'Bosnien Herzegowina' => 'BSH',
	'Canada' => 'CDN',
	'China' => 'CHF',
	'Großbritannien und Nordirland' => 'GB',
	'Korea' => 'ROK',
	'Malaysien' => 'MAL',
	'Mexico' => 'MEX',
	'Moldawien' => 'MLD',
	'Nicaragua' => 'NIC',
	'Russian Federation' => 'RSF',
	'Saudiarabien' => 'SA',
	'Taiwan' => 'RC',
	'Tschechische Republik' => 'TCH',
	'United States of America' => 'USA',
	'Vereinigte Arabische Emirate' => 'VE',
	'Sonstige' => 'XXX',
	'andere' => 'XXX'
);

$config['valuemappings']['frommo']['nation_code'] = $nations;

$config['valuemappings']['frommo']['nation'] = $nations;

$config['valuemappings']['frommo']['staatsbuergerschaft'] = $nations;

$config['valuemappings']['frommo']['sprache'] = array(
	'Englisch' => 'English',
	'Deutsch' => 'German'
);

$config['valuemappings']['frommo']['geschlecht'] = array(
	'M' => 'm',
	'W' => 'w'
);

$config['valuemappings']['frommo']['anrede'] = array(
	'M' => 'Herr',
	'W' => 'Frau',
	'm' => 'Herr',
	'w' => 'Frau'
);

$config['valuemappings']['frommo']['studiengang_kz'] = array(
	'Biomedical Engineering' => 227,
	'Campus International' => 10006,
	'Elektronik' => 254,
	'Elektronik/Wirtschaft' => 255,
	'Embedded Systems' => 297,
	//'Erneuerbare Urbane Energiesysteme' => 578,
	'Erneuerbare Urbane Energiesysteme Master' => 578,
	'Game Engineering und Simulation' => 585,
	'Gesundheits- und Rehabilitationstechnik' => 329,
	'Industrielle Elektronik' => 300,
	'Informatik/Computer Science' => 257,
	'Informations- und Kommunikationssysteme' => 258,
	'Informationsmanagement und Computersicherheit' => 303, /* alte Bezeichnung */
	'IT-Security' => 303,
	'Innovations- und Technologiemanagement' => 301,
	'Integrative Stadtentwicklung-Smart City' => 334,
	'Internationales Wirtschaftsingenieurwesen' => 335,
	'Internationales Wirtschaftsingenieurwesen Master' => 336,
	'Maschinenbau' => 779,
	'Maschinenbau Master' => 804,
	'Mechatronik/Robotik' => 330,
	'Mechatronik/Robotik Master' => 331,
	'Medical Engineering & eHealth' => 228,
	'Smart Homes und Assistive Technologien' => 768,
 	'Softwareentwicklung' => 299, /* alte Bezeichnung */
	'Software Engineering' => 299,
	'Sports Equipment Technology' => 327, /* alte Bezeichnung */
	'Human Factors and Sports Engineering' => 327,
	'Sports Equipment Technology Master' => 328, /* alte Bezeichnung */
	'Sports Technology Master' => 328,
	'Technisches Umweltmanagement und Ökotoxikologie' => 332,
	'Telekommunikation und Internettechnologien' => 298,
	'Tissue Engineering and Regenerative Medicine' => 692,
	'Unbekannt' => 0,
	'Urbane Erneuerbare Energietechnologien' => 476,
	'Verkehr und Umwelt' => 333,
	'Wirtschaftsinformatik' => 256,
	'Wirtschaftsinformatik Master' => 302
);

$config['valuemappings']['frommo']['zgvnation'] = $nations;

$config['valuemappings']['frommo']['zgvmas_code'] = array(
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

$config['valuemappings']['frommo']['zgvmanation'] = $nations;

$config['valuemappings']['frommo']['mobilitaetsprogramm_code'] = array(
	'685' => 7, // Erasmus (Praktikum)
	'688' => 7, // Erasmus (Semester)
	'681' => 7, // Erasmus (Mundus)
	'830' => 201, // Exchange Semester (with Bilateral Agreement)
	'682' => 202, // Free Mover
	'946' => 18 // Marshall Plan Scholarship
	/*	'Erasmus SMS' => 7,
	'Erasmus SMP' => 7,*/
	//'Erasmus (Studies)' => 7,
);

$config['valuemappings']['frommo']['buchungstyp_kurzbz'] = array(
	'685' => array('OEH', 'Studiengebuehr'),
	'688' => array('OEH', 'Studiengebuehr'),
	'681' => array('OEH', 'Studiengebuehr'),
	'830' => array('OEH', 'Studiengebuehr'),
	'682' => array('OEH', 'Studiengebuehr', 'Unkostenbeitrag'),
	'946' => array('OEH', 'Studiengebuehr')
);

// if Betrag is not set here, default from tbl_buchungstyp is used
$config['valuemappings']['frommo']['betrag'] = array(
	'685' => array('Studiengebuehr' => 0.00),
	'688' => array('Studiengebuehr' => 0.00),
	'681' => array('Studiengebuehr' => 0.00),
	'830' => array('Studiengebuehr' => 0.00),
	'682' => array('Studiengebuehr' => 0.00),
	'946' => array('Studiengebuehr' => 0.00)
);

$defaultbuchungen = array('OEH' => 'ÖH-Beitrag STG Semester', 'Studiengebuehr' => 'Studienbeitrag_Incoming');
$config['valuemappings']['frommo']['buchungstext'] = array(
	'685' => $defaultbuchungen,
	'688' => $defaultbuchungen,
	'681' => $defaultbuchungen,
	'830' => $defaultbuchungen,
	'946' => $defaultbuchungen,
	'682' => array('OEH' => 'ÖH-Beitrag STG Semester',
						  'Studiengebuehr' => 'Studienbeitrag STG Semester - Freemover',
						  'Unkostenbeitrag' => 'Unkostenbeitrag STG Semester')
);
