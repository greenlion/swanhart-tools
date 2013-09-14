<?php
require_once('Console/Getopt.php');

function &get_commandline() {

        $cg = new Console_Getopt();
        $args = $cg->readPHPArgv();
        array_shift($args);

        $shortOpts = 'h::v::';
        $longOpts  = array('ini=', 'schema==', 'table==');

        $params = $cg->getopt2($args, $shortOpts, $longOpts);
        if (PEAR::isError($params)) {
            echo 'Error: ' . $params->getMessage() . "\n";
            exit(1);
        }
        $new_params = array();
        foreach ($params[0] as $param) {
                $param[0] = str_replace('--','', $param[0]);
                $new_params[$param[0]] = $param[1];
        }
        unset($params);

        return $new_params;
}

$params = get_commandline();
$settings = false;

#support specifying location of .ini file on command line
if(!empty($params['ini'])) {
	$settings = @parse_ini_file($params['ini'], true);
}

require_once('include/flexcdc.php');
$cdc = new FlexCDC($settings);

if(empty($params['schema']) || empty($params['table'])) {
	die("usage: drop_changelog.php --schema=<SCHEMA> --table=<TABLE>\nWhere SCHEMA is the name of the database and table is the name of the table\n");
}

if(!$cdc->drop_mvlog($params['schema'], $params['table'])) {
	die("failure: Could not drop the change log table\n");
}

echo "success\n";

?>
