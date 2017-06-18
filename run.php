<?php

use Keboola\GenericExtractor\Executor;
use Keboola\Juicer\Common\Logger;
use Keboola\Juicer\Exception\ApplicationException;
use Keboola\Juicer\Exception\UserException;

require_once(dirname(__FILE__) . "/bootstrap.php");

const APP_NAME = 'ex-generic-v2';

function userError(UserException $e)
{
    Logger::log('error', $e->getMessage(), (array) $e->getData());
    exit(1);
}

try {
    $executor = new Executor($logger);
    $executor->run();
} catch (UserException $e) {
    userError($e);
} catch (ApplicationException $e) {
    Logger::log('error', $e->getMessage(), (array) $e->getData());
    exit($e->getCode() > 1 ? $e->getCode(): 2);
} catch (Exception $e) {
    if ($e instanceof \GuzzleHttp\Exception\RequestException
        && $e->getPrevious() instanceof UserException) {
        userError($e->getPrevious());
    }

    Logger::log('error', $e->getMessage(), [
        'errFile' => $e->getFile(),
        'errLine' => $e->getLine(),
        'trace' => $e->getTrace(),
        'exception' => get_class($e)
    ]);
    exit(2);
}

Logger::log('info', "Extractor finished successfully.");
exit(0);
