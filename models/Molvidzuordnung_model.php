<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages intermediate table for mapping fhcomplete lv ids to course ids in Mobility Online
 */
class Molvidzuordnung_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_mo_lvidzuordnung';
		$this->pk = array('lvid', 'molvid', 'studiensemester');
		$this->hasSequence = false;
	}
}
