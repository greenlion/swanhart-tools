<?php

class GearmanJobServerCLIController extends DooCliController {
	
	//
	// Usage: php cli.php stop_job_servers
	//
	function stop_job_servers(){
	
		//find current local job servers 
		foreach(Doo::db()->find('GearmanJobServers', array('where' => 'local = 1')) as $js){
			//stop job_server
			$p = new Process();
			if(isset($js->pid)){
				$p->setPid($js->pid);
				$p->stop();
			}
			$js->pid = null;
			$js->update(array('setnulls' => true));
		}
	}
	
	//
	// Usage: php cli.php check_job_servers 
	//
	function check_job_servers(){

		//loop schemas
		foreach(Doo::db()->find('Schemata') as $s){

			$schema_id = $s->id;

			//enabled config with PID
			foreach(Doo::db()->find('GearmanJobServers', array('where' => 'local = 1 AND enabled = 1 AND schema_id = ' . $schema_id)) as $js){
				if(!empty($js->pid)){

					//init
					$p = new Process();
					$p->setPid($js->pid);
			
					//check status
					if($p->status())
						continue;
		
				}

				//let start
				$js->pid = $this->start_local_job_server($js->id, $js->port, $s->id);
				$js->update();	
				
			}

			//disabled config
			foreach(Doo::db()->find('GearmanJobServers', array('where' => 'local = 1 AND enabled = 0 AND pid IS NOT NULL AND schema_id = ' . $schema_id)) as $js){
				//stop
				$p = new Process();
				$p->setPid($js->pid);
				$p->stop();
				$js->pid = null;
				$js->update(array('setnulls' => true));	
			}
		}
	}
	
	// 
	// Start new gearmand instance!
	//
	// Note: make sure ui/protected/log and ui/protected/var are owned the user running the daemon
	function start_local_job_server($id, $port, $schema_id){

		//get path
		$config = Doo::db()->getOne('SchemataConfig', array('where' => "schema_id = $schema_id AND var_name = 'gearmand_path'")); 				
		$gearmand_path = $config != false ? $config->var_value : '';

		if($gearmand_path === "") {
			$path = getenv('PATH');
			putenv("PATH=/sbin:/usr/sbin:/bin:/usr/bin:/usr/local/bin:$path");
			$which = `which gearmand`;
			if($which) $gearmand_path = trim($which); 
		} else {
			if(is_dir($gearmand_path)) $gearmand_path = rtrim($gearmand_path, "/") . '/gearmand';
		}

		$log_file = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "log/gearmand.{$id}.log";
		$pid_file = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "var/gearmand.{$id}.pid";

		if($gearmand_path === "") {
			$fh = fopen($log_file, 'a+');
			fputs($fh, `date` . " - could not find gearmand in path!\n");
			return false;
		}
		if(file_exists($pid_file))
			unlink($pid_file);

		//send command to shell
		$cmd =  $gearmand_path . " -d -L 127.0.0.1 -p $port -l $log_file --pid-file=$pid_file";

		$iam = trim(`whoami`);
		if($iam === "root") $cmd .= " -u shard-query";

		//execute command
		exec($cmd,$output);

		//wait for pid file creation
		sleep(2);

		//check pid file
		if(!file_exists($pid_file)) {
			$fh = fopen($log_file, 'a+');
			fputs($fh, trim(`date`) . " - gearman could not be started.  PID file not found. [$output]\n");
			return false;
		}
		
		//return pid
		return file_exists($pid_file) ? file_get_contents($pid_file) : null;
	}
}
