<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides functionality needed for Mobility Online sync
 */
class MobilityOnlineSyncLib
{
	// mapping for assigning fhcomplete field names to MobilityOnline field names
	protected $conffieldmappings = array();
	// Mappings of property values which are different in Mobility Online and fhc.
	private $_valuemappings = array();
	// defaults for fhcomplete tables
	private $_conffhcdefaults = array();
	// separate fielddefinitions
	protected $conffields = array();

	// mo string replacements for fhc values. Numeric indices mean callback function names used for replacements.
	private $_replacementsarrToMo = array(
		'studiensemester_kurzbz' => array(
			0 => 'mapSemesterToMo'
		),
		'studienjahr_kurzbz' => array(
			0 => 'mapStudienjahrToMo'
		),
		'lehrform_kurzbz' => array(
			'^ILV.*$' => 'LV',
			'^SE.*$' => 'SE',
			'^VO.*$' => 'VO',
			'^(?!(ILV|SE|VO)).*$' => 'LV'
		)
	);

	// fhc string replacements for mo values
	private $_replacementsarrToFHC = array(
		'gebdatum' => array(
			0 => 'mapDateToFhc'
		),
		'von' => array(
			0 => 'mapDateToFhc'
		),
		'bis' => array(
			0 => 'mapDateToFhc'
		),
		'studiengang_kz' => array(
			'.+' => ''//empty string if no studiengang
		),
		'anmerkung' => array(
			0 => 'replaceEmpty'
		)
	);

	/**
	 * MobilityOnlineSyncLib constructor.
	 * loads configs (mappings, defaults)
	 */
	public function __construct()
	{
		$this->ci =& get_instance();

		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/fieldmappings');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/valuemappings');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/valuedefaults');

		$this->conffieldmappings = $this->ci->config->item('fieldmappings');
		$this->_valuemappings = $this->ci->config->item('valuemappings');
		$this->_conffhcdefaults = $this->ci->config->item('fhcdefaults');
		$this->_confmodefaults = $this->ci->config->item('modefaults');
		$this->conffields = $this->ci->config->item('fields');

		$this->_setSemesterMappings();
		$this->_setStudienjahrMappings();
	}

	/**
	 * Converts MobilityOnline object to fhcomplete object
	 * Uses only fields defined in fieldmappings config
	 * Uses 1. valuemappings in configs, 2. valuemappings in _replacementsarrToFHC otherwise
	 * Also uses fhc valuedefaults to fill fhcobject
	 * takes unmodified MobilityOnline value if field not found in valuemappings
	 * @param $moobj MobilityOnline object as received from API
	 * @param $objtype type of object, e.g. application
	 * @return array with fhcomplete fields and values
	 */
	protected function convertToFhcFormat($moobj, $objtype)
	{
		$defaults = isset($this->_conffhcdefaults[$objtype]) ? $this->_conffhcdefaults[$objtype] : array();
		$valuemappings = $this->_valuemappings['frommo'];

		// cases where value is different format in MO than in FHC -> valuemappings in config
		$fhcobj = array();
		$fhcvalue = null;

		$moobjfieldmappings = $this->conffieldmappings[$objtype];
		foreach ($moobjfieldmappings as $fhctable => $mapping)
		{
			foreach ($moobj as $name => $value)
			{
				$fhcindeces = array_keys($mapping, $name);
				$fhcvalue = $moobj->$name;

				//if exists in valuemappings - take value
				if (!empty($fhcindeces))
				{
					foreach($fhcindeces as $fhcindex)
					{
						if (!empty($valuemappings[$fhcindex])
							&& array_key_exists($moobj->$name, $valuemappings[$fhcindex])
						)
						{
							$fhcvalue = $valuemappings[$fhcindex][$moobj->$name];
						}
						else//otherwise look in replacements array
						{
							if (isset($this->_replacementsarrToFHC[$fhcindex]))
							{
								foreach ($this->_replacementsarrToFHC[$fhcindex] as $pattern => $replacement)
								{
									//if numeric index, execute callback
									if (is_integer($pattern))
										$fhcvalue = $this->$replacement($fhcvalue);
									//otherwise replace with regex
									else
									{
										//add slashes for regex
										$pattern = '/' . str_replace('/', '\/', $pattern) . '/';
										$fhcvalue = preg_replace($pattern, $replacement, $fhcvalue);
									}
								}
							}
						}
						$fhcobj[$fhctable][$fhcindex] = $fhcvalue;
					}
				}
			}
		}

		// add FHC defaults (values with no equivalent in MO)
		foreach ($defaults as $fhckey => $fhcdefault)
		{
			foreach ($fhcdefault as $defaultkey => $defaultvalue)
			{
				if (!isset($fhcobj[$fhckey][$defaultkey]))
					$fhcobj[$fhckey][$defaultkey] = $defaultvalue;
			}
		}

		return $fhcobj;
	}

	/**
	* Converts fhcomplete object to MobilityOnline object
	* Uses only fields defined in fieldmappings config
	* Uses 1. valuemappings in configs, 2. valuemappings in _replacementsarrToMo otherwise
	* Also uses fhc valuedefaults to fill moobject
	* takes unmodified fhcomplete value if field not found in valuemappings
	* @param $fhcobj fhcomplete object as received from fhc database
	* @param $objtype type of object, i.e. table, e.g. person
	* @return array with MobilityOnline fields and values
	*/
	protected function convertToMoFormat($fhcobj, $objtype)
	{
		$fieldmappings = isset($this->conffieldmappings[$objtype]) ? $this->conffieldmappings[$objtype] : array();
		$defaults = $this->_confmodefaults[$objtype];
		$valuemappings = $this->_valuemappings['tomo'];
		$moobj = array();
		$movalue = null;

		foreach ($fhcobj as $name => $value)
		{
			if (!isset($fieldmappings[$name]))
				continue;

			$movalue = $fhcobj->$name;

			// null value - take default if exists
			if (!isset($value))
			{
				if (isset($fieldmappings[$name]['default']))
				{
					if (isset($fieldmappings[$name]['type']) && isset($fieldmappings[$name]['name']))
						$moobj[$fieldmappings[$name]['name']] = array($fieldmappings[$name]['type'] => $fieldmappings[$name]['default']);
					else
						$moobj[$fieldmappings[$name]] = $fieldmappings[$name]['default'];
					continue;
				}
				else
				{
					continue;
				}
			}

			//if exists in valuemappings - take value
			if (!empty($valuemappings[$name])
				&& array_key_exists($fhcobj->$name, $valuemappings[$name])
			)
			{
				$movalue = $valuemappings[$name][$fhcobj->$name];
			}
			else//otherwise look in replacements array
			{
				if (isset($this->_replacementsarrToMo[$name]))
				{
					foreach ($this->_replacementsarrToMo[$name] as $pattern => $replacement)
					{
						//if numeric index, execute callback
						if (is_integer($pattern))
							$movalue = $this->$replacement($movalue);
						//otherwise replace with regex
						else
						{
							//add slashes for regex
							$pattern = '/' . str_replace('/', '\/', $pattern) . '/';
							$movalue = preg_replace($pattern, $replacement, $movalue);
						}
					}
				}
			}

			if (isset($fhcobj->$name))
			{
				// if data has to be passed to MO as array, eg array('description' => 'bla')
				if (isset($fieldmappings[$name]['type']) && isset($fieldmappings[$name]['name']))
					$moobj[$fieldmappings[$name]['name']] = array($fieldmappings[$name]['type'] => $movalue);
				else
				{
					$moobj[$fieldmappings[$name]] = $movalue;
				}
			}
		}

		// add MO defaults (values with no equivalent in FHC)
		foreach ($defaults as $default)
		{
			foreach ($default as $defaultkey => $defaultvalue)
			{
				if (!isset($moobj[$defaultkey]))
					$moobj[$defaultkey] = $defaultvalue;
			}
		}

		return $moobj;
	}

	/**
	 * Converts fhc studiensemester to MobilityOnline format - e.g. Wintersemester 2018/2019
	 * @param $studiensemester_kurzbz
	 * @return MobilityOnline studiensemester
	 */
	public function mapSemesterToMo($studiensemester_kurzbz)
	{
		$mosem = $studiensemester_kurzbz;

		$year = substr($studiensemester_kurzbz, -4, 4);

		$mosem = preg_replace('/WS\d{4}/', 'Wintersemester '
			.$year.'/'.($year+1), $mosem);

		$mosem = str_replace('SS', 'Sommersemester ', $mosem);

		return $mosem;
	}

	/**
	 * Converts fhc studienjahr to MobilityOnline format - e.g. 2018/2019
	 * @param $studienjahr_kurzbz
	 * @return MobilityOnline studienjahr
	 */
	public function mapStudienjahrToMo($studienjahr_kurzbz)
	{
		$mojahr = $studienjahr_kurzbz;

		$year = substr($studienjahr_kurzbz, 0, strpos($studienjahr_kurzbz, '/'));

		$pattern = '/' . str_replace('/', '\/', $mojahr) . '/';

		$mojahr = preg_replace($pattern, $year.'/'.($year+1), $mojahr);

		return $mojahr;
	}

	/**
	 * Sets valuemappings for all studiensemester
	 */
	private function _setSemesterMappings()
	{
		$this->ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		$allstudiensemester = $this->ci->StudiensemesterModel->load();

		if (hasData($allstudiensemester))
		{
			foreach ($allstudiensemester->retval as $studiensemester)
			{
				$semobj = new StdClass();
				$semobj->studiensemester_kurzbz = $studiensemester->studiensemester_kurzbz;
				$convobj = $this->convertToMoFormat($semobj, 'course');

				$this->_valuemappings['tomo']['studiensemester_kurzbz'][$studiensemester->studiensemester_kurzbz] = $convobj[$this->conffieldmappings['course']['studiensemester_kurzbz']];
				$this->_valuemappings['frommo']['studiensemester_kurzbz'][$convobj[$this->conffieldmappings['course']['studiensemester_kurzbz']]] = $studiensemester->studiensemester_kurzbz;
			}
		}
	}

	/**
	 * Sets valuemappings for all studienjahre
	 */
	private function _setStudienjahrMappings()
	{
		$this->ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');

		$allstudienjahre = $this->ci->StudienjahrModel->load();

		if (hasData($allstudienjahre))
		{
			foreach ($allstudienjahre->retval as $studienjahr)
			{
				$semobj = new StdClass();
				$semobj->studienjahr_kurzbz = $studienjahr->studienjahr_kurzbz;
				$convobj = $this->convertToMoFormat($semobj, 'course');
				$mojahr = $convobj[$this->conffieldmappings['course']['studienjahr_kurzbz']['name']][$this->conffieldmappings['course']['studienjahr_kurzbz']['type']];

				$this->_valuemappings['tomo']['studienjahr_kurzbz'][$studienjahr->studienjahr_kurzbz] = $mojahr;
				$this->_valuemappings['frommo']['studienjahr_kurzbz'][$mojahr] = $studienjahr->studienjahr_kurzbz;
			}
		}
	}

	/**
	 * Converts MobilityOnline date to fhcomplete date format
	 * @param $modate
	 * @return mixed
	 */
	private function mapDateToFhc($modate)
	{
		return preg_replace('/(\d{2}).(\d{2}).(\d{4})/', '$3-$2-$1', $modate);
	}

	private function replaceEmpty($string)
	{
		if (isEmptyString($string))
			return null;
		return $string;
	}
}
