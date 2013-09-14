<?php
require_once('Console/Getopt.php');
require_once('include/flexcdc.php');

function &get_commandline() {

        $cg = new Console_Getopt();
        $args = $cg->readPHPArgv();
        array_shift($args);

        $shortOpts = 'h::v::';
        $longOpts  = array('ini=','force==');

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

$cdc = new FlexCDC($settings);

#this will read settings from the INI file and initialize
#the database and capture the source master position
echo "setup starting\n";
$cdc->setup(in_array('force',array_keys($params)));
echo "setup completed\n";

?>
