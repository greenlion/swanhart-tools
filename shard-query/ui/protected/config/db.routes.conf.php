<?php

// GET 
$route['get']['/db/shards/:id'] = array('db/DBShardController', 'view', 'match'=> array('id'=>'/^\d+$/'));
// POST
$route['post']['/db/shards'] = array('db/DBShardController', 'create');
// PUT
$route['put']['/db/shards/:id'] = array('db/DBShardController', 'update', 'match'=> array('id'=>'/^\d+$/'));
// DELETE
$route['delete']['/db/shards/:id'] = array('db/DBShardController', 'destroy', 'match'=> array('id'=>'/^\d+$/'));

// GET 
$route['get']['/db/gearman_job_servers/:id'] = array('db/DBGearman_Job_ServersController', 'view', 'match'=> array('id'=>'/^\d+$/'));
// POST
$route['post']['/db/gearman_job_servers'] = array('db/DBGearman_Job_ServersController', 'create');
// PUT
$route['put']['/db/gearman_job_servers/:id'] = array('db/DBGearman_Job_ServersController', 'update', 'match'=> array('id'=>'/^\d+$/'));
// DELETE
$route['delete']['/db/gearman_job_servers/:id'] = array('db/DBGearman_Job_ServersController', 'destroy', 'match'=> array('id'=>'/^\d+$/'));

// GET 
$route['get']['/db/gearman_functions/:id'] = array('db/DBGearman_FunctionsController', 'view', 'match'=> array('id'=>'/^\d+$/'));
// POST
$route['post']['/db/gearman_functions'] = array('db/DBGearman_FunctionsController', 'create');
// PUT
$route['put']['/db/gearman_functions/:id'] = array('db/DBGearman_FunctionsController', 'update', 'match'=> array('id'=>'/^\d+$/'));
// DELETE
$route['delete']['/db/gearman_functions/:id'] = array('db/DBGearman_FunctionsController', 'destroy', 'match'=> array('id'=>'/^\d+$/'));
    
// GET
$route['get']['/db/schemata/:id'] = array('db/DBSchemataController', 'view', 'match'=> array('id'=>'/^\d+$/'));
// POST
$route['post']['/db/schemata'] = array('db/DBSchemataController', 'create');
// PUT
$route['put']['/db/schemata/:id'] = array('db/DBSchemataController', 'update', 'match'=> array('id'=>'/^\d+$/'));
// DELETE
$route['delete']['/db/schemata/:id'] = array('db/DBSchemataController', 'destroy', 'match'=> array('id'=>'/^\d+$/'));
    
// GET
$route['get']['/db/schemata_config/:id'] = array('db/DBSchemata_ConfigController', 'view', 'match'=> array('id'=>'/^\d+$/'));
// POST
$route['post']['/db/schemata_config'] = array('db/DBSchemata_ConfigController', 'create');
// PUT
$route['put']['/db/schemata_config/:id'] = array('db/DBSchemata_ConfigController', 'update', 'match'=> array('id'=>'/^\d+$/'));
// DELETE
$route['delete']['/db/schemata_config/:id'] = array('db/DBSchemata_ConfigController', 'destroy', 'match'=> array('id'=>'/^\d+$/'));

// GET
$route['get']['/db/column_sequences/:id'] = array('db/DBColumn_SequencesController', 'view', 'match'=> array('id'=>'/^\d+$/'));
// POST
$route['post']['/db/column_sequences'] = array('db/DBColumn_SequencesController', 'create');
// PUT
$route['put']['/db/column_sequences/:id'] = array('db/DBColumn_SequencesController', 'update', 'match'=> array('id'=>'/^\d+$/'));
// DELETE
$route['delete']['/db/column_sequences/:id'] = array('db/DBColumn_SequencesController', 'destroy', 'match'=> array('id'=>'/^\d+$/'));

// GET
$route['get']['/db/shard_map/:id'] = array('db/DBShard_MapController', 'view', 'match'=> array('id'=>'/^\d+$/'));
// POST
$route['post']['/db/shard_map'] = array('db/DBShard_MapController', 'create');
// PUT
//$route['put']['/db/shard_map/:id'] = array('db/DBShard_MapController', 'update', 'match'=> array('id'=>'/^\d+$/'));
// DELETE
//$route['delete']['/db/shard_map/:id'] = array('db/DBShard_MapController', 'destroy', 'match'=> array('id'=>'/^\d+$/'));


/* NOT IMPLEMENTED YET
// GET
$route['get']['/db/shard_range_map/:id'] = array('db/DBShard_Range_MapController', 'view', 'match'=> array('id'=>'/^\d+$/'));
// POST
$route['post']['/db/shard_range_map'] = array('db/DBShard_Range_MapController', 'create');
// PUT
$route['put']['/db/shard_range_map/:id'] = array('db/DBShard_Range_MapController', 'update', 'match'=> array('id'=>'/^\d+$/'));
// DELETE
$route['delete']['/db/shard_range_map/:id'] = array('db/DBShard_Range_MapController', 'destroy', 'match'=> array('id'=>'/^\d+$/'));
*/

?>
