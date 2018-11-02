<?php

// Add Side-Menu-Entry to Main Page
$config['navigation_menu']['Vilesci/index']['administration']['children']['MobilityOnline'] = array(
		'link' => site_url('extensions/FHC-Core-MobilityOnline/MobilityOnline'),
		'icon' => 'exchange',
		'description' => 'MobilityOnline',
		'expand' => true
);

// Add Side-Menu-Entry to Extension Page
/*$config['navigation_menu']['extensions/FHC-Core-Extension/MyExtension/index'] = array(
	'Back' => array(
		'link' => site_url(),
		'description' => 'Zurück',
		'icon' => 'angle-left'
	),
	'Dashboard' => array(
		'link' => '#',
		'description' => 'MobilityOnline Sync',
		'icon' => 'exchange'
	)
);*/
