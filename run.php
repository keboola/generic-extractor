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
// Do all these in a function in EX TODO
$extractor->setBaseUrl($configuration->getBaseUrl($config)); // TODO
$extractor->setAuth(); // TODO
$extractor->setScroller(); // TODO
$extractor->setAppName("ex-generic-v2"); // TODO from cfg
$extractor->run($config);
