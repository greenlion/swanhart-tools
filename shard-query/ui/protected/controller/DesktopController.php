<?php

class DesktopController extends DooController {

	function index(){
		//Redirect to status as default page		
		header('Location: query');
	}
    
    function schemas(){
        $data['title'] = 'SQ - Sharded Schemas';

		//set default
		$default_schemata = Doo::db()->getOne("Schemata", array('where' => 'is_default_schema = 1'));
		$schema_id = isset($_GET['schema_id']) ? $_GET['schema_id'] : $default_schemata->id ;

		//filter for integer
		if(!preg_match('/^\d+$/', $schema_id))
			return 200;

		//save	
		$data['schema_id'] = $schema_id;
        
        //virtual schemas
        $data['schemata'] = Doo::db()->find("Schemata");

		//query config
		$data['config'] = Doo::db()->find("SchemataConfig", array('where' => "schema_id = $schema_id"));

		//query config items
		$data['config_items'] = Doo::db()->find('SchemataConfigItems');

        //render view
        $this->renderc('schemas', $data);
    }

	function query(){
        //page title
		$data['title'] = 'SQ - Query';

		//set default
		$default_schemata = Doo::db()->getOne("Schemata", array('where' => 'is_default_schema = 1'));
		$schema_id = isset($_GET['schema_id']) ? $_GET['schema_id'] : $default_schemata->id ;

		//filter for integer
		if(!preg_match('/^\d+$/', $schema_id))
			return 200;

		//save	
		$data['schema_id'] = $schema_id;
			
		//virtual schemas
		$data['schemata'] = Doo::db()->find("Schemata");
		
		//query shards
		$data['coord_shards'] = Doo::db()->find("Shards", array('where' => "schema_id = $schema_id AND coord_shard = 1"));
		
		//render view
		$this->renderc('query', $data);
	}

	function status(){
        //page title
		$data['title'] = 'SQ - Status';
		
		//set default
		$default_schemata = Doo::db()->getOne("Schemata", array('where' => 'is_default_schema = 1'));
		$schema_id = isset($_GET['schema_id']) ? $_GET['schema_id'] : $default_schemata->id ;
	
		//filter for integer
		if(!preg_match('/^\d+$/', $schema_id))
			return 200;
		
		//save	
		$data['schema_id'] = $schema_id;
		
		//virtual schemas
		$data['schemata'] = Doo::db()->find("Schemata");

		//query shards
		$data['shards'] = Doo::db()->find("Shards", array('where' => "schema_id = $schema_id"));

		//query gearman job servers
		$data['gearman-job-servers'] = Doo::db()->find("GearmanJobServers" , array('where' => "schema_id = $schema_id"));

		//query gearman functions
		$data['gearman-functions'] = Doo::db()->find("GearmanFunctions" , array('where' => "schema_id = $schema_id"));
        
        //function names
        $data['gearman_function_names']  = Doo::db()->find("GearmanFunctionNames");
        
		//function name map
		$function_names = array();
		foreach($data['gearman_function_names'] as $fn)
            $function_names[$fn->id] = $fn->function_name;
		
		//save function name map
		$data['gearman_function_names_map'] = $function_names;
		
		//render view
		$this->renderc('status', $data, true);
	}
	
	function key_directory(){
		//page title
		$data['title'] = 'SQ - Key Directory';

		//set default
		$default_schemata = Doo::db()->getOne("Schemata", array('where' => 'is_default_schema = 1'));
		$schema_id = isset($_GET['schema_id']) ? $_GET['schema_id'] : $default_schemata->id ;
		$page_num = isset($_GET['page_num']) ? $_GET['page_num'] : 1;


		//filter for integer
		if(!preg_match('/^\d+$/', $schema_id))
			return 200;

		//save	
		$data['schema_id'] = $schema_id;
		$data['page_num'] = $page_num;
			
		//virtual schemas
		$data['schemata'] = Doo::db()->find("Schemata");
			
		//columns
		$data['column_sequences'] = Doo::db()->find("ColumnSequences" , array('where' => "schema_id = $schema_id"));

		//column map
		$columns = array();
		foreach($data['column_sequences'] as $cs)
			$columns[$cs->id] = $cs->sequence_name;
		
		//save column map
		$data['columns'] = $columns;
		
		//mappings
		if(count($columns) > 0){
			$data['list_key_mappings'] = Doo::db()->find("ShardMap" , array('where' => "column_id IN(" . implode(",", array_keys($columns)) . ")", 'limit' => (($page_num - 1)* 20) . ", 20"));
            $data['range_key_mappings'] = Doo::db()->find("ShardRangeMap" , array('where' => "column_id IN(" . implode(",", array_keys($columns)) . ")"));
        }
		
		//shard map
		$shards = array();
		foreach(Doo::db()->find("Shards", array('where' => "schema_id = $schema_id")) as $s){
			$shards[$s->id] = $s->shard_name;
		}
		
		//save shard map
		$data['shards'] = $shards;
        
		//render view
		$this->renderc('key_directory', $data, true);
		
	}

	function gearman_status($server){

		require_once 'Net/Gearman/Manager.php';
		try{
			$manager = new Net_Gearman_Manager($server->hostname . ":" . $server->port);
			$res['data'] = $manager->status();
			$res['success'] = true;
		} catch (Net_Gearman_Exception $e) {
			$res['data'] = $e->getMessage(); 
			$res['success'] = false;
		}
		return $res;

	}
}
?>
