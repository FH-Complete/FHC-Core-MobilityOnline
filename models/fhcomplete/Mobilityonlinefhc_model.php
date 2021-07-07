<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages operations with fhcomplete database for Mobility Online sync
 */
class Mobilityonlinefhc_model extends DB_Model
{
	const TABLE_PREFIX= 'tbl_';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('crm/prestudent_model', 'PrestudentModel');
		$this->load->model('person/Kontakt_model', 'KontaktModel');
		$this->load->model('codex/bisiozweck_model', 'BisioZweckModel');
		$this->load->model('codex/bisioaufenthaltfoerderung_model', 'BisioAufenthaltfoerderungModel');
	}

	/**
	 * Gets prestudent data of an incoming, including stay from and to date
	 * @param $prestudent_id
	 * @return mixed
	 */
	public function getIncomingPrestudent($prestudent_id, $studiengang_kz = null)
	{
		$valuedefaults = $this->config->item('fhcdefaults');
		$emailbez = $valuedefaults['application']['kontaktmail']['kontakttyp'];
		$telefonbez = $valuedefaults['address']['kontakttel']['kontakttyp'];

		$this->PrestudentModel->addSelect('prestudent_id, person_id, vorname, nachname, uid, tbl_studiengang.bezeichnung, tbl_studiengang.english, tbl_bisio.von, tbl_bisio.bis');
		$this->PrestudentModel->addJoin('public.tbl_person', 'person_id');
		$this->PrestudentModel->addJoin('public.tbl_benutzer', 'person_id');
		$this->PrestudentModel->addJoin('public.tbl_studiengang', 'studiengang_kz');
		$this->PrestudentModel->addJoin('bis.tbl_bisio', 'uid = student_uid');

		$whereparams = array('prestudent_id' => $prestudent_id);

		if (isset($studiengang_kz) && is_numeric($studiengang_kz))
			$whereparams['studiengang_kz'] = $studiengang_kz;

		$prestudent = $this->PrestudentModel->loadWhere($whereparams);

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

	/**
	 * Gets Studiengaenge from FHC which are used in MobilityOnline.
	 * Used types and Studiengaenge are configured in values config.
	 * @return mixed
	 */
	public function getStudiengaenge()
	{
		$valuesconfig = $this->config->item('values');

		$qry = "SELECT studiengang_kz, tbl_studiengang.bezeichnung, tbl_studiengang.typ, tbl_studiengangstyp.bezeichnung AS typbezeichnung,
       			UPPER(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) as kuerzel
				FROM public.tbl_studiengang
				JOIN public.tbl_studiengangstyp USING (typ)
				WHERE aktiv
				AND (typ IN ? OR studiengang_kz IN ?)
			  	ORDER BY kuerzel, tbl_studiengang.bezeichnung, studiengang_kz";

		return $this->execQuery($qry, array($valuesconfig['studiengangtypentosync'], $valuesconfig['studiengaengetosync']));
	}

	/**
	 * Checks if a table column value exists in fhcomplete database
	 * @param $table
	 * @param $field
	 * @param $value
	 * @return mixed
	 */
	public function valueExists($table, $field, $value)
	{
		$query = "SELECT 1 FROM %s WHERE %s = ? LIMIT 1";
		return $this->execQuery(sprintf($query, $table, $field), array($value));
	}

	/**
	 * Checks if a table column value has right length
	 * @param $table
	 * @param $field
	 * @param $value
	 * @return bool
	 */
	public function checkLength($table, $field, $value)
	{
		$table = self::TABLE_PREFIX.$table;
		$query = "SELECT character_maximum_length FROM information_schema.columns
					WHERE table_name = ? AND column_name = ? LIMIT 1";
		$length = $this->execQuery($query, array($table, $field));

		if (isSuccess($length))
		{
			if (hasData($length))
			{
				$lengthdata = getData($length);
				$lengthdata = $lengthdata[0]->character_maximum_length;
				return !isset($lengthdata) || strlen($value) <= $lengthdata;
			}
			else
				return true;
		}
		return false;
	}

	/**
	 * Gets bisiodata, including concatenated Zweck.
	 * @param $student_uid
	 * @return object
	 */
	public function getBisio($student_uid)
	{
		$bisioqry = "SELECT tbl_bisio.bisio_id, tbl_bisio.von, tbl_bisio.bis, universitaet, 
					tbl_mobilitaetsprogramm.beschreibung as mobilitaetsprogramm, ort, tbl_nation.langtext as nation,
       				string_agg(tbl_zweck.bezeichnung, ', ') AS zweck
					FROM bis.tbl_bisio
					LEFT JOIN bis.tbl_mobilitaetsprogramm USING(mobilitaetsprogramm_code)
					LEFT JOIN bis.tbl_nation USING (nation_code)
					LEFT JOIN bis.tbl_bisio_zweck USING (bisio_id)
					LEFT JOIN bis.tbl_zweck ON tbl_bisio_zweck.zweck_code = tbl_zweck.zweck_code
					WHERE tbl_bisio.student_uid = ?
					GROUP BY tbl_bisio.bisio_id, tbl_mobilitaetsprogramm.beschreibung, tbl_nation.langtext
					ORDER BY tbl_bisio.von, tbl_bisio.updateamum, tbl_bisio.insertamum";

		return  $this->execQuery($bisioqry, array($student_uid));
	}

	/**
	 * Inserts bisio_zweck for a student if not present yet.
	 * @param $bisio_zweck
	 * @return int|null bisio_id and zweck_id of inserted bisio_zweck if successful, null otherwise.
	 */
	public function saveBisioZweck($bisio_zweck)
	{
		if (!isset($bisio_zweck['zweck_code']))
			return success(null);

		$bisiocheckresp = $this->BisioZweckModel->loadWhere(
			array(
				'bisio_id' => $bisio_zweck['bisio_id'],
				'zweck_code' => $bisio_zweck['zweck_code']
			)
		);

		if (isError($bisiocheckresp))
			return $bisiocheckresp;

		if (!hasData($bisiocheckresp))
			return $this->BisioZweckModel->insert($bisio_zweck);
		else
			return success(null);
	}

	/**
	 * Inserts bisio Aufenthaltsförderung for a student if not present, updates if present.
	 * @param $bisio_aufenthaltfoerderung
	 * @return int|null bisio_id and aufenthaltfoerderung_code of inserted aufenthaltfoerderung if successful, null otherwise.
	 */
	public function saveBisioAufenthaltfoerderung($bisio_aufenthaltfoerderung)
	{
		$result = null;

		if (!isset($bisio_aufenthaltfoerderung['aufenthaltfoerderung_code']))
			$result = success(null);
		else
		{
			$bisiocheckresp = $this->BisioAufenthaltfoerderungModel->loadWhere(
				array(
					'bisio_id' => $bisio_aufenthaltfoerderung['bisio_id'],
					'aufenthaltfoerderung_code' => $bisio_aufenthaltfoerderung['aufenthaltfoerderung_code']
				)
			);

			if (isError($bisiocheckresp))
				$result = $bisiocheckresp;
			else
			{
				if (!hasData($bisiocheckresp))
					$result = $this->BisioAufenthaltfoerderungModel->insert($bisio_aufenthaltfoerderung);
				else
					$result = success(null);
			}
		}

		return $result;
	}
}
