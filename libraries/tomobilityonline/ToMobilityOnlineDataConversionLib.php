<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class ToMobilityOnlineDataConversionLib
{
	const WINTERSEMESTER_PREFIX = 'WS';
	const SOMMERSEMESTER_PREFIX = 'SS';

	//~ private $_semestermappings = array();
	//~ private $_studyyearmappings = array();

	/**
	 * SyncFromMobilityOnlineLib constructor.
	 */
	public function __construct()
	{
		// get Code Igniter instance
		$this->ci =& get_instance();
	}


	/** ---------------------------------------------- Public methods ------------------------------------------------*/

	/**
	 * Converts fhc studiensemester to MobilityOnline format - e.g. Wintersemester 2018/2019
	 * @param string $studiensemester_kurzbz
	 * @return string MobilityOnline studiensemester
	 */
	public function mapSemesterToMo($studiensemester_kurzbz)
	{
		$moSem = $studiensemester_kurzbz;

		$year = substr($studiensemester_kurzbz, -4, 4);

		$moSem = preg_replace('/'.self::WINTERSEMESTER_PREFIX.'\d{4}/', 'Wintersemester '.
			$year.'/'.($year + 1), $moSem);

		$moSem = str_replace(self::SOMMERSEMESTER_PREFIX, 'Sommersemester ', $moSem);

		return $moSem;
	}

	/**
	 * Converts fhc studiensemester to Studienjahr MobilityOnline format
	 * (when Incoming give Studienjahr instead of semester)- e.g. Studienjahr 2018/2019
	 * @param string $studiensemester_kurzbz
	 * @return null|string
	 */
	public function mapSemesterToMoStudienjahr($studiensemester_kurzbz)
	{
		$studienjahrSemesterMo = null;
		$semesterYear = substr($studiensemester_kurzbz, 2, 4);

		if (strstr($studiensemester_kurzbz, self::WINTERSEMESTER_PREFIX))
		{
			$studienjahrSemesterMo = str_replace(self::WINTERSEMESTER_PREFIX, 'Studienjahr ', $studiensemester_kurzbz).'/'.($semesterYear + 1);
		}
		elseif (strstr($studiensemester_kurzbz, self::SOMMERSEMESTER_PREFIX))
		{
			$studienjahrSemesterMo = 'Studienjahr ' . ($semesterYear - 1) . '/'. $semesterYear;
		}

		return $studienjahrSemesterMo;
	}

	/**
	 * Converts fhc studienjahr to MobilityOnline format - e.g. 2018/2019
	 * @param string $studienjahr_kurzbz
	 * @return MobilityOnline studienjahr
	 */
	public function mapStudienjahrToMo($studienjahr_kurzbz)
	{
		$moJahr = $studienjahr_kurzbz;

		$year = substr($studienjahr_kurzbz, 0, strpos($studienjahr_kurzbz, '/'));

		$pattern = '/' . str_replace('/', '\/', $moJahr) . '/';

		$moJahr = preg_replace($pattern, $year.'/'.($year+1), $moJahr);

		return $moJahr;
	}

	/**
	 * Converts lehrform to MobilityOnline format
	 * @param string $lehrform_kurzbz
	 * @return MobilityOnline Lehrform
	 */
	public function mapLehrformToMo($lehrform_kurzbz)
	{
		return preg_replace(array('/^ILV.*$/', '/^SE.*$/', '/^VO.*$/', '/^(?!(ILV|SE|VO)).*$/'), array('LV', 'SE', 'VO', 'LV'), $lehrform_kurzbz);
	}
}
