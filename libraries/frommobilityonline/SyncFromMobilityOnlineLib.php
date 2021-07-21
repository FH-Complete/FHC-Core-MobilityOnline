<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Functionality for syncing MobilityOnline objects to fhcomplete
 */
class SyncFromMobilityOnlineLib extends MobilityOnlineSyncLib
{
	// defaults for fhcomplete tables
	private $_conffhcdefaults = array();
	// fielddefinitions for error check before sync to FHC
	protected $fhcconffields = array();

	// types for syncouput
	const ERROR_TYPE = 'error';
	const SUCCESS_TYPE = 'success';
	const INFO_TYPE = 'info';

	// user saved in db insertvon, updatevon fields
	const IMPORTUSER = 'mo_import';

	// output array containing errors, success etc messages when syncing
	protected $output = array();

	// fhc value replacements for mo values
	private $_replacementsarrToFHC = array(
		'gebdatum' => array(
			0 => '_mapDateToFhc'
		),
		'von' => array(
			0 => '_mapDateToFhc'
		),
		'bis' => array(
			0 => '_mapDateToFhc'
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
			0 => '_mapDateToFhc'
		),
		'zgvmas_code' => array(
			0 => 'replaceEmpty'
		),
		'zgvmanation' => array(
			0 => 'replaceEmpty'
		),
		'zgvmadatum' => array(
			0 => '_mapDateToFhc'
		),
		'inhalt' => array(
			0 => '_resizeBase64ImageBig'
		),
		'foto' => array(
			0 => '_resizeBase64ImageSmall'
		),
		'student_uid' => array(
			0 => 'replaceEmpty'
		),
		'aufenthaltfoerderung_code' => array(
			0 => 'replaceEmpty'
		),
		'zweck_code' => array(
			0 => 'replaceEmpty'
		),
		'ects_erworben' => array(
			0 => '_mapEctsToFhc'
		),
		'ects_angerechnet' => array(
			0 => '_mapEctsToFhc'
		)
		/*,
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


	/** ---------------------------------------------- Public methods ------------------------------------------------*/

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
	 * Checks if fhcomplete object has errors, e.g. missing fields, thus cannot be inserted in db.
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

						if ($required && !is_numeric($value) && !is_bool($value) && isEmptyString($value))
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
										if (!is_int($value) && !ctype_digit($value))
										{
											$wrongdatatype = true;
										}
										break;
									case 'float':
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
									case 'base64':
										if (!base64_encode(base64_decode($value, true)) === $value)
											$wrongdatatype = true;
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
								if (($params['type'] === 'string' || $params['type'] === 'base64') &&
									!$this->ci->MobilityonlinefhcModel->checkLength($table, $field, $value))
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
					{
						$haserror = true;
						$errortext = 'does not exist';
					}

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
				// if required table not present in object - show error
				$hasError->errorMessages[] = "data missing: $table";
				$hasError->error = true;
			}
		}

		return $hasError;
	}

	/**
	 * Gets max allowed post parameter length from php_ini config.
	 * Can be e.g. "4M" for 4 Megabyte or a number in bytes.
	 * If no value can be retrieved from ini, own configuration value is used.
	 * @return string|null
	 */
	public function getPostMaxSize()
	{
		$max_size_res = null;

		$post_max_size = ini_get('post_max_size');

		$max_size_res = $this->_extractPostMaxSize($post_max_size);

		if (!isset($max_size_res))
		{
			$config = $this->ci->config->item('FHC-Core-MobilityOnline');
			$post_max_size = $config['post_max_size'];
			$max_size_res = $this->_extractPostMaxSize($post_max_size);
		}

		return $max_size_res;
	}

	/** ---------------------------------------------- Protected methods ------------------------------------------------*/

	/**
	 * Converts MobilityOnline object to fhcomplete object
	 * Uses only fields defined in fieldmappings config
	 * Uses 1. valuemappings in configs, 2. valuemappings in _replacementsarrToFHC otherwise
	 * Also uses fhc valuedefaults to fill fhcobject
	 * takes unmodified MobilityOnline value if field not found in valuemappings
	 * @param $moObj MobilityOnline object as received from API
	 * @param $objType type of object, e.g. application
	 * @return array with fhcomplete fields and values
	 */
	protected function convertToFhcFormat($moObj, $objType)
	{
		if (!isset($moObj))
			return array();

		$defaults = isset($this->_conffhcdefaults[$objType]) ? $this->_conffhcdefaults[$objType] : array();

		// cases where value is different format in MO than in FHC -> valuemappings in config
		$fhcObj = array();
		$fhcValue = null;

		$moobjFieldmappings = $this->conffieldmappings[$objType];

		foreach ($moobjFieldmappings as $fhcTable => $mapping)
		{
			foreach ($moObj as $name => $value)
			{
				$fhcIndeces = array();

				//get all fieldmappings (string or 'name' array key match the MO name)
				foreach ($mapping as $fhcIdx => $moVal)
				{
					if ($moVal === $name || (isset($moVal['name']) && $moVal['name'] === $name))
						$fhcIndeces[] = $fhcIdx;
				}

				$moValue = $moObj->$name;

				if (!empty($fhcIndeces))
				{
					foreach ($fhcIndeces as $fhcIndex)
					{
						// if value is in object returned from MO, extract value

						// if data is returned as array, type is name of field where value is stored
						if (is_object($moValue) && isset($mapping[$fhcIndex]['type']))
						{
							$configType = $mapping[$fhcIndex]['type'];
							$moValue = $moObj->$name->$configType;
						}

						// convert extracted value to fhc value
						$fhcValue = $this->getFHCValue($fhcIndex, $moValue);

						$fhcObj[$fhcTable][$fhcIndex] = $fhcValue;
					}
				}
			}
		}

		// add FHC defaults (values with no equivalent in MO)
		foreach ($defaults as $fhcKey => $fhcDefault)
		{
			foreach ($fhcDefault as $defaultKey => $defaultValue)
			{
				if (!isset($fhcObj[$fhcKey][$defaultKey]))
					$fhcObj[$fhcKey][$defaultKey] = $defaultValue;
			}
		}

		return $fhcObj;
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
			&& isset($valuemappings[$fhcindex][$fhcvalue])
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
	 * Outputs success or error of a db modification.
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

				$this->_setOutput(self::INFO_TYPE, "$table $modtype successful, id " . $id);
			}
			else
			{
				$this->_setOutput(self::ERROR_TYPE, "$table $modtype error");
			}
		}
	}

	/**
	 * Sets timestamp and importuser for insert/update.
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

	protected function addErrorOutput($text)
	{
		$this->_setOutput(self::ERROR_TYPE, $text);
	}

	protected function addInfoOutput($text)
	{
		$this->_setOutput(self::INFO_TYPE, $text);
	}

	protected function addSuccessOutput($text)
	{
		$this->_setOutput(self::SUCCESS_TYPE, $text);
	}

	/** ---------------------------------------------- Private methods -----------------------------------------------*/

	/**
	 * Extracts numeric post size from post_max_size string.
	 * @param $post_max_size
	 * @return string|null
	 */
	private function _extractPostMaxSize($post_max_size)
	{
		$max_size_res = null;

		if (is_numeric($post_max_size) && (int)$post_max_size > 0)
		{
			$max_size_res = $post_max_size;
		}
		else
		{
			$sizeregex = '/\d+([KMG])/';

			if (preg_match($sizeregex ,$post_max_size))
			{
				preg_match_all('/\d+/', $post_max_size, $size);
				preg_match_all('/\D+/', $post_max_size, $unit);
				if (isset($unit[0][0]) && isset($size[0][0]))
				{
					$unit = $unit[0][0];
					$size = $size[0][0];

					$max_size_res = $size;

					$thousands = 0;
					switch ($unit)
					{
						case 'K':
							$thousands = 1;
							break;
						case 'M':
							$thousands = 2;
							break;
						case 'G':
							$thousands = 3;
							break;
					}

					for ($i = 0; $i < $thousands; $i++)
					{
						$max_size_res .= '000';
					}
				}
			}
		}

		return $max_size_res;
	}

	/**
	 * Converts MobilityOnline date to fhcomplete date format.
	 * @param $modate
	 * @return mixed
	 */
	private function _mapDateToFhc($modate)
	{
		$pattern = '/^(\d{1,2}).(\d{1,2}).(\d{4})$/';
		if (preg_match($pattern, $modate))
		{
			$date = DateTime::createFromFormat('d.m.Y', $modate);
			return date_format($date, 'Y-m-d');
		}
		else
			return null;
	}

	/**
	 * Converts MobilityOnline ects amount to fhcomplete format.
	 * @param $moects
	 * @return mixed
	 */
	private function _mapEctsToFhc($moects)
	{
		$pattern = '/^(\d+),(\d{2})$/';
		if (preg_match($pattern, $moects))
		{
			return (float) str_replace(',', '.', $moects);
		}
		else
			return null;
	}

	/**
	 * Makes sure base 64 image is not bigger than thumbnail size.
	 * @param $moimage
	 * @return string|null
	 */
	private function _resizeBase64ImageSmall($moimage)
	{
		return $this->_resizeBase64Image($moimage, 101, 130);
	}

	/**
	 * Makes sure base 64 image is not bigger than max fhc db image size.
	 * @param $moimage
	 * @return string|null
	 */
	private function _resizeBase64ImageBig($moimage)
	{
		return $this->_resizeBase64Image($moimage, 827, 1063);
	}

	/**
	 * If $moimage width/height is greater than given width/height, crop image, otherwise encode it.
	 * @param $moimage
	 * @param $maxwidth
	 * @param $maxheight
	 * @return string|null
	 */
	private function _resizeBase64Image($moimage, $maxwidth, $maxheight)
	{
		$fhcimage = null;

		//groesse begrenzen
		$width = $maxwidth;
		$height = $maxheight;
		$image = imagecreatefromstring(base64_decode(base64_encode($moimage)));

		if ($image)
		{
			$uri = 'data://application/octet-stream;base64,' . base64_encode($moimage);
			list($width_orig, $height_orig) = getimagesize($uri);

			$ratio_orig = $width_orig/$height_orig;

			if ($width_orig > $width || $height_orig > $height )
			{
				//keep proportions
				if ($width/$height > $ratio_orig)
				{
					$width = $height*$ratio_orig;
				}
				else
				{
					$height = $width/$ratio_orig;
				}

				$bg = imagecreatetruecolor($width, $height);
				imagecopyresampled($bg, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

				ob_start();
				imagejpeg($bg);
				$contents =  ob_get_contents();
				ob_end_clean();

				$fhcimage = base64_encode($contents);
			}
			else
				$fhcimage = base64_encode($moimage);
			imagedestroy($image);
		}

		return $fhcimage;
	}

	private function _setOutput($type, $text)
	{
		$outputObj = new stdClass();

		$outputObj->type = $type;
		$outputObj->text = $text;
		$this->output[] = $outputObj;
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
