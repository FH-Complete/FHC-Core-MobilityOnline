<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/'.EXT_FKT_PATH.'/generateuid.inc.php');
require_once('include/functions.inc.php');

/**
 * Functionality for syncing MobilityOnline objects to fhcomplete
 */
class SyncFromMobilityOnlineLib extends MobilityOnlineSyncLib
{
	// defaults for fhcomplete tables
	private $_conffhcdefaults = array();
	// fielddefinitions for error check before sync to FHC
	protected $fhcconffields = array();

	// user saved in db insertvon, updatevon fields
	const IMPORTUSER = 'mo_import';
	protected $output = '';

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
		),
		'zgvnation' => array(
			0 => 'replaceEmpty'
		),
		'zgvdatum' => array(
			0 => 'mapDateToFhc'
		),
		'zgvmas_code' => array(
			0 => 'replaceEmpty'
		),
		'zgvmanation' => array(
			0 => 'replaceEmpty'
		),
		'zgvmadatum' => array(
			0 => 'mapDateToFhc'
		)/*,
		'lehrveranstaltung_id' => array(
			//extracting lvid from MobilityOnline coursenumber, assuming format id_orgform_ausbildungssemester,
			//e.g. 35408_VZ_2sem
			'(\d+)_(.*)' => '$1'
		)*/
	);

	/**
	 * SyncFromMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_conffhcdefaults = $this->ci->config->item('fhcdefaults');
		$this->fhcconffields = $this->ci->config->item('fhcfields');

		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MobilityonlinefhcModel');
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
		if (!isset($moobj))
			return array();

		$defaults = isset($this->_conffhcdefaults[$objtype]) ? $this->_conffhcdefaults[$objtype] : array();

		// cases where value is different format in MO than in FHC -> valuemappings in config
		$fhcobj = array();
		$fhcvalue = null;

		$moobjfieldmappings = $this->conffieldmappings[$objtype];
		foreach ($moobjfieldmappings as $fhctable => $mapping)
		{
			foreach ($moobj as $name => $value)
			{
				$fhcindeces = array();

				//get all fieldmappings (string or 'name' array key match the MO name)
				foreach ($mapping as $fhcidx => $moval)
				{
					if ($moval === $name || (isset($moval['name']) && $moval['name'] === $name))
						$fhcindeces[] = $fhcidx;
				}

				$movalue = $moobj->$name;

				if (!empty($fhcindeces))
				{
					foreach ($fhcindeces as $fhcindex)
					{
						//if value is in object returned from MO, extract value
						if (is_object($movalue) && isset($mapping[$fhcindex]['type']))
						{
							$configtype = $mapping[$fhcindex]['type'];
							$movalue = $moobj->$name->$configtype;
						}

						$fhcvalue = $this->getFHCValue($fhcindex, $movalue);

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
	 * Gets fhcomplete value which maps to MobilityOnline value.
	 * Looks in valuemappings and replacementarray.
	 * @param $fhcindex name of fhcomplete field in db
	 * @param $movalue MobilityOnline value
	 * @return string
	 */
	protected function getFHCValue($fhcindex, $movalue)
	{
		$valuemappings = $this->valuemappings['frommo'];
		$fhcvalue = $movalue;
		//if exists in valuemappings - take value
		if (!empty($valuemappings[$fhcindex])
			&& array_key_exists($fhcvalue, $valuemappings[$fhcindex])
		)
		{
			$fhcvalue = $valuemappings[$fhcindex][$fhcvalue];
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
					elseif (is_string($replacement))
					{
						//add slashes for regex
						$pattern = '/' . str_replace('/', '\/', $pattern) . '/';
						$fhcvalue = preg_replace($pattern, $replacement, $fhcvalue);
					}
				}
			}
		}
		return $fhcvalue;
	}

	/**
	 * Gets sync output string
	 * @return string
	 */
	public function getOutput()
	{
		return $this->output;
	}

	/**
	 * Gets object for searching an Object in MobilityOnline API
	 * @param $objtype Type of object to search.
	 * @param $searchparams Fields with values to search for.
	 * @return array the object containing search parameters.
	 */
	public function getSearchObj($objtype, $searchparams)
	{
		$searchobj = array();

		$fields = $this->moconffields[$objtype];

		//prefill - also non-searched fields need to be passed with null
		foreach ($fields as $field)
		{
			$searchobj[$field] = null;
		}

		if (is_array($searchparams))
		{
			foreach ($searchparams as $paramname => $param)
			{
				$searchobj[$paramname] = $param;
			}
		}

		return $searchobj;
	}

	/**
	 * Checks if fhcomplete object has errors, like missing fields,
	 * so it cannot be inserted in db
	 * @param $fhcobj
	 * @param $objtype
	 * @return StdClass object with properties bollean for has Error and array with errormessages
	 */
	public function fhcObjHasError($fhcobj, $objtype)
	{
		$hasError = new StdClass();
		$hasError->error = false;
		$hasError->errorMessages = array();
		$allfields = $this->fhcconffields[$objtype];

		foreach ($allfields as $table => $fields)
		{
			if (array_key_exists($table, $fhcobj))
			{
				foreach ($fields as $field => $params)
				{
					$haserror = false;
					$errortext = '';
					$required = isset($params['required']) && $params['required'];

					if (isset($fhcobj[$table][$field]))
					{
						$value = $fhcobj[$table][$field];

						if ($required && !is_numeric($value) && isEmptyString($value))
						{
							$haserror = true;
							$errortext = 'is missing';
						}
						else
						{
							// right data type?
							$wrongdatatype = false;
							if (isset($params['type']))
							{
								switch($params['type'])
								{
									case 'integer':
										if (!is_numeric($value))
										{
											$wrongdatatype = true;
										}
										break;
									case 'boolean':
										if (!is_bool($value))
										{
											$wrongdatatype = true;
										}
										break;
									case 'date':
										if (!$this->_validateDate($value))
										{
											$wrongdatatype = true;
										}
										break;
									case 'string':
										if (!is_string($value))
										{
											$wrongdatatype = true;
										}
										break;
								}
							}
							elseif (!is_string($value))
							{
								$wrongdatatype = true;
							}
							else
							{
								$params['type'] = 'string';
							}

							if ($wrongdatatype)
							{
								$haserror = true;
								$errortext = 'has wrong data type';
							}
							elseif (!$haserror)
							{
								// right string length?
								if ($params['type'] === 'string' && !$this->ci->MobilityonlinefhcModel->checkLength($table, $field, $value))
								{
									$haserror = true;
									$errortext = "is too long ($value)";
								}
								// value referenced with foreign key exists?
								elseif (isset($params['ref']))
								{
									$fkfield = isset($params['reffield']) ? $params['reffield'] : $field;
									$foreignkeyexists = $this->ci->MobilityonlinefhcModel->valueExists($params['ref'], $fkfield, $value);

									if (!hasData($foreignkeyexists))
									{
										$haserror = true;
										$errortext = 'has no match in FHC';
									}
								}
							}
						}
					}
					elseif ($required)
						$haserror = true;

					if ($haserror)
					{
						$fieldname = isset($params['name']) ? $params['name'] : ucfirst($field);

						$hasError->errorMessages[] = ucfirst($table).": $fieldname ".$errortext;
						$hasError->error = true;
					}
				}
			}
			else
			{
				$hasError->errorMessages[] = "data missing: $table";
				$hasError->error = true;
			}
		}

		return $hasError;
	}

	/**
	 * Converts MobilityOnline date to fhcomplete date format
	 * @param $modate
	 * @return mixed
	 */
	private function mapDateToFhc($modate)
	{
		$pattern = '/^(\d{1,2}).(\d{1,2}).(\d{4})$/';
		if (preg_match($pattern, $modate))
		{
			$date = DateTime::createFromFormat('d.m.Y', $modate);
			return date_format($date, 'Y-m-d');
			//return preg_replace('/(\d{2}).(\d{2}).(\d{4})/', '$3-$2-$1', $modate);
		}
		else
			return null;
	}

	/**
	 * Outputs success or error of a db action
	 * @param $modtype insert, update,...
	 * @param $response of db action
	 * @param $table database table
	 */
	protected function log($modtype, $response, $table)
	{
		if ($this->debugmode)
		{
			if (isSuccess($response))
			{
				if (is_array($response->retval))
					$id = implode('; ', $response->retval);
				else
					$id = $response->retval;

				$this->output .= "<br />$table $modtype successful, id " . $id;
			}
			else
			{
				$this->output .= "<br />$table $modtype error";
			}
		}
	}

	/**
	 * Sets timestamp and importuser for insert/update
	 * @param $modtype
	 * @param $arr
	 */
	protected function stamp($modtype, &$arr)
	{
		$idx = $modtype.'amum';
		$arr[$idx] = date('Y-m-d H:i:s', time());
		$idx = $modtype.'von';
		$arr[$idx] = self::IMPORTUSER;
	}

	/**
	 * Checks if given date exists and is valid.
	 * @param $date
	 * @param string $format
	 * @return bool
	 */
	private function _validateDate($date, $format = 'Y-m-d')
	{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) === $date;
	}
}
