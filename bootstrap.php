<?php
// Define path to application directory
define('ROOT_PATH', __DIR__);

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
	realpath(ROOT_PATH . '/library'),
	get_include_path(),
)));
ini_set('display_errors', true);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Prague');

if (file_exists(__DIR__ . '/config.php')) {
	require_once __DIR__ . '/config.php';
}

// defined('STORAGE_API_URL')
// 	|| define('STORAGE_API_URL', getenv('STORAGE_API_URL') ? getenv('STORAGE_API_URL') : 'https://connection.keboola.com');
//
// defined('STORAGE_API_TOKEN')
// 	|| define('STORAGE_API_TOKEN', getenv('STORAGE_API_TOKEN') ? getenv('STORAGE_API_TOKEN') : 'your_token');
//
// defined('STORAGE_API_MAINTENANCE_URL')
// 	|| define('STORAGE_API_MAINTENANCE_URL', getenv('STORAGE_API_MAINTENANCE_URL') ? getenv('STORAGE_API_MAINTENANCE_URL') : 'https://maintenance-testing.keboola.com/');


require_once ROOT_PATH . '/vendor/autoload.php';
require_once 'tests/ExtractorTestCase.php';
