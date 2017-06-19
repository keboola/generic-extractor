<?php

use Keboola\GenericExtractor\Executor;
use Keboola\Juicer\Exception\ApplicationException;
use Keboola\Juicer\Exception\UserException;

require_once(__DIR__ . "/vendor/autoload.php");

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// initialize logger
$logger = new Monolog\Logger("logger");
$stream = fopen('php://stdout', 'r');
$logger->pushHandler(new \Monolog\Handler\StreamHandler($stream));
//$logger->debug("Starting up");

try {
    $executor = new Executor($logger);
    $executor->run();
} catch (UserException $e) {
    $logger->error($e->getMessage(), (array)$e->getData());
    exit(1);
} catch (ApplicationException $e) {
    $logger->error($e->getMessage(), (array)$e->getData());
    exit($e->getCode() > 1 ? $e->getCode() : 2);
} catch (Exception $e) {
    if ($e instanceof \GuzzleHttp\Exception\RequestException
        && $e->getPrevious() instanceof UserException) {
        /** @var UserException $ex */
        $ex = $e->getPrevious();
        $logger->error($ex->getMessage(), (array)$ex->getData());
        exit(1);
    }
    $logger->error($e->getMessage(), [
        'errFile' => $e->getFile(),
        'errLine' => $e->getLine(),
        'trace' => $e->getTrace(),
        'exception' => get_class($e)
    ]);
    exit(2);
}

$logger->info("Extractor finished successfully.");
exit(0);
