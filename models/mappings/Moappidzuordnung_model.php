<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages intermediate table for mapping fhcomplete prestudent ids to application ids in Mobility Online
 */
class Moappidzuordnung_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_mo_appidzuordnung';
		$this->pk = array('prestudent_id', 'mo_applicationid', 'studiensemester');
		$this->hasSequence = false;
	}

	/**
	 * Get Mobility Online application mappings by prestudent data.
	 * @param $prestudent_studiensemester_kurzbz
	 * @param $studiengang_kz
	 * @return object success or error
	 */
	public function getMappings($prestudent_studiensemester_kurzbz, $studiengang_kz = null)
	{
		$params = array($prestudent_studiensemester_kurzbz);
		$studiengangClause = '';

		if (isset($studiengang_kz))
		{
			$params[] = $studiengang_kz;
			$studiengangClause = ' AND tbl_prestudent.studiengang_kz = ?';
		}

		$query =
			"SELECT
				DISTINCT tbl_mo_appidzuordnung.prestudent_id, tbl_mo_appidzuordnung.mo_applicationid, tbl_mo_appidzuordnung.studiensemester_kurzbz
			FROM
				extension.tbl_mo_appidzuordnung
				JOIN public.tbl_prestudent USING (prestudent_id)
				JOIN public.tbl_prestudentstatus USING (prestudent_id)
			WHERE
				tbl_prestudentstatus.studiensemester_kurzbz = ?
				{$studiengangClause}";

		return $this->execQuery($query, $params);
	}
}
