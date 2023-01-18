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
		'studiensemester_kurzbz' => 'mapSemesterToMo',
		'studienjahr_kurzbz' => 'mapStudienjahrToMo',
		'lehrform_kurzbz' => 'mapLehrformToMo'
	);

	/**
	 * SyncToMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_confmodefaults = $this->ci->config->item('modefaults');

		$this->ci->load->library('extensions/FHC-Core-MobilityOnline/tomobilityonline/ToMobilityOnlineDataConversionLib');
	}

	/**
	 * Converts fhcomplete object to MobilityOnline object
	 * Uses only fields defined in fieldmappings config
	 * Uses 1. valuemappings in configs, 2. valuemappings in _replacementsarrToMo otherwise
	 * Also uses fhc valuedefaults to fill moobject
	 * takes unmodified fhcomplete value if field not found in valuemappings
	 * @param object $fhcObj fhcomplete object as received from fhc database
	 * @param string $objType type of object, i.e. table, e.g. person
	 * @return array with MobilityOnline fields and values
	 */
	protected function convertToMoFormat($fhcObj, $objType)
	{
		if (!isset($fhcObj))
			return array();

		$fieldmappings = isset($this->conffieldmappings[$objType]) ? $this->conffieldmappings[$objType] : array();
		$defaults = $this->_confmodefaults[$objType];
		$moObj = array();
		$moValue = null;

		foreach ($fhcObj as $name => $value)
		{
			if (!isset($fieldmappings[$name]))
				continue;

			$fhcValue = $fhcObj->$name;

			// null value - take default if exists
			if (!isset($value))
			{
				if (isset($fieldmappings[$name]['default']))
				{
					if (isset($fieldmappings[$name]['type']) && isset($fieldmappings[$name]['name']))
						$moObj[$fieldmappings[$name]['name']] = array($fieldmappings[$name]['type'] => $fieldmappings[$name]['default']);
					else
						$moObj[$fieldmappings[$name]] = $fieldmappings[$name]['default'];
				}
				continue;
			}

			$moValue = $this->getMoValue($name, $fhcValue);

			if (isset($fhcObj->$name) && isset($moValue))
			{
				// if data has to be passed to MO as array, e.g. array('description' => 'bla')
				if (isset($fieldmappings[$name]['type']) && isset($fieldmappings[$name]['name']))
				{
					// if multiple data values, e.g. Studiengangtyp Bachelor and Master
					if (is_array($moValue))
					{
						$moObj[$fieldmappings[$name]['name']] = array();

						foreach ($moValue as $item)
						{
							$moObj[$fieldmappings[$name]['name']][] = array($fieldmappings[$name]['type'] => $item);
						}
					}
					else
						$moObj[$fieldmappings[$name]['name']] = array($fieldmappings[$name]['type'] => $moValue);
				}
				else
				{
					$moObj[$fieldmappings[$name]] = $moValue;
				}
			}
		}

		// add MO defaults (values with no equivalent in FHC)
		foreach ($defaults as $default)
		{
			foreach ($default as $defaultKey => $defaultValue)
			{
				if (!isset($moObj[$defaultKey]))
					$moObj[$defaultKey] = $defaultValue;
			}
		}

		return $moObj;
	}

	/**
	 * Gets MobilityOnline value which maps to fhcomplete value.
	 * Looks in valuemappings and replacementarray.
	 * @param string $fhcIndex name of fhcomplete field in db
	 * @param mixed $fhcValue fhcomplete value
	 * @return string
	 */
	protected function getMoValue($fhcIndex, $fhcValue)
	{
		$valuemappings = $this->valuemappings['tomo'];

		$moValue = $fhcValue;

		// if exists in valuemappings - take value
		if (!empty($valuemappings[$fhcIndex]) && array_key_exists($moValue, $valuemappings[$fhcIndex]))
		{
			$moValue = $valuemappings[$fhcIndex][$moValue];
		}
		else// otherwise look in replacements array
		{
			if (isset($this->_replacementsarrToMo[$fhcIndex]))
			{
				$replacementFunc = $this->_replacementsarrToMo[$fhcIndex];
				// call replacement function
				if (is_string($replacementFunc) && is_callable(array($this->ci->tomobilityonlinedataconversionlib, $replacementFunc)))
					$moValue = $this->ci->tomobilityonlinedataconversionlib->{$replacementFunc}($fhcValue);
			}
		}

		return $moValue;
	}
}
