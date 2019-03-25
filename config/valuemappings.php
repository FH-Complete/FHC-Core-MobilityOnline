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
	'Elektronik' => 254,
	'Wirtschaftsinformatik' => 256,
	'Wirtschaftsinformatik Master' => 302,
	'Integrative Stadtentwicklung-Smart City' => 334,
	'Internationales Wirtschaftsingenieurwesen' => 335,
	'Informatik/Computer Science' => 257,
	'Maschinenbau' => 804,
	'Elektronik/Wirtschaft' => 255
);

$config['valuemappings']['frommo']['zgvnation'] = $nations;

$config['valuemappings']['frommo']['zgvmas_code'] = array(
	'postsek.Ausland' => 4,
	'postsek.Inland' => 3,
	'FH-Bachelor (A)' => 2,
	'FH-Bachelor (I)' => 1,
	'FH (I)' => 7,
	'FH (A)' => 8,
	'Uni-Bachelor (A)' => 6,
	'Uni-Bachelor (I)' => 5,
	'Uni (I)' => 9,
	'Uni (A)' => 10
);

$config['valuemappings']['frommo']['zgvmanation'] = $nations;

$config['valuemappings']['frommo']['mobilitaetsprogramm_code'] = array(
	'Erasmus SMS' => '7',
	'Erasmus SMP' => '7',
	'Erasmus Mundus' => '7',
);
