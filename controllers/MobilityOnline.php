<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

class MobilityOnline extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(array(
			'index'=>'admin:rw'
			)
		);
	}

	/**
	 * Index Controller
	 * @return void
	 */
	public function index()
	{
		$this->load->library('WidgetLib');
		$this->load->view('extensions/FHC-Core-MobilityOnline/mobilityOnline');
	}
}

