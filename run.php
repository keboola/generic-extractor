<?php

use	Keboola\GenericExtractor\Config\Configuration,
	Keboola\GenericExtractor\GenericExtractor;
use	Keboola\Temp\Temp;
use	Keboola\Juicer\Common\Logger;

require_once(dirname(__FILE__) . "/vendor/autoload.php");

const APP_NAME = 'ex-generic-v2';

$temp = new Temp(APP_NAME);

Logger::initLogger(APP_NAME);

$arguments = getopt("d::", array("data::"));
if (!isset($arguments["data"])) {
	print "Data folder not set.";
	exit(1);
}

$configuration = new Configuration(APP_NAME, $temp);
$config = $configuration->getConfig($arguments['data']);

$extractor = new GenericExtractor($temp);
$extractor->setApi($configuration->getApi());
$extractor->setAppName("ex-generic-v2"); // TODO from cfg
$extractor->setHeaders(); // TODO
$extractor->run($config);
