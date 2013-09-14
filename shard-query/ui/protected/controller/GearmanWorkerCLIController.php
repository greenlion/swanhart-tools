<?php
set_include_path(get_include_path() . PATH_SEPARATOR . '../../include');


class GearmanWorkerCLIController extends DooCliController {

	//
	// Usage: php cli.php worker schema_id function
	//
	function worker (){
		//check for args
		if(count($this->arguments) != 3){
			$this->writeLine("Usage: worker schema_id function");
			exit;
		}
		$schema_id = $this->arguments[1];
		$function = $this->arguments[2];
		
		#define('NET_GEARMAN_JOB_PATH', "../../include/Net/Gearman/Job/" );
		require_once 'Net/Gearman/Worker.php';

		//create server array
        	$servers = array();
		foreach( Doo::db()->find('GearmanJobServers', array('where' => "schema_id = $schema_id")) as $s){
			$servers[] = $s->hostname . ":" . $s->port;
		}
			
		try {
			$worker = new Net_Gearman_Worker($servers);
			$worker->addAbility($function);
			$worker->beginWork();
		} catch (Net_Gearman_Exception $e) {
			echo $e->getMessage() . "\n";
			exit;
		}
	}
	
	//
	// Usage: php cli.php stop_workers 
	//
	function stop_workers(){
	
		//find current workers
		$runningWorkers = Doo::db()->find('GearmanWorkers');
		foreach($runningWorkers as $worker){
		
			//init
			$p = new Process();
			$p->setPid($worker->pid);
			$p->stop();
			$worker->delete();
		}
	}
	
	//
	// Usage: php cli.php check_workers 
	//
	function check_workers(){

		//loop schemas
		foreach(Doo::db()->find('Schemata') as $s){

			$schema_id = $s->id;


			$funcs = Doo::db()->fetchAll("SELECT a.id, a.schema_id, b.function_name, a.worker_count, a.enabled
				FROM gearman_functions a
				LEFT JOIN gearman_function_names b
				ON a.function_name_id = b.id
				WHERE enabled = 1
				AND schema_id = $schema_id");
		
			//read worker config
			$worker_functions = array();
			foreach($funcs as $f){
				$worker_functions[$f["function_name"]] = $f["worker_count"];
			}

			//Loop enabled config
			foreach($worker_functions as $function => $worker_count){
				
				//find current workers for the function
				$runningWorkers = Doo::db()->find('GearmanWorkers', array('where' => "function_name = '$function' AND schema_id = $schema_id"));
			
				//scan workers to make sure they are still running
				foreach($runningWorkers as $worker){
				
					//init
					$p = new Process();
					$p->setPid($worker->pid);
				
					//check status
					if(!$p->status()){

						//crashed! Lets re init
						$gw = new GearmanWorkers;
						$gw->pid = $this->start_worker($function, $schema_id);
						$gw->function_name = $function;
						$gw->schema_id = $schema_id;
						$gw->insert();
						
						//remove crashed pid
						$worker->delete();
					}	
				}
				
				//calc delta workers
				$delta = $worker_count - count($runningWorkers);

				//add missing workers
				if($delta > 0){
					
					for($i = 0; $i < $delta ; $i++){
						//run process
						$sw = new GearmanWorkers;
						$sw->pid = $this->start_worker($function, $schema_id);
						$sw->function_name = $function;
						$sw->schema_id = $schema_id;
						$sw->insert();		
					}	
				}
				//remove extra workers
				if($delta < 0){
					//find current workers for the function
					$runningWorkers = Doo::db()->find('GearmanWorkers', array('where' => "function_name = '$function' AND schema_id = $schema_id"));
					for($i = 0; $i < abs($delta) ; $i++){
						//kill process
						$worker = $runningWorkers[$i];
						$p = new Process();
						$p->setPid($worker->pid);
						$p->stop();
						$worker->delete();
					}	
				}
			}

			//disabled config
			$funcs = Doo::db()->fetchAll("SELECT a.id, a.schema_id, b.function_name, a.worker_count, a.enabled
				FROM gearman_functions a
				LEFT JOIN gearman_function_names b
				ON a.function_name_id = b.id
				WHERE enabled = 0
				AND schema_id = $schema_id");

			foreach($funcs as $f){
				$function = $f["function_name"];

				//find current workers for the function
				$disabledWorkers = Doo::db()->find('GearmanWorkers', array('where' => "function_name = '$function' AND schema_id = $schema_id"));

				//scan workers and kill to disable
				foreach($disabledWorkers as $worker){
				
					//init
					$p = new Process();
					$p->setPid($worker->pid);
					$p->stop();
					$worker->delete();
				}
				
			}
		}
	}
	
	private function start_worker($function, $schema_id){

		$cmd = "php cli.php worker $schema_id $function > protected/log/gearman_worker.{$schema_id}.{$function}.log 2>&1";
		$p = new Process($cmd);
		return $p->getPid();
	}
	
}
