<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages intermediate table for mapping fhcomplete prestudent ids to application ids in Mobility Online
 */
class Moappidzuordnung_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_mo_appidzuordnung';
		$this->pk = array('prestudent_id', 'mo_applicationid', 'studiensemester');
		$this->hasSequence = false;
	}
}
