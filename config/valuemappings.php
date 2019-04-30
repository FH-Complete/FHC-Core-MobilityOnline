<?php
/**
 * Contains value mappings between fhcomplete and MobilityOnline
 * array structure: ['direction']['fhcvaluefieldname']
 */

$config['valuemappings']['tomo']['locale'] = array(
	'de-AT' => 'de',
	'en-US' => 'en'
);

/*$config['valuemappings']['tomo']['typ'] = array(
	'b' => 'B',
	'm' => 'M'
);*/

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
	'Sonstige' => 'XXX'
);

$config['valuemappings']['frommo']['nation_code'] = $nations;

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
	'W' => 'Frau'
);

$config['valuemappings']['frommo']['studiengang_kz'] = array(
	'Biomedical Engineering' => 227,
	'Campus International' => 10006,
	'Elektronik' => 254,
	'Elektronik/Wirtschaft' => 255,
	'Embedded Systems' => 297,
	'Erneuerbare Urbane Energiesysteme' => 578,
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
	'FH-Bachelor (I)' => 1,
	'FH-Bachelor (A)' => 2,
	'postsek.Inland' => 3,
	'postsek.Ausland' => 4,
	'Uni-Bachelor (I)' => 5,
	'Uni-Bachelor (A)' => 6,
	'FH (I)' => 7,
	'FH (A)' => 8,
	'Uni (I)' => 9,
	'Uni (A)' => 10
);

$config['valuemappings']['frommo']['zgvmanation'] = $nations;

$config['valuemappings']['frommo']['mobilitaetsprogramm_code'] = array(
	'Erasmus SMS' => 7,
	'Erasmus SMP' => 7,
	'Erasmus Mundus' => 7,
	'Erasmus (Studies)' => 7,
	'Incoming (mit Agreement)' => 13,
	'Incoming (with Bilateral Agreement or Free Mover)' => 201,
	'Free Mover' => 202
);
