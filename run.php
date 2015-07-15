<?php

use	Keboola\GenericExtractor\Config\Configuration;
use	Keboola\Temp\Temp;
use	Keboola\GenericExtractor\GenericExtractor;

require_once(dirname(__FILE__) . "/vendor/autoload.php");

const APP_NAME = 'ex-generic-v2';

$temp = new Temp(APP_NAME);


$arguments = getopt("d::", array("data::"));
if (!isset($arguments["data"])) {
	print "Data folder not set.";
	exit(1);
}

$configuration = new Configuration(APP_NAME, $temp);
$config = $configuration->getConfig($arguments['data']);
$configuration->initialize($config);


// $executor = new Executor($configuration);

$extractor = new GenericExtractor($temp);
$extractor->setApi($configuration->getApi());
$extractor->setAppName("ex-generic-v2"); // TODO from cfg
$extractor->setHeaders(); // TODO
$extractor->run($config);
