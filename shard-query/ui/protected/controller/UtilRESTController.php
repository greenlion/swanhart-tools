<?php

class UtilRESTController extends DooController {

 	public function beforeRun($resource, $action)
    {
        //Init
        $this->res = new response();

        //set content type
        $this->contentType = 'json';
    }

    public function afterRun($routeResult)
    { 
    	//Display Results
        if (isset($this->contentType))
            $this->setContentType($this->contentType);
        
        echo $this->res->to_json();
    }

	function remove_agg_tmp_tables(){
		require 'instrumentation.php';
		require 'simple-dal.php';
		$schema_id = $this->params['schema_id'];
		
		// shards
		$shards = Doo::db()->find('Shards', array('where' => "schema_id = $schema_id"));
		$drop_count = 0;
		foreach($shards as $s){
			$server = get_object_vars($s);
			$server['user'] = $s->username;
			$conn = SimpleDAL::factory($server);
			$conn->my_select_db($server['db']);
			//get tables
			$stmt = $conn->my_query("SHOW TABLES");

			while($row = $conn->my_fetch_assoc($stmt)) {
				$table = $row['Tables_in_' . $server['db']];
			
                                if(preg_match('/(aggregation_tmp_|agg_tmp_)/', $table)){
					$conn->my_query("DROP TABLE " . $table);
					$drop_count++;
				}
			}	
			$conn->my_close();
		}
		$this->res->message = "Dropped $drop_count Tables";
		$this->res->success = true;
	}
}
?>