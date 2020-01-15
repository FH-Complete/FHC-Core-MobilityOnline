<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides generic functionality needed for Mobility Online sync
 * particular objects are synced in subclasses
 */
class MobilityOnlineSyncLib
{
	const WINTERSEMESTER_PREFIX = 'WS';
	const SOMMERSEMESTER_PREFIX = 'SS';

	protected $mobilityonline_config;
	protected $debugmode = false;

	// mapping for assigning fhcomplete field names to MobilityOnline field names
	protected $conffieldmappings = array();
	// mappings of property values which are different in Mobility Online and fhc.
	protected $valuemappings = array();

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
		$this->debugmode = isset($this->mobilityonline_config['debugmode']) &&
			$this->mobilityonline_config['debugmode'] === true;

		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/fieldmappings');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/valuemappings');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/valuedefaults');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/values');
		$this->ci->config->load('extensions/FHC-Core-MobilityOnline/fields');

		$this->conffieldmappings = $this->ci->config->item('fieldmappings');
		$this->valuemappings = $this->ci->config->item('valuemappings');
		$this->moconffields = $this->ci->config->item('mofields');

		$this->_setSemesterMappings();
		$this->_setStudienjahrMappings();
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

		$mosem = preg_replace('/'.self::WINTERSEMESTER_PREFIX.'\d{4}/', 'Wintersemester '.
			$year.'/'.($year + 1), $mosem);

		$mosem = str_replace(self::SOMMERSEMESTER_PREFIX, 'Sommersemester ', $mosem);

		return $mosem;
	}

	/**
	 * Converts fhc studiensemester to Studienjahr MobilityOnline format
	 * (when Incoming give Studienjahr instead of semester)- e.g. Studienjahr 2018/2019
	 * @param $studiensemester_kurzbz
	 * @return null|string
	 */
	public function mapSemesterToMoStudienjahr($studiensemester_kurzbz)
	{
		$studienjahrsemestermo = null;
		$semesteryear = substr($studiensemester_kurzbz, 2, 4);

		if (strstr($studiensemester_kurzbz, self::WINTERSEMESTER_PREFIX))
		{
			$studienjahrsemestermo = str_replace(self::WINTERSEMESTER_PREFIX, 'Studienjahr ', $studiensemester_kurzbz).'/'.($semesteryear + 1);
		}
		elseif (strstr($studiensemester_kurzbz, self::SOMMERSEMESTER_PREFIX))
		{
			$studienjahrsemestermo = 'Studienjahr ' . ($semesteryear - 1) . '/'. $semesteryear;
		}

		return $studienjahrsemestermo;
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
				$mostudiensemester = $this->mapSemesterToMo($studiensemester->studiensemester_kurzbz);

				$this->valuemappings['tomo']['studiensemester_kurzbz'][$studiensemester->studiensemester_kurzbz] = $mostudiensemester;
				$this->valuemappings['frommo']['studiensemester_kurzbz'][$mostudiensemester] = $studiensemester->studiensemester_kurzbz;

				//special case: instead of Studiensemester Incoming has Studienjahr in Mobility Online -> map with Wintersemester!
				$mostudienjahrassemester = $this->mapSemesterToMoStudienjahr($studiensemester->studiensemester_kurzbz);
				if (isset($mostudienjahrassemester))
					$this->valuemappings['frommo']['studiensemester_kurzbz'][$mostudienjahrassemester] = $studiensemester->studiensemester_kurzbz;
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
				$mojahr = $this->mapStudienjahrToMo($studienjahr->studienjahr_kurzbz);

				$this->valuemappings['tomo']['studienjahr_kurzbz'][$studienjahr->studienjahr_kurzbz] = $mojahr;
				$this->valuemappings['frommo']['studienjahr_kurzbz'][$mojahr] = $studienjahr->studienjahr_kurzbz;
			}
		}
	}

	/**
	 * Replaces empty string with null
	 * @param $string
	 * @return null
	 */
	protected function replaceEmpty($string)
	{
		if (isEmptyString($string))
			return null;
		return $string;
	}
}
