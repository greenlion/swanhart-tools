<?php
require_once('parser/php-sql-parser.php');
require_once('rewriteengine.php');

class RewriteParallelRule extends RewriteBaseRule {
	private $task_query = ""; //Query to send to each shard
	private $final_query = ""; //Query to send to the coordination node
	private $group = array(); //list of positions which contain non-aggregate functions
	private $group_aliases = array(); //list of group aliases which will be indexed on the aggregation temp table
	private $task_odku = array();
	private $non_distrib = false;
	private $used_colrefs = array();
	private $push_pos = 0;
	private $straight_join = false;
	private $used_agg_func = false;
	private $no_pushdown_limit=false;
	private $task_group = "";
	private $final_group = "";
	private $order_by = "";
	private $with_rollup = "";
	private $tables = array();
	private $used_limit = false;
	private $push_select = array();
	private $used_distinct = false;
	private $distinct = false;
	private $task_sql = array();
	private $create_sql = "";
	private $settings = array();

	public function __construct($settings=null) {
		$this->settings=array('engine'=>'MyISAM');
		if(!isset($settings)) $this->settings=array_merge($this->settings, $settings);
		$this->set_capability('MULTI_INPUT', TRUE);
		$this->set_capability('MULTI_OUTPUT', TRUE);
		$this->set_capability('RULE_NAME', 'PARALLEL_RULE');
	}

	public function rewrite($sql, $table_info, $process_info) {
		if(!isset($table_info)) $table_info = $this->table_info;
		if(!isset($process_info)) $process_info = $this->process_info;
		$this->table_info = $table_info;
		$this->process_info = $process_info;

		/* Handle MULTI_INPUT plans */
		if(is_array($sql)) {
			$out_plan = array();	
			$has_rewrites = 0;
			$explain = array();
			foreach($sql['plan'] as $sub_sql) {
				$THIS_CLASS=get_class($this);
				$sub_rewrite = new $THIS_CLASS($this->settings);
				$plan = $sub_rewrite->rewrite($sub_sql, $table_info, $process_info);

				/* can't proceed with filtering if any of the queries return errors */
				if(!empty($plan['has_errors'])) {
					$this->errors[] = $plan->errors;
					return(array('has_rewrites'=>0, 'plan'=>$sql));
				}


				/* keep the original SQL if there were no rewrites */
				if($plan['has_rewrites'] == 1) {
					$has_rewrites = 1;
					$explain[] = $plan['explain'];
					$out_plan[] = $plan;
				} else {
					$out_plan[] = $sql;
				}
			}
			return(array('has_rewrites'=>$has_rewrites, 'plan'=>$out_plan, 'explain' => $explain));
		}

		/* Handle single SQL (this can also come from a multi-plan that has recursed here */
		
		global $REWRITE_PARSER;
		$this->sql = self::remove_comments($sql);
		$this->parsed = $REWRITE_PARSER->parse($this->sql);

		/* It is possible to get CREATE statements and INSERT .. SELECT, etc, from "upstream" filters, but they 
		   are not handled by this filter.  Just return them as they are and don't raise any errors.
		*/
		if(!self::is_select($this->parsed)) {
			return(array('has_rewrites' => 0, 'plan' => $sql));
		}

		/* Reset the private variables for this rewrite rule */
		$this->task_query = ""; //Query to send to each shard
		$this->final_query = ""; //Query to send to the coordination node
		$this->group = array(); //list of positions which contain non-aggregate functions
		$this->group_aliases = array(); //list of group aliases which will be indexed on the aggregation temp table
		$this->task_odku = array();
		$this->used_agg_func = 0;
		$this->non_distrib = false;
		$this->used_colrefs = array();
		$this->push_pos = 0;
		$this->straight_join = false;
		$this->errors = array();
		$this->used_agg_func = false;
		$this->push_select = array();
		$this->used_distinct = false;
		$this->no_pushdown_limit = false; //will be set if a rewrite forces us to abandon pushdown limit strategy

		/* generate a unique "signature" that will be used for subtables */
		$sig = md5($this->sql . uniqid());
		$this->table_name = "p" . $this->process_info['pid'] . "_agg_" . $sig;
	
		/* this function actually does the heavy lifting, parsing each SQL clause, forming the basis for our plan*/	
		/* if for some reason the rewrite can not continue, then abort the rewrite and return original SQL */
		if (!$this->process_sql()) {
			return array('has_rewrites'=>0, 'plan'=>$sql);
		}

		/* put together a plan (I love it when a plan comes together) */
		$this->create_sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} ";
		$create_subquery = str_replace('1=1','0=1', $this->task_sql[0]);
	
		if(stristr($this->task_sql[0], 'GROUP BY')) {
			if(!empty($this->agg_key_cols) && $this->agg_key_cols) $this->create_sql .= "(UNIQUE KEY gb_key (" . $this->agg_key_cols . "))";
		}

		$this->create_sql .= " ENGINE=". $this->settings['engine'];
		$this->create_sql .= " AS $create_subquery ";

		foreach($this->task_sql as $idx => $sql) {
			$this->task_sql[$idx] = "INSERT INTO {$this->table_name} ({$sql}) {$this->task_odku}";
		}

		/* the plan will be executed from the bottom to the top (0 is the bottom) and all queries at a given "level" are elgible to be executed in parallel */
		$plan[0] = $this->create_sql;
		$plan[1] = $this->task_sql; /* task_sql is an array for parallelism */
		if($this->used_distinct) {
			$this->DAL->my_query("alter table " . $this->table_name . " drop key gb_key");
		}
		$plan[2] = $this->final_sql;
		$plan[4] = "DROP TABLE IF EXISTS " . $this->table_name;

		$this->plan = $plan;
		return array('has_rewrites'=>true, 'plan'=>$this->plan, 'explain' => $this->explain);

	}


	private function process_sql() {
		$select = null;
		$parser_copy = $this->parsed;
		
		if (!empty($parser_copy['UNION ALL'])) {
			$this->errors[] = array(
				'Unsupported query',
				"UNION ALL not supported"
			);
			return false;
		} elseif (!empty($parser_copy['UNION'])) {
			$this->errors[] = array(
				'Unsupported query',
				"UNION not supported"
			);
			return false;
		} else {
			//reset the important variables 
			$select = $from = $where = $this->group = $order_by = "";
			$straight_join = $distinct = false;
			$this->errors = array();
			if(!empty($parser_copy['OPTIONS'])) {
				/* The following options ARE supported*/
				if (in_array('STRAIGHT_JOIN', $parser_copy['OPTIONS'])) {
					$this->straight_join = true;
				}
				
				if (in_array('DISTINCT', $parser_copy['OPTIONS'])) {
					$this->distinct = true;
				}

				$this->with_rollup = "";
				if (in_array('WITH ROLLUP', $parser_copy['OPTIONS'])) {
					$this->with_rollup = ' WITH ROLLUP';
				}

				/* The following options ARE NOT supported*/
				
				if (in_array('SQL_CALC_FOUND_ROWS', $parser_copy['OPTIONS'])) {
					$this->errors[] = array(
						'Unsupported query',
						'SQL_CALC_FOUND_ROWS is not supported'
					);
					return false;
				}
				
				if (in_array('FOR UPDATE', $parser_copy['OPTIONS'])) {
					$this->errors[] = array(
						'Unsupported query',
						'FOR UPDATE is not supported'
					);
					return false;
				}
				
				if (in_array('LOCK IN SHARE MODE', $parser_copy['OPTIONS'])) {
					$this->errors[] = array(
						'Unsupported query',
						'LOCK IN SHARE MODE is not supported'
					);
					return false;
				}
				
			}
			
			//ignore any other options
			unset($parser_copy['OPTIONS']);
			
			$procedure = "";
			if (!empty($parser_copy['PROCEDURE'])) {
				$this->errors[] = array(
					'Unsupported query',
					'PROCEDURE is not supported'
				);
				return false;
			}
			
			if (!empty($parser_copy['INTO'])) {
				$this->errors[] = array(
					'Unsupported query',
					'INTO is not supported'
				);
				return false;
				
			}
			
			$processed_ok = $this->process_select();
			if (!$processed_ok || !empty($this->errors)) {
				return false;
			}
			unset($parser_copy['SELECT']);

			/* handle queries with no tables.  maybe not necessary anymore*/
			if(trim($this->task_query) == 'SELECT') {
				$this->task_query = 'SELECT 1';
			}

			if (!empty($parser_copy['GROUP']) && empty($this->final_group)) {
				foreach ($parser_copy['GROUP'] as $gb) {
					if (!is_numeric(trim($gb['base_expr']))) {
						$id = "expr_" . mt_rand(1, 100000);
						
						$this->task_query .= "," . $gb['base_expr'] . ' AS `' . $id . '`';
						if ($this->final_group !== "") $this->final_group .= ',';
						$this->final_group .= $gb['base_expr'];
						
						if ($this->task_group !== "") $this->task_group .= ',';
						$this->task_group .= "`" . $id . "`";
					}
				}
			}
			$this->task_group = ltrim($this->task_group,',');
			$this->final_group = ltrim($this->final_group,',');
			
			unset($parser_copy['GROUP']);
		
/*	
			if ($having_info = $this->process_having($this)) {
				if (trim($having_info['select']))
					$this->task_query .= "," . $having_info['select'];
			} else {
				$having_info = array(
					'having' => " "
				);
			}
*/
			$this->order_by = "";
			if (!empty($parser_copy['ORDER'])) {
				$order_by = "";
				foreach ($parser_copy['ORDER'] as $o) {
					if ($order_by)
						$order_by .= ',';
					
					
					switch ($o['expr_type']) {
						
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
							$this->task_query .= ", $item as $expr_alias";
							$order_by .= $o['base_expr'] . '(' . $expr_alias . ')';
							
							if ($o['expr_type'] === 'aggregate_function') {
								switch (strtoupper($o['base_expr'])) {
									
									case 'SUM':
										$this->task_odku[] = "$expr_alias=$expr_alias + VALUES($expr_alias)";
										break;
									
									case 'MIN':
										$this->task_odku[] = "$expr_alias=IF($expr_alias < VALUES($expr_alias), VALUES($expr_alias),$expr_alias)";
										break;
									
									case 'MAX':
										$this->task_odku[] = "$expr_alias=IF($expr_alias > VALUES($expr_alias), VALUES($expr_alias), $expr_alias)";
										break;
									
									case 'COUNT':
										$this->task_odku[] = "$expr_alias=$expr_alias + VALUES($expr_alias)";
										break;
										
								}
								
							}
							
							break;
						
						default:
							if (strpos($o['base_expr'], '.') !== false) {
								$expr_alias = 'expr_' . mt_rand(1, 10000000000);
								$this->task_query .= ", {$o['base_expr']} as $expr_alias";
								$order_by .= $expr_alias;
							} else {
								$order_by .= $o['base_expr'];
							}
							break;
					}
					
					$order_by .= " " . $o['direction'];
				}
				$this->order_by = " ORDER BY {$order_by}";
				unset($parser_copy['ORDER']);
			}
			
			if (empty($parser_copy['FROM'])) {
				$this->errors[] = array(
					'Unsupported query',
					'Missing FROM clause'
				);
				return false;
			} else {

				$this->task_query .= "\n" . $this->process_from();

				/* tables that aren't partitioned don't need the pattern*/
				foreach($this->table_info as $table_alias => $table_info) {
					if(empty($table_info)) {
						$this->task_query=str_replace("%p$table_alias", "", $this->task_query); 
						unset($this->table_info[$table_alias]);
					} 
				}

				if (!isset($this->table_name) || $this->table_name == "") {
					$this->table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
				}

				$this->final_query .= "\nFROM `$this->table_name`";
				
				unset($parser_copy['FROM']);
			}
			
			//note that this will extract inlists and store them in $this->in_lists (if inlist optimization is on)	
			$where_clauses = $this->process_where();
			unset($parser_copy['WHERE']);
			
			
			$this->used_limit = 0;
			if (!empty($parser_copy['LIMIT'])) {
				$this->used_limit = 1;
				if ($parser_copy['LIMIT']['offset'] == "")
					$parser_copy['LIMIT']['offset'] = 0;
				$order_by .= " LIMIT {$parser_copy['LIMIT']['offset']},{$parser_copy['LIMIT']['rowcount']}";
				unset($parser_copy['LIMIT']);
			}
			
			foreach ($parser_copy as $key => $clauses) {
				$this->errors[] = array(
					'Unsupported query',
					$key . ' clause is not supported'
				);
			}
			
			if (!empty($this->errors)) {
				return false;
			}
			
			if ($this->final_group)
				$this->final_group = ' GROUP BY ' . $this->final_group . $this->with_rollup;

			if ($this->task_group)
				$this->task_group = ' GROUP BY ' . $this->task_group;
		
			/* Here is where any parallel query construction happens*/	
			$queries = array($this->task_query);

			/* first fix the FROM clause, expanding any partitions */
			if(!empty($this->table_info)) {
				$oq = $queries;
				$queries = array();
				foreach($oq as $q) {
					foreach($this->table_info as $table_alias => $table_info) {
						foreach($table_info as $part_name) {
							$queries[] = str_replace("%p$table_alias", " PARTITION(" . $part_name . ") ", $q); 
						}
					}
				}
			}

			/* now add the WHERE clause to each query */
			$old_queries = $queries;
			$queries = array();
			$where_clauses[] = "1=1";
			foreach($old_queries as $query) {
				$final_where_clause = "";
				foreach($where_clauses as $where_clause) {
					if($final_where_clause) $final_where_clause .= " AND ";
					$final_where_clause .= $where_clause;
				}
				$final_where_clause = "WHERE $final_where_clause";
				if (!$this->no_pushdown_limit) {
					$nq = $query . ' ' . $final_where_clause . ' ' . $this->task_group . ($this->used_limit ? $order_by : '');
				} else {
					$nq = $query . ' ' . $final_where_clause . ' ' . $this->task_group;
				}
				$queries[] = $nq;
			} 

		}

		if (empty($order_by)) $order_by = "";
		if (empty($this->final_group)) $this->final_group = "";
		if (empty($this->task_group)) $this->task_group = "";
		$this->final_sql = $this->final_query . ' ' . $this->final_group . ' ' . $order_by;

		if (!isset($this->task_odku) || empty($this->task_odku))
			$this->task_odku = "";
		else {
			$this->task_odku = ' ON DUPLICATE KEY UPDATE ' . join(', ', $this->task_odku);
		} 

		$this->task_sql = $queries;
		
		$this->agg_key_cols = ltrim($this->task_group,'GROUP BY ');
		
		$explain = "Parallel re-write optimizer messages:";
		if ($this->agg_key_cols) {
			$explain .= "\n	* The following GROUP BY expressions will be selected for a indexed merge operation: {$this->agg_key_cols}\n"; 
			if ($this->task_odku !== "")
				$explain .= "\n	* Merge operation will incrementally maintain the following expressions:\n	ON DUPLICATE KEY UPDATE " . $this->task_odku;
		}
		
		if (isset($this->messages)) {
			foreach ($this->messages as $msg) {
				$explain .= "\n	* $msg";
			}
		}
		
		$explain .= "\n\nSQL TO SEND TO THREADS:\n";
		$explain .= print_r($this->task_sql, true);
		
		$explain .= "RESULT SQL:\n{$this->final_sql}\n";
		
		$this->explain = $explain;
		
		return true;
		
	}

	/* Maybe these should be attached to the parser */

	private function concat_all_subtrees($clauses, &$item, $depth=0, $parent_type = "") {
		if (empty($clauses[0])) {
			$clauses = array(
				$clauses
			);
		}
		$pos = 0; 
		foreach ($clauses as $clause) {
			if (strpos($clause['base_expr'], '.') !== false) {
				$clause['base_expr'] = str_replace('`.`', '.', $clause['base_expr']);
				$clause['base_expr'] = trim($clause['base_expr'], '`');
			}

			if($pos > 0 && ($parent_type == 'function' || $parent_type == 'aggregate_function')) {
				if(!in_array($clauses[$pos-1]['expr_type'], array('operator','reserved'))) {
					if(!in_array($clauses[$pos]['expr_type'], array('operator','reserved'))) {
						$item .= ',';
					}
				}
			}
			
			switch ($clause['expr_type']) {
				
				case 'function':
				case 'aggregate_function':
				case 'expression':
					$item .= "{$clause['base_expr']}(";
					$this->concat_all_subtrees($clause['sub_tree'], $item, $depth + 1, $clause['expr_type']);
					$item .= ")";
					break;
				
				case 'reserved':
				case 'operator':
					if($pos > 0) $item .= " ";
					$item .= $clause['base_expr'] . " ";
					break;
				
				default:
					$item .= $clause['base_expr'];
			}
			++$pos;
		}
		return $item;
		
	}

	private function extract_colrefs($tree) {
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

	private function unquote_colrefs(&$tree) {
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

	private function make_alias($clause) {
		$alias = "";

		if (empty($clause['alias']) || $clause['alias'] == "") {
			if ($clause['expr_type'] != 'expression') {
				$alias = $this->concat_all_subtrees($clause, $item);
			} else {
				$alias = $clause['base_expr'];
			}
		} else {
			$alias = trim($clause['alias']['name'], "`");
		}
		
		if (strpos($alias, '.')) {
			$alias = trim($alias, '`\'');
			$alias = explode('.', $alias);
			$alias = trim($alias[1],"`'" );
			$alias = "`$alias`";
		}

		$alias = trim($alias, '`\'"');

		return "`$alias`";
	}

	private function append_all(&$queries, $append) {
		for ($i = 0; $i < count($queries); ++$i) {
			$queries[$i] .= $append;
		}
	}

	/* End of questionable functions */
	
	
	private function process_select() {

		$prev_clause = false;

		$select = $this->parsed['SELECT'];

		foreach ($select as $pos => $clause) {
			//this will recurse and fill up the proper structures
			$alias = $this->make_alias($clause);
 
			$this->process_select_item($pos, $clause, false, null, $alias, null);

			if($pos+1 < count($select)) {
				$this->task_query = rtrim($this->task_query, ", ");
				$this->final_query = rtrim($this->final_query, ", ");
				$this->task_query .= ",";
				$this->final_query .= ",";
			}
			
			$prev_clause = false;
		}

		$this->task_query = rtrim($this->task_query, ", ");
		$this->final_query = rtrim($this->final_query, ", ");
		
		$sql = "SELECT ";
		if ($this->straight_join)
			$sql .= "STRAIGHT_JOIN ";
		if ($this->distinct)
			$sql .= " DISTINCT ";
		
		$this->task_query = $sql . $this->task_query;
		$this->final_query = $sql . $this->final_query;
		
		foreach ($this->push_select as $clause) {
			$this->task_query .= "," . $clause;
		}
		
		$this->group = join(',', $this->group);
		$this->group_aliases = join(',', array_unique($this->group_aliases));

		return true;
		
	}

	private function process_select_item($pos, &$clause, $skip_alias = false, $prev_clause = null, $alias=null, $parent=null) {
		$return = array();
		$this->no_pushdown_limit = true;
		$non_distrib = false;


		/* handle SELECT * 
		*/
		if (!empty($clause['base_expr']) && $clause['base_expr'] == "*") {
			if($pos === 0) { 
				$this->task_query .= "*";
				$this->final_query .= "*";
				return true;
			} else {
				$clause['expr_type'] = 'operator';
			}
		} 

		
		if (!empty($clause['base_expr'])) {
			$base_expr = $clause['base_expr'];
		}

		
		switch ($clause['expr_type']) {

			case 'operator':
				$prev_clause = $clause;
				if(!$parent || ($parent && $parent['expr_type'] !== 'expression')) $this->final_query .= $clause['base_expr'];
				return true;

			case 'expression':
				if (!empty($clause['alias'])) {
					$alias = $clause['alias']['name'];
				}
				$prev_clause = null;

				foreach ($clause['sub_tree'] as $sub_pos => $sub_clause) {
					if ($sub_pos > 0) {
						$prev_clause = $clause['sub_tree'][$sub_pos - 1];
					} 

					if($sub_clause['expr_type'] == 'operator') {
						$this->final_query .= $sub_clause['base_expr']; 
						continue; 
					}
	
					if($sub_pos > 0) {
						$this->task_query .= ",";
					}

					$this->process_select_item($pos, $sub_clause, true, $prev_clause,"", $clause);
					
				}

				$this->final_query .= " $alias";

				break;
			
			case 'aggregate_function':
				$this->used_agg_func = 1;
				$item = "";
				$base_expr = $this->concat_all_subtrees($clause['sub_tree'], $item);

				$function = strtoupper($clause['base_expr']);
				$new_alias = "expr_" . crc32(uniqid());
				
				switch ($function) {
					case 'COUNT':
					case 'SUM':
					case 'MIN':
					case 'MAX':
						$expr_info = explode(" ", trim($base_expr,'()'));
						if (!empty($expr_info[0]) && strtolower($expr_info[0]) == 'distinct') {
							$this->messages[] = "DISTINCT aggregate expression used.	Pushing expression.\n";
							$this->no_pushdown_limit = true;
							
							unset($expr_info[0]);
							$new_expr = join(" ", $expr_info);
							
							if (empty($this->used_colrefs[trim($new_expr)])) {
								$this->task_query .= "$new_expr AS $new_alias";
								$this->task_odku[] = "$new_alias=VALUES($new_alias)";
								$this->group_aliases[] = $new_alias;
							}
							
							$this->used_colrefs[trim($new_expr)] = 1;
							$this->final_query .= "{$function}(distinct $new_alias)"; 
							$this->used_distinct = true;
							
						} else {
							switch ($function) {
								case 'SUM':
								case 'COUNT':
									$this->task_odku[] = "$new_alias=$new_alias + VALUES($new_alias)";
									break;
								case 'MIN':
									$this->task_odku[] = "$new_alias=IF($new_alias < VALUES($new_alias), VALUES($new_alias),$new_alias)";
									break;
								case 'MAX':
									$this->task_odku[] = "$new_alias=IF($new_alias > VALUES($new_alias), VALUES($new_alias), $new_alias)";
									break;
							}
							if($function == 'COUNT') {
								$this->task_query .= "COUNT({$base_expr}) AS $new_alias";
								$this->final_query .= "SUM($new_alias)";
							} else {
								$this->task_query .= "{$function}({$base_expr}) AS $new_alias";
								$this->final_query .= "{$function}({$new_alias})";
							}
						}
						if($alias && !$skip_alias)	$this->final_query .= " AS $alias";
						
						break;

					case 'AVG':
						$expr_info = explode(" ", $base_expr);
						if (!empty($expr_info[0]) && strtolower($expr_info[0]) == 'distinct') {
							$this->messages[] = "Detected a {$function} [DISTINCT] expression!\n";
							
							unset($expr_info[0]);
							$new_expr = join(" ", $expr_info);
							if (empty($this->used_colrefs[trim($new_expr)])) {
								$this->task_query .= "$new_expr AS $alias";
								$this->task_odku[] = "$alias=VALUES($alias)";
								$this->group_aliases[] = $alias;
							}

							$this->final_query .= "{$function}(distinct $alias)" . (!$skip_alias ? " AS $alias" : "");
							//since we pushed a GB expression we need to update the ODKU clause
						} else {
							$alias_cnt = trim($alias, '`');
							$alias_cnt = "`{$alias_cnt}_cnt`";
							$this->task_query .= "SUM({$base_expr}) AS $alias";
							$this->final_query .= "SUM({$alias})/SUM({$alias_cnt})" . (!$skip_alias ? " AS $alias" : "");
							
							//need to push a COUNT expression into the SELECT clause
							$this->push_select[] = "COUNT({$base_expr}) as {$alias_cnt}";
							$this->task_odku[] = "{$alias_cnt} = {$alias_cnt} + VALUES({$alias_cnt})";
							
						}
						
						break;
					
					case 'STDDEV':
					case 'STD':
					case 'STDDEV_POP':
					case 'STDDEV_SAMP':
					case 'VARIANCE':
					case 'VAR_POP':
					case 'VAR_SAMP':
					case 'GROUP_CONCAT':
					default:
						$this->errors[] = array(
							'error_clause' => $clause['base_expr'],
							'error_reason' => 'Unsupported aggregate function or unsupported expression.'
						);
						
						return false;
					break;
					
				}
				
				break;
			
			case 'operator':
			case 'reserved':
				break;
			
			
			case 'const':
				$this->final_query .= $base_expr;
				if(!$parent) $this->final_query .= " AS $alias";
				break;
			
			case 'function':
				$this->no_pushdown_limit = true;
				$this->final_query .= $base_expr . "(";
				$first = 0;
				foreach ($clause['sub_tree'] as $sub_pos => $sub_clause) {
					if ($sub_clause['expr_type'] == 'colref' || $sub_clause['expr_type'] == 'aggregate_function' || $sub_clause['expr_type'] == 'function') {
						$this->process_select_item($pos + 1, $sub_clause, true, $clause, "", $clause);
					} else {
						$this->final_query .= " " . $sub_clause['base_expr'];
					}
				}
				$this->final_query .= ") $alias";

				break;
			
			case 'colref':
			case 'subquery':
				if (empty($this->used_colrefs[$base_expr])) {
					$new_alias	= "expr$" . (count($this->used_colrefs));
				} else {
					$new_alias = "expr$" . $this->used_colrefs[$base_expr];
				}

				if(!$parent) {
					$this->final_query .= $new_alias . ' AS ' . $alias;
					$this->task_query .= $clause['base_expr'] . ' AS ' . $new_alias;
					$this->group[] = $pos + 1;
					$this->group_aliases[] = $new_alias;
				} else	{
					if (empty($this->used_colrefs[$base_expr])) {
						$new_alias	= "expr$" . (count($this->used_colrefs));
						$this->task_query .= $base_expr;
						if (strpos($base_expr, '.*') === false) {
							$this->task_query .= ' AS ' . $new_alias;
						} 
						$this->task_query .= ",";
						$this->group_aliases[] = $new_alias;
					} 
					$this->final_query .= " $new_alias";
				}

				if (empty($this->used_colrefs[$base_expr])) {
					$this->used_colrefs[$base_expr] = count($this->used_colrefs);
				} 
				break;
			
			default:
				
				$this->errors[] = array(
					'Unsupported expression:' => $clause['base_expr']
				);
				return false;
				break;
		}
		
		return true;
		
	}
	
	private function process_from() {
		$tables = $this->parsed['FROM'];	

		foreach ($tables as $key => $table) {
			if ($table['expr_type'] == 'subquery') {
				$this->errors[] = array(
					'Unsupported construct' => "subquery in from clause"
				);
				return false;
			/*	
				$sub_this = ShardQuery::new_this();
				
				$dest_table_name = "aggregation_tmp_" . mt_rand(1, 100000000);
				$sub_this->table_name = $sub_table_name;
				$sub_this->DAL = SimpleDAL::factory($sub_this->tmp_shard);
				
				//the aggregation table created by this query is going to have
				//to be copied to every shard the query executes against
				
				$this->query($table['sub_tree'], false, $sub_this, true, false);
				$this->dependent_subqueries[] = $sub_table_name;
				
				$this->extra_tables[] = $sub_table_name;
				$this->extra_tables = array_merge($this->extra_tables, $sub_this->extra_tables);
				
				$sql = '(' . $sub_this->final_sql . ')';
				$tables[$key]['table'] = $sql;
			*/
				
			} else {
				$alias = "";
				if (is_array($table['alias'])) $alias = $table['alias']['name'];
				
				$db = $this->process_info['current_schema'];
				$table_name = $table['table'];
				
				/* handle schema qualified names*/ 
				if (strpos($table_name, '.') !== false) {
					$info = explode(".", $table_name);
					$db = $info[0];
					$table_name = $info[1];
				}
				
				$this->tables[$table_name] = array(
					'schema' => $db,
					'alias' => $alias
				);
			}
		}
		
		$sql = "";
		
		if (empty($tables[0]['alias']) || $tables[0]['alias'] == "") {
			$alias = $tables[0]['table'];
		} else {
			$alias = $tables[0]['alias']['name'];
		}
		//escape the table name if it is unescaped
		$bare = $alias;
		if ($alias[0] != '`' && $alias[0] != '(') {
			$alias = '`' . $alias . '`';
		} else {
			$bare = trim($alias, '`');
		}

		if ($alias != "`dual`") {
			$alias = " %p$bare AS $alias ";
		} else {
			$alias = " ";
		}
		
		$sql = "FROM " . $tables[0]['table'] . $alias;

		//now add the rest of the FROM clause
		$cnt = count($tables);
		for ($i = 1; $i < $cnt; ++$i) {
			$item = "";
			$this->concat_all_subtrees($tables[$i]['ref_clause'],$item);
			if ($tables[$i]['ref_type'] == 'USING') {
				$tables[$i]['ref_clause'] = trim($tables[$i]['ref_clause'][0]['base_expr']);
			} elseif ($tables[$i]['ref_type'] == 'ON') {
				$item = "";
				$this->concat_all_subtrees($tables[$i]['ref_clause'],$item);
				$tables[$i]['ref_clause'] ="( $item ) ";
			}

			if ($sql)
				$sql .= " ";
			$alias = $tables[$i]['alias'];
			if (empty($alias) || $alias == "")
				$alias = $tables[$i]['table'];
			else
				$alias = $tables[$i]['alias']['name'];
			
			if ($alias[0] != '`' && $alias[0] != '(') {
				$pos = strpos($alias, '.');
				if ($pos !== false) {
					$info = explode('.', $alias);
					$table = $info[1];
					$alias = '`' . $table . '`';
				} else {
					$alias = '`' . $alias . '`';
				}
			}
			$bare = trim($alias,'`');
			if ($alias != "`dual`") {
				$alias = " %p$bare AS $alias";
			} else {
				$alias = " ";
			}
			if ($tables[$i]['join_type'] == 'CROSS')
				$tables[$i]['join_type'] = ' CROSS JOIN ';
			$sql .= $tables[$i]['join_type'] . ' ' . $tables[$i]['table'] . $alias . ' ' . $tables[$i]['ref_type'] . $tables[$i]['ref_clause'];
			 
		}
		return $sql;
	}
	
	
	private function process_where($where = null) {

		if(!isset($where)) $where = $this->parsed['WHERE'];

		if(empty($where)) {
			$out_queries = array(
				"1=1"
			);
		
			return array_values($out_queries);
		}
		
		$prev = "";
		$total_count = 0;
		$sql = "";
		$queries = array(
			$sql
		);
		
		foreach ($where as $pos => $clause) {

			if (empty($where[$pos])) continue;
			$sql .= " ";
			$this->append_all($queries, " ");
 
			if ($clause['expr_type'] === 'bracket_expression') {
				$new_queries = $this->process_where($clause['sub_tree']);
				$out = array();
				foreach ($new_queries as $new_query) {
					foreach ($queries as $idx => $orig_query) {
						$out[] = $orig_query . '(' . $new_query . ') ';
					}
				}
				
				$queries = $out;
				continue;
			}
			
			$this->append_all($queries, $clause['base_expr']);

			$prev = $clause['base_expr'];
				
		}
		
		$out_queries = array();
		foreach ($queries as $pos => $q) {
			if (!trim($q))
				continue;
			$out_queries[] = $q;
		}
		
		if (empty($out_queries))
			$out_queries = array(
				"1=1"
			);
		
		return array_values($out_queries);
	}
	
	
/*
	private function aggregate_result() {
		$stmt = false;
		if(!isset($this)) $this = $this->this;
		if (!$this->DAL->my_select_db($this->tmp_shard['db'])) {
			$this->errors[] = 'While aggregating result: ' . $this->DAL->my_error();
			$stmt = false;
		} else {

			if (!$stmt = $this->DAL->my_query($this->final_sql)) {
				$this->errors[] = 'While aggregating result: ' . $this->DAL->my_error();
				return false;
			}
		}
		
		return $stmt;
		
	}
	
*/

}
