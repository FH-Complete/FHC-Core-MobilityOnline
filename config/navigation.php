<?php

// Add Menu-Entry to Main Page
$config['navigation_header']['*']['Personen']['children']['MobilityOnline'] = array(
	'link' => site_url('extensions/FHC-Core-MobilityOnline/MobilityOnlineCourses'),
	'description' => 'MobilityOnline Sync',
	'expand' => false,
	'requiredPermissions' => 'inout/incoming:rw'
);

// Add Side-Menu-Entry to Extension Page
$config['navigation_menu']['extensions/FHC-Core-MobilityOnline/*'] = array(
	'Back' => array(
		'link' => site_url(),
		'description' => 'Zurück',
		'icon' => 'angle-left'
	),
	'MobilityOnline Courses sync' => array(
		'link' => site_url('extensions/FHC-Core-MobilityOnline/MobilityOnlineCourses'),
		'description' => 'Courses',
		'icon' => 'graduation-cap'
	),
	'MobilityOnline Incoming sync' => array(
		'link' => site_url('extensions/FHC-Core-MobilityOnline/MobilityOnlineIncoming'),
		'description' => 'Incomings',
		'icon' => 'group'
	),
	'MobilityOnline Incoming courses assignment' => array(
		'link' => site_url('extensions/FHC-Core-MobilityOnline/MobilityOnlineIncomingCourses'),
		'description' => 'Incoming Courses',
		'icon' => 'group'
	)
);
