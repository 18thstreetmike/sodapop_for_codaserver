<?php
require_once('Sodapop/Application.php');

ini_set('unserialize_callback_func', '__unserialize');

session_start();

ini_set('display_errors', '1');
error_reporting(E_ALL);


// determine the environment
$environment = 'production';
if (getenv('APPLICATION_ENVIRONMENT') && in_array(strtolower(getenv('APPLICATION_ENVIRONMENT')), array('production', 'staging', 'development'))) {
    $environment = strtolower(getenv('APPLICATION_ENVIRONMENT'));
}

// load the config file
$config = array();
if (file_exists('../configuration/configuration.ini')) {
    $parsedConfig = parse_ini_file('../configuration/configuration.ini', true);
    if ($parsedConfig) {
	$config = $parsedConfig;
    }
}

// instantiate the application
$application = new Sodapop_Application($environment, $config);
$application->bootstrap()->run();
