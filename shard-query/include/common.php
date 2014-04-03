<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 encoding=utf-8 */
/* $Id: */
/*
Copyright (c) 2010, Justin Swanhart
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the <organization> nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/*This script requires PEAR Net_Gearman */
/*It also requires Console_Getopt, but this should be installed by default with pear */
require_once 'instrumentation.php'; #new bsd Instrumentation-for-PHP 
Instrumentation::get_instance()->start_request();
require('const.php');
require_once 'Console/Getopt.php';
require_once 'Net/Gearman/Client.php';
require_once 'DAL/simple-dal.php';
require_once 'PHP-SQL-Parser/php-sql-parser.php';
require_once 'shard-key-mapper.php';
require_once 'custom_functions.php';

function &get_commandline($more_longopts=array()) {
	$cg = new Console_Getopt();
	$args = $cg->readPHPArgv();
	array_shift($args);

	$shortOpts = 'h::v::';
	$longOpts  = array('user=','ini=', 'password=', 'host=', 'db=', 'port=','help==','verbose==', 'method=', 'gearman=','inlist=','between=','directory=','push_where','inlist_merge_threshold==','inlist_merge_size==','coord_engine==');

	$longOpts = array_merge($longOpts, $more_longopts);

	@$params = $cg->getopt2($args, $shortOpts, $longOpts);
	if (@PEAR::isError($params)) {
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


function get_defaults($params) {
	return(get_shards($params,true));
}

function has_short_opt($needle,$haystack) {
	if(!is_array($needle)) {
		$needle=array($needle);
	}

	foreach ($needle as $opt) {
		if(in_array($opt,array_keys($haystack))) return true;
	}

	return false;
}

function write_line($msg){
	echo $msg . "\n";
}

function read_line($return_lf = false){
	$line = "";
	while(1) {
		$c = fgetc(STDIN);
		if($c == "\n" || $c == "\r") break; 
		$line .= $c;
	}

	return($line . ($return_lf ? "\n" : ""));
}

function run_query_help() {
	echo "\nrun query options:\n";
	echo "----------------------\n";
	echo "--file            Full path to the file to read queries from.  If not specified, then queries are read from stdin\n";
}

function loader_help() {
	echo "\nloader options:\n";
	echo "----------------------\n";
	echo "--spec		Full path to the .spec file (loader specfication file)\n";
}

function genspec_help() {
	echo "\ngenspec options:\n";
	echo "----------------------\n";
	echo "--db 		Comma separated list of databases (or just one) to create spec file for\n";
	echo "--user		User to connect as\n";
	echo "--password	Password to connect with\n";
	echo "--host		Host to connect to\n";
	echo "--port		Port to connect to\n";
	echo "--delimiter	Delimiter to use\n";
	echo "--skip		Number of rows to skip\n";
	echo "--enclosure	Enclosure to use\n";
	echo "--workers	Number of workers to invoke\n";
	echo "--path		Path where the input files are located\n";
	
}
	

function shard_query_common_help($exit=false) {
    echo "
common options:
----------------------
--help    This message
--ini     Full path to the .ini file used for shard information.  Only used during bootstrap process.  
--verbose Print verbose information about the work being done
";
	if($exit) exit;
}

function directory_setup_help() {
	exit;
}

