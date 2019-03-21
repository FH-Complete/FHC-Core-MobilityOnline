<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages operations with fhcomplete database for Mobility Online sync
 */
class Mobilityonlinefhc_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('crm/prestudent_model', 'PrestudentModel');
		$this->load->model('person/Kontakt_model', 'KontaktModel');
	}

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

	/**
	 * Gets prestudent data of an incoming, including stay from and to date
	 * @param $prestudent_id
	 * @return mixed
	 */
	public function getIncomingPrestudent($prestudent_id)
	{
		$valuedefaults = $this->config->item('fhcdefaults');
		$emailbez = $valuedefaults['application']['kontaktmail']['kontakttyp'];
		$telefonbez = $valuedefaults['address']['kontakttel']['kontakttyp'];

		$this->PrestudentModel->addSelect('prestudent_id, person_id, vorname, nachname, uid, tbl_studiengang.bezeichnung, tbl_studiengang.english, tbl_bisio.von, tbl_bisio.bis');
		$this->PrestudentModel->addJoin('public.tbl_person', 'person_id');
		$this->PrestudentModel->addJoin('public.tbl_benutzer', 'person_id');
		$this->PrestudentModel->addJoin('public.tbl_studiengang', 'studiengang_kz');
		$this->PrestudentModel->addJoin('bis.tbl_bisio', 'uid = student_uid');
		$prestudent = $this->PrestudentModel->load($prestudent_id);

		$return = error('error occured while getting prestudent');

		if (hasData($prestudent))
		{
			$prestudent = $prestudent->retval[0];

			$this->KontaktModel->addLimit(1);
			$mailkontakt = $this->KontaktModel->loadWhere(
				array(
				'person_id' => $prestudent->person_id,
				'kontakttyp' => $emailbez,
				'zustellung' => true)
			);

			if (hasData($mailkontakt))
			{
				$phonekontakt = $this->KontaktModel->loadWhere(
					array(
						'person_id' => $prestudent->person_id,
						'kontakttyp' => $telefonbez)
				);

				$phonenumber = hasData($phonekontakt) ? $phonekontakt->retval[0]->kontakt : '';

				$prestudentobj = new StdClass();

				$prestudentobj->prestudent_id = $prestudent->prestudent_id;
				$prestudentobj->vorname = $prestudent->vorname;
				$prestudentobj->nachname = $prestudent->nachname;
				$prestudentobj->uid = $prestudent->uid;
				$prestudentobj->email = $mailkontakt->retval[0]->kontakt;
				$prestudentobj->phonenumber = $phonenumber;
				$prestudentobj->studiengang = $prestudent->bezeichnung;
				$prestudentobj->stayfrom = $prestudent->von;
				$prestudentobj->stayto = $prestudent->bis;

				$return = success($prestudentobj);
			}
		}

		return $return;
	}
}
