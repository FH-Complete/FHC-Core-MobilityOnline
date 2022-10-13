<?php
/**
 * Miscellaneous values needed for sync
 */

// Studiengaenge which are considered when syncing syncing Incomings
// all Studiengaenge of given types
$config['miscvalues']['studiengangtypentosync'] = array('b', 'm');
// additional Studiengaenge to consider
$config['miscvalues']['studiengaengetosync'] = array(10006);

// document types to sync, parameter autoaccept defines if documents are automatically accepted after sync
$config['miscvalues']['documentstosync'] = array(
	'incoming' => array(
		'PASS_COPY' => array(
			'autoaccept' => false,
		)
	),
	'outgoing' => array(
		'GRANT_AGREE_SIGNED_FH' => array(
			'autoaccept' => true,
		),
		'CO_ATTENDANCE' => array(
			'autoaccept' => true,
		)
	)
);
