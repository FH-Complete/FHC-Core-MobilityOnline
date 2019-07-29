<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for syncing fhcomplete objects to MobilityOnline
 */
class SyncToMobilityOnlineLib extends MobilityOnlineSyncLib
{
	// fielddefinitions for search in MO
	private $_confmodefaults = array();

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

	/**
	 * SyncToMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_confmodefaults = $this->ci->config->item('modefaults');
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
		if (!isset($fhcobj))
			return array();

		$fieldmappings = isset($this->conffieldmappings[$objtype]) ? $this->conffieldmappings[$objtype] : array();
		$defaults = $this->_confmodefaults[$objtype];
		$moobj = array();
		$movalue = null;

		foreach ($fhcobj as $name => $value)
		{
			if (!isset($fieldmappings[$name]))
				continue;

			$fhcvalue = $fhcobj->$name;

			// null value - take default if exists
			if (!isset($value))
			{
				if (isset($fieldmappings[$name]['default']))
				{
					if (isset($fieldmappings[$name]['type']) && isset($fieldmappings[$name]['name']))
						$moobj[$fieldmappings[$name]['name']] = array($fieldmappings[$name]['type'] => $fieldmappings[$name]['default']);
					else
						$moobj[$fieldmappings[$name]] = $fieldmappings[$name]['default'];
				}
				continue;
			}

			$movalue = $this->getMoValue($name, $fhcvalue);

			if (isset($fhcobj->$name) && isset($movalue))
			{
				// if data has to be passed to MO as array, e.g. array('description' => 'bla')
				if (isset($fieldmappings[$name]['type']) && isset($fieldmappings[$name]['name']))
				{
					// if multiple data values, e.g. Studiengangtyp Bachelor and Master
					if (is_array($movalue))
					{
						$moobj[$fieldmappings[$name]['name']] = array();

						foreach ($movalue as $item)
						{
							$moobj[$fieldmappings[$name]['name']][] = array($fieldmappings[$name]['type'] => $item);
						}
					}
					else
						$moobj[$fieldmappings[$name]['name']] = array($fieldmappings[$name]['type'] => $movalue);
				}
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
	 * Gets MobilityOnline value which maps to fhcomplete value.
	 * Looks in valuemappings and replacementarray.
	 * @param $fhcindex name of fhcomplete field in db
	 * @param $fhcvalue fhcomplete value
	 * @return string
	 */
	protected function getMoValue($fhcindex, $fhcvalue)
	{
		$valuemappings = $this->valuemappings['tomo'];

		$movalue = $fhcvalue;

		//if exists in valuemappings - take value
		if (!empty($valuemappings[$fhcindex])
			&& array_key_exists($movalue, $valuemappings[$fhcindex])
		)
		{
			$movalue = $valuemappings[$fhcindex][$movalue];
		}
		else//otherwise look in replacements array
		{
			if (isset($this->_replacementsarrToMo[$fhcindex]))
			{
				foreach ($this->_replacementsarrToMo[$fhcindex] as $pattern => $replacement)
				{
					//if numeric index, execute callback
					if (is_integer($pattern))
						$movalue = $this->$replacement($movalue);
					//otherwise replace with regex
					elseif (is_string($replacement))
					{
						//add slashes for regex
						$pattern = '/' . str_replace('/', '\/', $pattern) . '/';
						$movalue = preg_replace($pattern, $replacement, $movalue);
					}
				}
			}
		}

		return $movalue;
	}
}
