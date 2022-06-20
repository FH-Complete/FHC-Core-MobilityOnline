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
		'gebdatum' => '_mapDateToFhc',
		'von' => '_mapDateToFhc',
		'bis' => '_mapDateToFhc',
		'studiengang_kz' => 'replaceByEmptyString',// empty string if no studiengang found in value mappings
		'anmerkung' => 'replaceEmptyByNull',
		'zgvnation' => 'replaceEmptyByNull',
		'zgvdatum' => '_mapDateToFhc',
		'zgvmas_code' => 'replaceEmptyByNull',
		'zgvmanation' => 'replaceEmptyByNull',
		'zgvmadatum' => '_mapDateToFhc',
		'geburtsnation' => 'replaceEmptyByNull',
		'foto' => '_resizeBase64ImageSmall',
		'titel' => '_getFileExtension',
		'mimetype' => '_mapFileToMimetype', // is placed before inhalt to get mime type from unencoded document
		/*'inhalt' => array( // field inhalt can be filled from different mo object, with different functions to execute
			'photo' => '_resizeBase64ImageBig'
		),*/
		'inhalt' => '_resizeBase64ImageBig',
		'file_content' => '_encodeToBase64',
		'erstelltam' => '_mapIsoDateToFhc',
		'student_uid' => 'replaceEmptyByNull',
		'aufenthaltfoerderung_code' => 'replaceEmptyByNull',
		'zweck_code' => 'replaceEmptyByNull',
		'ects_erworben' => '_mapEctsToFhc',
		'ects_angerechnet' => '_mapEctsToFhc',
		'betrag' => '_mapBetragToFhc',
		'buchungsdatum' => '_mapIsoDateToFhc'
	);

	/**
	 * SyncFromMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_conffhcdefaults = $this->ci->config->item('fhcdefaults');
		$this->fhcconffields = $this->ci->config->item('fhcfields');

		$this->ci->load->model('content/TempFS_model', 'TempFSModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/fhcomplete/Mobilityonlinefhc_model', 'MobilityonlinefhcModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mobilityonlineapi_model');//parent model
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mobilityonline/Mogetapplicationdata_model', 'MoGetAppModel');
		$this->ci->load->model('extensions/FHC-Core-MobilityOnline/mappings/Moakteidzuordnung_model', 'MoakteidzuordnungModel');

		$this->ci->load->library('AkteLib', array('who' => self::IMPORTUSER));
	}


	/** ---------------------------------------------- Public methods ------------------------------------------------*/

	/**
	 * Gets sync output
	 * @return array
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

		// prefill search object with mobility online fields from fieldmappings config
		// also non-searched fields need to be passed with null value
		foreach ($fields as $field)
		{
			$searchObj[$field] = null;
		}

		// fill search object with passed search params
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

			foreach ($moobjFieldmappings as $mapping)
			{
				foreach ($mapping as $value)
				{
					if (!in_array($value, $uniqueFieldmappingMoNames))
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

		// iterate over fields config
		foreach ($allFields as $table => $fields)
		{
			if (array_key_exists($table, $fhcObj))
			{
				// for each field with its setting parameters
				foreach ($fields as $field => $params)
				{
					$hasError = false;
					$errorText = '';
					$required = isset($params['required']) && $params['required'];

					// value could be an array, in this case all values in array are checked
					$fhcFields = isset($fhcObj[$table][0][$table]) ? $fhcObj[$table] :  array(array($table => $fhcObj[$table]));

					foreach ($fhcFields as $fhcField)
					{
						if (isset($fhcField[$table][$field]))
						{
							$value = $fhcField[$table][$field];

							// perform error check
							$errorResult = $this->_checkValueForErrors($table, $field, $value, $params);
							$hasError = $errorResult->error;
							$errorText = $errorResult->errorText;

							if ($hasError)
								break;
						}
						elseif ($required)
						{
							$hasError = true;
							$errorText = 'existiert nicht';
						}
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
				foreach ($fields as $field)
				{
					if (isset($field['required']) && $field['required'] === true)
					{
						// if required table not present in object - show error
						$hasErrorObj->errorMessages[] = "Daten fehlen: $table";
						$hasErrorObj->error = true;
						break;
					}
				}
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
	 * Gets a specified applicationDataElement value from MobilityOnline Application
	 * @param object $moApp the application
	 * @param string $valueType name of the attribute containing the value
	 * @param mixed $elementName
	 * @param bool $found set to true if applicationDateElement with given name and type was found
	 * @return mixed the value of the applicationDataElement
	 */
	protected function getApplicationDataElement($moApp, $valueType, $elementName, &$found = false)
	{
		$applicationDataElementsNames = array('applicationDataElements', 'nonUsedApplicationDataElements');

		foreach ($applicationDataElementsNames as $applicationDataElementsName)
		{
			if (isset($moApp->{$applicationDataElementsName}))
			{
				foreach ($moApp->{$applicationDataElementsName} as $element)
				{
					if ($element->elementName === $elementName && property_exists($element, $valueType))
					{
						$found = true;
						return $element->{$valueType};
					}
				}
			}
		}

		return null;
	}

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
						$fhcValue = $this->getFHCValue($objType, $fhcIndex, $moValue);

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
	 * @param string $moObjName name of mobilityonline object
	 * @param string $fhcField name of fhcomplete field in db
	 * @param mixed $moValue MobilityOnline value
	 * @return string
	 */
	protected function getFHCValue($moObjName, $fhcField, $moValue)
	{
		$valuemappings = $this->valuemappings['frommo'];
		$fhcValue = $moValue;
		//if exists in valuemappings - take value
		if (!empty($valuemappings[$fhcField])
			&& isset($valuemappings[$fhcField][$fhcValue])
		)
		{
			$fhcValue = $valuemappings[$fhcField][$fhcValue];
		}
		else//otherwise look in replacements array
		{
			if (isset($this->_replacementsarrToFHC[$fhcField]))
			{
				$replacementFunc = $this->_replacementsarrToFHC[$fhcField];

				if (is_array($replacementFunc)) // array if same-name-fields coming from different MO objects
				{
					foreach ($replacementFunc as $moIdx => $repFunc)
					{
						if ($moIdx == $moObjName)
						{
							$replacementFunc = $repFunc;
						}
					}
				}

				// call replacement function
				if (is_string($replacementFunc))
					$fhcValue = $this->{$replacementFunc}($fhcValue);
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

	/**
	 * Gets File from MO and converts to FHC format.
	 * @param int $appId
	 * @return array
	 */
	protected function getFiles($appId, $uploadSettingNumbers)
	{
		$documents = array();

		$fileDefaults = isset($this->_conffhcdefaults['file']) ? $this->_conffhcdefaults['file'] : array();

		if (!isEmptyArray($uploadSettingNumbers))
		{
			foreach ($uploadSettingNumbers as $uploadSettingNumber)
			{
				$idDocuments = $this->ci->MoGetAppModel->getFilesOfApplication($appId, $uploadSettingNumber);

				if (!isEmptyArray($idDocuments))
				{
					foreach ($idDocuments as $document)
					{
						$fhcFile = $this->convertToFhcFormat($document, 'file');

						// set fhc file defaults depending on file type i.e. the uploadSettingNumber
						foreach ($fileDefaults as $uploadSetting => $fileDefault)
						{
							if ($uploadSettingNumber == $uploadSetting)
							{
								$fhcFile = array_merge($fhcFile['akte'], $fileDefaults[$uploadSetting]);
							}
						}
						$documents[] = $fhcFile;
					}
				}
			}
		}

		return $documents;
	}

	/**
	 * Inserts or updates a document of a person as an akte.
	 * @param int $person_id
	 * @param array $akte
	 * @return int|null akte_id of inserted or updatedakte, null if nothing upserted
	 */
	protected function saveAkte($person_id, $akte)
	{
		$akte_id = null;

		if (isset($akte['mo_file_id']) && isset($akte['bezeichnung']))
		{
			$mo_file_id = $akte['mo_file_id'];
			unset($akte['mo_file_id']); // remove non-saved MO file id

			//$akte['titel'] = $bezeichnung.'_'.$person_id;

			$aktecheckResp = $this->ci->MoakteidzuordnungModel->loadWhere(array('mo_file_id' => $mo_file_id));

			if (isSuccess($aktecheckResp))
			{
				// prepend file name to title ending
				$akte['titel'] = $akte['bezeichnung'] . '_' . $person_id . $akte['titel'];

				// write temporary file
				$tempFileName = uniqid();
				$fileHandleResult = $this->writeTempFile($tempFileName, base64_decode($akte['file_content']));

				if (hasData($fileHandleResult))
				{
					$fileHandle = getData($fileHandleResult);

					if (hasData($aktecheckResp))
					{
						$akte_id = getData($aktecheckResp)[0]->akte_id;

						if ($this->debugmode)
						{
							$this->addInfoOutput($akte['bezeichnung'] . ' existiert bereits, akte_id ' . $akte_id);
						}
						$akteResp = $this->ci->aktelib->update($akte_id, $akte['titel'], $akte['mimetype'], $fileHandle, $akte['bezeichnung']);
						$this->log('update', $akteResp, 'akte');
					}
					else
					{
						// save new in dms
						$akteResp = $this->ci->aktelib->add($person_id, $akte['dokument_kurzbz'], $akte['titel'], $akte['mimetype'], $fileHandle, $akte['bezeichnung']);
						$this->log('insert', $akteResp, 'akte');

						if (hasData($akteResp))
						{
							$akte_id = getData($akteResp);

							// link Akte in sync table
							$this->ci->MoakteidzuordnungModel->insert(
								array(
									'akte_id' => $akte_id,
									'mo_file_id' => $mo_file_id
								)
							);
						}
					}

					// close and delete the temporary file
					$this->ci->TempFSModel->close($fileHandle);
					$this->ci->TempFSModel->remove($tempFileName);
				}
			}
		}

		return $akte_id;
	}

	/**
	 * Writes temporary file to file system.
	 * Used as template for saving documents to dms.
	 * @param string $filename
	 * @param string $file_content
	 * @return object containing pointer to written file
	 */
	protected function writeTempFile($filename, $file_content)
	{
		$readWriteResult = $this->ci->TempFSModel->openReadWrite($filename);

		if (isError($readWriteResult))
			return $readWriteResult;

		$readWriteFileHandle = getData($readWriteResult);
		$writtenTemp = $this->ci->TempFSModel->write($readWriteFileHandle, $file_content);

		if (isError($writtenTemp))
			return $writtenTemp;

		return $this->ci->TempFSModel->openRead($filename);
	}

	/** ---------------------------------------------- Private methods -----------------------------------------------*/

	/**
	 * Checks a given value for errors (data type, length, foreign key...)
	 * @param string $table fhcomplete table
	 * @param string $field fhcomplete field
	 * @param mixed $value the value
	 * @param array $params information for error check
	 * @return object containing hasError flag and error text
	 */
	private function _checkValueForErrors($table, $field, $value, $params)
	{
		$hasError = false;
		$errorText = '';
		$required = isset($params['required']) && $params['required'];

		// value empty, but required?
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
					case 'base64Document': // check base 64 document for validity, only certain doctypes are allowed.
						$decodedValue = base64_decode($value, true);
						$file_info = new finfo(FILEINFO_MIME_TYPE);
						if (!base64_encode($decodedValue) === $value ||
							!in_array($file_info->buffer($decodedValue), array('application/pdf', 'image/jpeg', 'image/png')))
						{
							$wrongDataType = true;
						}
						elseif (strlen($value) > 5 * pow(10, 8))
						{
							$hasError = true;
							$errorText = "ist zu lang";
						}
						break;
					case 'string':
						if (!is_string($value))
						{
							$wrongDataType = true;
						}
						break;
				}
			}
			elseif (!is_string($value)) // no type provided -> default string
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
				if (($params['type'] === 'string' || $params['type'] === 'base64' || $params['type'] === 'base64Document') &&
					!$this->ci->MobilityonlinefhcModel->checkLength($table, $field, $value))
				{
					$hasError = true;
					$errorText = "ist zu lang";
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

		$errorObject = new stdClass();
		$errorObject->errorText = $errorText;
		$errorObject->error = $hasError;

		return $errorObject;
	}

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

			if (preg_match($sizeRegex, $post_max_size))
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
			return (float)str_replace(',', '.', $moEcts);
		}
		else
			return null;
	}

	/**
	 * Converts MobilityOnline ects amount to fhcomplete format.
	 * @param float $moBetrag
	 * @return string fhcomplete betrag
	 */
	private function _mapBetragToFhc($moBetrag)
	{
		return number_format($moBetrag, 2, '.', '');
	}

	/**
	 * Converts MobilityOnline Buchungsdatum to fhcomplete format.
	 * @param string $buchungsdatum
	 * @return string fhcomplete betrag
	 */
	private function _mapIsoDateToFhc($buchungsdatum)
	{
		$fhcDate = substr($buchungsdatum, 0, 10);
		if ($this->_validateDate($fhcDate))
			return $fhcDate;
		else
			return null;
	}

	/**
	 * Makes sure base 64 image is not bigger than thumbnail size.
	 * @param string $moImage
	 * @return string resized image
	 */
	private function _encodeToBase64($moDoc)
	{
		return base64_encode($moDoc);
	}

	/**
	 * Extracts mimetype from file data.
	 * @param $moDoc file data
	 * @return string
	 */
	private function _mapFileToMimetype($moDoc)
	{
		$file_info = new finfo(FILEINFO_MIME_TYPE);
		$mimetype = $file_info->buffer($moDoc);

		if (is_string($mimetype))
			return $mimetype;

		return null;
	}

	/**
	 * Extracts file extension from a filename.
	 * @param string $moFilename
	 * @return string the file extension with pointor empty string if extension not determined
	 */
	private function _getFileExtension($moFilename)
	{
		$fileExtension = pathinfo($moFilename, PATHINFO_EXTENSION);

		if (isEmptyString($fileExtension))
			return '';

		return '.'.strtolower($fileExtension);
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

			if ($width_orig > $width || $height_orig > $height)
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
