<?php
/**
 * Brigades.org PHP Zend Project
 *
 * @author: Eamonn Pascal / Meynard Manaban
 * @version: 1.0
 */

set_include_path(realpath(dirname(__FILE__) . '/../').'/library' . PATH_SEPARATOR . realpath(dirname(__FILE__) . '/../').'/library/Brigade' . PATH_SEPARATOR . realpath(dirname(__FILE__) . '/../').'/application/default/models' . PATH_SEPARATOR . get_include_path());

require_once 'Zend/Loader.php';
require_once 'Zend/Loader/Autoloader.php';

// Set up autoload.
$loader = Zend_Loader_Autoloader::getInstance();
$loader->registerNamespace('ZFDebug', __DIR__ . '/../library/ZFDebug');

//Session
if (!Zend_Session::sessionExists()) {
    Zend_Session::start();
}

require_once 'Initializer.php';

// Prepare the front controller.
$frontController = Zend_Controller_Front::getInstance();

$configuration = new Zend_Config_Ini($root = realpath(dirname(__FILE__) . '/../') . '/configs/db/config.ini');
$frontController->registerPlugin(new Initializer($configuration->environment, null, $configuration->get($configuration->environment)));

// Dispatch the request using the front controller.
$frontController->dispatch();
