<?php
#Add include folder for shard-query
set_include_path(get_include_path() . PATH_SEPARATOR . '../include');

#load configs
include './protected/config/common.conf.php';
include './protected/config/routes.conf.php';
include './protected/config/db.conf.php';
include $config['BASE_PATH'].'Doo.php';
include $config['BASE_PATH'].'app/DooConfig.php';
include $config['BASE_PATH'].'app/DooCliApp.php';

# Uncomment for auto loading the framework classes.
spl_autoload_register('Doo::autoload');

//only run via CLI
if(!defined('STDIN') ) exit;

//new CLI App
$cli = new DooCliApp;

//config
Doo::conf()->set($config);

//set default db
Doo::db()->setDb($dbconfig, $config['APP_MODE']);

//set routes
$cli->route = $route;

//run controller in args
$cli->run($argv);
?>
