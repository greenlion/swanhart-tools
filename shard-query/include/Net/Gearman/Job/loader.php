<?php
#THIS JOB INSERTS DATA DIRECTLY INTO the tmp_shard
#This job also uses an unbuffered query
require_once 'worker_common.php';
require_once 'shard-loader.php';

if(function_exists('gc_enable')) gc_enable();

class Net_Gearman_Job_loader extends Net_Gearman_Job_Common
{
    public function run($arg) {

	if(!$arg) return;
	$arg = (object)$arg;

	$SQ = new ShardQuery();
	if(!isset($arg->use_fifo)) $arg->use_fifo = true;
	$SL = new ShardLoader($SQ, $arg->loadspec['delimiter'], $arg->loadspec['enclosure'], $arg->loadspec['line_terminator'], $arg->use_fifo);

	$result = $SL->load_segment($arg->path, $arg->table, $arg->start, $arg->end);
	if(is_array($result)) {
		register_completion($arg->job_id, 'loader', 'error', print_r($result, true));
		return false;
	} 

	register_completion($arg->job_id, 'loader', 'ok','no errors');

	return true;

    }

}

?>
