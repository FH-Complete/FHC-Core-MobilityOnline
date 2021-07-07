<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages intermediate table for mapping fhcomplete bisio ids to outgoing application ids in Mobility Online
 */
class Mobisioidzuordnung_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_mo_bisioidzuordnung';
		$this->pk = array('bisio_id', 'mo_applicationid');
		$this->hasSequence = false;
	}
}
