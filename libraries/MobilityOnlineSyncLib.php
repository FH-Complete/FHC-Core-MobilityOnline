<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides generic functionality needed for Mobility Online sync
 * specific objects are synced in subclasses
 */
class MobilityOnlineSyncLib
{
	protected $moObjectType = ''; // type of Mobility Online object to sync
	protected $mobilityonline_config;
	protected $debugmode = false;

	// mapping for assigning fhcomplete field names to MobilityOnline field names
	protected $conffieldmappings = array();
	// mappings of property values which are different in Mobility Online and fhc.
	protected $valuemappings = array();
	// field structure e.g. for checking if all fields are synced correctly
	protected $moconffields = array();

	/**
	 * MobilityOnlineSyncLib constructor.
	 * loads configs (mappings, defaults)
	 */
	public function __construct()
	{
		$this->ci =& get_instance();

		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/config');
		$this->mobilityonline_config = $this->ci->config->item('FHC-Core-MobilityOnline');
		$this->debugmode = isset($this->mobilityonline_config['debugmode']) && $this->mobilityonline_config['debugmode'] === true;

		// load configs and assign as properties
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/fieldmappings');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/valuemappings');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/valuedefaults');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/miscvalues');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/fields');

		$this->conffieldmappings = $this->ci->config->item('fieldmappings');
		$this->valuemappings = $this->ci->config->item('valuemappings');
		$this->moconffields = $this->ci->config->item('mofields');
	}
}
