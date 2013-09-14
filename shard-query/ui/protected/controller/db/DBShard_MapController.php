<?php
class DBShard_MapController extends DBRESTController
{
    function __construct(){
        $this->model = 'ShardMap';
    }
 
    //override
	public function create(){
		require('shard-key-mapper.php');
		require('shard-query-config.php');

		//init vars
		$this->initPutVars();
		$column_id = $this->puts['column_id'];
		$shard_id = $this->puts['shard_id'];
		$key_value = $this->puts['key_value'];

		//get column info
		$cs = new ColumnSequences();
		$cs->id = $column_id;
		$cs = $cs->getOne(array( 'asArray' => true));
		
		//get shard info
		$s = new Shards();
		$s->id = $shard_id;
		$shard = $s->getOne();

		//get schemata info
		$s = new Schemata();
		$s->id = $shard->schema_id;
		$schemata = $s->getOne();
		
		//init DirectoryShardKeyMapper
		$mapper = new DirectoryShardKeyMapper($config_database);
		$result = $mapper->new_key($cs['sequence_name'], $key_value, $shard->shard_name, $schemata->schema_name, $cs);

		//assumed success
		$this->res->message = $result;
		$this->res->success = true;
	}   
}
?>
