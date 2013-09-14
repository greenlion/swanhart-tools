<?php
declare(ticks = 1);

function sig_handler($signo)
{
     switch ($signo) {
         case SIGTERM:
         case SIGHUP:
	     exit;
     }
}

require_once('Console/Getopt.php');
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

if(in_array('daemon', array_keys($params))) {
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
		exit();
	}
}

#support pid file
if(!empty($params['pid'])) {
	if(file_exists($params['pid'])) {
		$pid = trim(file_get_contents($params['pid']));

		$ps = `ps -p$pid`;

		if(preg_match('/php/i',$ps)) {
			die("Already running!\n");
		} else {
			echo "Stale lockfile detected.\n";
		}
	}
	file_put_contents($params['pid'], getmypid());
}


require_once('include/flexsbr.php');
$cdc = new FlexSBR($settings);
#capture changes forever (-1):
$cdc->capture_changes(-1);
