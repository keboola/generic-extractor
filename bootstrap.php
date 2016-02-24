<?php
// Define path to application directory
define('ROOT_PATH', __DIR__);

// Ensure library/ is on include_path
// set_include_path(implode(PATH_SEPARATOR, array(
//     realpath(ROOT_PATH . '/library'),
//     get_include_path(),
// )));
ini_set('display_errors', true);
error_reporting(E_ALL);
set_error_handler(
    function ($errno, $errstr, $errfile, $errline, array $errcontext)
        {
            // error was suppressed with the @-operator
            if (0 === error_reporting()) {
                return false;
            }
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }
);


date_default_timezone_set('Europe/Prague');

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

require_once ROOT_PATH . '/vendor/autoload.php';
require_once 'tests/ExtractorTestCase.php';
