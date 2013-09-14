<?php

class QueryRESTController extends DooController {

 	public function beforeRun($resource, $action)
    {
        //Init
        $this->res = new response();

        //set content type
        $this->contentType = 'json';

        //require shard-query
        require_once 'shard-query.php'; 
    }

    public function afterRun($routeResult)
    { 
    	//Display Results
        if (isset($this->contentType))
            $this->setContentType($this->contentType);
        
        echo $this->res->to_json();
    }

	//POST
	function submit(){	
	
		//Check required Fields
		$missing = false;	
		if(!isset($_POST["sql"]) || strlen($_POST["sql"]) == 0)
			$missing = true;
		
		if($missing){
			$this->res->success = false;
			$this->res->message = "missing required field!";
			return null;
		}
		
		//get vars
		$sql = $_POST["sql"];
		$schema_id = $this->params['schema_id'];
		$schema = Doo::db()->getOne('Schemata', array('where' => 'id = ' . $schema_id));	
		$async = isset($_POST['async']) ? $_POST['async'] : 0;
		$coord_name = isset($_POST['coord_name']) ? $_POST['coord_name'] : null; 
    $query_id = isset($_POST["query_id"]) ? $_POST["query_id"] : strtoupper(md5(uniqid(rand(), true)));

		//init
		$shard_query = new ShardQuery($schema->schema_name);

		//error!
		if(!empty($shard_query->errors)) {
			//return
			$this->res->message = $shard_query->errors;
			$this->res->success = false;
			return null;
		}
		
		//set async
		$shard_query->async = ($async == true ? true : false);

		//set coord shard
		if(isset($coord_name))
			$shard_query->set_coordinator($coord_name);
				
		//execute query
		$stmt = $shard_query->query($sql);

		//error!
		if(!$stmt && !empty($shard_query->errors)) {
			//return
			$this->res->message = $shard_query->errors;
			$this->res->success = false;
			return null;
		}

		//empty results
		if($stmt == null && empty($shard_query->errors)){
			$this->res->data = array();
		}

		//build data
		if(!is_int($stmt)) {
			$this->res->data = $this->json_format($shard_query, $stmt);
			$shard_query->DAL->my_free_result($stmt);
		}else {
			//save job_id
			$this->res->data = $stmt;
		}

		//save message
		$this->res->message = $query_id;
	
		//return
		$this->res->success = true;
	}

	//POST
	function explain(){

		//Check required Fields
		$missing = false;	
		if(!isset($_POST["sql"]) || strlen($_POST["sql"]) == 0)
			$missing = true;
		
		if($missing){
			$this->res->success = false;
			$this->res->message = "missing required field!";
			return null;
		}
		
		//get vars
		$sql = $_POST["sql"];
		$schema_id = $this->params['schema_id'];
		$schema = Doo::db()->getOne('Schemata', array('where' => 'id = ' . $schema_id));		

		//init
		$shard_query = new ShardQuery($schema->schema_name);
				
		//execute query
		$stmt = $shard_query->query($sql, true);

		//build data
		$this->res->message = $stmt;
	
		//return
		$this->res->success = true;
	}

	//GET
	function async_completion_perc(){
		$schema_id = $this->params['schema_id'];
		$job_id = $this->params['job_id'];

		//lookup schema name
		$schema = Doo::db()->getOne('Schemata', array('where' => 'id = ' . $schema_id));	


		//update jobs before we check!
		$shard_query = new ShardQuery($schema->schema_name);
		$shard_query->state->mapper->sweep_jobs();


		//get job
		$j = new Jobs();
		$j->id = $job_id;
		$j = $j->getOne();

		//check if job exist
		if(!$j){
			$this->res->success = false;
			$this->res->message = "Job_ID: $job_id doesnt exist!";
			return null;
		}

		$this->res->success = true;
		$this->res->data = $j->completion_percent;
		$this->res->message = $j->job_status;
	}

	//GET
	function fetch(){
		$schema_id = $this->params['schema_id'];
		$job_id = $this->params['job_id'];

		//lookup schema name
		$schema = Doo::db()->getOne('Schemata', array('where' => 'id = ' . $schema_id));	

		//update jobs before we check!
		$shard_query = new ShardQuery($schema->schema_name);
		$shard_query->state->mapper->sweep_jobs();

		//get job
		$j = new Jobs();
		$j->id = $job_id;
		$j = $j->getOne();

		//check if job exist
		if(!$j){
			$this->res->success = false;
			$this->res->message = "Job_ID: $job_id doesnt exist!";
			return null;
		}

		//check if job compeleted
		if($j->job_status != 'completed'){
			$this->res->success = false;
			$this->res->message = "Job_ID: $job_id not completed!";
		}else{

			//get result from job_id
			$stmt = $shard_query->get_async_result($job_id);

			//build data
			if($stmt) {
				$this->res->success = true;
				$this->res->data = $this->json_format($shard_query, $stmt);
				$shard_query->DAL->my_free_result($stmt);
			}else {
				//error
				$this->res->success = false;
				$this->res->data = $stmt;
				$this->res->message = $shard_query->DAL->my_error();
			}
		}
	}

	private function json_format($shard_query, $stmt){
		$data = array();
		//loop rows
		while($row = $shard_query->DAL->my_fetch_assoc($stmt)) {
			$data[] = $row;
		}
		return $data;
	}
}
?>
