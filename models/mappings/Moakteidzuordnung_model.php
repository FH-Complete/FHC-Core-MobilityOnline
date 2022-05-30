<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages intermediate table for mapping fhcomplete bisio ids to outgoing application ids in Mobility Online
 */
class Moakteidzuordnung_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_mo_akteidzuordnung';
		$this->pk = array('akte_id', 'mo_file_id');
		$this->hasSequence = false;
	}
}
