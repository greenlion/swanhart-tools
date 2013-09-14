<?php

class SchemaRestController extends DooController {

	function tables(){
		$res = new response;
		$db = $this->params["db"];

		//query
		$result = Doo::db()->fetchAll("SHOW TABLES IN $db");
		
		foreach($result as $row){
			$data[] = $row["Tables_in_$db"];
		}
		
		$res->data = $data;
		$res->success = true;
		echo $res->to_json();
	}
	
	
	function fields(){
		$res = new response;
		$db = $this->params["db"];
		$table = $this->params["table"];
		
		//query
		$result = Doo::db()->fetchAll("SHOW COLUMNS IN $table IN $db");
		
		foreach($result as $row){
			$data[] = array('field' => $row["Field"], 'type' => $row["Type"], 'null' => $row['Null'], 'key' => $row['Key'], 'default' => $row['Default']);
		}
	
		$res->data = $data;
		$res->success = true;
		echo $res->to_json();
	}

}


?>
