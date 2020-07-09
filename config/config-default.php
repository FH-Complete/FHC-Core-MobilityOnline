<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

$config['FHC-Core-MobilityOnline']['wsdlurl'] = 'https://www.mobilityexample.com/services';

$config['FHC-Core-MobilityOnline']['soapversion'] = SOAP_1_2;

$config['FHC-Core-MobilityOnline']['encoding'] = 'utf8';

$config['FHC-Core-MobilityOnline']['authority'] = 'authority';

$config['FHC-Core-MobilityOnline']['debugmode'] = false;

$config['FHC-Core-MobilityOnline']['post_max_size'] = '4M';

$config['FHC-Core-MobilityOnline']['services'] = array(
	'getMasterData' => array(
		'service' => 'GetMasterDataService',
		'endpoint' => 'GetMasterDataServiceHttpsSoap12Endpoint'
	),
	'setMasterData' => array(
		'service' => 'SetMasterDataService',
		'endpoint' => 'SetMasterDataServiceHttpsSoap12Endpoint'
	),
	'getApplicationData' => array(
		'service' => 'GetApplicationDataService',
		'endpoint' => 'GetApplicationDataServiceHttpsSoap12Endpoint'
	)
);
