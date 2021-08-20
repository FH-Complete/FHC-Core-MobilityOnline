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
	 * @param string $objType Type of object to search.
	 * @param array $searchParams Fields with values to search for.
	 * @param bool $withSpecifiedElementsForReturn limit result to only certain applicationElements
	 * @return array the object containing search parameters.
	 */
	public function getSearchObj($objType, $searchParams, $withSpecifiedElementsForReturn = true)
	{
		$searchObj = array();

		$fields = $this->moconffields[$objType];

		//prefill - also non-searched fields need to be passed with null
		foreach ($fields as $field)
		{
			$searchObj[$field] = null;
		}

		if (is_array($searchParams))
		{
			foreach ($searchParams as $paramName => $param)
			{
				$searchObj[$paramName] = $param;
			}
		}

		// specify elements to be included in searchresult
		if ($withSpecifiedElementsForReturn === true)
		{
			$searchObj['specifiedElementsForReturn'] = array();
			$uniqueFieldmappingMoNames = array();

			$moobjFieldmappings = $this->conffieldmappings[$objType];

			foreach ($moobjFieldmappings as $fhcTable => $mapping)
			{
				foreach ($mapping as $name => $value)
				{
					if (!(in_array($value, $uniqueFieldmappingMoNames)))
					{
						$elementToReturn = new StdClass();
						$elementToReturn->elementName = $value;
						$searchObj['specifiedElementsForReturn'][] = $elementToReturn;
						$uniqueFieldmappingMoNames[] = $value;
					}
				}
			}
		}

		return $searchObj;
	}

	/**
	 * Checks if fhcomplete object has errors, e.g. missing fields, thus cannot be inserted in db.
	 * @param array $fhcObj
	 * @param string $objType
	 * @return StdClass object with properties boolean for has Error and array with errormessages
	 */
	public function fhcObjHasError($fhcObj, $objType)
	{
		$hasErrorObj = new StdClass();
		$hasErrorObj->error = false;
		$hasErrorObj->errorMessages = array();
		$allFields = $this->fhcconffields[$objType];

		foreach ($allFields as $table => $fields)
		{
			if (array_key_exists($table, $fhcObj))
			{
				foreach ($fields as $field => $params)
				{
					$hasError = false;
					$errorText = '';
					$required = isset($params['required']) && $params['required'];

					if (isset($fhcObj[$table][$field]))
					{
						$value = $fhcObj[$table][$field];

						if ($required && !is_numeric($value) && !is_bool($value) && isEmptyString($value))
						{
							$hasError = true;
							$errorText = 'fehlt';
						}
						else
						{
							// right data type?
							$wrongDataType = false;
							if (isset($params['type']))
							{
								switch($params['type'])
								{
									case 'integer':
										if (!is_int($value) && !ctype_digit($value))
										{
											$wrongDataType = true;
										}
										break;
									case 'float':
										if (!is_numeric($value))
										{
											$wrongDataType = true;
										}
										break;
									case 'boolean':
										if (!is_bool($value))
										{
											$wrongDataType = true;
										}
										break;
									case 'date':
										if (!$this->_validateDate($value))
										{
											$wrongDataType = true;
										}
										break;
									case 'base64':
										if (!base64_encode(base64_decode($value, true)) === $value)
											$wrongDataType = true;
										break;
									case 'string':
										if (!is_string($value))
										{
											$wrongDataType = true;
										}
										break;
								}
							}
							elseif (!is_string($value))
							{
								$wrongDataType = true;
							}
							else
							{
								$params['type'] = 'string';
							}

							if ($wrongDataType)
							{
								$hasError = true;
								$errorText = 'hat falschen Datentyp';
							}
							elseif (!$hasError)
							{
								// right string length?
								if (($params['type'] === 'string' || $params['type'] === 'base64') &&
									!$this->ci->MobilityonlinefhcModel->checkLength($table, $field, $value))
								{
									$hasError = true;
									$errorText = "ist zu lang ($value)";
								}
								// value referenced with foreign key exists?
								elseif (isset($params['ref']))
								{
									$fkField = isset($params['reffield']) ? $params['reffield'] : $field;
									$foreignkeyExists = $this->ci->MobilityonlinefhcModel->valueExists($params['ref'], $fkField, $value);

									if (!hasData($foreignkeyExists))
									{
										$hasError = true;
										$errorText = 'hat kein Equivalent in FHC';
									}
								}
							}
						}
					}
					elseif ($required)
					{
						$hasError = true;
						$errorText = 'existiert nicht';
					}

					if ($hasError)
					{
						$fieldName = isset($params['name']) ? $params['name'] : ucfirst($field);

						$hasErrorObj->errorMessages[] = ucfirst($table).": $fieldName ".$errorText;
						$hasErrorObj->error = true;
					}
				}
			}
			else
			{
				// if required table not present in object - show error
				$hasErrorObj->errorMessages[] = "Daten fehlen: $table";
				$hasErrorObj->error = true;
			}
		}

		return $hasErrorObj;
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
	 * @param object $moObj MobilityOnline object as received from API
	 * @param string $objType type of object, e.g. application
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
	 * @param string $fhcIndex name of fhcomplete field in db
	 * @param mixed $moValue MobilityOnline value
	 * @return string
	 */
	protected function getFHCValue($fhcIndex, $moValue)
	{
		$valuemappings = $this->valuemappings['frommo'];
		$fhcValue = $moValue;
		//if exists in valuemappings - take value
		if (!empty($valuemappings[$fhcIndex])
			&& isset($valuemappings[$fhcIndex][$fhcValue])
		)
		{
			$fhcValue = $valuemappings[$fhcIndex][$fhcValue];
		}
		else//otherwise look in replacements array
		{
			if (isset($this->_replacementsarrToFHC[$fhcIndex]))
			{
				foreach ($this->_replacementsarrToFHC[$fhcIndex] as $pattern => $replacement)
				{
					//if numeric index, execute callback
					if (is_integer($pattern))
						$fhcValue = $this->$replacement($fhcValue);
					//otherwise replace with regex
					elseif (is_string($replacement))
					{
						//add slashes for regex
						$pattern = '/' . str_replace('/', '\/', $pattern) . '/';
						$fhcValue = preg_replace($pattern, $replacement, $fhcValue);
					}
				}
			}
		}
		return $fhcValue;
	}

	/**
	 * Outputs success or error of a db modification.
	 * @param string $modtype insert, update,...
	 * @param object $response of db action
	 * @param string $table database table
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

				$this->_setOutput(self::INFO_TYPE, "$table $modtype erfolgreich, Id " . $id);
			}
			else
			{
				$this->_setOutput(self::ERROR_TYPE, "$table $modtype Fehler");
			}
		}
	}

	/**
	 * Sets timestamp and importuser for insert/update.
	 * @param string $modtype
	 * @param array $arr to be filled with data
	 */
	protected function stamp($modtype, &$arr)
	{
		$idx = $modtype.'amum';
		$arr[$idx] = date('Y-m-d H:i:s', time());
		$idx = $modtype.'von';
		$arr[$idx] = self::IMPORTUSER;
	}

	/**
	 * Adds error syncoutput.
	 * @param string $text
	 */
	protected function addErrorOutput($text)
	{
		$this->_setOutput(self::ERROR_TYPE, $text);
	}

	/**
	 * Adds info syncoutput.
	 * @param string $text
	 */
	protected function addInfoOutput($text)
	{
		$this->_setOutput(self::INFO_TYPE, $text);
	}

	/**
	 * Adds success syncoutput.
	 * @param string $text
	 */
	protected function addSuccessOutput($text)
	{
		$this->_setOutput(self::SUCCESS_TYPE, $text);
	}

	/** ---------------------------------------------- Private methods -----------------------------------------------*/

	/**
	 * Extracts numeric post size from post_max_size string.
	 * @param int $post_max_size
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
			$sizeRegex = '/\d+([KMG])/';

			if (preg_match($sizeRegex ,$post_max_size))
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
	 * @param string $moDate
	 * @return string fhcomplete date
	 */
	private function _mapDateToFhc($moDate)
	{
		$pattern = '/^(\d{1,2}).(\d{1,2}).(\d{4})$/';
		if (preg_match($pattern, $moDate))
		{
			$date = DateTime::createFromFormat('d.m.Y', $moDate);
			return date_format($date, 'Y-m-d');
		}
		else
			return null;
	}

	/**
	 * Converts MobilityOnline ects amount to fhcomplete format.
	 * @param float $moEcts
	 * @return float fhcomplete ects
	 */
	private function _mapEctsToFhc($moEcts)
	{
		$pattern = '/^(\d+),(\d{2})$/';
		if (preg_match($pattern, $moEcts))
		{
			return (float) str_replace(',', '.', $moEcts);
		}
		else
			return null;
	}

	/**
	 * Makes sure base 64 image is not bigger than thumbnail size.
	 * @param string $moImage
	 * @return string resized image
	 */
	private function _resizeBase64ImageSmall($moImage)
	{
		return $this->_resizeBase64Image($moImage, 101, 130);
	}

	/**
	 * Makes sure base 64 image is not bigger than max fhc db image size.
	 * @param $moImage
	 * @return string resized image
	 */
	private function _resizeBase64ImageBig($moImage)
	{
		return $this->_resizeBase64Image($moImage, 827, 1063);
	}

	/**
	 * If $moimage width/height is greater than given width/height, crop image, otherwise encode it.
	 * @param string $moImage
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @return string possibly cropped, base64 encoded image
	 */
	private function _resizeBase64Image($moImage, $maxWidth, $maxHeight)
	{
		$fhcImage = null;

		//groesse begrenzen
		$width = $maxWidth;
		$height = $maxHeight;
		$image = imagecreatefromstring(base64_decode(base64_encode($moImage)));

		if ($image)
		{
			$uri = 'data://application/octet-stream;base64,' . base64_encode($moImage);
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

				$fhcImage = base64_encode($contents);
			}
			else
				$fhcImage = base64_encode($moImage);
			imagedestroy($image);
		}

		return $fhcImage;
	}

	/**
	 * Add text output to show as syncoutput.
	 * @param string $type
	 * @param string $text
	 */
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
