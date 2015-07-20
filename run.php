<?php

use	Keboola\GenericExtractor\Config\Configuration,
	Keboola\GenericExtractor\GenericExtractor;
use	Keboola\Temp\Temp;
use	Keboola\Juicer\Common\Logger,
	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;

require_once(dirname(__FILE__) . "/vendor/autoload.php");

const APP_NAME = 'ex-generic-v2';

$temp = new Temp(APP_NAME);

Logger::initLogger(APP_NAME);

try {
	$arguments = getopt("d::", array("data::"));
	if (!isset($arguments["data"])) {
		throw new UserException('Data folder not set.');
	}

	$configuration = new Configuration($arguments['data'], APP_NAME, $temp);
	$config = $configuration->getConfig();

	$extractor = new GenericExtractor($temp);
	$extractor->setApi($configuration->getApi());
	$extractor->setAppName("ex-generic-v2"); // TODO from cfg
	$extractor->setMetadata($configuration->getConfigMetadata());

	$results = $extractor->run($config);

	$configuration->storeResults(
		$results,
		$config->getConfigName(),
		'ex-api-' . $configuration->getApi()->getName()
	);
	$configuration->saveConfigMetadata($extractor->getMetadata());
} catch(UserException $e) {
	Logger::log('error', $e->getMessage(), $e->getData());
	exit(1);
} catch(ApplicationException $e) {
	Logger::log('error', $e->getMessage(), $e->getData());
	exit($e->getCode() ?: 2);
}

Logger::log('info', "Extractor finished successfully.");
exit(0);
