<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

use \CI3_Events as Events;
use \FHCAPI_Controller as FHCAPI_Controller;

Events::on('mobility_delete', function ($bisio_id)
{
	$CI =& get_instance();
	$CI->load->model('extensions/FHC-Core-MobilityOnline/mappings/Mobisioidzuordnung_model', 'MobisioidzuordnungModel');

	$result = $CI->MobisioidzuordnungModel->loadWhere([
		'bisio_id' => $bisio_id
	]);
	if (isError($result))
		return $CI->addError(getError($result), FHCAPI_Controller::ERROR_TYPE_GENERAL);
	$data = $result->retval;
	if (!$data)
		return;
	else
	{
		return $CI->addError(
			$CI->p->t('mobility', 'error_existingEntryInExtension'),
			FHCAPI_Controller::ERROR_TYPE_GENERAL
		);
	}
});
