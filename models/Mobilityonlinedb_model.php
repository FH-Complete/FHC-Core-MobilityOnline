<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages interaction with fhcomplete database for Mobility Online sync
 */
class Mobilityonlinedb_model extends DB_Model
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
			SELECT tbl_lehrveranstaltung.*, ? AS studiensemester_kurzbz, ? AS studienjahr_kurzbz, UPPER(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) AS studiengang_kuerzel, 
			tbl_studiengang.bezeichnung AS studiengang_bezeichnung, tbl_studiengang.english AS studiengang_bezeichnung_english, tbl_studiengang.typ, substring(tbl_sprache.locale, 0, 3) AS sprachkuerzel
			FROM lehre.tbl_lehrveranstaltung
			JOIN public.tbl_studiengang ON tbl_lehrveranstaltung.studiengang_kz = tbl_studiengang.studiengang_kz
			JOIN public.tbl_sprache ON tbl_lehrveranstaltung.sprache = tbl_sprache.sprache
			WHERE tbl_lehrveranstaltung.lehrtyp_kurzbz != 'modul'
			AND (
				EXISTS 
				(
					SELECT 1 FROM 
					lehre.tbl_studienplan_lehrveranstaltung
					JOIN lehre.tbl_studienplan ON tbl_studienplan_lehrveranstaltung.studienplan_id = tbl_studienplan.studienplan_id
					JOIN lehre.tbl_studienordnung ON tbl_studienordnung.studienordnung_id = tbl_studienplan.studienordnung_id
					JOIN public.tbl_studiensemester semvon ON lehre.tbl_studienordnung.gueltigvon = semvon.studiensemester_kurzbz OR lehre.tbl_studienordnung.gueltigvon IS NULL
					JOIN public.tbl_studiensemester sembis ON lehre.tbl_studienordnung.gueltigbis = sembis.studiensemester_kurzbz OR lehre.tbl_studienordnung.gueltigbis IS NULL
					WHERE tbl_studienplan_lehrveranstaltung.lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id
					AND (?::date >= semvon.start OR semvon.start IS NULL) AND (?::date <= sembis.ende OR sembis.ende IS NULL)
				)
				OR EXISTS (SELECT 1 FROM lehre.tbl_lehreinheit WHERE lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id AND studiensemester_kurzbz = ?)
			)
			AND incoming > 0
			AND tbl_lehrveranstaltung.aktiv
			AND tbl_studiengang.typ IN ('b', 'm')
			ORDER BY studiengang_kuerzel, tbl_lehrveranstaltung.bezeichnung, tbl_lehrveranstaltung.lehrveranstaltung_id
		";

		return $this->execQuery($query, $parametersarray);
	}
}
