<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages table for Lehrveranstaltungen from Mobility Online
 */
class Mooutgoinglv_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_mo_outgoing_lv';
		$this->pk = array('outgoing_lehrveranstaltung_id');
		$this->hasSequence = false;
	}
}
