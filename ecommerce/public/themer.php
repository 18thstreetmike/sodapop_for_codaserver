<?php
/*
    This file loads the approapriate resources for a given Sodapop theme.
 */

// determine the current directory and file requested
$requestPath = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['SCRIPT_NAME'], '/'));
$applicationRoot = str_replace('/public/themer.php', '', $_SERVER['SCRIPT_FILENAME']);

if (substr($requestPath, -1) == '/') {
    echo 'Directory listing denied!';
}

// determine the environment
$environment = 'production';
if (getenv('APPLICATION_ENVIRONMENT') && in_array(strtolower(getenv('APPLICATION_ENVIRONMENT')), array('production', 'staging', 'development'))) {
    $environment = strtolower(getenv('APPLICATION_ENVIRONMENT'));
}

// read the ini file to determine the theme and theme path
$config = array();
if (file_exists('../configuration/configuration.ini')) {
    $parsedConfig = parse_ini_file('../configuration/configuration.ini', true);
    if ($parsedConfig) {
	$config = $parsedConfig;
    }
}

require_once('Sodapop/Application.php');
$appConfig = Sodapop_Application::parseIniFile($environment, $config, $applicationRoot);

$path = $appConfig['view.themes.root_directory'].'/'.$appConfig['view.themes.current'].$requestPath;

// get the file
header("Content-type: ".determine_mime_type($path, 'mime.ini'));

if (file_exists($path)) {
    $fp = fopen($path, 'r', false);
    fpassthru($fp);
    fclose($fp);
}
