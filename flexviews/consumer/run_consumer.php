<?php
ini_set('output_buffering',false);
if (is_resource(STDIN)) fclose(STDIN);
require_once('include/flexcdc.php');
require_once('Console/Getopt.php');
declare(ticks = 1);
$ERROR_FILE=false;
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, "sig_handler");
	pcntl_signal(SIGHUP,  "sig_handler");
}

function sig_handler($signo)
{
     switch ($signo) {
         case SIGTERM:
         case SIGHUP:
	     die1(0);
     }
}

#
#if(!function_exists('pcntl_fork')) {
#	function pcntl_fork() {
#		die("The --daemon option requires the pcntl extension.\n");
#	}
#}

function &get_commandline() {

        $cg = new Console_Getopt();
        $args = $cg->readPHPArgv();
        array_shift($args);

        $shortOpts = 'h::v::';
        $longOpts  = array('ini=', 'help==', 'pid=', 'daemon==' );

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

if(!empty($settings['flexcdc']['error_log'])) $ERROR_FILE=$settings['flexcdc']['error_log']; else $ERROR_FILE="flexcdc.err";		
$ERROR_FILE = fopen($ERROR_FILE, 'w') or die1("could not open the error log for writing");

if(in_array('daemon', array_keys($params))) {
	if (is_resource(STDERR)) fclose(STDERR);
	if (is_resource(STDOUT)) fclose(STDOUT);
	$pid = pcntl_fork();
	if($pid == -1) {
		die('Could not fork a new process!\n');
	} elseif($pid == 0) {
		#we are now in a child process, and the capture_changes
	        #below will be daemonized
		pcntl_signal(SIGTERM, "sig_handler");
		pcntl_signal(SIGHUP,  "sig_handler");

	} else {
		#return control to the shell
		exit(0);
	}
}

#support pid file
if(!empty($params['pid'])) {
	if(file_exists($params['pid'])) {
		$pid = trim(file_get_contents($params['pid']));

		$ps = `ps -p$pid`;

		if(preg_match('/php/i',$ps)) {
			echo("Already running!\n");
			exit(1000);
		} else {
			echo "Stale lockfile detected.\n";
		}
	}
	file_put_contents($params['pid'], getmypid());
}

if(empty($settings['flexcdc']['error_log'])) {
	$error_log = "flexcdc.err";  
} else {
	$error_log = $settings['flexcdc']['error_log'];
}


$cdc = new FlexCDC($settings);
#capture changes forever (-1):
$cdc->capture_changes(-1);
