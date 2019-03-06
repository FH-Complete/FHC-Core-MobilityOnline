<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages operations with fhcomplete database for Mobility Online sync
 */
class Mobilityonlinefhc_model extends DB_Model
{
	/**
	 * Gets Lehrveranstaltungen for Mobility Online synchronisation.
	 * Only
	 * 1. Lvs with incoming places > 0
	 * 2. Studienordnung valid in current semester or with Lehrauftrag (i.e. assigned Lehreinheit)
	 * @param $studiensemester_kurzbz
	 * @return query object
	 */
	public function getLvs($studiensemester_kurzbz)
	{
		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		$studsemres = $this->StudiensemesterModel->load($studiensemester_kurzbz);

		if (!hasData($studsemres))
			return null;

		$semstart = $studsemres->retval[0]->start;
		$semende = $studsemres->retval[0]->ende;

		$parametersarray = array($studiensemester_kurzbz, $studsemres->retval[0]->studienjahr_kurzbz, $semstart, $semende, $studiensemester_kurzbz);

		$query = "
			SELECT lv.*, ? AS studiensemester_kurzbz, ? AS studienjahr_kurzbz, UPPER(stg.typ::varchar(1) || stg.kurzbz) AS studiengang_kuerzel, 
			stg.bezeichnung AS studiengang_bezeichnung, stg.english AS studiengang_bezeichnung_english, stg.typ, tbl_sprache.locale
			FROM lehre.tbl_lehrveranstaltung lv
			JOIN public.tbl_studiengang stg ON lv.studiengang_kz = stg.studiengang_kz
			JOIN public.tbl_sprache ON lv.sprache = tbl_sprache.sprache
			WHERE lv.lehrtyp_kurzbz != 'modul'
			AND (
				EXISTS 
				(
					SELECT 1 FROM 
					lehre.tbl_studienplan_lehrveranstaltung
					JOIN lehre.tbl_studienplan ON tbl_studienplan_lehrveranstaltung.studienplan_id = tbl_studienplan.studienplan_id
					JOIN lehre.tbl_studienordnung ON tbl_studienordnung.studienordnung_id = tbl_studienplan.studienordnung_id
					JOIN public.tbl_studiensemester semvon ON lehre.tbl_studienordnung.gueltigvon = semvon.studiensemester_kurzbz OR lehre.tbl_studienordnung.gueltigvon IS NULL
					JOIN public.tbl_studiensemester sembis ON lehre.tbl_studienordnung.gueltigbis = sembis.studiensemester_kurzbz OR lehre.tbl_studienordnung.gueltigbis IS NULL
					WHERE tbl_studienplan_lehrveranstaltung.lehrveranstaltung_id = lv.lehrveranstaltung_id
					AND (?::date >= semvon.start OR semvon.start IS NULL) AND (?::date <= sembis.ende OR sembis.ende IS NULL)
				)
				OR EXISTS (SELECT 1 FROM lehre.tbl_lehreinheit WHERE lehrveranstaltung_id = lv.lehrveranstaltung_id AND studiensemester_kurzbz = ?)
			)
			AND lv.incoming > 0
			AND lv.aktiv
			AND stg.typ IN ('b', 'm')
			ORDER BY studiengang_kuerzel, lv.bezeichnung, lv.lehrveranstaltung_id
		";

		return $this->execQuery($query, $parametersarray);
	}
}
