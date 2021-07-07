<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages intermediate table for mapping fhcomplete buchungsnr to course ids in Mobility Online
 */
class Mozahlungidzuordnung_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_mo_zahlungidzuordnung';
		$this->pk = array('buchungsnr', 'mo_zahlungid');
		$this->hasSequence = false;
	}
}
