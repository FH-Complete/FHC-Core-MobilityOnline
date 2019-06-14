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
}
