<?php
/**
 * Miscellaneous fields needed for sync
 */

// Studiengaenge which are considered when syncing syncing Incomings
// all Studiengaenge of given types
$config['values']['studiengangtypentosync'] = array('b', 'm');
// additional Studiengaenge to consider
$config['values']['studiengaengetosync'] = array(10006);

// stati in application cycle, for displaying last status
// in chronological order!!
$config['values']['pipelinestati'] = array(
	'is_mail_best_bew',
	'is_registriert',
	'is_mail_best_reg',
	'is_pers_daten_erf',
	'is_abgeschlossen'
);
