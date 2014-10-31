<?php
/* $Id: */
/*
Copyright (c) 2010, Justin Swanhart
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
* Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
* Neither the name of the <organization> nor the
names of its contributors may be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

ini_set('memory_limit', '-1');
define('SQ_DEBUG', false);

/*This script requires PEAR Net_Gearman */
/*It also requires Console_Getopt, but this should be installed by default with pear */
require_once 'common.php';
require_once 'shard-loader.php';
//$params = get_commandline();

class ShardQuery {
  var $parser = false;
  var $messages = array();
  var $warnings = array();
  var $verbose;
  var $info = array();
  
  var $workers; // the count of tasks
  var $shard_column = false;
  
  var $col_metadata_cache = array();
  var $table_is_sharded_cache = array();
  var $async = false;
  var $total_time = 0;
  var $exec_time = 0;
  var $parse_time = 0;
  var $errors = array();
  
  function __get($propname) {
    if(isset($this->$propname))
      return ($this->$propname);
    if(isset($this->state) && isset($this->state->$propname))
      return ($this->state->$propname);
  }
  
  static function new_state() {
    $state = new StdClass;
    
    $state->insert_values = "";
    $state->force_shard = array();
    $state->table_name = "";
    $state->agg_key_cols = "";
    $state->non_distrib = false;
    $state->insert_sql = "";
    $state->used_colrefs = array();
    $state->coord_odku = array();
    $state->subqueries = array();
    $state->broadcast_query = false;
    $state->tmp_shard = null;
    $state->client = null;
    $state->store_resultset_count = 0;
    $state->function_count = 0;
    $state->ignore_virtual_schema = false;
    $state->dependent_subqueries = array();
    $state->push_join = array();
    $state->push_where = array();
    $state->used_distinct = false;
    $state->windows = array();
    $state->added_where = false;
    $state->used_star = false;
    
    return $state;
  }
  
  function __construct($schema_name = null, $config_file = 'shard-query-config.php') {
    $this->parser = new PHPSQLParser();
    ShardQueryCustomFunctions::register_all($this->parser);
    $state = ShardQuery::new_state();
    $this->state = $this->set_schema($schema_name, $state, $config_file);
  }
  
  function set_coordinator($shard_name = null) {
    if(!isset($shard_name))
      $shard_name = array_rand($this->state->shards);
    
    if(isset($this->state->shards[$shard_name])) {
      $this->state->tmp_shard = $this->state->shards[$shard_name];
      return true;
    } else {
      return false;
    }
  }
  
  function set_schema($schema_name = null, &$state = null, $config_file = 'shard-query-config.php') {
    if(!isset($state))
      $state = ShardQuery::new_state();
    
    require($config_file);
    if(!$schema_name && isset($config_database['default_virtual_schema']))
      $schema_name = $config_database['default_virtual_schema'];
    $state->schema_name = $schema_name;
    
    /* reset defaults */
    $state->verbose = false;
    $state->inlist_opt = false;
    $state->between_opt = false;
    $state->gearman_servers = array();
    $state->inlist_merge_threshold = 128;
    $state->inlist_merge_size = 128;
    $state->star_opt = false;
    $state->current_schema = $schema_name;
    $state->extra_tables = array(); //any tables that get created along the way and need cleaning up at the end
    
    switch($config_database['mapper_type']) {
      case SQ_DIRECTORY:
        $mapper = new DirectoryShardKeyMapper($config_database);
        break;
      
      case SQ_HASH:
        $mapper = new HashShardKeyMapper($config_database);
        break;
      
      case SQ_NONE:
        $mapper = new NoneShardKeyMapper($config_database);
        break;
      
      default:
        throw new Exception('Unsupported mapper: ' . $config_database['mapper']);
        break;
    }
    
    $params = $mapper->get_params($schema_name);
    $shards = $mapper->get_shards($schema_name);

    $state->shards = $shards;
    
    $state->mapper =& $mapper;

    if(!empty($params['shared_path'])) {
      $state->shared_path = $params['shared_path'];
    } else {
      $state->shared_path = "";
    }
    
    if(in_array('verbose', array_keys($params))) {
      $state->verbose = true;
    }
    
    if(!empty($params['inlist_merge_threshold'])) {
      $state->inlist_merge_threshold = $params['inlist_merge_threshold'];
    }
    
    if(!empty($params['inlist_merge_size'])) {
      $state->inlist_merge_size = $params['inlist_merge_size'];
    }
    
    if(!empty($params['ignore_virtual_schema'])) {
      if($params['ignore_virtual_schema'] == 1 || $params['ignore_virtual_schema'] == 'true') {
        $state->ignore_virtual_schema = true;
      }
    }
    
    if(!empty($params['star_schema'])) {
      if($params['star_schema'] == 1 || $params['star_schema'] == 'true') {
        $state->star_opt = 1;
      }
    }

    if(!empty($params['aws_access_key'])) {
      $state->aws_access_key = $params['aws_access_key'];
    }

    if(!empty($params['aws_secret_key'])) {
      $state->aws_secret_key = $params['aws_secret_key'];
    }
    
    $state->engine = 'InnoDB';
    if(!empty($params['coord_engine'])) {
      $state->engine = $params['coord_engine'];
    }
    
    $this->set_shard_mapper($column = $mapper->get_column($schema_name), array(
      $mapper,
      'map'
    ));
    
    /* One shard acts as the coordination shard.  A table is created on this shard
    to coallesce the results from the other shards.  
    */
    $coords = $mapper->get_shards($schema_name, true);
    if(!empty($coords)) {
      $state->tmp_shard = $coords[array_rand($coords)];
    } else {
      $state->tmp_shard = $shards[array_rand($shards)];
    }
    
    if(!empty($params['inlist'])) {
      $state->inlist_opt = trim($params['inlist']);
      if($state->inlist_opt == "-")
        $state->inlist_opt = $column;
    }
    
    if(!empty($params['between'])) {
      $state->between_opt = trim($params['between']);
      if($state->inlist_opt == "-")
        $state->inlist_opt = $column;
    }
    
    $state->client = null;
    $servers = $mapper->get_gearman_servers($schema_name);
    $cnt = count($servers);
    for($i = 0; $i < $cnt; ++$i) {
      $key = array_rand($servers);
      $server = $servers[$key];
      unset($servers[$key]);
      try {
        $state->client = new Net_Gearman_Client($server);
        break;
      }
      catch(Net_Gearman_Exception $e) {
        $state->client = null;
      }
    }
    
    
    if(!isset($state->client)) {
      $state->errors[] = "Could not connect to any of the specified gearman servers.";
      return false;
    }
    
    return $state;
    
  }
  
  function create_agg_table(&$state = null) {
    if(!isset($state))
      $state = $this->state;
    
    if(empty($state->shard_sql[0]) || strtolower(substr($state->shard_sql[0], 0, 6)) !== "select")
      return;
    $sql = $state->shard_sql[0];
    $sql = preg_replace('/\s+limit\s+\d+,*\s*\d*/i', '', $sql) . ' LIMIT 0';

    if(!$state->DAL->my_select_db($state->tmp_shard['db'])) {
      $this->errors[] = 'While aggregating result: ' . $state->DAL->my_error();
      $stmt = false;
      return false;
    } 

    if(!$stmt = $state->DAL->my_query($sql)) {
      $this->errors[] = 'While getting coordinator columns: ' . $state->DAL->my_error();
      return false;
    }

    $meta = $state->DAL->my_metadata($stmt);
    for($i=0;$i<count($meta);++$i) {
      $columns[$meta[$i]['name']] = 1;
    }
    $columns_sql = "";
    foreach($columns as $column => $discard) {
      if($columns_sql) $columns_sql .= ",";
      $columns_sql .= "$column VARCHAR(255)";
    }

    $args = $columns_sql;
    if(stristr($state->shard_sql[0], 'GROUP BY')) {
      if(!empty($state->agg_key_cols) && $state->agg_key_cols) {
        if($args != "") $args .= ",";
        $args .= "UNIQUE KEY gb_key (" . $state->agg_key_cols . ")";
      }
    }

    if(!empty($state->windows)) {
      if($args !== "") $args .= ",";
      $args .= "wf_rownum bigint auto_increment, key(wf_rownum)";
      foreach($state->windows as $num => $win) {
        $args .= ",";
        $args .= "wf{$num} tinytext null";
      }
    }
    if($args) $args = "($args)";
     
    $sql = "CREATE TABLE " . $state->table_name . " $args"; 
    $sql .= " ENGINE=" . $state->engine;

    if(!$stmt = $state->DAL->my_query($sql)) {
      $this->errors[] = 'While creating coordinator view: ' . $state->DAL->my_error();
      return false;
    }
  }
  
  function query($sql, $explain_only = false, $alt_state = null, $keep_result = false, $reset_state = true) {
    if($reset_state) {
      if($this->state) {
        $schema_name = $this->state->schema_name;
      } else {
        $schema_name = $this->schema_name;
      }
      unset($this->state);
      $this->state = $this->set_schema($schema_name);
    }

    if($this->async)
      $keep_result = true;
    if(isset($alt_state))
      $state = $alt_state;
    else
      $state = $this->state;
    
    $this->hashes = array();
    $state->custom_functions = array();
    $this->errors = array();
    $state->info = array();
    $state->orig_sql = $sql;
    
    $state->insert_sql = "";
    $state->insert_values = array();
    $state->got_rows = 0;
    $state->messages = array();
    
    if(empty($state->tmp_shard)) {
      $this->errors[] = "Initialization failed.  Check that gearman is running.";
      return false;
    }
    
    $start = microtime(true);
    $state->total_time = 0;
    
    $state->DAL = SimpleDAL::factory($state->tmp_shard);
    
    
    if($state->DAL->my_error()) {
      $this->errors[] = "Could not connect to the coordinator DB:" . $state->DAL->my_error();
      return false;
    }
    
    if(!$state->DAL->my_select_db($state->tmp_shard['db'])) {
      $this->errors[] = "Could not connect to the coordinator DB:" . $state->DAL->my_error();
      return false;
    }
    $this->workers = 0;
    $error_count = 0;
    $sub_sql = "select version() as `version`, @@version_comment as `comment`, ((version() like '5.6%' or version() like '5.7%' or version() like '10.%') ) as supports_partition_hint";
    $stmt = $state->DAL->my_query($sub_sql);
    if(!$stmt) {
      $this->errors[] = "Could not get version of the coordinator DB:" . $state->DAL->my_error();
      return false;
    }
    $row = $state->DAL->my_fetch_assoc($stmt);
    $state->mysql_version = $row;
    
    $state->subqueries = array();
    
    if(!$this->process_sql($sql, $state)) {
      return false;
    }
    
    if($explain_only)
      return $state->explain;
    
    $this->total_time = $this->parse_time = microtime(true) - $start;
    $start = microtime(true);
    
    if($state->insert_sql) {
      $result_count = 0;
      
      foreach($state->insert_values as $shard_id => $_vals) {
        $insert_sql = $state->insert_sql . $_vals;
        $state->DAL = SimpleDAL::factory($state->shards[$shard_id]);
        $state->DAL->my_select_db($state->shards[$shard_id]['db']);
        $result = $state->DAL->my_query($insert_sql);
        if($result) {
          $result_count += $state->DAL->my_affected_rows();
        } else {
          $this->errors[] = $state->DAL->my_error();
        }
        
        
      }
      
      
      $sql = "SELECT 'INSERT statement processed.  Inserted {$result_count} rows.' as result";
      $stmt = $state->DAL->my_query($sql);
      $state->exec_time = microtime(true) - $start;
      $state->total_time += $state->exec_time;
      return ($stmt);
    }
    
    if($state->broadcast_query !== false) {
      $result = true;
      $this->broadcast($state->broadcast_query, array(
        $this,
        'generic_callback'
      ), null, 'no_resultset', $state);
      $this->wait_for_workers();
      if($this->errors)
        $result = false;
      
      $state->exec_time = microtime(true) - $start;
      $state->total_time += $state->exec_time;
      return ($result);
    }
    
    
    $collect_cb = array(
      $this,
      'generic_callback'
    );
    $failure_cb = array(
      $this,
      'handle_failure'
    );
    
    //subqueries in the from clause get parallelized on each storage node
    if($this->errors || $this->errors) {
      return false;
    }
    
    $job_id = null;
    
    if($this->async)
      $job_id = $state->mapper->register_job($state->tmp_shard['id'], 0, 0, "''", $state->coord_sql);
    
    $state->query_id = $job_id;

    if(!empty($state->shard_sql)) {
      $set = new Net_Gearman_Set();
      $this->workers = 0;
      
      $this->create_gearman_set($state->shard_sql, $state->table_name, $collect_cb, $failure_cb, $set, "store_resultset", $state);
      
      $this->run_set($set, $state);
      $this->wait_for_workers();
      
      if($this->errors) {
        return false;
      }
      
      $stmt = false;
      
      unset($set);
      
      $this->errors = array();

      /* If the query has window functions then they must be processed now */
      if(!empty($state->windows)) {
        if(!$this->run_window_functions($state)) return false;
      }
      
      /* If the query has custom functions, then the custom function workers must be invoked once all of the
       *  store_resultset workers finish their jobs.  The store_resultset worker collects hashes which represent
       *  the distinct group by attributes in the query.  One worker is invoked per hash to increase parallelism
       *  whenever possible, but if the custom function covers the whole resultset, then this computation is
       *  single threaded as only one worker will be invoked. 
       */
      if(isset($state->custom_functions) && !empty($state->custom_functions)) {
        $set = new Net_Gearman_Set();
        
        foreach($state->custom_functions as $func_call_id => $function) {
          if(!empty(ShardQueryCustomFunctions::$list[$function['function']]) && ShardQueryCustomFunctions::$list[$function['function']]['sort_input'] === true) {
            $key = "(gb_hash";
            foreach($function['colref_map'] as $col) {
              $key .= ", $col";
            }
            $key .= ")";
            
            $sql = "ALTER TABLE `{$state->table_name}` ADD KEY $key";
            
            $state->DAL->my_query($sql);
          }
          
          if($this->async) {
            $task_type = Net_Gearman_task::JOB_BACKGROUND;
          } else {
            $task_type = Net_Gearman_task::JOB_NORMAL;
          }
          /*
           * If there were no rows returned by the original query, then there can be no hashes collected
           * by the store_resultset worker.  A child worker still needs to be invoked to create a
           * result table.   The table can not simply be created here, because the structure is dependent 
           * upon the function being invoked, to ensure the validity of the syntax of the coordinator query.  
           * Thus it is necessary to push the "catch all" or "one row" hash into the list to force creation of an empty table.
           */
          if(empty($this->hashes)) {
            $this->hashes = array(
              'ONE_ROW_RESULTSET' => 1
            );
          }
          foreach($this->hashes as $hash => $discard) {
            $task = new Net_Gearman_Task('custom_function', array(
              'func_call_id' => $func_call_id,
              'function_info' => $function,
              'tmp_shard' => $state->tmp_shard,
              'table' => $state->table_name,
              'gb_hash' => $hash
            ), uniqid() . mt_rand(1, 10000000), $task_type);
            $state->function_count++;
            $state->extra_tables[] = $func_call_id;
            
            $task->attachCallback($collect_cb);
            $task->attachCallback($failure_cb, Net_Gearman_Task::TASK_FAIL);
            
            $set->addTask($task);
          }
          
        }
        
        $this->run_set($set, $state);
        $this->wait_for_workers();
        
        $this->cleanup_tables($state);

        if(!empty($this->errors)) { 
          return false;
        }
        
      }
      
      if($state->got_rows) {
        if(!$keep_result) {
          $state->DAL = SimpleDAL::factory($state->tmp_shard);
          $stmt = $this->aggregate_result($state);
        } else {
          $stmt = true;
        }
        
      } else {
        //return null if no rows
        $stmt = null;
      }
      
    } else {
      if(!$keep_result) {
        $state->DAL = SimpleDAL::factory($state->tmp_shard);
        $stmt = $this->aggregate_result($state);
      } else {
        $stmt = true;
      }
    }
    $shard_id = $state->tmp_shard['id'];
    $table_list = array();
    $table_list[] = $state->table_name;
    if(!empty($state->extra_tables))
      $table_list = array_merge($table_list, $state->extra_tables);
    
    /* return a job_id if this is an async job */
    if($this->async) {
      $list = join($table_list);
      $state->mapper->update_job($job_id, $state->store_resultset_count, $state->function_count, $list, $state->coord_sql);
      $stmt = (int) $job_id;
    }
    
    
    $this->DAL = $state->DAL;
    
    $state->exec_time = microtime(true) - $start;
    $state->total_time += $state->exec_time;
    
    return $stmt;
    
  }
  
  function wait_for_workers() {
    if($this->async)
      return true;
    do {
      //usleep(1000);
    } while($this->workers > 0);
    
    return true;
  }
  
  
  
  //this function broadcast one or more queries of any type to all shards
  function broadcast($queries, $collect_cb = null, $failure_cb = null, $action = "no_resultset", &$state) {
    if(!$collect_cb)
      $collect_cb = array(
        $this,
        'generic_callback'
      );
    if(!$failure_cb)
      $failure_cb = array(
        $this,
        'handle_failure'
      );
    if(!is_array($queries))
      $queries = array(
        $queries
      );
    $set = new Net_Gearman_Set();
    $this->run_set($this->create_gearman_set($queries, false, $collect_cb, $failure_cb, $set, $action, $state), $state);
    
  }
  
  
  protected function make_alias($clause) {
    $alias = "";
    
    if(empty($clause['alias']) || $clause['alias'] == "") {
      if($clause['expr_type'] != 'expression') {
        $alias = $this->concat_all_subtrees($clause, $item);
      } else {
        $alias = $clause['base_expr'];
      }
    } else {
      $alias = trim($clause['alias']['name'], "`");
    }
    
    if(strpos($alias, '.')) {
      $alias = trim($alias, '`\'');
      $alias = explode('.', $alias);
      $alias = trim($alias[1], "`'");
      $alias = "`$alias`";
    }
    
    $alias = trim($alias, '`\'"');
    
    return "`$alias`";
  }
  
  protected function process_select_item($pos, &$clause, &$shard_query = "", &$coord_query = "", &$push_select = array(), &$group_aliases = array(), &$error = array(), $skip_alias = false, &$coord_odku, $prev_clause = null, &$state, $alias, $parent, &$custom_functions,$winfunc_num=false, &$winfunc_query = "") {
    $return = array();
    $no_pushdown_limit = true;
    $non_distrib = false;
    $new_alias = "";

    /* handle SELECT * 
     */
    if(!empty($clause['base_expr']) && ($clause['base_expr'] == "*" || strpos($clause['base_expr'],'.*'))) {
      if(strpos($clause['base_expr'],'.')) {
	$shard_query .= $clause['base_expr'];
	if($state->used_star == false) $coord_query .= "*";
	$state->used_star = true;
        return true;
      } else {
        if($pos === 0) {
          $shard_query .= "*";
          $coord_query .= "*";
	  $state->used_star = true;
          return true;
        } else {
          $clause['expr_type'] = 'operator';
        }
      }
    }
    
    if(!empty($clause['base_expr'])) {
      $base_expr = $clause['base_expr'];
    }
    switch($clause['expr_type']) {

      case 'operator':
        $prev_clause = $clause;
        if(!$parent || ($parent && $parent['expr_type'] !== 'expression')) {
          if($winfunc_num !== false) {
             $winfunc_query .= $clause['base_expr'];
           } else {
             $coord_query .= $clause['base_expr'];
           }
        }
        return true;
      break;
      
      case 'expression':
        $x = strtoupper($clause['sub_tree'][1]['base_expr']);
        $i = 1;
        if($x === 'OVER' || $x === 'FROM_FIRST' || $x === 'FROM_LAST' || $x === 'RESPECT') { // handle window functions
          $win = array();
          $win['func'] = $clause['sub_tree'][0];
          $win['over'] = array();
          $win['respect_nulls'] = true;
          $win['from_first'] = true;

          if($x === "FROM_FIRST") {
            ++$i;
          } elseif($x === "FROM_LAST") {
            $win['from_first'] = false;
            ++$i;
          }

          $x = strtoupper($clause['sub_tree'][$i]['base_expr']);
          if($x == "RESPECT") {
           $i+=2;
          }elseif($x == "IGNORE") {
            $win['respect_nulls']=false;
            $i+=2;
          } 

          #combine the subtrees together
          foreach($clause['sub_tree'][$i]['sub_tree'] as $sub_item) {
            if(!empty($sub_item['sub_tree'])) {
              foreach($sub_item['sub_tree'] as $sub_item2) {
                $win['over'][]= $sub_item2;
              }
            } else {
              $win['over'][]= $sub_item;
            }
          }
          $partition_cols = array();
          $order_by = array();
          $has_frame_clause = false;
          $i =0; // reuse i
          if(!empty($win['over']) && strtoupper($win['over'][0]['base_expr']) == 'PARTITION') {
            for($i=1;$i<count($win['over']);++$i) {
              if(strtoupper($win['over'][$i]['base_expr']) == "ORDER") break;
              if($win['over'][$i]['expr_type'] != 'reserved') $partition_cols[] = $win['over'][$i];
            }
          }
          if(!empty($win['over']) && strtoupper($win['over'][$i]['base_expr']) == 'ORDER') {
            for(;$i<count($win['over']);++$i) {
              if(strtoupper($win['over'][$i]['base_expr']) == 'DESC') $order_by[count($order_by)-1]['direction'] = 'desc';
              if($win['over'][$i]['expr_type'] != 'reserved') { 
                $win['over'][$i]['direction'] = 'asc';
                $order_by[] = $win['over'][$i];
              } elseif(strtoupper($win['over'][$i]['base_expr']) == 'ROWS' || strtoupper($win['over'][$i]['base_expr']) == 'RANGE') {
                $has_frame_clause = 1;
                break;
              }
           }
          }

          $state->windows[] = $win;
          $num = count($state->windows)-1;

          // "parse" the framing clause
          if($has_frame_clause) {
            if(!isset($state->windows)) $state->windows=array();
            $state->windows[$num]['mode'] = strtoupper($win['over'][$i]['base_expr']);
            $in_start = true;
            $i++;
            $skip_next=false;
            while($i < count($win['over'])) {

              if($skip_next) { 
                $skip_next = false; 
                ++$i;
                continue; 
              }
    
              $tok = strtoupper($win['over'][$i]['base_expr']);
    
              if($win['over'][$i]['expr_type'] == 'const') {
                if($in_start) {
                  $state->windows[$num]['start'] = $tok;
                } else {
                  $state->windows[$num]['end'] = $tok;
                }
                ++$i;
                continue;
              }
    
    
              if(!empty($win['over'][$i+1])) $next_tok = strtoupper($win['over'][$i+1]['base_expr']); else $next_tok = null;
    
    
              switch($tok) {
    
                case 'ROW':
                  ++$i;
                  continue;
                break;
    
                case 'BETWEEN':
                  $in_start = true;
                break;
    
                case 'AND':
                  $in_start = false;
                break;
    
                case 'CURRENT':
                  if($in_start) {
                    $state->windows[$num]['start'] = 0;
                  } else{
                    $state->windows[$num]['end'] = 0;
                  }
    
                case 'UNBOUNDED':
                  if($in_start) {
                    if($next_tok == "FOLLOWING") {
                      $this->errors['UNBOUNDED FOLLOWING may not be used as the start of a window frame'];
                      return false;
                    } 
                    $state->windows[$num]['start'] = false;
                  } else {
                    if($next_tok == "PRECEDING") {
                      $this->errors['UNBOUNDED PRECIDING may not be used as the end of a window frame'];
                      return false;
                    }
                    $state->windows[$num]['end'] = false;
                  }
                  $skip_next = true;
                break;
    
                case 'PRECEDING':
                  if($in_start) {
                    $state->windows[$num]['start'] *= -1;
                  } else {
                    $state->windows[$num]['end'] *= -1;
                  }
                break;
    
                case 'FOLLOWING':
                break;
              }
              ++$i;
            }
          } else { // default for each frame is RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW 
            $state->windows[$num]['mode'] = 'RANGE'; 
            $state->windows[$num]['start'] = false; // false means unbounded preceeding
            $state->windows[$num]['end'] = 0; // 0 means current_row
          }
    
          $state->windows[$num]['partition'] = $partition_cols;
          $state->windows[$num]['order'] = $order_by;
          unset($state->windows[$num]['over']);
    
          #$state->windows[$num]['func']['expr_type'] = 'window_function';
          if(!empty($state->windows[$num]['func']['sub_tree']) && $state->windows[$num]['func']['sub_tree'][0]['expr_type'] !== 'const') { 
            $this->process_select_item($pos+1,$state->windows[$num]['func']['sub_tree'][0],$shard_query, $coord_query, $push_select, $group_aliases, $error, true,$coord_odku, $prev_clause, $state, "", true, $custom_functions, $num, $winfunc_query);
          } else {
           $shard_query .= "NULL";
           $winfunc_query .= "NULL";
          }
          $shard_query .= " as wf{$num}";
          $coord_query .= "wf{$num} as $alias";
          $winfunc_query .= " as wf{$num}";
          $winfunc_query = ltrim($winfunc_query,",");
        } else {

          if(!empty($clause['alias'])) {
            $alias = $clause['alias']['name'];
          }
          $prev_clause = null;
          $in_case = false;        
          
          foreach($clause['sub_tree'] as $sub_pos => $sub_clause) {

            if($sub_pos > 0) {
              $prev_clause = $clause['sub_tree'][$sub_pos - 1];
            }

            if($sub_clause['expr_type'] == 'reserved' || $sub_clause['expr_type'] == 'operator') {
              $coord_query .= ' ' . $sub_clause['base_expr'] . ' ';
              continue;
            }

            if($sub_pos > 0) {
              $shard_query = rtrim($shard_query,",") . ",";
            }
            
            $this->process_select_item($pos, $sub_clause, $shard_query, $coord_query, $push_select, $group_aliases, $error, true, $coord_odku, $prev_clause, $state, "", $clause, $custom_functions,$winfunc_num, $winfunc_query);
            
          }
          
          $coord_query .= " $alias";

        }
        
        break;
      
      case 'custom_function':
        $state->need_hash = true;
        $function = $clause['base_expr'];
        
        if(!empty(ShardQueryCustomFunctions::$list[$function]) && ShardQueryCustomFunctions::$list[$function]['unique_input'] === true) {
          $func_call_id = "agg_tmp_" . md5(uniqid());
          $custom_functions[$func_call_id] = array(
            'function' => $clause['base_expr'],
            'arg' => $clause['sub_tree'],
            'colref_map' => array()
          );
          
          foreach($clause['sub_tree'] as $expr) {
            #TODO: support types of expressions other than colref
            if($expr['expr_type'] !== 'colref')
              continue;
            
            $c = trim($expr['base_expr']);
            
            if(!isset($state->used_colrefs[$c])) {
              $new_alias = "expr$" . (count($state->used_colrefs));
              $state->used_colrefs[$c] = count($state->used_colrefs);
              $shard_query .= $c . " AS $new_alias";
              $group_aliases[$c] = array(
                'alias' => $new_alias,
                'pushed' => true
              );
            } else {
              $new_alias = "expr$" . $state->used_colrefs[$c];
            }
            // The custom function gets input based on column name, this maps name to alias in the coord table
            $custom_functions[$func_call_id]['colref_map'][$c] = $new_alias;
          }
          /* there is a string replace later which replaces #gb_hash# with the appropriate hash expression */
          $coord_query .= "MAX(( select retval from `$func_call_id` cfr where cfr.gb_hash = #gb_hash# limit 1 )) AS $alias";
        } else {
          // since it doesn't take unique input, we have to be undistributable :(
          $no_pushdown_limit = true;
          $non_distrib = true;
        }
        
        break;
      
      case 'aggregate_function':

        $item = "";
        $base_expr = $this->concat_all_subtrees($clause['sub_tree'], $item);
        $function = strtoupper($clause['base_expr']);
        if($winfunc_num !== false) {
          $new_alias = "wf{$winfunc_num}";  
        } else {
          $new_alias = "expr_" . crc32(uniqid());
        }
        
        
        switch($function) {
          case 'COUNT':
          case 'SUM':
          case 'MIN':
          case 'MAX':
            $expr_info = explode(" ", trim($base_expr, '() '));
            if(!empty($expr_info[0]) && strtolower($expr_info[0]) == 'distinct') {
              $this->messages[] = "DISTINCT aggregate expression used.  Pushing expression.\n";
              $no_pushdown_limit = true;
              $state->pushed = true; 
              unset($expr_info[0]);
              $new_expr = join(" ", $expr_info);
              
              if(!isset($state->used_colrefs[trim($new_expr)])) {
                $shard_query .= "$new_expr AS $new_alias";
                $coord_odku[] = "$new_alias=VALUES($new_alias)";
                $group_aliases[trim($new_expr)] = array(
                  'alias' => $new_alias,
                  'pushed' => true
                );
              }
              
              $state->used_colrefs[trim($new_expr)] = count($state->used_colrefs);
              if($winfunc_num !== false) { 
                $winfunc_query .= "{$function}(distinct $new_alias)";
              } else {
                $coord_query .= "{$function}(distinct $new_alias)";
              }
              $state->used_distinct = true;
              
            } else {
              switch($function) {
                case 'SUM':
                case 'COUNT':
                  $coord_odku[] = "$new_alias=$new_alias +  VALUES($new_alias)";
                  break;
                case 'MIN':
                  $coord_odku[] = "$new_alias=IF($new_alias < VALUES($new_alias), VALUES($new_alias),$new_alias)";
                  break;
                case 'MAX':
                  $coord_odku[] = "$new_alias=IF($new_alias > VALUES($new_alias), VALUES($new_alias), $new_alias)";
                  break;
              }
              if($function == 'COUNT') {
                $shard_query .= "COUNT({$base_expr})";
                if($winfunc_num !== false) {
                  $winfunc_query .= "SUM($new_alias)";
                } else {
                  $shard_query .= " AS $new_alias";
                  $coord_query .= "SUM($new_alias)";
                }
              } else {
                $shard_query .= "{$function}({$base_expr})";
                if($winfunc_num !== false) { 
                  $winfunc_query .= "{$function}({$new_alias})";
                } else { 
                  $shard_query .= " AS $new_alias";
                  $coord_query .= "{$function}({$new_alias})";
                }
              }
            }
            if($alias && !$skip_alias && $winfunc_num === false)
                $coord_query .= " AS $alias";

            break;
          
          case 'AVG':
            $expr_info = explode(" ", trim($base_expr, '() '));
            if(!empty($expr_info[0]) && strtolower($expr_info[0]) == 'distinct') {
              $this->messages[] = "Detected a {$function} [DISTINCT] expression!\n";
              
              unset($expr_info[0]);
              $new_expr = join(" ", $expr_info);
              if(!isset($state->used_colrefs[trim($new_expr)])) {
                $shard_query .= "$new_expr AS $alias";
                $coord_odku[] = "$alias=VALUES($alias)";
                $group_aliases[trim($new_expr)] = array(
                  'alias' => $alias,
                  'pushed' => true
                );
              }
              $state->used_colrefs[trim($new_expr)] = 1;
              if($winfunc_num !== false) { 
                $winfunc_query .= "{$function}(distinct $new_alias)";
              } else {
                $coord_query .= "{$function}(distinct $new_alias)";
              }
              
              $coord_query .= "{$function}(distinct $alias)" . (!$skip_alias ? " AS $alias" : "");
              //since we pushed a GB expression we need to update the ODKU clause
            } else {
              $alias_cnt = trim($alias, '`');
              $alias_cnt = "`{$alias_cnt}_cnt`";
              $shard_query .= "SUM({$base_expr})";
              if($winfunc_num !== false) {
                $winfunc_query .= "SUM({$alias})/SUM({$alias_cnt})"; 
              } else {
                $shard_query .= " AS $alias";
                $coord_query .= "SUM({$alias})/SUM({$alias_cnt})" . (!$skip_alias ? " AS $alias" : "");
              } 
              //need to push a COUNT expression into the SELECT clause
              $push_select[] = "COUNT({$base_expr}) as {$alias_cnt}";
              $coord_odku[] = "{$alias_cnt}={$alias_cnt} +  VALUES({$alias_cnt})";
              
            }
            
            break;
          
          case 'VARIANCE':
          case 'VAR_POP':
          case 'VAR':
          case 'VAR_SAMP':
            $alias_expr = trim($alias, '`');
            $alias_sum2 = "`{$alias_expr}_sum2`";
            $alias_sum = "`{$alias_expr}_sum`";
            $alias_cnt = "`{$alias_expr}_cnt`";
            
            //need to push expressions into the SELECT clause
            $shard_query .= "SUM({$base_expr}) as {$alias_sum}";
            $push_select[] = "COUNT({$base_expr}) as {$alias_cnt}";
            $push_select[] = "SUM(POW({$base_expr},2)) as {$alias_sum2}";
            $coord_odku[] = "{$alias_cnt}={$alias_cnt} +  VALUES({$alias_cnt})";
            $coord_odku[] = "{$alias_sum}={$alias_sum} +  VALUES({$alias_sum})";
            $coord_odku[] = "{$alias_sum2}={$alias_sum2} +  VALUES({$alias_sum2})";
            
            #$coord_query .= "SUM({$alias_sum})/SUM({$alias_cnt})" . (!$skip_alias ? " AS $alias" : "");
            switch($function) {
              case 'VAR_SAMP':
                #$coord_query .= "IF( $alias_cnt != 0, ((SUM({$alias_sum2})/SUM({$alias_cnt})) - POW((SUM({$alias_sum})/SUM($alias_cnt)),2)) *  ( ($alias_cnt/($alias_cnt-1) ) ), NULL)" . (!$skip_alias ? " AS $alias" : "");
                $coord_query .= "((SUM({$alias_sum2})/SUM({$alias_cnt})) - POW((SUM({$alias_sum})/SUM($alias_cnt)),2)) *  ( ($alias_cnt/($alias_cnt-1) ) )" . (!$skip_alias ? " AS $alias" : "");
                break;
              
              default:
                $coord_query .= "((SUM({$alias_sum2})/SUM({$alias_cnt})) - POW((SUM({$alias_sum})/SUM($alias_cnt)),2))" . (!$skip_alias ? " AS $alias" : "");
                break;
            }
            
            break;
          
          case 'STDDEV':
          case 'STD_POP':
          case 'STD':
          case 'STD_SAMP':
            $alias_expr = trim($alias, '`');
            $alias_sum2 = "`{$alias_expr}_sum2`";
            $alias_sum = "`{$alias_expr}_sum`";
            $alias_cnt = "`{$alias_expr}_cnt`";
            
            
            //need to push expressions into the SELECT clause
            $shard_query .= "SUM({$base_expr}) as {$alias_sum}";
            $push_select[] = "COUNT({$base_expr}) as {$alias_cnt}";
            $push_select[] = "SUM(POW({$base_expr},2)) as {$alias_sum2}";
            $coord_odku[] = "{$alias_cnt}={$alias_cnt} +  VALUES({$alias_cnt})";
            $coord_odku[] = "{$alias_sum}={$alias_sum} +  VALUES({$alias_sum})";
            $coord_odku[] = "{$alias_sum2}={$alias_sum2} +  VALUES({$alias_sum2})";
            
            #$coord_query .= "SUM({$alias_sum})/SUM({$alias_cnt})" . (!$skip_alias ? " AS $alias" : "");
            switch($function) {
              case 'STD_SAMP':
                $coord_query .= "POW( ((SUM({$alias_sum2})/SUM({$alias_cnt})) - POW((SUM({$alias_sum})/SUM($alias_cnt)),2)) *  ( ($alias_cnt/($alias_cnt-1) ) ),.5)" . (!$skip_alias ? " AS $alias" : "");
                break;
              
              default:
                $coord_query .= "POW( ((SUM({$alias_sum2})/SUM({$alias_cnt})) - POW((SUM({$alias_sum})/SUM($alias_cnt)),2)),.5)" . (!$skip_alias ? " AS $alias" : "");
                break;
            }
            break;
          
          
          case 'GROUP_CONCAT':
            $no_pushdown_limit = true;
            $non_distrib = true;
            break 2;
            
            break;
          
          default:
            $error[] = array(
              'error_clause' => $clause['base_expr'],
              'error_reason' => 'Unsupported aggregate function'
            );
            
            break;
        }
        
        break;
      
      case 'operator':
      case 'reserved':
        break;
      
      
      case 'const':
        $coord_query .= $base_expr;
        if(!$parent)
          $coord_query .= " AS $alias";
        #$shard_query .= $base_expr; 
        break;
      
      case 'function':
        $no_pushdown_limit = true;
        if($winfunc_num !== false) { 
          $winfunc_query .= $base_expr . "(";
        } else { 
          $coord_query .= $base_expr . "(";
        }
         $first = 0;

        foreach($clause['sub_tree'] as $sub_pos => $sub_clause) {
          if($sub_clause['expr_type'] == 'colref' || $sub_clause['expr_type'] == 'aggregate_function' || $sub_clause['expr_type'] == 'function' || $sub_clause['expr_type'] == 'expression') {
            $this->process_select_item($pos + 1, $sub_clause, $shard_query, $coord_query, $push_select, $group_aliases, $error, true, $coord_odku, $clause, $state, "", $clause, $custom_functions,$winfunc_num, $winfunc_query);
          } else {
            if($winfunc_num !== false) { 
              $winfunc_query .= " " . $sub_clause['base_expr'];
            } else {
              $coord_query .= " " . $sub_clause['base_expr'];
            }
          }
          if(!empty($clause['sub_tree'][$sub_pos+1])) {
           if($winfunc_num !== false) {
             $winfunc_query .= ",";
           } else { 
             $coord_query .= ",";
           }
          }
        }
        if($winfunc_num !== false) {
          $winfunc_query .= ") ";
        } else  {
          $coord_query .= ")";
          if(!$skip_alias) $coord_query .= " $alias";
        } 
        break;
      
      case 'colref':
      case 'subquery':
        if(!isset($state->used_colrefs[$base_expr])) {
          $new_alias = "expr$" . (count($state->used_colrefs));
        } else {
          $new_alias = "expr$" . $state->used_colrefs[$base_expr];
        }
        if(!$parent) {
          $coord_query .= $new_alias . ' AS ' . $alias;
          $shard_query .= $clause['base_expr'] . ' AS ' . $new_alias;
          //$group[] = $pos + 1;
          $group_aliases[$clause['base_expr']] = array(
            'alias' => $new_alias,
            'pushed' => false
          );
        } else {
          if(!isset($state->used_colrefs[$base_expr])) {
            $shard_query .= $base_expr;
            if(strpos($base_expr, '.*') === false && $winfunc_num === false) {
                $shard_query .= ' AS ' . $new_alias;
            }
            $group_aliases[$base_expr] = array(
              'alias' => $new_alias,
              'pushed' => false
            );
          } else {
            if($winfunc_num !== false) {
              $shard_query .= "NULL ";
              $winfunc_query .= "expr$" . $state->used_colrefs[$base_expr];
            }
          }
          if($winfunc_num === false) {
            $coord_query .= " $new_alias";
          }
          #$coord_query .= ",";
        }
        if(!isset($state->used_colrefs[$base_expr])) {
          $state->used_colrefs[$base_expr] = count($state->used_colrefs);
        }
        break;
      
      default:
        
        $this->errors[] = array(
          'Unsupported expression:' => $clause['base_expr']
        );
        return false;
        break;
    }
    
    $state->no_pushdown_limit = $no_pushdown_limit;
    $state->non_distrib = $non_distrib;
    
    return true;
    
  }
  
  protected function process_select($select, $straight_join = false, $distinct = false, &$state) {
    $error = array();
    $shard_query = ""; //Query to send to each shard
    $coord_query = ""; //Query to send to the coordination node
    $winfunc_query = ""; //Query used to get data for window functions
    
    $group = array(); //list of positions which contain non-aggregate functions
    $group_aliases = array(); //list of group aliases which will be indexed on the aggregation temp table
    
    $coord_odku = array();
    
    $push_select = array(); //list of expressions to push onto the end of the SELECT clause sent to the shards
    $state->non_distrib = false;
    
    $state->used_colrefs = array();
    $state->push_pos = 0;
    
    $state->no_pushdown_limit = false; //will be set if a rewrite forces us to abandon pushdown limit strategy
    $prev_clause = false;
    $custom_functions = array();
    $state->need_hash = false;
    $state->aliases = "";
    $win_num = 0;
    foreach($select as $pos => $clause) {

      $alias = $this->make_alias($clause);
      $this->state->aliases[] = $alias;
      $this->process_select_item($pos, $clause, $shard_query, $coord_query, $push_select, $group_aliases, $error, false, $coord_odku, null, $state, $alias, null, $custom_functions,false,$winfunc_query);
      if($pos + 1 < count($select)) {
        $shard_query = rtrim($shard_query, ", ");
        $coord_query = rtrim($coord_query, ", ");
        $winfunc_query = rtrim($winfunc_query, ", ");
        $shard_query .= ",";
        $coord_query .= ",";
        $winfunc_query .= ",";
      }
      
      $prev_clause = false;
    }

    $shard_query = trim($shard_query, ", ");
    $coord_query = trim($coord_query, ", ");
    $winfunc_query = trim($winfunc_query, ", ");

    $sql = "SELECT ";
    if($straight_join)
      $sql .= "STRAIGHT_JOIN ";
    if($distinct)
      $sql .= " DISTINCT ";

    $coord_query = $sql . $coord_query;
    
    if(!empty($state->windows)) {
      $sql .= "NULL as wf_rownum, ";
    }
    $shard_query = $sql . $shard_query;

    foreach($push_select as $clause) {
      $shard_query .= "," . $clause;
    }
    
    #take all the group by columns and use them on the shard
    $shard_group = join(',', array_keys($group_aliases));

    #only non-pushed group items are sent to coordinator
    #also put together the hash statements
    $coord_group = $shard_hash = $coord_hash = "";
    $all_aliases = "";
    foreach($group_aliases as $col => $ary_alias) {
      if($shard_hash)
        $shard_hash .= ",";
      $shard_hash .= $col;
      if($coord_hash)
        $coord_hash .= ",";
      $coord_hash .= $ary_alias['alias'];
      if($all_aliases)
        $all_aliases .= ",";
      $all_aliases .= $ary_alias['alias'];
      if($ary_alias['pushed'] != false)
        continue;
      if($coord_group)
        $coord_group .= ',';
      $coord_group .= $ary_alias['alias'];
    }
    
    if($shard_hash == "")
      $shard_hash = "'ONE_ROW_RESULTSET'";
    if($coord_hash == "")
      $coord_hash = "'ONE_ROW_RESULTSET'";

    if($state->need_hash) { 
      $shard_hash = "SHA1(CONCAT_WS('#'," . $shard_hash . "))";
      $coord_hash = "SHA1(CONCAT_WS('#'," . $coord_hash . "))";
      $coord_query = str_replace('#gb_hash#', $coord_hash, $coord_query);
      $shard_query = trim($shard_query, ", ") . "," . $shard_hash . " as `gb_hash`";
    }
    //we can't send pushed group by to the coord shard, so send the expression based 
    return array(
      'error' => $error,
      'shard_sql' => $shard_query,
      'coord_odku' => $coord_odku,
      'coord_sql' => $coord_query,
      'shard_group' => $shard_group,
      'coord_group' => $coord_group,
      'group_aliases' => $all_aliases,
      'custom_functions' => $custom_functions,
      'winfunc_sql' => $winfunc_query
    );
  }
  
  protected function process_undistributable_select_item(&$pos, &$real_pos, &$clauses, &$shard_query, &$coord_query, &$custom_functions, $func_call_id = null, &$state = null) {
    static $last_pos = null;
    static $is_group_item = false;
    static $is_custom_function = false;
    
    if($last_pos !== $pos) {
      $last_pos = $pos;
      $is_group_item = false;
    }
    
    if(empty($clauses[0])) {
      $clauses = array(
        $clauses
      );
    }
    
    foreach($clauses as $clause) {
      switch($clause['expr_type']) {
        
        case 'colref':
          if($shard_query)
            $shard_query .= ",";
          $new_alias = "`p{$pos}_{$real_pos}`";
          ++$real_pos;
          $shard_query .= $clause['base_expr'] . " AS $new_alias";
          if(!$is_custom_function) {
            $coord_query .= $new_alias;
            if(!$func_call_id)
              $is_group_item = true;
          } else {
            $custom_functions[$func_call_id]['colref_map'][$clause['base_expr']] = $new_alias;
          }
          break;
        
        case 'custom_function':
          $is_custom_function = true;
          $func_call_id = "agg_tmp_" . md5(uniqid());
          $custom_functions[$func_call_id] = array(
            'function' => $clause['base_expr'],
            'arg' => $clause['sub_tree']
          );
          
          $coord_query .= "MAX(( select retval from `$func_call_id` cfr where cfr.gb_hash = #gb_hash# limit 1 )) ";
          $this->process_undistributable_select_item($pos, $real_pos, $clause['sub_tree'], $shard_query, $coord_query, $custom_functions, $func_call_id, $state);
          $is_group_item = false;
          $func_call_id = null;
          $is_custom_function = false;
          
          break;
        
        case 'aggregate_function':
          //if($clause['expr_type'] == 'aggregate_function') $is_group_item = false;
          $is_group_item = false;
          if($clause['base_expr'] == 'count') {
            if($clause['sub_tree'][0]['base_expr'] == '*') {
              $clause['sub_tree'][0]['base_expr'] = "1";
            }
          }
        case 'function':
          $func_call_id = md5(uniqid());
        case 'expression':
          $coord_query .= "{$clause['base_expr']}(";
          $this->process_undistributable_select_item($pos, $real_pos, $clause['sub_tree'], $shard_query, $coord_query, $custom_functions, $func_call_id, $state);
          $coord_query .= ")";
          
          $func_call_id = false;
          
          break;
        
        case 'reserved':
        case 'operator':
        default:
          if(!isset($func_call_id)) {
            $coord_query .= $clause['base_expr'] . " ";
          }
          
          break;
          
      }
    }
    
    return $is_group_item;
    
  }

  protected function concat_all_subtrees($clauses, &$item, $depth = 0, $parent_type = "") {

    if(empty($clauses[0])) {
      $clauses = array(
        $clauses
      );
    }
    $pos = 0;
    foreach($clauses as $clause) {
      if(strpos($clause['base_expr'], '.') !== false) {
        $clause['base_expr'] = str_replace('`.`', '.', $clause['base_expr']);
        $clause['base_expr'] = trim($clause['base_expr'], '`');
      }
      
      if($pos > 0 && ($parent_type == 'function' || $parent_type == 'aggregate_function')) {
        if(!in_array($clauses[$pos - 1]['expr_type'], array(
          'operator',
          'reserved'
        ))) {
          if(!in_array($clauses[$pos]['expr_type'], array(
            'operator',
            'reserved'
          ))) {
            $item .= ',';
          }
        }
      }
      
      switch($clause['expr_type']) {
        
        case 'function':
          $item .= " {$clause['base_expr']}(";
          $this->concat_all_subtrees($clause['sub_tree'], $item, $depth + 1, $clause['expr_type']);
          $item .= ")";
        break;
        case 'aggregate_function':
        case 'expression':
          $item .= " {$clause['base_expr']} ";
          #$this->concat_all_subtrees($clause['sub_tree'], $item, $depth + 1, $clause['expr_type']);
          #$item .= ")";
          break;
        
        case 'reserved':
        case 'operator':
          if($pos > 0)
            $item .= " ";
          $item .= $clause['base_expr'] . " ";
          break;
        
        default:
          $item .= $clause['base_expr'];
      }
      ++$pos;
    }
    return $item;
    
  }
  
  protected function process_having(&$state) {
    if(empty($state->parsed['HAVING']))
      return false;
    
    $clauses = $state->parsed['HAVING'];
    unset($state->parsed['HAVING']);
    
    $having = "";
    $sselect = "";
    
    
    foreach($clauses as $clause) {
      $pos = 0;
      $shard = "";
      $coord = "";
      $push = array();
      $group_aliases = array();
      $group = array();
      $error = array();
      $skip_alias = true;
      $coord_odku = array();
      switch($clause['expr_type']) {
        case 'function':
        case 'aggregate_function':
        case 'expression':
          if($select)
            $select .= ",";
          
          $nothing = " "; $custom_functions = array();
          $this->process_select_item($pos + 1, $clause, $select, $having, $push, $group_aliases, $group, $error, $skip_alias, $coord_odku, $state, $nothing, null,$custom_functions);
          
          break;
        
        default:
          $having .= $clause['base_expr'];
      }
    }
    
    if($having)
      $having = ' HAVING ' . $having . ' ';
    
    return array(
      'having' => $having,
      'select' => $select
    );
  }
  
  
  protected function process_undistributable_select($select, $straight_join = false, $distinct = false, &$state = null) {
    $shard_query = ""; //Query to send to each shard
    $coord_query = ""; //Query to send to the coordination node
    
    $state->no_pushdown_limit = true;
    $state->used_colrefs = array();
    $custom_functions = array();
    $group_items = "";
    foreach($select as $pos => $clause) {
      if(empty($clause['alias'])) {
        $alias = "";
        $alias = '`' . $this->concat_all_subtrees($clause, $alias) . '`';
      } else {
        $alias = "`" . $clause['alias']['name'] . "`";
      }
      //this will recurse and fill up the proper structures
      $prev_clause = false;
      $real_pos = 0;
      $shard_query_item = "";
      $coord_query_item = "";
      
      $is_group_by_item = $this->process_undistributable_select_item($pos, $real_pos, $clause, $shard_query_item, $coord_query_item, $custom_functions, null, $state);
      
      if($shard_query)
        $shard_query .= ",";
      if($coord_query)
        $coord_query = rtrim($coord_query, ',') . ",";
      
      $shard_query .= $shard_query_item;
      $coord_query .= $coord_query_item . " AS " . $alias;
      
      
      $strip_item_at = strpos($shard_query_item, " AS `");
      $strip_item = substr($shard_query_item, 0, $strip_item_at);
      
      if($is_group_by_item) {
        $group_items[] = array(
          'expr' => $strip_item,
          'alias' => $alias
        );
      }
      
    }
    
    $sql = "SELECT ";
    if($straight_join)
      $sql .= "STRAIGHT_JOIN ";
    if($distinct)
      $sql .= " DISTINCT ";
    
    $shard_query = $sql . $shard_query;
    $coord_query = $sql . $coord_query;
    
    $return = array(
      'error' => array(),
      'shard_sql' => $shard_query,
      'coord_odku' => array(),
      'coord_sql' => $coord_query,
      'shard_group' => "",
      'coord_group' => "",
      'group_aliases' => "",
      'custom_functions' => $custom_functions
    );
    
    $gb_hash = "";
    
    
    if($group_items != "") {
      foreach($group_items as $item) {
        if($gb_hash)
          $gb_hash .= ",";
        $gb_hash .= $item['expr'];
      }
    } else {
      /* There was no GROUP BY in the query, but we still need
       *  a group by hash in the query, so use a constant.
       *  Any constant would do.  
       */
      
      $gb_hash = "'ONE_ROW_RESULTSET'";
    }
    
    $gb_hash = "SHA1(CONCAT_WS('#'," . $gb_hash . "))";
    $return['coord_sql'] = str_replace('#gb_hash#', $gb_hash, $return['coord_sql']);
    $return['shard_sql'] = trim($return['shard_sql'], ", ") . "," . $gb_hash . " as `gb_hash`";
    
    return ($return);
  }
  
  protected function process_from($tables, &$state) {
    /* DEPENDENT-SUBQUERY handling
     */
    
    $state->tables = array();
    foreach($tables as $key => $table) {
      if($table['expr_type'] == 'subquery') {
        if(!isset($state->tmp_shard)) {
          $state->tmp_shard = $state->shards[array_rand($state->shards)];
        }
        $sub_state = ShardQuery::new_state();
        
        //initialize the new state with a call to set_schema
        $this->set_schema($this->schema_name, $sub_state);
        $sub_state->tmp_shard = $state->tmp_shard;
        $sub_table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
        $sub_state->table_name = $sub_table_name;
        $sub_state->DAL = SimpleDAL::factory($sub_state->tmp_shard);
        
        //the aggregation table created by this query is going to have
        //to be copied to every shard the query executes against
        
        $this->query($table['sub_tree'], false, $sub_state, true, false);
        $state->dependent_subqueries[] = $sub_table_name;
        
        $state->extra_tables[] = $sub_table_name;
        $state->extra_tables = array_merge($state->extra_tables, $sub_state->extra_tables);
        
        $sql = '(' . $sub_state->coord_sql . ')';
        $tables[$key]['table'] = $sql;
        
      } else {
        $alias = "";
        if(is_array($table['alias']))
          $alias = $table['alias']['name'];
        
        $db = $state->tmp_shard['db'];
        $table_name = $table['table'];
        
        /* 
         * If the table name has a schema qualifier we have to deal with it.
         */
        if(strpos($table['table'], '.') !== false) {
          $info = explode(".", $table['table']);
          /* 
           * If the option ignore_virtual_schema is turned on, then Shard-Query
           * does not use a virtual schema and will allow any tables to be specified
           * in the query.
           *
           * If this option is not enabled, then the only allowable schema prefix
           * is the current virtual schema, which will be stripped off of the query.
           */
          if($info[0] !== $this->current_schema || $this->state->ignore_virtual_schema) {
            /* Uh-oh, we aren't ignoring virtual schema and a bad virtual schema name was given*/
            if(!$this->state->ignore_virtual_schema) {
              $this->errors[] = "Invalid virtual schema name given: " . $info[0];
              return false;
            }
            /*
             * virtual schema has been ignored, so strip off the schema name for the 
             * partition lookup, but leave it on for the queries later (don't modify $tables)
             */
            $db = $info[0];
            $table_name = $info[1];
            
          } else {
            /*
             * strip off the virtual schema name (modify $tables)
             */
            $table['table'] = $info[1];
            $tables[$key]['table'] = $info[1];
            $tables[$key]['base_expr'] = $info[1];
          }
          /* 
           * In either case, the table name is specified after the . (dot)
           */
          $table_name = $info[1];
          
        }
        
        /* VIEW processing.
         *  Here Shard-Query treats tables with start with a v_ specially, as it
         *  assumes they are views.  We need to verify that the table really is a
         *  view, and that the underlying table specified in the postfix exists.
         */
        $is_virtual = false;
        if(substr($table_name, 0, 2) === 'v_') {
          $possible_table_name = substr($table_name, 2);
          
          $sql = "select if(table_type = 'view','yes','no') as is_view
                              from information_schema.tables 
                             where table_schema='" . $state->DAL->my_escape_string($db) . "' " . "   and table_name = '" . $state->DAL->my_escape_string($table_name) . "'";
          
          $stmt = $state->DAL->my_query($sql);
          
          if($stmt) {
            $row = $state->DAL->my_fetch_assoc();
            if($row['is_view'] === 'yes') {
              /*
               *  Check to make sure there is a base table matching the view name.
               */
              $sql = "select if(table_type = 'view','yes','no') as is_view
                                      from information_schema.tables 
                                     where table_schema='" . $state->DAL->my_escape_string($db) . "' " . "   and table_name = '" . $state->DAL->my_escape_string($possible_table_name) . "'";
              
              $stmt = $state->DAL->my_query($sql);
              $row = $state->DAL->my_fetch_assoc();
              
              if($row['is_view'] === 'no') {
                /* 
                 * Use the prefix-stripped name as our table name for the partition lookup.
                 */
                $table_name = $possible_table_name;
                $is_virtual = true;
                $tables[$key]['real_name'] = $table_name;
              }
              
            }
            
          }
          
        }
        
        /*
         * Here we lookup the partitions for the table.  Per above, if a valid view was used, we use the
         * stripped name here, because MySQL EXPLAIN PARTITIONS will reference the base table
         */
        $state->tables[$table_name] = array(
          'alias' => $alias,
          'is_virtual' => $is_virtual,
          'partition_info' => $state->DAL->enumerate_partitions($db, $table_name, $state->mysql_version['supports_partition_hint'] == 1)
        );
      }
    }
    
    $sql = "";
    
    
    if(empty($tables[0]['alias']) || $tables[0]['alias'] == "") {
      $alias = $tables[0]['table'];
    } else {
      $alias = $tables[0]['alias']['name'];
    }
    //escape the table name if it is unescaped
    if($alias[0] != '`' && $alias[0] != '(')
      $alias = '`' . $alias . '`';
    if($alias != "`dual`") {
      $bare = trim($alias, '`');
      if(!empty($tables[0]['real_name']))
        $bare = $tables[0]['real_name'];
      if($tables[0]['expr_type'] != 'subquery' && $state->mysql_version['supports_partition_hint']) {
        $alias = " %p$bare AS $alias ";
      } else {
        $alias = " AS $alias";
      }
    } else {
      $alias = " ";
    }
    
    //the first table is always prefixed by FROM
    $sql = "FROM " . $tables[0]['table'] . $alias;
    
    $cnt = count($tables);
    
    //now create the rest of the FROM clause
    for($i = 1; $i < $cnt; ++$i) {
      $item = "";
      $this->concat_all_subtrees($tables[$i]['ref_clause'], $item);
      if($tables[$i]['ref_type'] == 'USING') {
        $tables[$i]['ref_clause'] = "(" . trim($tables[$i]['ref_clause'][0]['base_expr']) . ")";
      } elseif($tables[$i]['ref_type'] == 'ON') {
        $item = "";
        $this->concat_all_subtrees($tables[$i]['ref_clause'], $item);
        $tables[$i]['ref_clause'] = "( $item ) ";
      }
      
      if($sql)
        $sql .= " ";
      $alias = $tables[$i]['alias'];
      if(empty($alias) || $alias == "")
        $alias = $tables[$i]['table'];
      else
        $alias = $tables[$i]['alias']['name'];
      
      if($alias[0] != '`' && $alias[0] != '(') {
        $pos = strpos($alias, '.');
        if($pos !== false) {
          $info = explode('.', $alias);
          $table = $info[1];
          $alias = '`' . $table . '`';
        } else {
          $alias = '`' . $alias . '`';
        }
      }
      $bare = trim($alias, '`');
      if($alias != "`dual`") {
        if($tables[$i]['expr_type'] != 'subquery' && $state->mysql_version['supports_partition_hint'] == 1) {
          if(!empty($tables[0]['real_name']))
            $bare = $tables[0]['real_name'];
          $alias = " %p$bare AS $alias";
        } else {
          $alias = " AS $alias";
        }
      } else {
        $alias = " ";
      }
      if($tables[$i]['join_type'] == 'CROSS')
        $tables[$i]['join_type'] = ' CROSS JOIN ';
      if($tables[$i]['join_type'] == 'LEFT')
        $tables[$i]['join_type'] = ' LEFT JOIN ';
      if($tables[$i]['join_type'] == 'RIGHT')
        $tables[$i]['join_type'] = ' RIGHT JOIN ';
      if($tables[$i]['join_type'] == 'NATURAL')
        $tables[$i]['join_type'] = ' NATURAL JOIN ';

      $sql .= $tables[$i]['join_type'] . ' ' . $tables[$i]['table'] . $alias . ' ' . $tables[$i]['ref_type'] . $tables[$i]['ref_clause'];
      
    }
    return $sql;
  }
  
  function set_shard_mapper($column, $callback) {
    $this->shard_column = $column;
    if(!is_callable($callback)) {
      throw new Exception('Invalid callback (is_callable failed)');
    }
    
    $this->callback = $callback;
  }
  
  public function map_shard($column = null, $key = null, $schema = null, $operator = "=", $create = false) {
    //fixme
    //call new_key here and return the value
    if(!isset($key))
      return false;
    if(!isset($schema))
      $schema = $this->state->current_schema;
    if(!isset($column))
      $column = $this->shard_column;
    $result = call_user_func($this->callback, $column, $key, $schema, $operator, $create);
    if($result)
      return array_values($result);
    return false;
  }
  
  protected function append_all(&$queries, $append) {
    for($i = 0; $i < count($queries); ++$i) {
      $queries[$i] .= $append;
    }
  }
  
  protected function process_where($where, &$state = null) {

    $state->in_lists = array();
    $prev = "";
    $next_is_shard_column = false;
    $shard_id = false;
    $total_count = 0;
    $sql = "";
    $queries = array(
      $sql
    );
    
    $start_count = count($where);
    $shard_column_operator = null;
    $shard_lookup_map = array();
    $partition_elimination_used = false;
    $not_in = false;
    
    foreach($where as $pos => $clause) {
      if(empty($where[$pos]))
        continue;
      $sql .= " ";
      $this->append_all($queries, " ");
      
      if($clause['expr_type'] === 'bracket_expression') {
        $new_queries = $this->process_where($clause['sub_tree'], $state);
        $out = array();
        foreach($new_queries as $new_query) {
          foreach($queries as $idx => $orig_query) {
            //if($orig_query !== "") $orig_query .= " AND ";
            $out[] = $orig_query . '(' . $new_query . ') ';
          }
        }
        
        $queries = $out;
        continue;
      }
      
      
      if($next_is_shard_column) {
        if(!trim($clause['base_expr']))
          continue;
        if($clause['expr_type'] == 'const' || $clause['expr_type'] == 'in-list') {
          $shard_lookup_map[] = array(
            'operator' => $shard_column_operator,
            'value' => $clause['base_expr']
          );
        }
        $next_is_shard_column = false;
      }
      
      if($clause['expr_type'] == 'operator') {
        if(isset($prev_operator) && $prev_operator === 'not' && $clause['base_expr'] == 'in') {
          $not_in = true;
        } else {
          $not_in = false;
        }
        $prev_operator = $clause['base_expr'];
        
        if(strtolower($clause['base_expr']) == 'between' && $this->between_opt && ($this->between_opt == '*' || $this->between_opt == $prev)) {
          $offset = 0;
          $operands = array();
          //find the operands to the between expression   
          $and_count = 0;
          
          for($n = $pos + 1; $n < $start_count; ++$n) {
            if($where[$n]['expr_type'] == 'operator' && strtoupper($where[$n]['base_expr']) == 'AND') {
              if($and_count) {
                break;
              } else {
                $and_count += 1;
              }
            }
            $operands[$offset] = array(
              'pos' => $n,
              'base_expr' => $where[$n]['base_expr']
            );
            ++$offset;
          }
          
          if(strtolower(trim($prev, '`')) === $this->shard_column) {
            $shard_lookup_map[] = array(
              'operator' => 'BETWEEN',
              'value' => " " . $operands[0]['base_expr'] . " AND " . $operands[2]['base_expr']
            );
          }
          
          //determine what kinds of operands are in use
          $matches = $vals = array();
          $is_date = false;
          
          if(is_numeric(trim($operands[0]['base_expr'])) || preg_match("/('[0-9]+-[0-9]+-[0-9]+')/", $operands[0]['base_expr'], $matches)) {
            if($matches) {
              $vals[0] = $matches[0];
              $matches = array();
              preg_match("/('[0-9]+-[0-9]+-[0-9]+')/", $operands[2]['base_expr'], $matches);
              $vals[1] = $matches[0];
              
              $is_date = true;
            } else {
              $vals[0] = $operands[0]['base_expr'];
              $vals[1] = $operands[2]['base_expr'];
              
            }
            if(!$is_date) {
              $sub_tree = array();
              for($n = $vals[0]; $n <= $vals[1]; ++$n) {
                $sub_tree[] = $n;
              }
              
            } else {
              //conversion of date between requires connecting
              //to the database to make sure that the date_diff calculation
              //is accurate for the timezone in which the database servers are
              
              $date_sql = "SELECT datediff(" . $vals[1] . ',' . $vals[0] . ") as `d`";
              $this->messages[] = "Used database to do date calculation:\n$date_sql\n\n";
              
              $stmt = $state->DAL->my_query($date_sql);
              if(!$stmt) {
                $this->errors[] = "Error computing date difference: " . $state->DAL->my_error();
                return false;
              }
              
              $row = $state->DAL->my_fetch_assoc();
              $days = $row['d'];
              for($n = 0; $n <= $days; ++$n) {
                $sub_tree[] = $vals[0] . " + interval $n day";
              }
            }
            
            for($n = $pos + 1; $n <= $operands[2]['pos']; ++$n) {
              unset($where[$n]);
            }
            
            $this->messages[] = "A BETWEEN has been converted to an IN list with " . count($sub_tree) . " items\n";
            $state->in_lists[] = $sub_tree;
            $old = $queries;
            
            $queries = array();
            $ilist = "";
            $sub_tree = array_values($sub_tree);
            
            if(count($sub_tree) >= $this->inlist_merge_threshold) {
              for($z = 0; $z < count($sub_tree); ++$z) {
                if($ilist)
                  $ilist .= ",";
                $ilist .= $sub_tree[$z];
                if((($z + 1) % $this->inlist_merge_size) == 0) {
                  foreach($old as $sql) {
                    $queries[] = $sql . " IN (" . $ilist . ")";
                  }
                  $ilist = "";
                }
              }
              foreach($old as $sql) {
                if($ilist)
                  $queries[] = $sql . " IN (" . $ilist . ")";
              }
              $ilist = "";
            } else {
              foreach($sub_tree as $val) {
                foreach($old as $sql) {
                  $queries[] = $sql .= " = $val";
                }
              }
            }
            
            unset($sub_tree);
            
            continue;
          } else {
            $this->messages[] = "BETWEEN could not be optimized - invalid operands";
          }
          
        } elseif($clause['expr_type'] == 'operator' /*($clause['base_expr'] == '=' || strtolower($clause['base_expr']) == 'in') */ && ($this->shard_column && strtolower($this->shard_column) == trim(strtolower($prev), ' `'))) {
          $shard_column_operator = $clause['base_expr'];
          $next_is_shard_column = true;
        }
        $this->append_all($queries, $clause['base_expr']);
      } elseif($clause['expr_type'] != 'in-list') {
        /*Semi-Join materialization*/
        if($clause['expr_type'] == 'subquery') {
          
          if(!$not_in) {
            $this->append_all($queries, "($prev)"); // makes clause expr in (expr) which will always evaluate to true (filter is applied through join)
          } else {
            $this->append_all($queries, "(NOT $prev)"); // makes clause expr not in (NULL) which will always evaluate to true (filter is applied through left join and where clause)
          }
          
          if(!isset($state->tmp_shard)) {
            $state->tmp_shard = $state->shards[array_rand($state->shards)];
          }
          $sub_state = ShardQuery::new_state();
          
          //initialize the new state with a call to set_schema
          $this->set_schema($this->schema_name, $sub_state);
          $sub_state->tmp_shard = $state->tmp_shard;
          $sub_table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
          $sub_state->table_name = $sub_table_name;
          $sub_state->DAL = SimpleDAL::factory($sub_state->tmp_shard);
          
          $t =& $clause['sub_tree'];
          
          if(empty($t['GROUP'])) {
            if(strtoupper($t['SELECT'][0]['base_expr']) != 'DISTINCT') {
              array_unshift($t['SELECT'], array('base_expr' => distinct, 'expr_type'=>'reserved'));
            }
          }
          
          if(!$not_in) {
            $state->push_join[] = " JOIN $sub_table_name on $prev = $sub_table_name.expr$0 ";
          } else {
            $state->push_join[] = " LEFT JOIN $sub_table_name on $prev = $sub_table_name.expr$0 ";
            $state->push_where[] = " AND $sub_table_name.expr$0 IS NULL ";
            $not_in = false;
          }
          
          $this->query($t, false, $sub_state, true, false);
          
          $state->dependent_subqueries[] = $sub_table_name;
          
          $state->extra_tables[] = $sub_table_name;
          $state->extra_tables = array_merge($state->extra_tables, $sub_state->extra_tables);
          
        } elseif($clause['expr_type'] == 'function') {
           $sub_vals = "";
           $sub_vals = $this->concat_all_subtrees($clause['sub_tree'], $sub_vals);
           $this->append_all($queries, "{$clause['base_expr']}($sub_vals)");      
        } else {
          $this->append_all($queries, $clause['base_expr']);
          
        }
        $prev = $clause['base_expr'];
        //strip off any alias for evaluation later
        if(strpos($prev, '.') !== false) {
          list($alias, $column) = explode('.', $prev);
          $prev = $column;
        }
        
      } elseif($this->inlist_opt && ($this->inlist_opt == '*' || $this->inlist_opt == $prev)) {
        $old = $queries;
        $queries = array();
        
        foreach($clause['sub_tree'] as $vals) {
          foreach($old as $sql) {
            $queries[] = "$sql (${vals['base_expr']}) ";
          }
        }
        
      } else {
        $prev = $clause['base_expr'];
        $this->append_all($queries, $prev);
        
        //strip off any alias for evaluation later
        if(strpos($prev, '.') !== false) {
          list($alias, $column) = explode($prev, '.');
          $prev = $column;
        }
      }
      
    }
    
    $output_map = array();
    
    if(!empty($shard_lookup_map)) {
      foreach($shard_lookup_map as $lookup) {
        $shard_ids = $this->map_shard($this->shard_column, $lookup['value'], $this->state->current_schema, $lookup['operator']);
        if($shard_ids == null) {
          //map failed (invalid operator?) must broadcast!
          $output_map = null;
          break;
        }
        
        if($shard_ids == false) {
          //mapping simply was not found
          continue;
        }
        
        foreach($shard_ids as $shard) {
          $shard_name = array_pop(array_keys($shard));
          $output_map[$shard_name] = $this->shards[$shard_name];
          $state->messages[$shard_name] = "Mapped query to shard: $shard_name";
        }
      }
    }
    
    if(!empty($output_map))
      $state->force_shard = array_merge($state->force_shard, $output_map);
    
    $out_queries = array();
    foreach($queries as $pos => $q) {
      if(!trim($q))
        continue;
      $out_queries[] = $q;
    }
    
    /*if(empty($out_queries))
      ($out_queries = array(
        "1=1"
      );
    */
    
    return array_values($out_queries);
  }

  protected function process_subquery_factoring($sql) {
    $this->errors[] = "Subquery factoring not yet supported (coming soon).\n";
    return false;
  }

  protected function clean_token($token) {
    if($token[0] == '\'' || $token[0] == '"') {
      $token = substr($token, 1, strlen($token)-2);
    }
    return $token;
  }

  protected function process_load_data($sql) {
    $file_name = false;
    $table_name = "";
    $fields_terminated_by = ",";
    $lines_terminated_by = "\\n";
    $fields_escaped_by = "\\";
    $fields_optionally_enclosed =  false;
    $fields_enclosed_by = '';
    $local = false;
    $charset = "latin1";
    $ignore = false;
    $replace = false;
    $columns_str = null;
    $set_str = null;
    $lines_starting_by = "";
    $chunk_size = 16 * 1024 *1024;

    $sql = trim($sql, ';');
    $regex = "/[A-Za-z_.]+\(.*?\)+|\(.*?\)+|\"(?:[^\"]|\"|\"\")*\"+|'[^'](?:|\'|'')*'+|`(?:[^`]|``)*`+|[^ ,]+ |,/x"; $regex = trim($regex);
    preg_match_all($regex, $sql, $matches, PREG_OFFSET_CAPTURE);
    $tokens = $matches[0];

    if(strtolower($tokens[0][0]) != 'load') {
      $this->errors[]='Malformed LOAD DATA statement.  LOAD is missing.'; 
      return false;
    }
    $skip_next = false; 
    $line_options = false;
    $field_options = false;

    $ignore_lines = 0;
 
    $set_pos_at = 0;
    $past_infile=false; 

    foreach($tokens as $key => $token) {
      if($token[0] == "'") continue;
      if($token[0] == "(") {
        $columns_str = $token;
        continue;
      }


      if($skip_next) { $skip_next = false; continue; }
      $token = $token[0];
      switch(strtolower($token)) {
        case 'load':
        case 'data':
        case 'low_priority':
        case 'concurrent':
          break;

        /* allow configuration of the size of each chunk from the file */
        case 'chunksize':
          $chunk_size = $tokens[$key+1][0];

          $base = substr($chunk_size, 0, strlen($chunk_size)-1);
          $lastchar = substr($chunk_size, -1);
          switch(strtolower($lastchar)) {
            case 'b':
              $chunk_size = $base;
              break;
            case 'k':
              $chunk_size = $base * 1024;
              break; 
            case 'm':
              $chunk_size = $base * 1024 * 1024;
              break; 
            case 'g':
              $chunk_size = $base * 1024 * 1024 * 1024;
              break; 
          }
          $skip_next = true;
          break;

        case 'replace':
          $replace = true;
          break;
 
        case 'ignore':
          if($past_infile) {
            $ignore_lines = $tokens[$key+2][0];
          } else {
            $ignore = true;
          }
          break;

        case 'character':
          $charset = $tokens[$key+2][0];
          $skip_next = true;
          break;

        case 'local':
          $local = true;
          break;

        case 'infile':
          $past_infile=true;
          $file_name = $this->clean_token($tokens[$key+1][0]);
          $skip_next = true;
          break;
       
        case 'into':
         break;

        case 'table':
          $table_name = $tokens[$key+1][0];
          $skip_next = true;
          break;

        case 'fields':
        case 'columns':
          $field_options = true;
          break;

        case 'terminated':
          if($line_options) {
            $lines_terminated_by = $this->clean_token($tokens[$key+2][0]);
          } else {
            $fields_terminated_by = $this->clean_token($tokens[$key+2][0]);
          }
          break;

        case 'by':
          $skip_next = true;
          break;

        case 'optionally':
          $fields_optionally_enclosed=true;
          break;

        case 'enclosed':
          $fields_enclosed_by = $this->clean_token($tokens[$key+2][0]);
          break;

        case 'lines':
          $line_options = true;
          $field_options = false;
          break;

        case 'starting':
          if($line_options) {
            $lines_starting_by = $tokens[$key+2][0];
          } 
          break;

        case 'set':
          $set_pos_at = $tokens[$key][1];
          break 2; #leave for loop

      }
    }
    if($table_name === "") {
      $this->errors[] = "LOAD DATA requires a table name";
      return false;
    }

    if($file_name === "") {
      $this->errors[] = "LOAD DATA requires a file name";
      return false;
    }

    if($replace)$replace="REPLACE";else $replace="";
    if($ignore)$ignore="IGNORE";else $ignore="";

    if($set_pos_at) $set_str = substr($sql, $set_pos_at); else $set_str = "";

    $lines_terminated_by = str_replace("\\n", "\n", $lines_terminated_by);
    #TODO: Handle setting the shard_key in the SET clause
    $SL = new ShardLoader($this, $fields_terminated_by, $fields_enclosed_by, $lines_terminated_by, true /*useFifo*/, $chunk_size, $charset, $ignore, $replace, $lines_starting_by, $fields_escaped_by);

    if($local) {
      if(!$SL->load($file_name, $table_name, null, null, $columns_str, $set_str, $ignore, $replace)) {
        $this->errors = $SL->errors;
        return false;
      }
    } else {
      if(!(strstr($file_name, 's3://') || strstr($file_name, 'http://') || strstr($file_name, 'https://'))) {
        $file_name = "{$this->state->shared_path}/{$file_name}";
      }
      if(!$SL->load_gearman($file_name, $table_name, null, null, $columns_str, $set_str, $ignore, $replace)) {
        $this->errors = $SL->errors;
      }
    }
    unset($SL);
   
    return false;

  }
  
  /* if $sql is an Array(), then it is assumed it is already parsed */
  protected function process_sql($sql, &$state) {
    //only useful for the fetch worker for debugging    
    $state->sql = $sql;
    $parser = null;
    $straight_join = false;
    
    $state->shard_sql = ""; //identical SQL which will be broadcast to all shards
    $state->coord_sql = ""; //the summary table sql
    $state->in_lists = array();
    
    $error = array();
    
    $select = null;

    if(!is_array($sql)) {
      #//we need to check the syntax
      $sql = preg_replace(array(
        "%-- [^\\n](?:\\n|$)%",
        "%/\\*.*\\*/%"
      ), '', trim($sql));

      if(!$sql) { 
        $this->errors[] = "SQL is empty\n";
	return false;
      }

      if(strtolower(substr($sql,0,4)) == 'with') {
        // handle the WITH clauses and rewrite the SQL to use the new table names
        $sql = $this->process_subquery_factoring($sql);
        if(!$sql) { 
	  return false;
        }
      }

      if(strtolower(substr($sql,0,4)) == 'load') {
        $result = $this->process_load_data($sql);
        if(!$result) return false;
      }

      $blacklist = '/(?:create|drop|alter)\s+database|last_insert_id|sql_calc_found_rows|row_count|:=|connection_id|' . 'current_user|session_user|user\(\)|mysql\.|system_user|found_rows|get_lock|free_lock|' . 'is_free_lock|is_used_lock|load_file|infile|master_pos_wait|name_const|release_lock|^show\s+(?:master|slave)|' . '^show\s+bin|^show\s+create\s+database|^show\s+databases|^show\s+logs|^show\s+mutex|' . '^show\s+engine|^show\s+innodb|^show\s+open|^show\s+privileges|^show\s+(?:status|variables)|^lock\s+table|' . '^check\s+table|^checksum\s+table|^backup|^repair|^restore|^call|^handler|^begin|^start\s+transaction|^commit|' . '^rollback|^set transaction|savepoint|^xa\s+(?:start|begin|end|prepare|commit|rollback|recover)/i';
      
      if(preg_match($blacklist, $sql, $matches)) {
        $this->errors[] = 'Unsupported SQL: ' . print_r($matches, true);
        return false;
      }
      
      #syntax check is broken on ICE due to LIMIT 0 issue
      $version_check_sql = 'select @@version_comment c';
      $stmt = $state->DAL->my_query($version_check_sql);
      $comment = "";
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        $comment = $row['c'];
      }

      if(strpos($comment, '(ice)') === false) {
        if(preg_match('/^select/i', $sql)) {
          $check_sql = trim($sql, "; \n");
          //custom functions have to pass the syntax check.  concat takes a variable number of 
          //parameters, so it can be substituted for any custom function for syntax purposes
          foreach(ShardQueryCustomFunctions::$list as $key => $val) {
            $check_sql = preg_replace('/' . $key . '/i', 'concat', $check_sql);
          }
          
          //we need to remove the virtual schema name from any tables in the FROM clause
          //The parser could be used here, but a simple regex will suffice for this simple
          //syntax check
          
          $schema_tokens = array(
            '"' . $this->current_schema . '"' . ".",
            "`{$this->current_schema}`.",
            $this->current_schema . "."
          );
          $check_sql = str_replace($schema_tokens, "", $check_sql);
          $check_sql = preg_replace('/\s+limit\s+\d+,*\s*\d*/i', '', $check_sql) . ' LIMIT 0';
          $check_sql = "select count(*) from (" . trim($check_sql, ";\r\n ") . ") check_sql where 0=1";
          
         /* if(!$state->DAL->my_query($check_sql)) {
              $this->errors[] = $state->DAL->my_error();
              return false;
          }*/
        }
      }

      $sql = preg_replace("/\s+FROM\s+(LAST|FIRST)\s+/i","FROM_$1 ", $sql);

      $state->parsed = $this->parser->parse($sql);
    } else {
      $state->parsed = $sql;
    }

    if(!empty($state->parsed['UNION ALL'])) {
      $queries = array();
      $table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
      $state->table_name = $table_name;
      
      $coord_sql = "";
      foreach($state->parsed['UNION ALL'] as $sub_tree) {
        $sub_state = ShardQuery::new_state();
        
        //initialize the new state with a call to set_schema
        $this->set_schema($this->schema_name, $sub_state);
        $sub_state->tmp_shard = $state->tmp_shard;
        $sub_table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
        $sub_state->table_name = $sub_table_name;
        $sub_state->DAL = SimpleDAL::factory($sub_state->tmp_shard);

        $this->query($sub_tree, false, $sub_state, true, false);

        $state->extra_tables[] = $sub_table_name;
        $state->extra_tables = array_merge($state->extra_tables, $sub_state->extra_tables);
        
        if($coord_sql)
          $coord_sql .= " UNION ALL ";
        $coord_sql .= "( " . $sub_state->coord_sql . " )";
        $state->extra_tables[] = $sub_table_name;
        
      }
      
      $select['coord_sql'] = $coord_sql;
      unset($coord_sql);
    } elseif(!empty($state->parsed['UNION'])) {
      $queries = array();
      $table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
      $state->table_name = $table_name;
      
      $coord_sql = "";
      foreach($state->parsed['UNION'] as $sub_tree) {
        $sub_state = shardquery::new_state();
        
        //initialize the new state with a call to set_schema
        $this->set_schema($this->schema_name, $sub_state);
        $sub_state->tmp_shard = $state->tmp_shard;
        $sub_table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
        $sub_state->table_name = $sub_table_name;
        $sub_state->dal = simpledal::factory($sub_state->tmp_shard);

        $this->query($sub_tree, false, $sub_state, true, false);

        $state->extra_tables[] = $sub_table_name;
        $state->extra_tables = array_merge($state->extra_tables, $sub_state->extra_tables);
        
        if($coord_sql)
          $coord_sql .= " UNION ";
        $coord_sql .= "( " . $sub_state->coord_sql . " )";
        
      }
      //UNION operation requires deduplication of the temporary table
      $select['coord_sql'] = $coord_sql;
      unset($coord_sql);
    } elseif(!empty($state->parsed['SELECT']) && empty($state->parsed['INSERT']) && empty($state->parsed['CREATE']) && empty($state->parsed['REPLACE'])) {
      //reset the important variables 
      $select = $from = $where = $group = $order_by = "";
      $straight_join = $distinct = false;
      $this->errors = array();
      
      /* Attempt star schema optimization */
      if($state->star_opt) {
        $star_result = $this->star_optimize($state);
        if($star_result !== false) {
          $state->parsed = $star_result['parsed'];
          $state->push_where = $star_result['push_where'];
          
        } else {
          #critical error occured during star optimization
          if(!empty($this->errors))
            return false;
        }
      }
      
      //The SELECT clause is processed first.
      if(!empty($state->parsed['OPTIONS']) && in_array('STRAIGHT_JOIN', $state->parsed['OPTIONS'])) {
        $straight_join = true;
      }
      
      if(strtoupper($state->parsed['SELECT'][0]['base_expr']) == 'DISTINCT') {
        $distinct = true;
      }
      
      if(!empty($state->parsed['OPTIONS']) && in_array('SQL_CALC_FOUND_ROWS', $state->parsed['OPTIONS'])) {
        $this->errors[] = array(
          'Unsupported query',
          'SQL_CALC_FOUND_ROWS is not supported'
        );
        return false;
      }
      
      if(!empty($state->parsed['OPTIONS']) && in_array('FOR UPDATE', $state->parsed['OPTIONS'])) {
        $this->errors[] = array(
          'Unsupported query',
          'FOR UPDATE is not supported'
        );
        return false;
      }
      
      if(!empty($state->parsed['OPTIONS']) && in_array('LOCK IN SHARE MODE', $state->parsed['OPTIONS'])) {
        $this->errors[] = array(
          'Unsupported query',
          'LOCK IN SHARE MODE is not supported'
        );
        return false;
      }
     
      $with_rollup = "";
      if(!empty($state->parsed['OPTIONS'])) {
        foreach($state->parsed['OPTIONS'] as $option) {
          if(strtoupper($option['base_expr']) === 'WITH ROLLUP') $with_rollup = ' WITH ROLLUP'; 
        }
      }

      //ignore any other options
      unset($state->parsed['OPTIONS']);
      
      $procedure = "";
      if(!empty($state->parsed['PROCEDURE'])) {
        $procedure = 'PROCEDURE ' . join('', $state->parsed['PROCEDURE']);
        unset($state->parsed['PROCEDURE']);
      }
      
      if(!empty($state->parsed['INTO'])) {
        $this->errors[] = array(
          'Unsupported query',
          'INTO is not supported'
        );
        return false;
        
      }
      
      /*This will process the SELECT clause, doing any SQL rewrites needed for aggregation at the shard level
      The $this->non_distrib flag will be set if the query contains any non-distributable aggregate functions (none currently)
      
      If non-distributable aggregate functions are detected, we will throw this work away and instead use
      $this->process_undistributable_select() [below]
      */
      $select = $this->process_select($state->parsed['SELECT'], $straight_join, $distinct, $state);
      
      /* Now some special window function columns are needed */
      if(!empty($state->windows)) {
        $used_cols = array();
        for($i=0;$i<count($state->windows);++$i) {
          $win = $state->windows[$i];
          $shard_hash = "";
        
          foreach($win['partition'] as $part) {
            if(empty($used_cols[$part['base_expr']])) {
              $select['shard_sql'] .= "," . $part['base_expr'];
              $used_cols[$part['base_expr']] = 1;
            } else {
              continue;
            }
            if($shard_hash) $shard_hash .= ",";
            $shard_hash .= $part['base_expr'];
          }
          if($shard_hash == "") $shard_hash="'ONE_PARTITION'";
          $select['shard_sql'] .= ",SHA1(CONCAT_WS('#'," . $shard_hash . ")) as wf{$i}_hash"; 

          $obclause ="";
          $obhash = "";
          foreach($win['order'] as $ob) {
            if(empty($used_cols[$ob['base_expr']])) {
              $select['shard_sql'] .= "," . $ob['base_expr'];
              $used_cols[$ob['base_expr']] = 1;
            } 
              
            if($obclause) $obclause .= ",";
            if($obhash) $obclause .= ",";
            $obclause .= $ob['base_expr'] . " " . $ob['direction'];
            $obhash .= $ob['base_expr'];
          }
          if($obhash) { 
            $select['shard_sql'] .= ",SHA1(CONCAT_WS('#'," . $obhash . ")) as wf{$i}_obhash";
          }
          $state->windows[$i]['order_by'] = $obclause;
        }
      }

      if(trim($select['shard_sql']) == 'SELECT') {
        $select['shard_sql'] = 'SELECT 1';
      }
      
      if(!empty($select['error'])) {
        $this->errors[] = $select['error'];
        return false;
      }
      
      if(!$state->non_distrib) {
        unset($state->parsed['SELECT']);
      } else {
        $select = $this->process_undistributable_select($state->parsed['SELECT'], $straight_join, $distinct);
        unset($state->parsed['SELECT']);
      }

      if(!empty($select['error'])) {
        $this->errors[] = $select['error'];
        return false;
      }

      $coord_group = "";
      $shard_group = "";
      if(!empty($state->parsed['GROUP'])) {
        $group = $state->parsed['GROUP'];
        foreach($group as $item) {
          if($coord_group) $coord_group .= ",";
          $coord_group .= $this->process_group_item($item, $state->used_colrefs,0, $select);
        }
      }

      if($shard_group !== "") {
        if( $select['group_aliases'] !== "") $select['group_aliases'] .= ",";
        $select['group_aliases'] .= $shard_group;
      }
      $select['coord_group'] = $coord_group;
      $select['shard_group'] = $select['group_aliases'];
      
      if(empty($state->pushed) && empty($state->parsed['GROUP'])) {
        $select['coord_group'] = "";
        $select['shard_group'] = "";
      } 

      unset($state->parsed['GROUP']);

      if($having_info = $this->process_having($state)) {
        if(trim($having_info['select']))
          $select['shard_sql'] .= "," . $having_info['select'];
      } else {
        $having_info = array(
          'having' => " "
        );
      }
      $order_by = "";
      if(!empty($state->windows)) {
        $order_by = "wf0_hash";
        if(!empty($state->windows[0]['order_by'])) { 
          $order_by .= ",";
          $order_by .= $state->windows[0]['order_by'];
        }
      }
      
      if(!empty($state->parsed['ORDER'])) {
        foreach($state->parsed['ORDER'] as $o) {
          if($order_by)
            $order_by .= ',';
          
          switch($o['expr_type']) {
            
            case 'subquery':
              $this->errors[] = "Subqueries are not supported in the ORDER BY clause.\n";
              return false;
              break;
            
            case 'custom_function':
              $this->errors[] = "Custom functions are not supported in the ORDER BY clause.\n";
              return false;
              break;
            
            case 'function':
            case 'expression':
            case 'aggregate_function':
              $item = "";
              $expr_alias = 'expr_' . mt_rand(1, 10000000000);
              $this->concat_all_subtrees($o, $item);
              $select['shard_sql'] .= ", $item as $expr_alias";
              $order_by .= $o['base_expr'] . '(' . $expr_alias . ')';
              
              if($o['expr_type'] === 'aggregate_function') {
                switch(strtoupper($o['base_expr'])) {
                  
                  case 'SUM':
                    $select['coord_odku'][] = "$expr_alias=$expr_alias +  VALUES($expr_alias)";
                    break;
                  
                  case 'MIN':
                    $select['coord_odku'][] = "$expr_alias=IF($expr_alias < VALUES($expr_alias), VALUES($expr_alias),$expr_alias)";
                    break;
                  
                  case 'MAX':
                    $select['coord_odku'][] = "$expr_alias=IF($expr_alias > VALUES($expr_alias), VALUES($expr_alias), $expr_alias)";
                    break;
                  
                  case 'COUNT':
                    $select['coord_odku'][] = "$expr_alias=$expr_alias +  VALUES($expr_alias)";
                    break;

                  default:
                    $this->errors[] = "Unsupported aggregate function in ORDER BY";
                    return false;
                    
                }
                
              }
              
              break;
            
            default:
              if(strpos($o['base_expr'], '.') !== false) {
                $expr_alias = 'expr_' . mt_rand(1, 10000000000);
                $select['shard_sql'] .= ", {$o['base_expr']} as $expr_alias";
                $order_by .= $expr_alias;
              } else {
                $order_by .= $o['base_expr'];
              }
              break;
          }
          
          $order_by .= " " . $o['direction'];
        }
      }
      if($order_by) $order_by = " ORDER BY {$order_by}";
      unset($state->parsed['ORDER']);
      
      if(empty($state->parsed['FROM'])) {
        $this->errors = array(
          'Unsupported query',
          'Missing FROM clause'
        );
        return false;
      } else {
        $select['shard_sql'] .= "\n" . $this->process_from($state->parsed['FROM'], $state);
        
        $in_tables = "";
        $is_virtual = false;
        foreach($state->tables as $val => $attr) {
          if($attr['is_virtual'])
            $is_virtual = true;
          if($in_tables !== "")
            $in_tables .= ",";
          $in_tables .= "'{$val}'";
        }
        
        
        /*
         * If no real tables were used (only subquery in from clause for example)
         * then direct the query to the coordinator shard, as it will read from
         * the output of the subquery in the from clause or other construct.
         */
        if($in_tables === "") {
          $state->force_shard = array(
            $state->tmp_shard
          );
        } elseif($in_tables !== "" && !$is_virtual) {
          /* 
           * Otherwise, if this isn't a virtual table (v_ view) then we have to inspect
           * the tables to see if any of them contain the shard key.  If any v_ tables are
           * used then we assume it is sharded (what is point of virtual table otherwise?)
           */
          $sql = "select count(*) cnt from information_schema.columns where table_schema='" . $state->tmp_shard['db'] . "' and column_name='" . $this->shard_column . "' and table_name IN({$in_tables});";
          
          if(!$stmt = $state->DAL->my_query($sql)) {
            $this->errors[] = 'Could not access information schema: ' . $state->DAL->my_error();
            return false;
          }
          $row = $state->DAL->my_fetch_assoc();
          
          /* If none of the tables contain the sharding key, the query goes to all shards */
          if($row['cnt'] === '0') {
            if(isset($state->tmp_shard)) {
              $state->force_shard = array(
                $state->tmp_shard
              );
            } else {
              $state->force_shard = array(
                $state->shards[array_rand($state->shards)]
              );
            }
          }
        } else {
          $state->force_shard = array();
        }
        
        if(!isset($state->table_name) || $state->table_name == "")
          $state->table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
        $select['coord_sql'] .= "\nFROM `$state->table_name`";
        $select['winfunc_sql'] .= "\nFROM `$state->table_name`";
        
        unset($state->parsed['FROM']);
      }
      
      //note that this will extract inlists and store them in $this->in_lists (if inlist optimization is on)  
      $where_clauses = $this->process_where(empty($state->parsed['WHERE']) ? array() : $state->parsed['WHERE'], $state);
      
      if(!empty($state->parsed['WHERE'])) {
        unset($state->parsed['WHERE']);
      }
      
      
      if(empty($state->force_shard))
        $state->force_shard = $state->shards;
      
      /* Handle dependent subqueries if the subquery has to be on one or more shards that are different from tmp_shard.*/
      foreach($state->dependent_subqueries as $sub_table) {
        if(empty($state->tables))
          continue; //don't do this work if there are no real tables 
        
        $sql = "SHOW CREATE TABLE $sub_table";
        $stmt = $state->DAL->my_query($sql);
        
        $row = $state->DAL->my_fetch_array();
        $create_sql = $row[1];
        if(!$row) {
          $this->errors[] = 'Could not get DDL for intermediate table: ' . $sub_table . "(" . $state->DAL->my_error() . ")";
          $this->cleanup_tables($state);
          return false;
        }
        
        $export_sql = "SELECT * FROM $sub_table";
        $stmt = $state->DAL->my_unbuffered_query($export_sql);
        if(!$stmt) {
          $this->errors[] = 'Could not open intermediate result from: ' . $sub_table . "(" . $state->DAL->my_error() . ")";
          $this->cleanup_tables($state);
          return false;
        }
        
        $fname = tempnam('/tmp', 'SQ');
        $fh = fopen($fname, 'wb');
        if(!$fh) {
          $this->errors[] = 'Could not open temporary file for spooling dependent subquery to shards: ' . $fname;
          return false;
        }
        
        while($row = $state->DAL->my_fetch_array()) {
          $outrow = "";
          foreach($row as $col) {
            $col = str_replace('"', '\\"', $col);
            if(!is_numeric($col))
              $col = '"' . $col . '"';
            if($outrow)
              $outrow .= ",";
            $outrow .= $col;
          }
          if(!fputs($fh, $outrow . "\n")) {
            $this->errors[] = 'Could not write to temporary file for spooling dependent subquery to shards: ' . $fname;
            return false;
          }
        }
        fclose($fh);
        
        foreach($state->force_shard as $shard) {
          if($shard === $state->tmp_shard)
            continue;
          $dal = SimpleDAL::factory($shard);
          $dal->my_select_db($shard['db']);
          $stmt = $dal->my_query($create_sql);
          if(!$stmt) {
            $this->errors[] = "Failed to create destination table: $sub_table during intermediate copy" . "(" . $dal->my_error() . ")";
            $this->cleanup_tables($state);
            return false;
          }
          $load_sql = "LOAD DATA LOCAL INFILE '$fname' INTO TABLE `{$shard['db']}`.$sub_table FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\\\\'";
          $stmt = $dal->my_query($load_sql);
          if(!$stmt) {
            $this->errors[] = 'Failed to LOAD DATA LOCAL INFILE to table: $sub_table during intermediate copy' . "(" . $dal->my_error() . ")";
            $this->cleanup_tables($state);
            return false;
          }
          $dal->my_close();
        }
        unlink($fname);
      }
      
      /* semijoin optimization */
      if(!empty($state->push_join)) {
        $select['shard_sql'] .= join('', $state->push_join);
      }
      
      $used_limit = 0;
      if(!empty($state->parsed['LIMIT'])) {
        $used_limit = 1;
        if($state->parsed['LIMIT']['offset'] == "")
          $state->parsed['LIMIT']['offset'] = 0;
        $order_by .= " LIMIT {$state->parsed['LIMIT']['offset']},{$state->parsed['LIMIT']['rowcount']}";
        unset($state->parsed['LIMIT']);
      }
      
      foreach($state->parsed as $key => $clauses) {
        $this->errors[] = array(
          'Unsupported query',
          $key . ' clause is not supported'
        );
      }
      
      if($this->errors) {
        return false;
      }
      
      //process_select only provides a list of positions
      if($select['coord_group'])
        $select['coord_group'] = ' GROUP BY ' . $select['coord_group'] . $having_info['having'] . $with_rollup . $procedure;
      if($select['shard_group'])
        $select['shard_group'] = ' GROUP BY ' . $select['shard_group'];
      
      $queries = array();
      $where_base = " 1=1 ";
      
      if(!empty($state->push_where)) {
        $where_base .= ' AND ' . join(' ', $state->push_where);
      }
      
      if($state->mysql_version['supports_partition_hint'] == 1) {
        
        #if table is not partitioned, then don't inject any partition hints
        #have to do all removals in a first pass
        
        foreach($state->tables as $table_name => $table_info) {
          if(empty($table_info['alias']) || $table_info['alias'] === '') {
            $alias = $table_name;
          } else {
            $alias = $table_info['alias'];
          }
          $alias = trim($alias,'`');
          if($table_info['partition_info']['partition_names'][0] == "") {
            $select['shard_sql'] = str_replace('%p' . $alias, "", $select['shard_sql']);
          }
        }
        
        #now build out queries for each partition for each table
        foreach($state->tables as $table_name => $table_info) {
          
          if(empty($table_info['alias']) || $table_info['alias'] === '') {
            $alias = $table_name;
          } else {
            $alias = $table_info['alias'];
          }
          $alias = trim($alias,'`');
          if($table_info['partition_info']['partition_names'][0] != "") {
            if(empty($queries)) {
              foreach($table_info['partition_info']['partition_names'] as $x => $partition_name) {
                $queries[] = str_replace('%p' . $alias, " PARTITION(" . $partition_name . ") ", $select['shard_sql']);
              }
            } else {
              $old_queries = $queries;
              $queries = array();
              foreach($old_queries as $query) {
                foreach($table_info['partition_info']['partition_names'] as $x => $partition_name) {
                  $query = str_replace('%p' . $alias, " PARTITION(" . $partition_name . ") ", $query);
                  $queries[] = $query;
                }
              }
            }
            #remove the token so that it doesn't mess up further tables in the query
            $select['shard_sql'] = str_replace('%p' . $alias, "", $select['shard_sql']);
          }
          
        }
        
      } else {
        
        $explain_query = "EXPLAIN PARTITIONS " . $select['shard_sql'] . ' ' . $where;
        
        $stmt = $state->DAL->my_query($explain_query);
        $state->added_where = 0;
        if($where == "") $where = " WHERE 1=1";
        while($row = $state->DAL->my_fetch_assoc($stmt)) {
          $table_or_alias = $row['table'];
          $partitions = $row['partitions'];
          $partitions = explode(',', $partitions);
          foreach($partitions as $partition_name) {
            foreach($state->tables as $table_name => $table_info) {
              if(($table_or_alias === $table_name) || ($table_or_alias === $table_info['alias'])) {
                if(!empty($table_info['partition_info']['where_clauses'][$partition_name])) {
                  $expr = $table_info['partition_info']['where_clauses'][$partition_name];
                  if($table_info['alias'] !== '') {
                    $expr = preg_replace("/" . $table_info['partition_info']['partition_expression'] . "/", $table_info['alias'] . '.' . $table_info['partition_info']['partition_expression'], $expr);
                  }
                  $queries[] = $select['shard_sql'] . ' ' . $where . ' AND ' . $expr;
                  $state->added_where = 1;
                }
              }
            }
          }
        }
        
      }

      $where_base  = $state->added_where != 1 ? " WHERE $where_base " : " AND 1=1 ";
      if(empty($where_clauses)) {
        $where_clauses = array($where_base);
      } else {
        $old_clauses = $where_clauses;
        $where_clauses = array();
        foreach($old_clauses as $new_where_clause) {
          $where_clauses[] = $where_base . ' AND ' . $new_where_clause;
        }
      }

      #queries is empty if no partition parallelism was added
      #parallelism may still have been added from BETWEEN clauses ($where_clauses may be an array of clauses)	
      if(empty($queries)) {
        foreach($where_clauses as $where_clause) {
          if(!$state->no_pushdown_limit) {
            $queries[] = $select['shard_sql'] . $where_clause . $select['shard_group'] . ($used_limit ? $order_by : '');
          } else {
            $queries[] = $select['shard_sql'] . $where_clause . $select['shard_group'];
          }
        }
      } else {
        $old_queries = $queries;
        $queries = array();
        foreach($old_queries as $query) {
          foreach($where_clauses as $where_clause) {
            if(!$state->no_pushdown_limit) {
              $nq = $query . ' ' . $where_clause . ' ' . $select['shard_group'] . ($used_limit ? $order_by : '');
            } else {
              $nq = $query . ' ' . $where_clause . ' ' . $select['shard_group'];
            }
            $queries[] = $nq;
          }
        }
      }
      
      
    } elseif(!empty($state->parsed['INSERT']) || !empty($state->parsed['REPLACE'])) {
      if(!empty($state->parsed['INSERT'])) {
        $replace = "";;
        $to_table = $state->parsed['INSERT'][1]['table']; 
      } else { 
        $replace=" REPLACE ";
        $to_table = $state->parsed['REPLACE'][1]['table'];
      }
      $result = $this->get_insert_cols($state);
      if($result === false) return false;
      $cols = $result[0];
      $col_list = $result[1];

      $shard_column_pos = false;
      foreach($cols as $pos => $col) {
        if(trim($col, '` ') == $this->shard_column) {
          $shard_column_pos = $pos;
          break;
        }
      }

      if(!empty($state->parsed['SELECT'])) {
        $parsed = $state->parsed;
        $ignore = "";
        if(!empty($parsed['OPTIONS'])) {
          foreach($parsed['OPTIONS'] as $option) {
            if(strtolower($option) == 'ignore') {
              $ignore = 'ignore';
              break;
            }
          }
        }
        unset($parsed['INSERT']);
        unset($parsed['REPLACE']);
        unset($parsed['OPTIONS']);

        $sub_state = shardquery::new_state();
        $this->set_schema($this->schema_name, $sub_state);
        $sub_state->tmp_shard = $state->tmp_shard;
        $sub_table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
        $sub_state->table_name = $sub_table_name;
        $sub_state->dal = simpledal::factory($sub_state->tmp_shard);
        $this->query($parsed, false, $sub_state, true, false);
        $state->extra_tables[] = $sub_table_name;
        $state->extra_tables = array_merge($state->extra_tables, $sub_state->extra_tables);

        $result = $this->load_from_table($sub_state, $to_table, $col_list, $shard_column_pos, $ignore, $replace);
        if($result) {
          $this->state->coord_sql = "select 'INSERT .. SELECT completed ok' as message from dual"; 
          return true;
        } else {
          return false;
        }

      } else {
        
        if($replace == "") {
          $sql = "INSERT INTO `" . trim($state->parsed['INSERT'][1]['table'],'`') . "` {$col_list} VALUES ";
          $replace = "INSERT";
        } else {
          $sql = "REPLACE INTO `" . trim($state->parsed['REPLACE'][1]['table'],'`') . "` {$col_list} VALUES ";
        }

        $values = array();
        $val_count = 0;
        
        $column_count = count($cols);
        foreach($state->parsed['VALUES'] as $record) {
          $vals = trim($record['base_expr'],',');
          //this row is going to go to a single shard
          if($shard_column_pos !== false) {
            if(count($record['data']) !== $column_count) {
              $this->errors[] = 'Value count does not match column count!';
              return false;
            }
            $shard_id = $this->map_shard($this->shard_column, $record['data'][$shard_column_pos]['base_expr'], $this->state->schema_name, '=', true);
            $shard_id = @array_pop(array_keys(array_pop($shard_id)));
            
            if(empty($values[$shard_id])) {
              $values[$shard_id] = $vals;
            } else {
              $values[$shard_id] .= ",{$vals}";
            }
            
            $val_count++;
            
            //the row is going to all shards
          } else {
            foreach($this->shards as $shard_id => $shard) {
              if(empty($values[$shard_id])) {
                $values[$shard_id] = $vals;
              } else {
                $values[$shard_id] .= ",{$vals}";
              }
              
              $val_count++;
            }
          }
        }
        
        $state->insert_sql = $sql;
        $state->insert_values = $values;
        
      }
      
      return true;
      
    } else {
      if(!empty($state->parsed['CREATE']) && !empty($state->parsed['TABLE']) && !empty($state->parsed['SELECT'])) { /* CTAS */
        $table_name = $state->parsed['TABLE']['base_expr'];
        $schema_tokens = array(';',
          '"' . $this->current_schema . '"' . ".",
          "`{$this->current_schema}`.",
          $this->current_schema . "."
        );
        $create_sql = $state->orig_sql;
        $create_sql = str_replace($schema_tokens, "", $create_sql);
        $create_sql = preg_replace('/\s+limit\s+\d+/i', '', $create_sql) . ' LIMIT 0';
        foreach($this->shards as $name => $shard) {
          $dal = SimpleDAL::factory($shard);
          $dal->my_select_db($shard['db']);
          $dal->my_query($create_sql);
          if($dal->my_error()) {
            $this->errors[] = "on shard: " . $name . " errorno:" . $dal->my_errno() . ",message:" . $dal->my_error();
          }
          $dal->my_close();
        }

        unset($state->parsed['CREATE']);
        unset($state->parsed['TABLE']);
        $result = $this->get_insert_cols($state, $table_name);

        if($result === false) return false;
        $cols = $result[0];
        $col_list = $result[1];

        $shard_column_pos = false;
        foreach($cols as $pos => $col) {
          if(trim($col, '` ') == $this->shard_column) {
            $shard_column_pos = $pos;
            break;
          }
        }
        $ignore = ""; $replace = "";
        $sub_state = shardquery::new_state();
        $this->set_schema($this->schema_name, $sub_state);
        $sub_state->tmp_shard = $state->tmp_shard;
        $sub_table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
        $sub_state->table_name = $sub_table_name;
        $sub_state->dal = simpledal::factory($sub_state->tmp_shard);
        $this->query($state->parsed, false, $sub_state, true, false);
        $state->extra_tables[] = $sub_table_name;
        $state->extra_tables = array_merge($state->extra_tables, $sub_state->extra_tables);

        $result = $this->load_from_table($sub_state, $table_name, "", $shard_column_pos, $ignore, $replace);
        if($result) {
          $this->state->coord_sql = "select 'CREATE .. SELECT completed ok' as message from dual";
          return true;
        } else {
          return false;
        }
         
      } /*else*/

      //This query should be broadcast to all nodes
      #$state->broadcast_query = $state->orig_sql;
      foreach($this->shards as $shard_name => $shard) {
        $dal = SimpleDAL::factory($shard);
        $dal->my_select_db($shard['db']);
        if($dal->my_error()) {
          $this->errors[] = $dal->my_error();
          continue;
        }
        $dal->my_query($state->orig_sql);
        if($dal->my_error()) {
          $this->errors[] = $dal->my_error();
        }
      }
      $this->state->coord_sql = "select 'Sent query to all shards' as message from dual";
      return true;
    }
    
    
    //if(!isset($state->table_name)) $state->table_name = "agg_ 
    if(empty($order_by))
      $order_by = "";
    if(empty($select['coord_group']))
      $select['coord_group'] = "";
    if(empty($select['coord_odku']))
      $select['coord_odku'] = "";
    if(empty($select['group_aliases']))
      $select['group_aliases'] = "";
    $state->coord_sql = $select['coord_sql'] . ' ' . $select['coord_group'] . ' ' . $order_by;
    $state->winfunc_sql = $select['winfunc_sql'];
    $state->winfunc_group = $select['coord_group'];
    if(!isset($state->coord_odku) || empty($state->coord_odku))
      $state->coord_odku = $select['coord_odku'];
    $state->shard_sql = $queries;

    $state->agg_key_cols = $select['group_aliases'];
    
    if(!empty($select['custom_functions'])) {
      $state->custom_functions = $select['custom_functions'];
    } else {
      $state->custom_functions = array();
    }
    
    $explain = "Shard-Query optimizer messages:";
    if($state->agg_key_cols) {
      $explain .= "\n  * The following projections may be selected for a UNIQUE CHECK on the storage node operation:\n  {$state->agg_key_cols}\n";
      if($state->coord_odku)
        $explain .= "\n  * storage node result set merge optimization enabled:\n  ON DUPLICATE KEY UPDATE\n\t" . join(",\n\t", $state->coord_odku);
    }
    
    if(isset($state->messages)) {
      foreach($state->messages as $msg) {
        $explain .= "\n  * $msg";
      }
    }
    
    if($state->non_distrib) {
      $explain .= "\n  * Aggregation pushdown disabled due to use of non-distributable and/or custom functions.";
    }
    
    $explain .= "\n\nSQL TO SEND TO SHARDS:\n";
    $explain .= print_r($state->shard_sql, true);
    
    $explain .= "SQL TO SEND TO COORDINATOR NODE:\n{$state->coord_sql}\n";
    
    $state->explain = $explain;
    
    return true;
    
  }
  
  protected function create_gearman_set($queries, $table, $complete_cb = null, $failure_cb = null, &$set, $action = "store_resultset", &$state) {
    if($this->async) {
      $task_type = Net_Gearman_task::JOB_BACKGROUND;
      $query_id = $state->query_id;
    } else {
      $task_type = Net_Gearman_task::JOB_NORMAL;
      $query_id = null;
    }
    
    $sets = array();
    if(!$set)
      $set = new Net_Gearman_Set();
    if(isset($state->force_shard) && !empty($state->force_shard))
      $shards = $state->force_shard;
    else
      $shards = $state->shards;
    
    $this->create_agg_table($state);
    
    for($i = 0; $i < count($queries); ++$i) {
      foreach($shards as $shard) {
        $task = new Net_Gearman_Task("store_resultset", array(
          'engine' => $state->engine,
          'table_name' => $table,
          'sql' => $queries[$i],
          'coord_odku' => $state->coord_odku,
          'shard' => $shard,
          'tmp_shard' => $state->tmp_shard,
          'agg_key_cols' => $state->agg_key_cols,
          'when' => microtime(true),
          'action' => $action,
          /*
          'custom_functions' => $custom_functions*/
          'query_id' => $query_id
        ), uniqid(md5($queries[$i]), true), $task_type);
        $task->attachCallback($complete_cb);
        $task->attachCallback($failure_cb, Net_Gearman_Task::TASK_FAIL);
        $set->addTask($task);
        $state->store_resultset_count++;
      }
      $sets[] = $set;
    }
    
    return $sets;
  }
  
  protected function run_set($set, &$state) {
    if(!is_array($set))
      $set = array(
        $set
      );
    
    foreach($set as $the_set) {
      $this->workers++;
      $state->client->runSet($the_set);
    }
  }
  
  
  protected function aggregate_result(&$state = null) {
    $stmt = false;
    if(!isset($state))
      $state = $this->state;
    if(!$state->DAL->my_select_db($state->tmp_shard['db'])) {
      $this->errors[] = 'While aggregating result: ' . $state->DAL->my_error();
      $stmt = false;
    } else {
      if($state->used_distinct) {
        $state->DAL->my_query("alter table " . $state->table_name . " drop key gb_key");
      }
      
      if(!$stmt = $state->DAL->my_query($state->coord_sql)) {
        $this->errors[] = 'While aggregating result: ' . $state->DAL->my_error();
        return false;
      }
    }
    
    $this->cleanup_tables($state);
    
    return $stmt;
    
  }
  
  public function get_async_result($result_id, $keep_result = false, &$state = null) {
    require('shard-query-config.php');
    if(!isset($state))
      $state = $this->state;
    
    if(!is_numeric($result_id))
      return false;
    
    $sql = "select shard_name, tables_used, job_result_sql from jobs join shards on jobs.shard_id = shards.id where jobs.id = $result_id";
    $DAL = SimpleDal::factory($config_database);
    $DAL->my_select_db($config_database['db']);
    $stmt = $DAL->my_query($sql);
    $row = $DAL->my_fetch_assoc();
    
    $sql = $row['job_result_sql'];
    $tables = $row['tables_used'];
    $shard = $state->shards[$row['shard_name']];
    $DAL->my_close();
    
    //get new dal shard
    $DAL = SimpleDal::factory($shard);
    $DAL->my_connect($shard);
    $DAL->my_select_db($shard['db']);
    $stmt = $DAL->my_query($sql);
    $this->DAL = $DAL;
    $this->state->DAL = $DAL;
    if(!$keep_result) {
      $DAL->my_query('DROP TABLE ' . $tables);
    }
    
    return $stmt;
    
  }
  
  function cleanup_tables(&$state = null) {
    
    if(!isset($state))
      $state = $this->state;
    
    $sql = "DROP TABLE IF EXISTS {$state->table_name};";
    @$state->DAL->my_query($sql);
    
    foreach($state->extra_tables as $table) {
      $sql = "DROP TABLE IF EXISTS {$table};";
      @$state->DAL->my_query($sql);
    }
  }
  
  //callback that records that one of the workers failed  
  function handle_failure($func) {
    --$this->workers;
    if(!$this->errors)
      $this->errors = array();
    $this->errors[] = "WORKER_FAIL: handle_failure registered unsuccessful worker completion.\n";
  }
  
  function generic_callback($func, $handle, $result) {
    --$this->workers;
    if(!$this->errors)
      $this->errors = array();
    if(!$this->info)
      $this->info = array();
    if(is_array($result) && !empty($result['error'])) {
      $this->errors[] = $result['error'];
    }
    if(is_array($result) && !empty($result['info'])) {
      $this->info[] = $result['info'];
    }
    if(!empty($result['done']) && $result['done'] > 0)
      $this->state->got_rows += $result['done'];
    if(!empty($result['hashes']['gb_hash'])) {
      //dedupe the hashes using an associative array
      foreach(array_keys($result['hashes']['gb_hash']) as $hash) {
        if(empty($this->hashes[$hash])) {
          $this->hashes[$hash] = 1;
        } else {
          $this->hashes[$hash]++;
        }
      }
    }
  }
  
  function star_optimize(&$state = null) {
    $push_where = array();
    /* list of tables by their alias */
    $table_aliases = array();
    $parsed = $state->parsed; #copy the original parse tree
    
    /* to prevent double backtick quoting, strip off any backtick quotes*/
    $this->unquote_colrefs($parsed);
    
    #FIXME: Using the delayed join optimization is still appropriate in this case and we shouldn't leave!
    if(empty($parsed['WHERE'])) {
      $this->warnings[] = "Star optimization requires filters on dimensions in the WHERE clause.  No filters found.";
      return false;
    }
    
    if(count($parsed['FROM']) < 3) {
      $this->warnings[] = "Star optimization requires at least three tables in the FROM clause.";
      return false;
    }
    
    $conn = SimpleDAL::factory($state->tmp_shard);
    $conn->my_select_db($state->tmp_shard['db']);
    
    
    /*
     * Build the array that maps table aliases to table structure and metadata.
     This takes one pass over the FROM clause, and sends two queries to the I_S
     for each table.
     */
    
    //the "big" table is the fact table    
    $big_table_size = 0;
    $big_table_name = "";
    $big_table_alias = "";
    
    foreach($parsed['FROM'] as $idx => $table) {
      if($table['expr_type'] != 'table')
        continue;
      
      if(!empty($table['ref_clause']) && !(count($table['ref_clause']) === 1 || count($table['ref_clause']) === 3)) {
        $this->warnings[] = "Join condition:" . $table['ref_clause'] . " includes more than one column or a complex expression.  Must be fact.column = dim.column. Join can not be optimized.\n";
        continue;
      }
      
      if(empty($table['alias'])) {
        $alias = $table['table'];
      } else {
        $alias = $table['alias']['name'];
      }
      
      $table_aliases[$alias] = array(
        'table' => $table['table'],
        'columns' => array(),
        'ref_type' => $table['ref_type'],
        'ref_clause' => $table['ref_clause'],
        'orig_idx' => $idx,
        'sharded' => false,
        'row_count' => null
      );
      
      $sql = "select lower(column_name) column_name from information_schema.columns where table_schema = '" . $state->tmp_shard['db'] . "' and table_name='" . $table['table'] . "'";
      $conn->my_query($sql);
      if($conn->my_error() != "") {
        $this->errors[] = $conn->my_error();
        return false;
      }
      while($row = $conn->my_fetch_array()) {
        $table_aliases[$alias]['columns'][] = $row[0];
        if($row[0] === $this->shard_column) {
          $table_aliases[$alias]['sharded'] = true;
        }
      }
      
      $sql = "select table_rows from information_schema.tables where table_schema = '" . $state->tmp_shard['db'] . "' and table_name='" . $table['table'] . "'";
      $conn->my_query($sql);
      if($conn->my_error() != "") {
        $this->errors[] = $conn->my_error();
        return false;
      }
      
      while($row = $conn->my_fetch_array()) {
        $table_aliases[$alias]['row_count'] = $row[0];
        if($row[0] > $big_table_size) {
          $big_table_size = $row[0];
          $big_table_name = $table['table'];
          $big_table_alias = $alias;
        }
      }
      
    }
    
    $start = 0;
    $end = 0;
    $filters = array();
    /*
     * Extract the filter conditions from the WHERE clause
     * the filters will be used to scan the dimensions 
     * individually, then the resulting rows will be
     * added to the WHERE clause with an IN on the fact table.
     */
    while($end < count($parsed['WHERE'])) {
      
      $operator = "";
      if(!empty($parsed['WHERE'][$end + 1]) && $parsed['WHERE'][$end + 1]['expr_type'] === 'operator')
        $operator = $parsed['WHERE'][$end + 1]['base_expr'];
      $filter = $this->star_extract_where($parsed['WHERE'], $table_aliases, $start, $end, $big_table_alias, $state);
      if($filter !== false) {
        /* A type zero filter means an error happened and we need to abort star optimization*/
        if($filter['filter_type'] === 0) {
          return false;
          /* A type 2 filter is just text which needs to be appended */
        } elseif($filter['filter_type'] === 2) {
          foreach($filter as $key => $val) {
            if($key === 'filter_type')
              continue;
            if(empty($filters[$key]))
              $filters[$key] = "";
            if($operator !== "" && $filters[$key] !== "")
              $filters[$key] .= " $operator ";
            $filters[$key] .= "( " . $filter[$key] . ' )';
          }
        } else {
          /* A type 1 filter is an object which text must be constructed from*/
          if($filter['table_alias'] !== $big_table_alias) {
            $a = $filter['table_alias'];
            if(empty($filters[$a]))
              $filters[$a] = "";
            if($operator !== "" && $filters[$a] !== "")
              $filters[$a] .= " $operator ";
            
            $filters[$a] .= "( `" . $filter['column_name'] . '` ' . $filter['operator'] . ' ' . $filter['condition'] . ' )';
            
          }
        }
      }
      $start = $end + 1;
    }
    
    $dim_sql = array();
    
    foreach($filters as $table_alias => $filter) {
      $dim = $table_aliases[$table_alias];
      $join_column = null;
      $fact_col = "";
      
      if(empty($dim['ref_clause'])) {
        foreach($table_aliases as $key => $candidate) {
          if($key === $table_alias)
            continue;
          if($candidate['table'] === $dim['table'])
            continue;
          
          if($candidate['ref_type'] === 'USING') {
            $found_column = false;
            #search dimension columns to see if any of them 
            #match the fact column which is being joined on
            foreach($dim['columns'] as $dim_column) {
              if($candidate['ref_clause'][0]['base_expr'] === $dim_column) {
                $found_column = true;
                $fact_column = $big_table_alias . "." . $column;
                $dim_column = $table_alias . "." . $column;
                break;
              }
            }
          } elseif($candidate['ref_type'] === 'ON') {
            $fact_col = "";
            $dim_col = "";
            
            foreach($candidate['ref_clause'] as $item) {
              if($item['expr_type'] === 'colref') {
                $dot_pos = strpos($item['base_expr'], '.');
                if($dot_pos === false) {
                  #search the fact table first
                  if($fact_col === "") {
                    foreach($table_aliases[$big_table_alias]['columns'] as $column) {
                      if($column === $item['base_expr']) {
                        $fact_col = $column;
                      }
                    }
                    if($fact_col === "" && $dim_col === "")
                      $dim_col = $item['base_expr'];
                  } else {
                    if($dim_col === "")
                      $dim_col = $item['base_expr'];
                  }
                  
                  #if the item was not in the fact table, them it has to be in the dimension 
                } else {
                  $a = substr($item['base_expr'], 0, $dot_pos);
                  $c = substr($item['base_expr'], $dot_pos + 1);
                  
                  if($a === $big_table_alias) {
                    $fact_col = $c;
                  } else {
                    $dim_col = $c;
                  }
                  
                }
              }
            }
            if($fact_col !== "" && $dim_col !== "")
              break;
          }
        }
      } else {
        switch($dim['ref_type']) {
          case 'USING':
            $fact_col = $dim_col = $dim['ref_clause'][0]['base_expr'];
            break;
          
          case 'ON':
            $pos = -1;
            foreach($dim['ref_clause'] as $item) {
              $pos++;
              if($item['expr_type'] === 'colref') {
                $dot_pos = strpos($item['base_expr'], '.');
                if($dot_pos === false) {
                  #search the fact table first
                  if($fact_col === "") {
                    foreach($table_aliases[$big_table_alias]['columns'] as $column) {
                      if($column === $item['base_expr']) {
                        $fact_col = $column;
                        break;
                      }
                    }
                    
                    #if the item was not in the fact table, them it has to be in the dimension 
                  }
                  if($fact_col !== "" && $pos > 0)
                    $dim_col = $item['base_expr'];
                  
                } else {
                  $a = substr($item['base_expr'], 0, $dot_pos);
                  $c = substr($item['base_expr'], $dot_pos + 1);
                  
                  if($a === $big_table_alias) {
                    $fact_col = $c;
                  } else {
                    $dim_col = $c;
                  }
                  
                }
              }
            }
            break;
        }
      }
      if($dim_col == "")
        die("Assertion failed: dim_col is empty string");
      $filter = preg_replace('/^ and /', '', $filter);
      $dim_sql[$table_alias]['sql'] = "select `$dim_col` from " . $table_aliases[$table_alias]['table'] . " where {$filter}";
      $dim_sql[$table_alias]['fact_ref'] = $big_table_alias . ".`" . $fact_col . "`";
    }
    
    /* 
     * Star optimization scans the dimension tables
     * and provides an IN clause in the where clause to
     * identify matched rows.  If a dimension table is
     * only used for selection (only in the where clause)
     * then it can be eliminated from the FROM clause because
     * the filter/join is satisfied by the new IN clause.
     * 
     * To determine if it is safe to prune a dimension, 
     * it is necessary to scan the SELECT list to
     * determine which dimension tables are used for projection.
     */
    $colrefs = $this->extract_colrefs($parsed['SELECT']);
    $dimensions_used = array();
    foreach($colrefs as $colref) {
      $found_in_dim = "";
      $found_in_fact = false;
      $colref = $colref['base_expr'];
      $dot_pos = strpos($colref, '.');
      if($dot_pos === false) {
        foreach($table_aliases as $key => $table_alias) {
          foreach($table_alias['columns'] as $column) {
            if($column == $colref) {
              if($key === $big_table_alias) {
                $found_in_fact = true;
                break 2;
              }
              $found_in_dim = $key;
              break 2;
            }
          }
        }
      } else {
        $a = substr($item['base_expr'], 0, $dot_pos);
        $c = substr($item['base_expr'], $dot_pos + 1);
        if($a === $big_table_alias) {
          $found_in_fact = true;
        } else {
          $found_in_dim = $a;
        }
      }
      
      if(!$found_in_fact) {
        $dimensions_used[$found_in_dim] = $found_in_dim;
      }
    }
    
    $prune_dimension = array();
    #figure out which dimensions to prune
    foreach($table_aliases as $k => $a) {
      if($k === $big_table_alias)
        continue;
      
      if(!in_array($k, $dimensions_used)) {
        $prune_dimension[$k] = $a['table'];
      }
    }
    
    #construct filters and set them aside to be pushed into WHERE clause later
    foreach($dim_sql as $table_alias => $sql) {
      $inlist = "";
      
      if($table_aliases[$table_alias]['sharded'] === false) {
        $conns = array(
          $conn
        );
      } else {
        $conns = array();
        foreach($this->shards as $shard) {
          $n = SimpleDAL::factory($shard);
          $n->my_select_db($shard['db']);
          $conns[] = $n;
          if(trim($n->my_error()))
            $this->errors[] = $n->my_error();
        }
        if(!empty($this->errors))
          return false;
      }
      
      $i = 0;
      foreach($conns as $the_conn) {
        $the_conn->my_query($sql['sql']);
        $found = 0;
        while($row = $the_conn->my_fetch_array()) {
          if($inlist)
            $inlist .= ",";
          if(!is_numeric($row[0]))
            $row[0] = "'" . $the_conn->my_escape_string($row[0]) . "'";
          $inlist .= $row[0];
          ++$i;
        }
      }
      
      #If the filter didn't match any rows, guarantee a fast fail for the query
      if($inlist === "")
        $inlist = 'NULL';
      $push_where[] = " AND " . $sql['fact_ref'] . " IN ($inlist) ";
      
    }
    
    #prune dimensions
    $unset_next_ref = false;
    
    foreach($prune_dimension as $table_alias => $table_name) {
      /* 
       * If the first table in the join list is being unset
       * then we need to bump the next table to the first
       
       * Note: only tables that have been optimized for selection are eligible for pruning!
       */
      if(!empty($dim_sql[$table_alias])) {
        #if passed check, table is elgible for pruning
        
        #if we are pruning the first table, make the next table the first table with
        #respect to join clause
        if(empty($table_aliases[$table_alias]['ref_type'])) {
          $parsed['FROM'][1]['ref_type'] = "";
          $parsed['FROM'][1]['ref_clause'] = "";
        }
        unset($parsed['FROM'][$table_aliases[$table_alias]['orig_idx']]);
      }
    }
    
    return array(
      'parsed' => $parsed,
      'push_where' => $push_where
    );
  }
  
  #produces a "type 2" filter when the input forces recursion
  #a type 0 is a parsing error or logic violation error ( (a.c1 = 1 or a.c1=2) and b.c1 = 1) <-- not valid for star filter criteria
  protected function star_extract_where(&$where, &$aliases, $start_pos = 0, &$end_pos = 0, $big_table_alias, &$state) {
    $found_op = false;
    $found_colref = false;
    $found_item = false;
    $table_name = "";
    $is_between = false;
    for($end_pos = $start_pos; $end_pos < count($where); ++$end_pos) {
      /* For sub-expressions (a and b) recursion is required to process the sub-expression
      as if it where a stand-alone where clause.
      */
      if($where[$end_pos]['expr_type'] === 'bracket_expression') {
        $new_end = 0;
        $new_start = 0;
        $filters = array();
        
        while($new_end < count($where[$end_pos]['sub_tree'])) {
          $operator = "";
          if(!empty($where[$end_pos]['sub_tree'][$new_end + 1]) && $where[$end_pos]['sub_tree'][$new_end + 1]['expr_type'] === 'operator') {
            $operator = $where[$end_pos]['sub_tree'][$new_end + 1]['base_expr'];
          }
          $filter = $this->star_extract_where($where[$end_pos]['sub_tree'], $aliases, $new_start, $new_end, $big_table_alias, $state);
          
          if($filter !== false) {
            if($filter['filter_type'] === 0)
              return $filter; #propogate error
            
            if($filter['filter_type'] === 2) {
              foreach($filter as $key => $val) {
                if($key === 'filter_type')
                  continue;
                if($operator !== "" && $filters[$key] !== "")
                  $filters[$key] .= " $operator ";
                $filters[$key] .= "( " . $filter[$key] . ' )';
              }
            } else {
              /* star optimization can only use filters on dimension tables */
              if($filter['table_alias'] !== $big_table_alias) {
                $a = $filter['table_alias'];
                if(empty($filters[$a]))
                  $filters[$a] = "";
                if($operator !== "" && $filters[$a] !== "")
                  $filters[$a] .= " $operator ";
                
                $filters[$a] .= "( " . $filter['column_name'] . ' ' . $filter['operator'] . ' ' . $filter['condition'] . ' )';
              }
            }
          }
          $new_start = $new_end + 1;
        }
        
        if(count($filters) > 1) {
          $this->warnings[] = "Expression used more than one dimension table.  This is not supported by the star optimizer.";
          return array(
            'filter_type' => 0
          );
        }
        $filters['filter_type'] = 2;
        
        return ($filters);
        
      }
      
      if($where[$end_pos]['expr_type'] === 'operator') {
        $found_op = $where[$end_pos]['base_expr'];
        if(strtolower($found_op) === "between") {
          $is_between = true;
          $found_op = "between";
        }
        continue;
      }
      
      if($where[$end_pos]['expr_type'] === 'colref') {
        $column_name = $where[$end_pos]['base_expr'];
        $table_name = false;
        
        if(strpos($column_name, '.') !== false) {
          $table_name = substr($column_name, 0, strpos($column_name, '.') - 1);
          $column_name = strstr($column_name, '.');
        }
        $column_name = ltrim($column_name, '.');
        
        if($table_name == false) {
          
          foreach($aliases as $key => $alias) {
            if(in_array($column_name, $alias['columns'])) {
              $table_name = $key;
              break;
            }
            
          }
        }
        if($table_name === "") {
          $this->errors[] = "Could not find a used column:" . $where[$end_pos]['base_expr'] . " in any tables!\n";
          return false;
        }
        
        /*
         * this is necessary for possible join reduction
         * also optimizes away extra redundant filter work 
         * from the SQL layer later
         */
        if($table_name != $big_table_alias) {
          $where[$end_pos]['base_expr'] = '1';
          $where[$end_pos]['expr_type'] = 'const';
        }
        
      } elseif($where[$end_pos]['expr_type'] === 'const' || $where[$end_pos]['expr_type'] === "in-list") {
        if($is_between) {
          if($found_op === "between") {
            $save_const = $where[$end_pos]['base_expr'];
          } else {
            $found_item = $where[$end_pos];
            $found_item['base_expr'] = $save_const . " and " . $found_item['base_expr'];
            $save_const = "";
            $found_colref = true;
            $found_op = "between";
          }
        } else {
          $found_colref = true;
          $found_item = $where[$end_pos];
        }
        
        if($table_name == "") {
          $this->warnings[] = "For star optimization, expressions must be in form of `alias`.`column` = CONSTANT";
          
          return array(
            'filter_type' => 0
          );
        }
        
        #other half of the join reduction
        #if($table_name !== $big_table_alias) {
        if($where[$end_pos]['expr_type'] === "in-list") {
          $where[$end_pos]['expr_type'] = 'const';
          $where[$end_pos]['base_expr'] = '(1)';
        } elseif($where[$end_pos]['expr_type'] === 'const') {
          $where[$end_pos]['base_expr'] = '1';
        }
        #}
      }
      
      
      if($found_op && $found_item && $found_colref) {
        $found_item = $found_item['base_expr'];
        return array(
          'filter_type' => 1,
          'table_alias' => $table_name,
          'column_name' => $column_name,
          'operator' => $found_op,
          'condition' => $found_item
        );
      }
    }
    return false;
  }
  
  protected function extract_colrefs($tree) {
    $colrefs = array();
    foreach($tree as $item) {
      if(!empty($item['sub_tree'])) {
        $colrefs = array_merge($colrefs, $this->extract_colrefs($item['sub_tree']));
      }
      if($item['expr_type'] === 'colref') {
        $colrefs[] = $item;
      }
    }
    return $colrefs;
  }
  
  protected function unquote_colrefs(&$tree) {
    $colrefs = array();
    foreach($tree as $key => $item) {
      if(!empty($item['sub_tree'])) {
        $this->unquote_colrefs($item['sub_tree']);
        continue;
      }
      
      if(!empty($item[0])) {
        $this->unquote_colrefs($item);
        continue;
      }
      
      if(empty($item['expr_type']) || $item['expr_type'] === 'colref') {
        $b = $item['base_expr'];
        $b = trim($b, '`');
        $b = str_replace('`.`', '.', $b);
        $tree[$key]['base_expr'] = $b;
      }
    }
  }

  protected function check_error(&$dal) {
    $error = $dal->my_error();
    if($error) {
      $this->errors[] = $error;
      return true;
    }
 
    return false;
  }

  protected function get_insert_cols(&$state, $table_name = false) {
    $parsed = $state->parsed;
    if($table_name) $force = true; else $force=false;
    if(!$table_name) {
      if(!empty($parsed['INSERT'])) {
         trim($table_name = $parsed['INSERT'][1]['table'],'`');
      } else {
         trim($table_name = $parsed['REPLACE'][1]['table'],'`');
      }
    } 
    if($force || ( empty($parsed['INSERT'][2]) && empty($parsed['REPLACE'][2]) ) ) {
      if(empty($this->col_metadata_cache[$table_name])) {
        $sql = "select column_name 
                          from information_schema.columns 
                         where table_name='{$table_name}'
                           and table_schema='{$this->tmp_shard['db']}' 
                             order by ordinal_position;";
        
        if(!$stmt = $state->DAL->my_query($sql)) {
          $this->errors[] = $state->DAL->my_error();
        }
        
        $cols = array();
        $col_list = "";
        while($row = $state->DAL->my_fetch_assoc($stmt)) {
          $this->check_error($state->DAL);
          if($col_list)
            $col_list .= ",";
          $col_list .= $row['column_name'];
          $cols[] = $row['column_name'];
        }
        $col_list = "($col_list)";
        if(count($cols) == 0) {
          $this->errors[] = 'Table not found in data dictionary';
          return false;
        }
        $this->col_metadata_cache[$table_name] = array(
          'cols' => $cols,
          'col_list' => $col_list
        );
      } else {
        $cols = $this->col_metadata_cache[$table_name]['cols'];
        $col_list = $this->col_metadata_cache[$table_name]['col_list'];
      }
      
    } else {
      if(!empty($parsed['INSERT'])) $col_list = $parsed['INSERT']['2']['base_expr']; else $col_list = $parsed['REPLACE']['2']['base_expr'];
      $out = array();
      $cols = explode(",", trim($col_list,' )('));
    }

    return array($cols, $col_list);
  }

  /* load using a table on a shard as the source */
  protected function load_from_table(&$state, $to_table,  $columns_str, $shard_col_pos=false, $ignore="", $replace="") {
    $dal = &$state->dal;

    $from_table = "`" . trim($state->table_name,'`') . "`";
    $to_table = "`" . trim($to_table, '`') . "`";
    $sql = "select * from $from_table";

    $dal->my_select_db($state->tmp_shard['db']);
    $this->check_error($dal);
    $state->DAL->my_query($sql);
    $insert_sql = "INSERT INTO $to_table (" . trim($columns_str,'()') . ")  VALUES ";
    $values = "";

    if ($shard_col_pos===false) { /* load goes to all shards */
      foreach ($this->shards as $shard_name => $shard) {
        $stmt = $dal->my_query($sql);
        $dal2 = SimpleDAL::factory($shard);
        if($this->check_error($dal2)) return false;
        $dal2->my_query("select @@max_allowed_packet as map");
        if($this->check_error($dal2)) return false;
        $row = $dal2->my_fetch_assoc();
        $max_allowed_packet = $row['map'] - 16384;
        $row = false;
        $dal2->my_select_db($shard['db']);
        if($this->check_error($dal2)) return false;
        while ($row = $dal->my_fetch_assoc($stmt)) {
          if(strlen($values) >= $max_allowed_packet) {
            $dal2->my_query($insert_sql . $values);
            if($this->check_error($dal2)) return false;
            $values = "";
          }  
          if($values) $values .= ",";
          $rowvals = ""; 
          foreach($row as $key => $val) {
            if(!is_numeric($val)) $val = "'" . $dal2->my_real_escape_string($val) . "'";
            if($rowvals) $rowvals .= ",";
            $rowvals .= $val;
          }
          $values .= "($rowvals)";
        } 
        if($values != "") {
          $dal2->my_query($insert_sql . $values);
          if($this->check_error($dal2)) return false;
        }
      }
      return true;
    } else { /* load goes to specific shards */
      $stmt = $dal->my_query($sql);
      if($this->check_error($dal)) return false;
      $out_data = array(); // buffer the data for each shard in here
      while ($row = $dal->my_fetch_assoc($stmt)) {
        $row = array_values($row);
        $info = $this->map_shard($this->shard_column, $row[$shard_col_pos], $this->state->current_schema, '=', true);
      
        if (!$info) {
          $err      = "Discarded row because the partition mapper did not return a valid result.\n";
          $errors[] = array(
            'error' => $err,
            'file_pos' => $line_start
          );
          continue;
        }
      
        if (count($info) > 1) {
          $err      = "Discarded row because the partition mapper returned more than one shard for this row.\n";
          $errors[] = array(
            'error' => $err,
            'file_pos' => $line_start
          );
          continue;
        }
      
        $shard_name = @array_pop(array_keys(array_pop($info)));
      
        if (empty($out_data[$shard_name]))
        $out_data[$shard_name] = array();
        $line = "";
        foreach($row as $key => $val) {
          $rowvals = "";
          foreach($row as $key => $val) {
            if(!is_numeric($val)) $val = "'" . $dal2->my_real_escape_string($val) . "'";
            if($rowvals) $rowvals .= ",";
            $rowvals .= $val;
          }
          $line = "($rowvals)";
        }
        $out_data[$shard_name][] = $line;
      }
     
      $dal2 = null; 
      foreach ($out_data as $shard_name => $lines) {
        if(isset($dal2)) $dal2->my_close();

        $dal2 = SimpleDAL::factory($this->shards[$shard_name]);
        if($this->check_error($dal2)) return false;

        $dal2->my_select_db($this->shards[$shard_name]['db']);
        if($this->check_error($dal2)) return false;

        $dal2->my_query("select @@max_allowed_packet as map");
        if($this->check_error($dal2)) return false;

        $row = $dal2->my_fetch_assoc();
        if($this->check_error($dal2)) return false;
        $max_allowed_packet = $row['map'] - 16384;
        $row = false;
        $values = "";

        foreach($lines as $line) {

          if(strlen($values) >= $max_allowed_packet) {
            $dal->my_query($insert_sql . $values);
            if($this->check_error($dal)) return false;
            $values = "";
          }  

          if($values) $values .= ",";
          $values .= $line;

        }

        if($values != "") {
          $dal2->my_query($insert_sql . $values);
          if($this->check_error($dal2)) return false;
        }

      }
 
      if (!empty($errors)) {
        $this->errors = $errors;
        return false;
      }
    
      /*
      ALL OK
      */
      return true;
    } 
  }

  protected function process_group_item(&$item, &$map,$depth=0,&$select) {
    if($depth===0) $out = "";
    if(empty($item['expr_type'])) {
      foreach($item as $sub_item) {
        if(!isset($out)) $out = "";
        $out .= $this->process_group_item($sub_item, $map, $depth+1,$select);
      }
      return $out;
    }

    if($item['expr_type'] == 'colref') {
        if(!isset($out)) $out = "";
        $base_expr = $item['base_expr'];
        if(isset($map[trim($item['base_expr'])])) {
          $out .= "expr$" . $map[$item['base_expr']];
        } else {
          $out .= "expr$" . count($map);
          $select['shard_sql'] .= "," . $item['base_expr'] . " expr$" . count($map);
          if($select['group_aliases'] != "") $select['group_aliases'] .= ",";
          $select['group_aliases'] .= "expr$" . count($map);
          $map[$item['base_expr']] = count($map);
        }
    } else {
        $out .= $item['base_expr'];
        if($item['expr_type'] == 'function' || $item['expr_type'] == 'aggregregate_function') { 
          $out .= "("; 
        }
    }
    if(!isset($out)) $out = "";
    if(!empty($item['sub_tree'])) $out .= $this->process_group_item($item['sub_tree'], $map, $depth + 1);
    if($item['expr_type'] == 'function' || $item['expr_type'] == 'aggregregate_function') $out .= ")"; 
    return $out;
  }

  protected function process_shard_group_item(&$item, &$map,$depth=0) {
    if(empty($item['expr_type'])) {
      foreach($item as $sub_item) {
        if(!isset($out)) $out = "";
        $out .= $this->process_shard_group_item($sub_item, $map, $depth+1);
      }
      return $out;
    }

    if($item['expr_type'] == 'colref') {
        if(!isset($out)) $out = "";
        $out .= "expr$" . $map[$item['base_expr']];
    } 
    if(!isset($out))$out = "";
    if(!empty($item['sub_tree'])) $out .= $this->process_shard_group_item($item['sub_tree'], $map, $depth + 1);
    return $out;
  }

  protected function wf_sum($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    if($win['order_by'] == "") { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        $colref = "wf{$num}";
        $sql = "select sum($colref) as s from (select " . $state->winfunc_sql . " WHERE wf{$num}_hash = '{$row['h']}') sq";
        $stmt2 = $state->DAL->my_query($sql);
        $row2 = $state->DAL->my_fetch_assoc($stmt2);
        $sum = $row2['s'];
        $sql = "UPDATE " . $state->table_name . " SET wf$num = $sum WHERE wf{$num}_hash = '{$row['h']}'"; 
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
      return true;
    } else { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) { /* loop over each partition */

        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
        $partition_rows = $this->get_all_rows($sql, $state);
        if(!$partition_rows) return false;

        $colref = "wf{$num}";

        for($i=0;$i<count($partition_rows);++$i) {
          $row3 = $partition_rows[$i];
          $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");

          if($this->all_null($frame)) { // will also return true on empty set
            $sum = "NULL"; 
          } else { 
            $sum = array_sum($frame);
          }

          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$sum} WHERE wf_rownum = {$row3['wf_rownum']}";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
        }
      }
    }
    return true;
  }

  protected function wf_avg($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    if($win['order_by'] == "") { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        $colref = "wf{$num}";
        #$sql = "select avg($colref) a from " . $state->table_name . " WHERE wf{$num}_hash = '{$row['h']}'";
        $sql = "select avg($colref) as a from (select " . $state->winfunc_sql . " WHERE wf{$num}_hash = '{$row['h']}') sq";
        $stmt2 = $state->DAL->my_query($sql);
        $row2 = $state->DAL->my_fetch_assoc($stmt2);
        $avg = $row2['a'];
        $sql = "UPDATE " . $state->table_name . " SET wf$num = $avg WHERE wf{$num}_hash = '{$row['h']}'"; 
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
      return true;
    } else { 
      /* moving avg*/
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        #$sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
        $colref = "wf{$num}";
        
        $partition_rows = $this->get_all_rows($sql, $state);
        if(!$partition_rows) return false;

        for($i=0;$i<count($partition_rows);++$i) {
          $row3 = $partition_rows[$i];
          $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");

          if($this->all_null($frame)) { // will also return true on empty set
            $avg = "NULL";
          } else {
            $avg = array_sum($frame)/$this->count2($frame);
          }

          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$avg} WHERE wf_rownum = {$row3['wf_rownum']}";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
        }
      }
    }
    return true;
  }

  protected function wf_min($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    if($win['order_by'] == "") { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        $colref = "wf{$num}";
        #$sql = "select min($colref) m from " . $state->table_name . " WHERE wf{$num}_hash = '{$row['h']}'";
        $sql = "select min($colref) as m from (select " . $state->winfunc_sql . " WHERE wf{$num}_hash = '{$row['h']}') sq";
        $stmt2 = $state->DAL->my_query($sql);
        $row2 = $state->DAL->my_fetch_assoc($stmt2);
        $min = $row2['m'];
        $sql = "UPDATE " . $state->table_name . " SET wf$num = $min WHERE wf{$num}_hash = '{$row['h']}'"; 
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
      return true;
    } else { 
      /* moving min*/
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      $last_hash = "";
      $hash = "";
      $last_ob_hash = "";
      $ob_hash = "";
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        #$sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
        
        $partition_rows = $this->get_all_rows($sql, $state);
        if(!$partition_rows) return false;

        $colref = "wf{$num}";

        for($i=0;$i<count($partition_rows);++$i) {
          $row3 = $partition_rows[$i];
          $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");

          if($this->all_null($frame)) { // will also return true on empty set
            $min = "NULL";
          } else {
            $min = min($frame);
          }

          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$min} WHERE wf_rownum = {$row3['wf_rownum']}";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
        }
      }
    }
    return true;
  }

  protected function wf_max($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    if($win['order_by'] == "") { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        $colref = "wf{$num}";
        #$sql = "select max($colref) m from " . $state->table_name . " WHERE wf{$num}_hash = '{$row['h']}'";
        $sql = "select max($colref) as m from (select " . $state->winfunc_sql . " WHERE wf{$num}_hash = '{$row['h']}') sq";
        $stmt2 = $state->DAL->my_query($sql);
        $row2 = $state->DAL->my_fetch_assoc($stmt2);
        $max = $row2['m'];
        $sql = "UPDATE " . $state->table_name . " SET wf$num = $max WHERE wf{$num}_hash = '{$row['h']}'"; 
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
      return true;
    } else { 
      /* moving max*/
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        #$sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
        $colref = "wf{$num}";
        
        $partition_rows = $this->get_all_rows($sql, $state);
        if(!$partition_rows) return false;

        for($i=0;$i<count($partition_rows);++$i) {
          $row3 = $partition_rows[$i];
          $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");

          if($this->all_null($frame)) { // will also return true on empty set
            $max = "NULL";
          } else {
            $max = max($frame);
          }

          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$max} WHERE wf_rownum = {$row3['wf_rownum']}";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
        }
      }
    }
    return true;
  }

  protected function wf_count($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    if($win['order_by'] == "") { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        $colref = "wf{$num}";
        #$sql = "select count($colref) c from " . $state->table_name . " WHERE wf{$num}_hash = '{$row['h']}'";
        $sql = "select count($colref) as c from (select " . $state->winfunc_sql . " WHERE wf{$num}_hash = '{$row['h']}') sq";
        $stmt2 = $state->DAL->my_query($sql);
        $row2 = $state->DAL->my_fetch_assoc($stmt2);
        $cnt = $row2['c'];
        $sql = "UPDATE " . $state->table_name . " SET wf$num = $cnt WHERE wf{$num}_hash = '{$row['h']}'"; 
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
      return true;
    } else { 
      /* moving max*/
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      $last_ob_hash = "";
      $ob_hash = "";
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        #$sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
        
        $partition_rows = $this->get_all_rows($sql, $state);
        if(!$partition_rows) return false;

        $colref = "wf{$num}";

        for($i=0;$i<count($partition_rows);++$i) {
          $row3 = $partition_rows[$i];
          $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");

          if($this->all_null($frame)) { // will also return true on empty set
            $cnt = 0;
          } else {
            $cnt = $this->count2($frame);
          }

          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$cnt} WHERE wf_rownum = {$row3['wf_rownum']}";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
        }
      }
    }
    return true;
  }

  protected function wf_std($num,$state, $samp = true) {
    static $sum;
    $win = $state->windows[$num];
    if($win['order_by'] == "") { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        $colref = "wf{$num}";
        $sql = "select ";
        if($samp) $sql .= "stddev_samp"; else $sql .= "stddev_pop";
        #$sql .= "($colref) s from " . $state->table_name . " WHERE wf{$num}_hash = '{$row['h']}'";
        $sql .= "($colref) as s from (select " . $state->winfunc_sql . " WHERE wf{$num}_hash = '{$row['h']}') sq";
        $stmt2 = $state->DAL->my_query($sql);
        $row2 = $state->DAL->my_fetch_assoc($stmt2);
        $std = $row2['s'];
        $sql = "UPDATE " . $state->table_name . " SET wf$num = $std WHERE wf{$num}_hash = '{$row['h']}'"; 
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
      return true;
    } else { 
      /* running sum*/
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      $last_ob_hash = "";
      $ob_hash = "";
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        #$sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
        $colref = "wf{$num}";
        
        $partition_rows = $this->get_all_rows($sql, $state);
        if(!$partition_rows) return false;

        $colref = "wf{$num}";

        for($i=0;$i<count($partition_rows);++$i) {
          $row3 = $partition_rows[$i];
          $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");

          if($this->all_null($frame)) { // will also return true on empty set
            $std = 0;
          } else {
            $std = $this->standard_deviation($frame,$samp);
          }

          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$std} WHERE wf_rownum = {$row3['wf_rownum']}";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
        }
      }
    }
    return true;
  }

  protected function wf_var($num,$state, $samp = true) {
    static $sum;
    $win = $state->windows[$num];
    if($win['order_by'] == "") { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        $colref = "wf{$num}";
        $sql = "select ";
        if($samp) $sql .= "var_samp"; else $sql .= "var";
        #$sql .= "($colref) s from " . $state->table_name . " WHERE wf{$num}_hash = '{$row['h']}'";
        $sql .= "($colref) as s from (select " . $state->winfunc_sql . " WHERE wf{$num}_hash = '{$row['h']}') sq";
        $stmt2 = $state->DAL->my_query($sql);
        $row2 = $state->DAL->my_fetch_assoc($stmt2);
        $var = $row2['s'];
        $sql = "UPDATE " . $state->table_name . " SET wf$num = $var WHERE wf{$num}_hash = '{$row['h']}'"; 
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
      return true;
    } else { 
      /* running sum*/
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      $last_ob_hash = "";
      $ob_hash = "";
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        #$sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
        
        $partition_rows = $this->get_all_rows($sql, $state);
        if(!$partition_rows) return false;

        $colref = "wf{$num}";

        for($i=0;$i<count($partition_rows);++$i) {
          $row3 = $partition_rows[$i];
          $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");

          if($this->all_null($frame)) { // will also return true on empty set
            $var = 0;
          } else {
            $var = $this->variance($frame,$samp);
          }
          if(!$var) $var = 'NULL';

          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$var} WHERE wf_rownum = {$row3['wf_rownum']}";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
        }
      }
    }
    return true;
  }

  protected function wf_rank($num,$state,$dense=false,$percent=false) {
    static $sum;
    $win = $state->windows[$num];
    if(empty($win['order'])) {
      if($percent)
        $sql = "update " . $state->table_name . " set wf{$num}=1";
      else
        $sql = "update " . $state->table_name . " set wf{$num}=0";
         
      $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      return true;
    } else { 
      /* running sum*/
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      $last_hash = "";
      $hash = "";
      $last_ob_hash = "";
      $ob_hash = "";
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $stmt2 = $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
        $done=array();
        $rows=array();
        while($row2=$state->DAL->my_fetch_assoc($stmt2)) {
          $rows[] = $row2;
        }
        $last_hash = "";
        $last_ob_hash = "";
        $i = 0;
        $rowlist="";
        $rank = 0;

        while($i<count($rows)) {
          $row2 = $rows[$i];
          ++$rank;
          $push_rank = 0;
          $ob_hash = $row2["wf{$num}_obhash"];
          $rowlist=$row2['wf_rownum'];
          for($n=$i+1;$n<count($rows);++$n) {
            $row3 = $rows[$n];
            $new_ob_hash = $row3["wf{$num}_obhash"];
            if($new_ob_hash != $ob_hash) {
              break;
            }
            if(!$dense) $push_rank++;
            $rowlist .= "," . $row3['wf_rownum'];
            ++$i;
          }
          if($percent) { 
            $pct = ($rank-1)/(count($rows)-1);
            $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$pct} WHERE wf_rownum in ({$rowlist})";
          } else {
            $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$rank} WHERE wf_rownum in ({$rowlist})";
          }
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
          $rank += $push_rank;
          ++$i;
        }
      }
    }
    return true;
  }

  protected function wf_cume_dist($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    if(empty($win['order'])) {
      if($percent)
        $sql = "update " . $state->table_name . " set wf{$num}=1";
      else
        $sql = "update " . $state->table_name . " set wf{$num}=0";
         
      $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      return true;
    } else { 
      /* running sum*/
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      $last_hash = "";
      $hash = "";
      $last_ob_hash = "";
      $ob_hash = "";
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $stmt2 = $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
        $done=array();
        $rows=array();
        while($row2=$state->DAL->my_fetch_assoc($stmt2)) {
          $rows[] = $row2;
        }
        $last_hash = "";
        $last_ob_hash = "";
        $i = 0;
        $rowlist="";
        $rank = 0;

        while($i<count($rows)) {
          $row2 = $rows[$i];
          ++$rank;
          $ob_hash = $row2["wf{$num}_obhash"];
          $rowlist=$row2['wf_rownum'];
          for($n=$i+1;$n<count($rows);++$n) {
            $row3 = $rows[$n];
            $new_ob_hash = $row3["wf{$num}_obhash"];
            if($new_ob_hash != $ob_hash) {
              break;
            }
            $rowlist .= "," . $row3['wf_rownum'];
            ++$i;
            ++$rank;
          }
          $dist = $rank/count($rows);
          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$dist} WHERE wf_rownum in ({$rowlist})";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
          ++$i;
        }
      }
    }
    return true;
  }

  protected function wf_ntile($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
    $stmt = $state->DAL->my_query($sql);
    if($err = $state->DAL->my_error()) {
      $this->errors[] = $err;
      return false;
    }
    while($row = $state->DAL->my_fetch_assoc($stmt)) {
      $sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "'";
      if(!empty($win['order_by'])) $sql .= ' ORDER BY ' . $win['order_by'];
      $stmt2 = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
      $this->errors[] = $err;
        return false;
      }
      $rows=array();
      while($row2=$state->DAL->my_fetch_assoc($stmt2)) {
        $rows[] = $row2;
      }
      $i = 0;
      $buckets = $win['func']['sub_tree'][0]['base_expr'];
      $per_bucket=ceil(count($rows) * (1/$buckets)); 
      if($buckets > count($rows)) $per_bucket=1; 
      $bucket = 1;
      $cnt = 0;
      while($i<count($rows)) {
        $row2 = $rows[$i];
        $rownum=$row2['wf_rownum'];
        if($cnt >= $per_bucket) { 
          ++$bucket; 
          $cnt = 0; 
        }
        $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$bucket} WHERE wf_rownum = {$rownum}";
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
        ++$i;
        ++$cnt;
      }
    }
    return true;
  }

  protected function wf_rownum($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    if(empty($win['order']) && empty($win['partition'])) {
      $sql = "update " . $state->table_name . " set wf{$num}=wf_rownum";
      $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      return true;
    } else { 
      $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name . " ORDER BY " . $win['order_by']; 
      $stmt = $state->DAL->my_query($sql);
      if($err = $state->DAL->my_error()) {
        $this->errors[] = $err;
        return false;
      }
      $last_hash = "";
      $hash = "";
      $last_ob_hash = "";
      $ob_hash = "";
      while($row = $state->DAL->my_fetch_assoc($stmt)) {
        #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "' ORDER BY " . $win['order_by'];
        $stmt2 = $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
        $done=array();
        $rows=array();
        while($row2=$state->DAL->my_fetch_assoc($stmt2)) {
          $rows[] = $row2;
        }
        $last_hash = "";
        $last_ob_hash = "";
        $i = 0;
        $rowlist="";
        $rownum = 0;
        while($i<count($rows)) {
          ++$rownum;
          $row2 = $rows[$i];
          $rowlist=$row2['wf_rownum'];
          $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$rownum} WHERE wf_rownum = $rowlist";
          $state->DAL->my_query($sql);
          if($err = $state->DAL->my_error()) {
            $this->errors[] = $err;
            return false;
          }
          ++$i;
        }
      }
    }
    return true;
  }


  protected function wf_first_value($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
    $stmt = $state->DAL->my_query($sql);
    if($err = $state->DAL->my_error()) {
      $this->errors[] = $err;
      return false;
    }
    while($row = $state->DAL->my_fetch_assoc($stmt)) { /* loop over each partition */
      #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "'";
      $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
      if(!empty($win['order_by'])) $sql .= " ORDER BY " . $win['order_by'];
      $partition_rows = $this->get_all_rows($sql, $state);
      if(!$partition_rows) return false;

      $colref = "wf{$num}";

      for($i=0;$i<count($partition_rows);++$i) {
        $row3 = $partition_rows[$i];
        $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");
        $first = $frame[0];
        if(!is_numeric($first)) $first=0;
        $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$first} WHERE wf_rownum = {$row3['wf_rownum']}";
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
    }
    return true;
  }

  protected function wf_last_value($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
    $stmt = $state->DAL->my_query($sql);
    if($err = $state->DAL->my_error()) {
      $this->errors[] = $err;
      return false;
    }
    while($row = $state->DAL->my_fetch_assoc($stmt)) { /* loop over each partition */
      #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "'";
      $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group . " ORDER BY " . $win['order_by'];
      if(!empty($win['order_by'])) $sql .= " ORDER BY " . $win['order_by'];
      $partition_rows = $this->get_all_rows($sql, $state);
      if(!$partition_rows) return false;

      $colref = "wf{$num}";

      for($i=0;$i<count($partition_rows);++$i) {
        $row3 = $partition_rows[$i];
        $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");
        $last = $frame[count($frame)-1];
        if(!is_numeric($last)) $last=0;
        $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$last} WHERE wf_rownum = {$row3['wf_rownum']}";
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
    }
    return true;
  }

  protected function wf_nth_value($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    $from_first = $win['from_first'];
    $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
    $stmt = $state->DAL->my_query($sql);
    if($err = $state->DAL->my_error()) {
      $this->errors[] = $err;
      return false;
    }
    while($row = $state->DAL->my_fetch_assoc($stmt)) { /* loop over each partition */
      #$sql = "select *, " . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'";
      $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group;
      if($win['order_by'] != "") $sql .= " ORDER BY " . $win['order_by'];
      $partition_rows = $this->get_all_rows($sql, $state);
      if(!$partition_rows) return false;

      $colref = "wf{$num}";
      $n = $win['func']['sub_tree'][1]['base_expr'];

      for($i=0;$i<count($partition_rows);++$i) {
        $row3 = $partition_rows[$i];
        $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash");
        if($n > count($partition_rows || $n < 0)) {
          $last = null;
        } else {
          if($from_first) {
            $last = $frame[$n-1];
          } else {
            $last = $frame[count($frame)-$n-1];
          }
        }
        if(!is_numeric($last)) $last=0;
        $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$last} WHERE wf_rownum = {$row3['wf_rownum']}";
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
    }
    return true;
  }
  protected function wf_lead($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
    $stmt = $state->DAL->my_query($sql);
    if($err = $state->DAL->my_error()) {
      $this->errors[] = $err;
      return false;
    }
    while($row = $state->DAL->my_fetch_assoc($stmt)) { /* loop over each partition */
      #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "'";
      $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group;
      if(!empty($win['order_by'])) $sql .= " ORDER BY " . $win['order_by'];
      $partition_rows = $this->get_all_rows($sql, $state);
      if(!$partition_rows) return false;

      $colref = "wf{$num}";
      if(empty($win['func']['sub_tree'][1])) {
        $offset = 1;
      } else {
        $offset = $win['func']['sub_tree'][1]['base_expr'];
      }
      if(empty($win['func']['sub_tree'][2])) {
        $default = null;
      } else {
        $default = $win['func']['sub_tree'][2]['base_expr'];
      }

      for($i=0;$i<count($partition_rows);++$i) {
        $row3 = $partition_rows[$i];
        $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash",$default);
        $lead = $i + $offset;
        if($lead > count($partition_rows) || $lead < 0) {
          $lead = $default;
        } else {
          if($lead>(count($frame)-1)) $lead = $default; else $lead = $frame[$lead];
        }
        if(!is_numeric($lead)) $lead="'" . $state->DAL->my_real_escape_string($lead) . "'";
        $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$lead} WHERE wf_rownum = {$row3['wf_rownum']}";
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
    }
    return true;
  }


  protected function wf_lag($num,$state) {
    static $sum;
    $win = $state->windows[$num];
    $sql = "SELECT distinct wf{$num}_hash h from " . $state->table_name;
    $stmt = $state->DAL->my_query($sql);
    if($err = $state->DAL->my_error()) {
      $this->errors[] = $err;
      return false;
    }
    while($row = $state->DAL->my_fetch_assoc($stmt)) { /* loop over each partition */
      #$sql = "select * from " . $state->table_name . " where wf{$num}_hash='" . $row['h'] . "'";
      $sql = "SELECT *," . $state->winfunc_sql . " where wf{$num}_hash='" . $row['h'] . "'" . $state->winfunc_group;
      if(!empty($win['order_by'])) $sql .= " ORDER BY " . $win['order_by'];
      $partition_rows = $this->get_all_rows($sql, $state);
      if(!$partition_rows) return false;

      $colref = "wf{$num}";
      if(empty($win['func']['sub_tree'][1])) {
        $offset = 1;
      } else {
        $offset = $win['func']['sub_tree'][1]['base_expr'];
      }
      if(empty($win['func']['sub_tree'][2])) {
        $default = null;
      } else {
        $default = $win['func']['sub_tree'][2]['base_expr'];
      }

      for($i=0;$i<count($partition_rows);++$i) {
        $row3 = $partition_rows[$i];
        $frame = $this->frame_window($partition_rows, $win, $i, $colref, "wf{$num}_obhash",$default);
        $lag = $i - $offset;
        if($lag > count($partition_rows || $lag < 0)) {
          $lag = $default;
        } else {
          $lag = $frame[$lag];
        }
        if(!is_numeric($lag)) $lag=0;
        $sql = "UPDATE " . $state->table_name . " SET wf{$num} = {$lag} WHERE wf_rownum = {$row3['wf_rownum']}";
        $state->DAL->my_query($sql);
        if($err = $state->DAL->my_error()) {
          $this->errors[] = $err;
          return false;
        }
      }
    }
    return true;
  }

  function standard_deviation($aValues, $bSample = false) {
      $fMean = array_sum($aValues) / count($aValues);
      $fVariance = 0.0;
      foreach ($aValues as $i)
      {
          $fVariance += pow($i - $fMean, 2);
      }
      $fVariance /= ( $bSample ? count($aValues) - 1 : count($aValues) );
      return (float) sqrt($fVariance);
  }

  function variance($aValues, $bSample = false) {
      
      $fMean = array_sum($aValues) / count($aValues);
      $fVariance = 0.0;
      foreach ($aValues as $i)
      {
          $fVariance += pow($i - $fMean, 2);
      }
      $cnt = count($aValues);
      if($bSample) $cnt -=1;
      if($cnt <=0) return false;
      $fVariance /= ( $bSample ? count($aValues) - 1 : count($aValues) );
      return (float) $fVariance;
  }

  /* This function calculates the 'frame' for a window */
  protected function &frame_window(&$rows,$win, $cur=0,$key = "wf_rownum", $ob_key="",$default=null) {
    $key = ltrim($key,",");
    $start = $win['start'];
    $end = $win['end'];
    $mode = $win['mode'];
    $peers = true;

    if($start === 0) { // 0 is current_row
      $start = $cur;
    } elseif($start === false) { //unbounded preceeding
      $start =0;
    } else {
      $start = $cur + $start; // positive if "value following" or negative for "value preceeding" 
    }
    
    if($end === false) {
      $end = count($rows); // unbounded following
    } elseif($end === 0) {
      $end = $cur;
    } else {
      $end = $cur + $end; // positive for "value following", negative for "value preceeding"
    }

    if($mode == 'ROWS') $peers = false;
    $vals = array();
    /* The frame can extend from before the resultset or past the end of it, but the 
       values are NULL when that happens.  
    */ 
    if($start < 0) {
      $rows_to_add = abs($start);
      for($i=1;$i<=$rows_to_add;$i++) {
        $rows[-1*$i][$key] = $default;  
      }
    }

    if($end < 0) {
      $rows_to_add = abs($end);
      for($i=1;$i<=$rows_to_add;$i++) {
        $rows[count($rows)+($i-1)][$key] = $default;  
      }
    }

    if($start > count($rows)) {
      $rows_to_add = $start - count($rows);
      for($i=1;$i<=$rows_to_add;$i++) {
        $rows[count($rows)+($i-1)][$key] = $default;  
      }
    }

    if($end> count($rows)) {
      $rows_to_add = $end - count($rows);
      for($i=1;$i<=$rows_to_add;$i++) {
        $rows[count($rows)+($i-1)][$key] = $default;  
      }
    }
    
    $i = $start;
    
    while($i<count($rows)) {

      $row = $rows[$i];
      $val = $row[$key];
      if(!empty($row[$ob_key])) $sort = $row[$ob_key];
      $vals[] = $val;
      if($i == $end && !$peers) break;
      if($i == $end && !empty($row[$ob_key])) {
        for($n=$i+1;$n<count($rows);++$n) { // continue through peers
          $row2 = $rows[$n];
          $val2 = $row2[$key];
          $sort2 = $row2[$ob_key];
          if($sort != $sort2) break 2;
          $vals[] = $val; 
          ++$i;
        }
      }
      ++$i; 
    }

    return $vals;
  }

  protected function &get_all_rows($sql, &$state) {
    $err = false;
    $stmt = $state->DAL->my_query($sql);
    if($err = $state->DAL->my_error()) {
      $this->errors[] = $err;
      return $err;
    }
    $rows = array(); 
    while($row = $state->DAL->my_fetch_assoc($stmt)) {
      $rows[] = $row;
    }
    return $rows;
  }

  protected function run_window_functions(&$state) {
    $DB = &$state->DAL;
    foreach($state->windows as $num => $win) {
      if(@strtoupper($win['func']['sub_tree'][0]['sub_tree'][0]['base_expr']) == "DISTINCT") {
        $this->errors[] = "DISTINCT not implemented for window functions";
        return false;
      } 
      switch(strtoupper($win['func']['base_expr'])) {
        case 'RANK':
          if(!$this->wf_rank($num, $state)) return false;    
        break;
        case 'DENSE_RANK':
          if(!$this->wf_rank($num, $state,true)) return false;    
        break;
        case 'PERCENT_RANK':
          if(!$this->wf_rank($num, $state,false, true)) return false;    
        break;
        case 'CUME_DIST':
          if(!$this->wf_cume_dist($num, $state)) return false;    
        break;
        case 'NTILE':
          if(!$this->wf_ntile($num, $state)) return false;    
        break;
        case 'LAST_VALUE':
          if(!$this->wf_last_value($num, $state)) return false;    
        break;
        case 'FIRST_VALUE':
          if(!$this->wf_first_value($num, $state)) return false;    
        break;
        case 'NTH_VALUE':
          if(!$this->wf_nth_value($num, $state)) return false;    
        break;
        case 'LEAD':
          if(!$this->wf_lead($num, $state)) return false;    
        break;
        case 'LAG':
          if(!$this->wf_lag($num, $state)) return false;    
        break;
        case 'ROW_NUMBER':
          if(!$this->wf_rownum($num, $state)) return false;    
        break;
        case 'SUM':
          if(!$this->wf_sum($num, $state)) return false;    
        break;
        case 'AVG':
          if(!$this->wf_avg($num, $state)) return false;    
        break;
        case 'MIN':
          if(!$this->wf_min($num, $state)) return false;    
        break;
        case 'MAX':
          if(!$this->wf_max($num, $state)) return false;    
        break;
        case 'COUNT':
          if(!$this->wf_count($num, $state)) return false;    
        break;
        case 'STDDEV':
        case 'STDDEV_SAMP':
        case 'STD':
        case 'STD_SAMP':
          if(!$this->wf_std($num, $state,true)) return false;    
        break;
        case 'STDDEV_POP':
        case 'STD_POP':
          if(!$this->wf_std($num, $state,false)) return false;    
        break;
        case 'VARIANCE':
        case 'VARIANCE_SAMP':
        case 'VAR':
        case 'VAR_SAMP':
          if(!$this->wf_var($num, $state,true)) return false;    
        break;
        case 'VARIANCE_POP':
        case 'VAR_POP':
          if(!$this->wf_var($num, $state,false)) return false;    
        break;

      }
    }
    return true;
  }

  function all_null(&$ary) {
    foreach($ary as $a) {
      if($a != null) return false;
    }
    return true;
  }

  function count2(&$ary) {
    $cnt =0;
    foreach($ary as $a) {
      if($a != null) $cnt++;
    }
    return $cnt;
  }

}
