<?php

use Keboola\GenericExtractor\Executor;
use Keboola\Juicer\Common\Logger,
    Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException;

require_once(dirname(__FILE__) . "/bootstrap.php");

const APP_NAME = 'ex-generic-v2';

// TODO create an Exception handler, register in bootstrap and handle the try/catch there?
try {
    $executor = new Executor;
    $executor->run();
} catch(UserException $e) {
    Logger::log('error', $e->getMessage(), (array) $e->getData());
    exit(1);
} catch(ApplicationException $e) {
    Logger::log('error', $e->getMessage(), (array) $e->getData());
    exit($e->getCode() > 1 ? $e->getCode(): 2);
} catch(Exception $e) {
    Logger::log('error', $e->getMessage(), [
        'errFile' => $e->getFile(),
        'errLine' => $e->getLine(),
        'trace' => $e->getTrace()
    ]);
    exit(2);
}

Logger::log('info', "Extractor finished successfully.");
exit(0);
