<?php

class StatusRESTController extends DooController {
	
	function gearman_status(){
		$res = new response;
		require_once 'Net/Gearman/Manager.php';
		
		$id = $this->params["id"];

		$server = Doo::db()->getOne('GearmanJobServers', array('where' => "id = $id"));
		if($server == false){
			$res->message = "invalid id";
			$res->success = false;
		
		}else{
			try{
				$manager = new Net_Gearman_Manager($server->hostname . ":" . $server->port);
				$res->data = $manager->status();
				$res->success = true;
			} catch (Net_Gearman_Exception $e) {
				$res->message = $e->getMessage(); 
				$res->success = false;
			}
		}
		echo $res->to_json();
	}

}
?>
