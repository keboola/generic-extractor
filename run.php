<?php

use	Keboola\GenericExtractor\Config\Configuration,
	Keboola\GenericExtractor\GenericExtractor;
use	Keboola\Temp\Temp;
use	Keboola\Juicer\Common\Logger,
	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;

require_once(dirname(__FILE__) . "/bootstrap.php");

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

	if ($config->getAttribute('debug')) {
		Logger::initLogger(APP_NAME, true);
	}

	$api = $configuration->getApi($config);

	$extractor = new GenericExtractor($temp);
	$extractor->setLogger(Logger::getLogger());
	$extractor->setApi($api);
	$extractor->setMetadata($configuration->getConfigMetadata() ?: []);

	$results = $extractor->run($config);

	$outputBucket = $config->getAttribute('outputBucket') ?:
		'ex-api-' . $api->getName() . "-" . $config->getConfigName();

	$configuration->storeResults(
		$results,
		$outputBucket
	);
	$configuration->saveConfigMetadata($extractor->getMetadata());
} catch(UserException $e) {
	Logger::log('error', $e->getMessage(), (array) $e->getData());
	exit(1);
} catch(ApplicationException $e) {
	Logger::log('error', $e->getMessage(), (array) $e->getData());
	exit($e->getCode() > 1 ? $e->getCode(): 2);
} catch(ErrorException $e) {
	Logger::log('error', $e->getMessage(), [
		'errFile' => $e->getFile(),
		'errLine' => $e->getLine(),
		'trace' => $e->getTrace()
	]);
	exit(2);
} catch(Exception $e) {
	Logger::log('error', $e->getMessage());
	exit(2);
}


Logger::log('info', "Extractor finished successfully.");
exit(0);
