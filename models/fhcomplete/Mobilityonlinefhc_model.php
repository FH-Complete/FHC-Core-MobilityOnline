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
		$this->load->model('codex/bisio_model', 'BisioModel');
		$this->load->model('codex/bisiozweck_model', 'BisioZweckModel');
		$this->load->model('codex/bisioaufenthaltfoerderung_model', 'BisioAufenthaltfoerderungModel');
	}

	/**
	 * Gets prestudent data of an incoming, including stay from and to date
	 * @param int $prestudent_id
	 * @param string $studiengang_kz
	 * @return object prestudent data or error
	 */
	public function getIncomingPrestudent($prestudent_id, $studiengang_kz = null)
	{
		$valuedefaults = $this->config->item('fhcdefaults');
		$emailbez = $valuedefaults['application']['kontaktmail']['kontakttyp'];
		$telefonbez = $valuedefaults['address']['kontakttel']['kontakttyp'];

		$this->PrestudentModel->addSelect(
			'tbl_prestudent.prestudent_id, person_id, vorname, nachname, uid, tbl_studiengang.bezeichnung, tbl_studiengang.english'
		);
		$this->PrestudentModel->addJoin('public.tbl_person', 'person_id');
		$this->PrestudentModel->addJoin('public.tbl_benutzer', 'person_id');
		$this->PrestudentModel->addJoin('public.tbl_studiengang', 'studiengang_kz');
		//$this->PrestudentModel->addJoin('bis.tbl_bisio', 'prestudent_id');

		$whereParams = array('tbl_prestudent.prestudent_id' => $prestudent_id);

		if (isset($studiengang_kz) && is_numeric($studiengang_kz))
			$whereParams['studiengang_kz'] = $studiengang_kz;

		$prestudent = $this->PrestudentModel->loadWhere($whereParams);

		$return = error('error occured while getting prestudent');

		if (hasData($prestudent))
		{
			$prestudent = getData($prestudent)[0];

			$this->KontaktModel->addLimit(1);
			$mailkontakt = $this->KontaktModel->loadWhere(
				array(
				'person_id' => $prestudent->person_id,
				'kontakttyp' => $emailbez,
				'zustellung' => true)
			);

			// get phone number
			$phonenumber = '';
			if (hasData($mailkontakt))
			{
				$phonekontakt = $this->KontaktModel->loadWhere(
					array(
						'person_id' => $prestudent->person_id,
						'kontakttyp' => $telefonbez
					)
				);
				if (hasData($phonekontakt)) $phonenumber = getData($phonekontakt)[0]->kontakt;
			}

			// get bisio(s)
			$this->BisioModel->addSelect('von, bis');
			$bisioRes = $this->BisioModel->loadWhere(array('prestudent_id' => $prestudent_id));

			if (hasData($bisioRes))
			{
				$bisios = getData($bisioRes);

				$prestudentObj = new StdClass();
				$prestudentObj->prestudent_id = $prestudent->prestudent_id;
				$prestudentObj->vorname = $prestudent->vorname;
				$prestudentObj->nachname = $prestudent->nachname;
				$prestudentObj->uid = $prestudent->uid;
				$prestudentObj->email = $mailkontakt->retval[0]->kontakt;
				$prestudentObj->phonenumber = $phonenumber;
				$prestudentObj->studiengang = $prestudent->bezeichnung;
				$prestudentObj->stays = $bisios;

				$return = success($prestudentObj);
			}
		}

		return $return;
	}

	/**
	 * Gets Studiengaenge from FHC which are used in MobilityOnline.
	 * Used types and Studiengaenge are configured in values config.
	 * @return object
	 */
	public function getStudiengaenge()
	{
		$valuesconfig = $this->config->item('miscvalues');

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
	 * Gets prestudents by uid, Studiengang, and Studiensemester.
	 * @param string uid
	 * @param string studiengang_kz
	 * @param string studiensemester_kurzbz
	 * @return object success or error
	 */
	public function getPrestudents($uid, $studiengang_kz, $studiensemester_kurzbz)
	{
		$qry = "SELECT
					DISTINCT prestudent_id, studiensemester_kurzbz
				FROM
					public.tbl_prestudent ps
					JOIN public.tbl_person USING (person_id)
					JOIN public.tbl_benutzer ben USING (person_id)
					JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
					JOIN public.tbl_studiensemester USING (studiensemester_kurzbz)
				WHERE
					ben.uid = ?
					AND ps.studiengang_kz = ?
					AND pss.status_kurzbz IN ('Student', 'Diplomand')";

		return $this->execQuery($qry, array($uid, $studiengang_kz));
	}

	/**
	 * Checks if a table column value exists in fhcomplete database
	 * @param string $table
	 * @param string $field
	 * @param string $value
	 * @return object
	 */
	public function valueExists($table, $field, $value)
	{
		$query = "SELECT 1 FROM %s WHERE %s = ? LIMIT 1";
		return $this->execQuery(sprintf($query, $table, $field), array($value));
	}

	/**
	 * Checks if a table column value has right length
	 * @param string $table
	 * @param string $field
	 * @param string $value
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
				$lengthData = getData($length);
				$lengthData = $lengthData[0]->character_maximum_length;
				return !isset($lengthData) || strlen($value) <= $lengthData;
			}
			else
				return true;
		}
		return false;
	}

	/**
	 * Gets bisiodata, including concatenated Zweck.
	 * @param string $prestudent_id
	 * @return object
	 */
	public function getBisio($prestudent_id)
	{
		$bisioqry = "SELECT
						tbl_bisio.bisio_id, tbl_bisio.von, tbl_bisio.bis, tbl_bisio.prestudent_id, universitaet,
						tbl_mobilitaetsprogramm.beschreibung as mobilitaetsprogramm, ort, tbl_nation.langtext as nation,
						string_agg(tbl_zweck.bezeichnung, ', ') AS zweck
					FROM
						bis.tbl_bisio
						LEFT JOIN bis.tbl_mobilitaetsprogramm USING(mobilitaetsprogramm_code)
						LEFT JOIN bis.tbl_nation USING (nation_code)
						LEFT JOIN bis.tbl_bisio_zweck USING (bisio_id)
						LEFT JOIN bis.tbl_zweck ON tbl_bisio_zweck.zweck_code = tbl_zweck.zweck_code
					WHERE
						tbl_bisio.prestudent_id = ?
					GROUP BY
						tbl_bisio.bisio_id, tbl_mobilitaetsprogramm.beschreibung, tbl_nation.langtext
					ORDER BY
						tbl_bisio.von, tbl_bisio.updateamum, tbl_bisio.insertamum";

		return  $this->execQuery($bisioqry, array($prestudent_id));
	}

	/**
	 * Inserts bisio_zweck for a student if not present yet.
	 * @param array $bisio_zweck
	 * @return int|null bisio_id and zweck_id of inserted bisio_zweck if successful, null otherwise.
	 */
	public function saveBisioZweck($bisio_zweck)
	{
		if (!isset($bisio_zweck['zweck_code']))
			return success(null);

		$bisioCheckResp = $this->BisioZweckModel->loadWhere(
			array(
				'bisio_id' => $bisio_zweck['bisio_id'],
				'zweck_code' => $bisio_zweck['zweck_code']
			)
		);

		if (isError($bisioCheckResp))
			return $bisioCheckResp;

		if (!hasData($bisioCheckResp))
			return $this->BisioZweckModel->insert($bisio_zweck);
		else
			return success(null);
	}

	/**
	 * Inserts bisio Aufenthaltsförderung for a student if not present, updates if present.
	 * @param array $bisio_aufenthaltfoerderung
	 * @return int|null bisio_id and aufenthaltfoerderung_code of inserted aufenthaltfoerderung if successful, null otherwise.
	 */
	public function saveBisioAufenthaltfoerderung($bisio_aufenthaltfoerderung)
	{
		$result = null;

		if (!isset($bisio_aufenthaltfoerderung['aufenthaltfoerderung_code']))
			$result = success(null);
		else
		{
			$bisioCheckResp = $this->BisioAufenthaltfoerderungModel->loadWhere(
				array(
					'bisio_id' => $bisio_aufenthaltfoerderung['bisio_id'],
					'aufenthaltfoerderung_code' => $bisio_aufenthaltfoerderung['aufenthaltfoerderung_code']
				)
			);

			if (isError($bisioCheckResp))
				$result = $bisioCheckResp;
			else
			{
				if (!hasData($bisioCheckResp))
					$result = $this->BisioAufenthaltfoerderungModel->insert($bisio_aufenthaltfoerderung);
				else
					$result = success(null);
			}
		}

		return $result;
	}

	/**
	 * Deletes all zweck entries for a bisio.
	 * @param int $bisio_id
	 * @param array $excludedZweckCodes zweck_code to exclude from deletion
	 * @return object
	 */
	public function deleteBisioZweck($bisio_id, $excludedZweckCodes)
	{
		$qry = "DELETE FROM bis.tbl_bisio_zweck WHERE bisio_id = ? AND zweck_code NOT IN ?";

		return  $this->execQuery($qry, array($bisio_id, $excludedZweckCodes));
	}

	/**
	 * Deletes all aufenthaltfoerderung entries for a bisio.
	 * @param int $bisio_id
	 * @param array $excludedAufenthaltfoerderungCodes aufenthaltfoerderung_code to exclude from deletion
	 * @return object
	 */
	public function deleteBisioAufenthaltfoerderung($bisio_id, $excludedAufenthaltfoerderungCodes)
	{
		$qry = "DELETE FROM bis.tbl_bisio_aufenthaltfoerderung WHERE bisio_id = ? AND aufenthaltfoerderung_code NOT IN ?";

		return  $this->execQuery($qry, array($bisio_id, $excludedAufenthaltfoerderungCodes));
	}
}
