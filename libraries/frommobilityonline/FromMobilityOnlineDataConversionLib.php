<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class FromMobilityOnlineDataConversionLib
{
	const WINTERSEMESTER_PREFIX = 'WS';
	const SOMMERSEMESTER_PREFIX = 'SS';

	private $_semestermappings = array();
	private $_studyyearmappings = array();

	/**
	 * SyncFromMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		// get Code Igniter instance
		$this->ci =& get_instance();

		$this->ci->load->library('extensions/FHC-Core-MobilityOnline/tomobilityonline/ToMobilityOnlineDataConversionLib');

		// set semester and studienjahr mappings
		$this->_setSemesterMappings();
		$this->_setStudienjahrMappings();
	}


	/** ---------------------------------------------- Public methods ------------------------------------------------*/

	/**
	 * Converts MobilityOnline Studienjahr to fhc semester.
	 * Returns WS and SS for the Studienjahr.
	 * @param string $mo_studienjahr
	 * @return array
	 */
	public function mapMoStudienjahrToSemester($mo_studienjahr)
	{
		$semester = array();

		$year = str_replace('Studienjahr ', '', $mo_studienjahr);
		$semester[] = self::WINTERSEMESTER_PREFIX . substr($year, 0, 4);
		$semester[] = self::SOMMERSEMESTER_PREFIX . substr($year, 5, 4);

		return $semester;
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function mapStudiensemesterToFhc($moSemester)
	{
		return isset($this->_semestermappings[$moSemester]) ? $this->_semestermappings[$moSemester] : null;
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function mapStudienjahrToFhc($moYear)
	{
		return isset($this->_studyyearmappings[$moYear]) ? $this->_studyyearmappings[$moYear] : null;
	}

	/**
	 * Converts MobilityOnline MobilityOnlineDataConversionLib.phpdate to fhcomplete date format.
	 * @param string $moDate
	 * @return string fhcomplete date
	 */
	public function mapDateToFhc($moDate)
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
	public function mapEctsToFhc($moEcts)
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
	public function mapBetragToFhc($moBetrag)
	{
		return number_format($moBetrag, 2, '.', '');
	}

	/**
	 * Converts MobilityOnline Buchungsdatum to fhcomplete format.
	 * @param string $buchungsdatum
	 * @return string fhcomplete betrag
	 */
	public function mapIsoDateToFhc($buchungsdatum)
	{
		$fhcDate = substr($buchungsdatum, 0, 10);
		if ($this->validateDate($fhcDate))
			return $fhcDate;
		else
			return null;
	}

	/**
	 * Encodes document into base64 string.
	 * @param string $moDoc
	 * @return string encoded string
	 */
	public function encodeToBase64($moDoc)
	{
		return base64_encode($moDoc);
	}

	/**
	 * Extracts mimetype from file data.
	 * @param $moDoc file data
	 * @return string
	 */
	public function mapFileToMimetype($moDoc)
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
	public function getFileExtension($moFilename)
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
	public function resizeBase64ImageSmall($moImage)
	{
		return $this->_resizeBase64Image($moImage, 101, 130);
	}

	/**
	 * Makes sure base 64 image is not bigger than max fhc db image size.
	 * @param $moImage
	 * @return string resized image
	 */
	public function resizeBase64ImageBig($moImage)
	{
		return $this->_resizeBase64Image($moImage, 827, 1063);
	}

	/**
	 * Replaces empty string with null.
	 * @param $string
	 * @return null|string
	 */
	public function replaceEmptyByNull($string)
	{
		if (isEmptyString($string))
			return null;
		return $string;
	}

	/**
	 * Replaces string with empty string.
	 * @param $string
	 * @return string
	 */
	public function replaceByEmptyString($string)
	{
		return preg_replace('/.+/', '', $string);
	}

	/**
	 * Checks if given date exists and is valid.
	 * @param $date
	 * @param string $format
	 * @return bool
	 */
	public function validateDate($date, $format = 'Y-m-d')
	{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) === $date;
	}

	/** ---------------------------------------------- Private methods ------------------------------------------------*/

	/**
	 * Sets mappings for all studiensemester
	 */
	private function _setSemesterMappings()
	{
		$this->ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		$allStudiensemester = $this->ci->StudiensemesterModel->load();

		if (hasData($allStudiensemester))
		{
			foreach ($allStudiensemester->retval as $studiensemester)
			{
				$moStudiensemester = $this->ci->tomobilityonlinedataconversionlib->mapSemesterToMo($studiensemester->studiensemester_kurzbz);

				$this->_semestermappings[$moStudiensemester] = $studiensemester->studiensemester_kurzbz;

				//special case: instead of Studiensemester Incoming has Studienjahr in Mobility Online -> map with Wintersemester!
				if (strstr($studiensemester->studiensemester_kurzbz, self::WINTERSEMESTER_PREFIX))
				{
					$moStudienjahrAsSemester = $this->ci->tomobilityonlinedataconversionlib->mapSemesterToMoStudienjahr($studiensemester->studiensemester_kurzbz);
					if (isset($moStudienjahrAsSemester))
						$this->_semestermappings[$moStudienjahrAsSemester] = $studiensemester->studiensemester_kurzbz;
				}
			}
		}
	}

	/**
	 * Sets mappings for all studienjahre
	 */
	private function _setStudienjahrMappings()
	{
		$this->ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');

		$allSStudienjahre = $this->ci->StudienjahrModel->load();

		if (hasData($allSStudienjahre))
		{
			foreach ($allSStudienjahre->retval as $studienjahr)
			{
				$mojahr = $this->ci->tomobilityonlinedataconversionlib->mapStudienjahrToMo($studienjahr->studienjahr_kurzbz);

				//$this->_studyyearmappings['tomo'][$studienjahr->studienjahr_kurzbz] = $mojahr;
				//$this->_studyyearmappings['frommo'][$mojahr] = $studienjahr->studienjahr_kurzbz;
				$this->_studyyearmappings[$mojahr] = $studienjahr->studienjahr_kurzbz;
			}
		}
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
}
