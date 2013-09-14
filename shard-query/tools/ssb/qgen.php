<?php
class DRV_MYSQL extends StdClass {
	var $_handle = false;
	var $_opts = "";
	function _connect($DB=false) {
		global $options;
		if(!$this->_handle)	{
			$this->_handle = mysql_connect($options->DB_HOST . ':' . $options->DB_PORT,$options->DB_USER,$options->DB_PASS,true);
		}
		mysql_select_db($DB ? $DB : $options->DB_SCHEMA,$this->_handle);
	}

	function run_query($sql,$DB=false) {
		echo $sql . "\n";
		$sql = str_replace(';',' LIMIT 0;',$sql);
		$this->_connect($DB);		
		$stmt = mysql_query($sql,$this->_handle);
		if(!$stmt) {
			echo(mysql_error());
			echo "\n$sql\n";
			exit;
			return -1;
		}
	}
}

function def($const, $key, $default) {
	global $cmdline, $VERBOSE;
	global $options;

	$keys = array_keys($cmdline);
	if(!in_array($key, $keys)) {
		$val = $default;
	} else {
		$val = $cmdline[$key];
	}
	
	$options->$const = $val;
}

function template_replace($sql,$key, $val) {
  return str_replace(':' . $key,$val, $sql);
}

function write_result($qID, $resTime) {
	global $q, $options;
	$fp = fopen("result.log", "a") or die('could not open result.log for writing!');

	if (flock($fp, LOCK_EX)) { // do an exclusive lock
    fwrite($fp, $options->WORKLOAD . "\t" . $options->RUN_INFO . "\t" .$qID . "\t" . "$resTime\n");
    flock($fp, LOCK_UN); 
	} else {
		die('could not lock result file');
	}
	fclose($fp);
}

$cmdline_input ="I:F:h:u:p:P:d:W:L:S:r:";

$cmdline= getopt($cmdline_input);

echo "\n";
$options = new StdClass;

def("RUN_INFO",'I', '"' . trim(`uname -a`) . '"');
def("DB_HOST",'h', '127.0.0.1');
def("DB_USER",'u', 'root');
def("DB_PASS",'p', false);
def("DB_PORT",'P', '3306');
def("DB_DRIVER",'d', 'mysql');
def("WORKLOAD",'W', 'SSB');
def("LOOP_COUNT",'L', 1);
def("SCALE", 'F', 1);
def("DB_SCHEMA",'S',strtolower($options->WORKLOAD) . '_sf' . $options->SCALE);
def("RANDOMIZE", 'r', 'false');

require_once($options->WORKLOAD . '.php');

echo "Configuration after reading command line options:\n";
echo "------------------------------------------------\n";
foreach($options as $key => $val) {
	echo str_pad($key, 18, ' ', STR_PAD_RIGHT) . "|  $val\n";
}
if(empty($cmdline)) { 
	echo "\nCommand line options:\n-Fscale_factor -Hhost -Pport -ppass -Uuser -Sschema -Ddbname -Lloopcount -Irun_information -Wworkload -rrandomize\n"; 
	exit; 
}
echo "\n";

for($z=0;$z<$options->LOOP_COUNT;++$z) {
		if($options->RANDOMIZE !== 'false') shuffle($q[$options->WORKLOAD]);
	
		$db = new DRV_MYSQL;
		foreach($q[$options->WORKLOAD] as $qry) {
			echo "running {$qry['qid']}\n";
			$time=microtime(true);
			if($db->run_query($qry['template'],$options->DB_SCHEMA)) {
				$time=-1;
			} else {
				$time = microtime(true) - $time;
			}
			write_result($qry['qid'], $time);
		}
}

?>

