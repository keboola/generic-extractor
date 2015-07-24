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
	$arguments = getopt("d::", ["data::"]);
	if (!isset($arguments["data"])) {
		throw new UserException('Data folder not set.');
	}

	$configuration = new Configuration($arguments['data'], APP_NAME, $temp);
	$config = $configuration->getConfig();
	$api = $configuration->getApi($config);

	$extractor = new GenericExtractor($temp);
	$extractor->setLogger(Logger::getLogger());
	$extractor->setApi($api);
	$extractor->setMetadata($configuration->getConfigMetadata());

	$results = $extractor->run($config);

	$configuration->storeResults(
		$results,
		$config->getConfigName(),
		'ex-api-' . $api->getName()
	);
	$configuration->saveConfigMetadata($extractor->getMetadata());
} catch(UserException $e) {
	Logger::log('error', $e->getMessage(), (array) $e->getData());
	exit(1);
} catch(ApplicationException $e) {
	Logger::log('error', $e->getMessage(), (array) $e->getData());
	exit($e->getCode() ?: 2);
}

Logger::log('info', "Extractor finished successfully.");
exit(0);
