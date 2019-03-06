<?php

// Add Side-Menu-Entry to Main Page
$config['navigation_menu']['Vilesci/index']['administration']['children']['MobilityOnline'] = array(
	'link' => site_url('extensions/FHC-Core-MobilityOnline/MobilityOnlineCourses'),
	'icon' => 'exchange',
	'description' => 'MobilityOnline Sync',
	'expand' => false
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
		'description' => 'MobilityOnline Courses Sync',
		'icon' => 'graduation-cap'
	),
	'MobilityOnline Incoming sync' => array(
		'link' => site_url('extensions/FHC-Core-MobilityOnline/MobilityOnlineIncoming'),
		'description' => 'MobilityOnline Incoming Sync',
		'icon' => 'group'
	),
	'MobilityOnline Incoming courses assignment' => array(
		'link' => site_url('extensions/FHC-Core-MobilityOnline/MobilityOnlineIncomingCourses'),
		'description' => 'MobilityOnline Incoming Courses Assignment',
		'icon' => 'group'
	)
);
