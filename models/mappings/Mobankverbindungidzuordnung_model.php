<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages intermediate table for mapping fhcomplete bankverbindung ids to outgoing application ids in Mobility Online
 */
class Mobankverbindungidzuordnung_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_mo_bankverbindungidzuordnung';
		$this->pk = array('bankverbindung_id', 'mo_person_id');
		$this->hasSequence = false;
	}
}
