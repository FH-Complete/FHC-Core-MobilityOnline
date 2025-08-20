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
		)
	)
);

// priorities for saving Orgform (lower index - higher priority)
$config['miscvalues']['orgform_priorities'] = array(0 => 'VZ', 1 => 'BB');

// fallback for Orgform: if no Orgform found, get Orgform by Studiengang type
$config['miscvalues']['orgform_studiengangtyp_fallback'] = array('b' => 'VZ', 'm' => 'BB');
