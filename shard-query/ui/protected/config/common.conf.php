<?php
/* 
 * Common configuration that can be used throughout the application
 * Please refer to DooConfig class in the API doc for a complete list of configurations
 * Access via Singleton, eg. Doo::conf()->BASE_PATH;
 */
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('UTC');

/**
 * for benchmark purpose, call Doo::benchmark() for time used.
 */
//$config['START_TIME'] = microtime(true);

$config['APP_NAME'] = 'shard-query';

//For framework use. Must be defined. Use full absolute paths and end them with '/'      eg. /var/www/project/
$config['SITE_PATH'] = realpath('..').'/ui/';
//$config['PROTECTED_FOLDER'] = 'protected/';
$config['BASE_PATH'] = '/usr/share/dooframework/';

//for production mode use 'prod'
$config['APP_MODE'] = 'prod';

//----------------- optional, if not defined, default settings are optimized for production mode ----------------
//if your root directory is /var/www/ and you place this in a subfolder eg. 'app', define SUBFOLDER = '/app/'

/*$config['SUBFOLDER'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\','/',$config['SITE_PATH']));
if(strpos($config['SUBFOLDER'], '/')!==0){
	$config['SUBFOLDER'] = '/'.$config['SUBFOLDER'];
}*/

$config['SUBFOLDER'] = '/'. $config['APP_NAME'] . '/';
$config['APP_URL'] = (isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'].$config['SUBFOLDER'] : '');
//$config['AUTOROUTE'] = TRUE;
$config['DEBUG_ENABLED'] = TRUE;

//$config['TEMPLATE_COMPILE_ALWAYS'] = TRUE;

//register functions to be used with your template files
//$config['TEMPLATE_GLOBAL_TAGS'] = array('url', 'url2', 'time', 'isset', 'empty');

/**
 * Path to store logs/profiles when using with the logger tool. This is needed for writing log files and using the log viewer tool
 */
//$config['LOG_PATH'] = '/var/logs/';


/**
 * defined either Document or Route to be loaded/executed when requested page is not found
 * A 404 route must be one of the routes defined in routes.conf.php (if autoroute on, make sure the controller and method exist)
 * Error document must be more than 512 bytes as IE sees it as a normal 404 sent if < 512b
 */
//$config['ERROR_404_DOCUMENT'] = 'error.php';
$config['ERROR_404_ROUTE'] = '/error';


/**
 * Settings for memcache server connections, you don't have to set if using localhost only.
 * host, port, persistent, weight
 * $config['MEMCACHE'] = array(
 *                       array('192.168.1.31', '11211', true, 40),
 *                       array('192.168.1.23', '11211', true, 80)
 *                     );
 */
 
/**
 * Defines modules that are allowed to be accessed from an auto route URI.
 * Example, we have a module in SITE_PATH/PROTECTED_FOLDER/module/example
 * It can be accessed via http://localhost/example/controller/method/parameters
 * 
 * $config['MODULES'] = array('example');
 * 
 */

/**
 * Unique string ID of the application to be used with PHP 5.3 namespace and auto loading of namespaced classes
 * If you wish to use namespace with the framework, your classes must have a namespace starting with this ID.
 * Example below is located at /var/www/app/protected/controller/test and can be access via autoroute http://localhost/test/my/method
 * <?php
 * namespace myapp\controller\test;
 * class MyController extends \DooController {
 *     .....
 * } ?>
 *
 * You would need to enable autoload to use Namespace classes in index.php 
 * spl_autoload_register('Doo::autoload');
 *
 * $config['APP_NAMESPACE_ID'] = 'myapp';
 *
 */
 
/**
 * To enable autoloading, add directories which consist of the classes needed in your application. 
 */
  $config['AUTOLOAD'] = array(
                            //internal directories, live in the app
                            'class', 'model', 
                            //external directories, live outside the app
                              '/var/php/library/classes'
                        );
/*/

/**
 * you can include self defined config, retrieved via Doo::conf()->variable
 * Use lower case for you own settings for future Compability with DooPHP
 */
//$config['pagesize'] = 10;
