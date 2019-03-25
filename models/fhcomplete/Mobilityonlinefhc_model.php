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
