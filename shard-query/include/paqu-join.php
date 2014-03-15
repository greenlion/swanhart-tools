<?php

/*
	Copyright (c) 2012, Adrian Partl @ Leibniz Inst. for Astrophysics Potsdam
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

	error_reporting(E_ALL);

	require_once 'PHP-SQL-Parser/php-sql-parser.php';

/**
 * @file parOptimImplicitJoin.php
 * @brief Functions identifying implicit joins and casting them into nested subqueries for further
 *	  processing through shard-query-prallel.php
 *
 * The functions in this set are meant to extend the PHP Parallel Shard framework developed by 
 * http://code.google.com/p/shard-query/ which we used to build our Spider Engine version. Since
 * the origina shard query framework was not able to handle implicit joins, this was extended using
 * the functions below.
 * 
 * The general idea was to identify implicit joins and transform them into nested queries which can
 * be executed one by one on the shards and further aggregated on the head node. For this a simple
 * heuristic is used, to start with the most restricting implicit join subquery first. We assume that
 * the query with the most WHERE statement restrictions is also the most restricting query of all
 * the dependant joins. Relational algebra allows us to restructure them accordingly. This is acchieved
 * through the functions below. 
 * 
 * Further functions are provided which will build up the new SQL parse tree with the approperiate subqueries
 * in place. This is eventually a rewriting of the complete SQL command. Further any aggregate functions in the
 * SQL query are handled approperiate and the necessary columns are selected.
 * 
 * Unfortunately this doxygen file only gives a very incomplete view of the whole algorithm, with a detailed 
 * documentation eventually comming at later times.
 */


/**
 * @brief Wrapper function for the whole implicit join finder and rewriter
 * @param sqlTree an SQL parser tree representation of the SQL query
 * @param headNodeTables list of tables that are completely located on the head node and are not sharded
 * @return a reorganised SQL parser tree with the properly nested subqueries representation
 *	   of any aggregate or implicit join
 * 
 * This function identifies all implicit joins and rewrites them using the heuristic described
 * in the file description. Further all the aggregate functions are accounted for. This process
 * is now described in somewhat more detail:
 * 
 * First, any initial user defined subqueries are identified and handled. These subtrees are identified
 * in WHERE and FROM statements. These nodes are treated in recursive manner using PHPSQLbuildShardQuery
 * (i.e. like as if they are independant queries, which eventually they are). The result is saved in a
 * subquery array, which needs to be linked to the newly parallellised tree further down. One gotcha is
 * here: The nested query tree is build by assuming that any table involved in the query, may it be a
 * participant of an implicit join, an explicit one or a subquery is used to build the tree recursively.
 * I.e. numTables recursion will be made to generate the nested tree. There is NO mechanism to determine
 * whether the recursion is needed or not (in a perfect world it would not be needed for explicit joins
 * and user defined subqueries). Therefore after the nested tree is generated with all the implicit
 * joins, the explicit subqueries need to be properly relinked with the tree, otherwise unnecessary 
 * recursive levels appear in the tree. This is undesirably. I aggree, this might not be the most
 * elegant way to achieve this, but it is functional...
 * 
 * Note on the actual working of the algorithm:
 *	-# #First treat user given subqueries in FROM and WHERE recursively, save them to 
 *	   an array for further linking
 *	-# #Parse all the WHERE nodes, linking groups of colrefs and operators that belong 
 *	   together. Basically this puts brackets around each term - terms linked with OR will
 *	   be bracketed as well, since associative property does not hold for them.
 *	-# #Find all the tables in the query and link all the columns to the tables
 *	-# #Loop through the WHERE terms. Those that are independant of other tables are linked to the
 *	   approperiate tables. Those that are dependant on other tables are saved in another array.
 *	-# #Count the independant WHERE terms for each table. This is the simple selectivity heuristic
 *	   we apply. It is assumed, that the table with the most independant WHERE terms is the most
 *	   selective one.
 *	-# #Determine the table with the highest number of independant WHERE terms to start with it in the
 *	   innermost subquery.
 *	-# #Build the nested queries for all the tables, starting with the most selective table.
 *	    -# #In each recursive run a new table is selected. Again it is assumed, that the table with
 *		the most WHERE terms has the highest selectivity. This time, all the dependant WHERE terms
 *		that can already be processed (i.e. where the data has already been selected) will be 
 *		accounted for. \n (NOTE: A proper way to do this would be, to determine all the possible combinations of
 *		       possible subqueries and determine the most selective ones, taking subqueries and
 *		       the order in which they can be processed into account.)
 *	    -# #Resort the table processing list according to the new WHERE term count for all tables <= recursion
 *		level.
 *	-# #Link the user defined subqueries from the FROM and WHERE statements to the new nested query tree.
 */

$P = new PHPSQLParser("select a, count(*) from t1, t2, t3 where t1.id = t2.t1_id and t3.id = t2.date_id group by a");
var_dump(PHPSQLbuildShardQuery($P->parsed, array("t3")));


function PHPSQLbuildShardQuery($sqlTree, $headNodeTables = array()) {
	#check for subqueries
	#this handles nested subqueries that the user already provided. no idea how to handle these
	#together with the automatic joins found below...
	$subQueries = array();
	if(!empty($sqlTree['FROM'])) {
		foreach ($sqlTree['FROM'] as $subQuery) {
			if ($subQuery['table'] == 'DEPENDENT-SUBQUERY') {
				$subQuery['sub_tree'] = PHPSQLbuildShardQuery($subQuery['sub_tree']);
				array_push($subQueries, $subQuery);
			}
		}
	}
	if (!empty($sqlTree['WHERE'])) {
		foreach ($sqlTree['WHERE'] as $node) {
			if ($node['expr_type'] == 'subquery') {
				$node['sub_tree'] = PHPSQLbuildShardQuery($node['sub_tree']);
				array_push($subQueries, $node);
			}
		}
	}

	$newSqlTree = PHPSQLGroupWhereTerms($sqlTree);

	$listOfTables = array();
	PHPSQLGroupTablesAndCols($newSqlTree, $listOfTables);

	$dependantList = PHPSQLGroupWhereCond($newSqlTree, $listOfTables);

	PHPSQLCountWhereConditions($listOfTables);
	$listOfTables = PHPSQLdetStartTable($listOfTables, $headNodeTables, $dependantList);

	$nestedQuery = PHPSQLbuildNestedQuery($newSqlTree, $listOfTables, $dependantList, 0);

	#link subqueries
	linkSubqueriesToTree($nestedQuery, $subQueries);
	linkNestedWheresToTree($nestedQuery, $subQueries);

	return $nestedQuery;
}

/**
 * @brief Links the user defined subqueries in the WHERE node with the nested tree
 * @param nestedQuery the nested query tree as produced by the implicit join algorithm
 * @param subQueries an array containing all the processed user defined subqueries from WHERE and FROM
 * 
 * This function will loop through the WHERE node on the top level of the nested query tree to find all
 * the subqueries that need to be linked.
 * 
 * TODO: This function cannot yet handle subqueries in nested WHERE statements (i.e. something like 
 *	 WHERE (foo.bar = BLA OR (foo.bar != BLA2 and foo.bar = (SELECT ...))))! 
 *	 For this recursiveness needs to be implemented whenever needed!
 * 
 */
function linkNestedWheresToTree(&$nestedQuery, &$subQueries) {
	if (array_key_exists('WHERE', $nestedQuery)) {
		foreach ($nestedQuery['WHERE'] as &$whereNode) {
			if ($whereNode['sub_tree'] !== false) {
				foreach ($whereNode['sub_tree'] as &$subQuery) {
					if ($subQuery['expr_type'] == "subquery") {
						#find subquery
						foreach ($subQueries as $node) {
							if (!array_key_exists('expr_type', $node))
								continue;

							if ($node['expr_type'] == 'subquery' && $subQuery['base_expr'] == $node['base_expr']) {
								$subQuery['sub_tree'] = $node['sub_tree'];
								break;
							}
						}
					}
				}
			}
		}
	}
}

/**
 * @brief Links the user defined subqueries in the FROM node with the nested tree
 * @param nestedQuery the nested query tree as produced by the implicit join algorithm
 * @param subQueries an array containing all the processed user defined subqueries from WHERE and FROM
 * 
 * This function will loop through the FROM node on the top level of the nested query tree to find all
 * the subqueries that need to be linked.
 * 
 */
function linkSubqueriesToTree(&$nestedQuery, &$subQueries) {
	//fix possible errors in the aliases when comming from sub-trees
	//create a list of all SELECT columns in sub-trees and compare them with the
	//ones in this selectTree's SELECT statement. If errors in the base_expr are
	//found, correct them accordingly

	if(empty($nestedQuery['FROM'])) {
		return;
	}

	//fix subquery tree first
	foreach ($nestedQuery['FROM'] as &$subQuery) {
		if ($subQuery['table'] == 'DEPENDENT-SUBQUERY') {
			#we need to descend further down.
			linkSubqueriesToTree($subQuery['sub_tree'], $subQueries);

			#find subquery
			foreach ($subQueries as $subNode) {
				if (array_key_exists('alias', $subQuery) && $subQuery['alias'] == $subNode['alias']) {
					//push this further down if needed
					foreach($subQuery['sub_tree']['FROM'] as &$currQueryNode) {
						if($currQueryNode['table'] == 'DEPENDENT-SUBQUERY') {
							linkSubqueriesToTree($currQueryNode['sub_tree'], $subQueries);
						}
					}

					$subQuery['sub_tree'] = $subNode['sub_tree'];
					fixSelectsInAliasedSubquery($nestedQuery, $subQuery['sub_tree'], $subQuery['alias']);
					break;
				}
			}
		}
	}

	//columnList will hold column name as key an corresponding table alias as value
	$columnList = array();

	foreach ($nestedQuery['FROM'] as $fromNode) {
		if($fromNode['table'] === "DEPENDENT-SUBQUERY") {
			$currSubNode = $fromNode['sub_tree'];
			$currAlias = trim($fromNode['alias'], "`");

			foreach ($currSubNode['SELECT'] as $subCol) {
				$columnList[trim($subCol['alias'], "`")] = $currAlias;
			}
		}
	}

	//go through SELECT of the selectTree and correct all mistakes
	foreach ($nestedQuery['SELECT'] as &$selNode) {
		if(array_key_exists($selNode['base_expr'], $columnList)) {
			$selNode['base_expr'] = "`" . $columnList[$selNode['base_expr']] . "`.`" . $selNode['base_expr'] . "`";
		}
	}
}

/**
 * @brief Fixes the names in the SELECT statement, linking alias of subquery with the column names
 * @param selectTree the SELECT node of the SQL tree
 * @param subQuery the subquery tree
 * @param tableAlias the name of the table alias which the given SELECT node needs to refere to
 * 
 * This function properly rewrites the aliases of the columns of any subquery to the given table alias.
 * For this it parses the SELECT tree to identify all the colrefs that need a rewrite and parses the subquery
 * tree to identify the name of the column. They are then approperiately rewritten. This handles cases like this:
 * SELECT h.foo FROM (SELECT b.foo FROM bar as b) as h and transforms it into 
 * SELECT `h`.`b.foo` as `h.foo` FROM (SELECT b.foo as `b.foo` FROM bar as b) as h
 * 
 */
function fixSelectsInAliasedSubquery(&$selectTree, &$subQuery, $tableAlias) {
	//fix possible errors in the aliases when comming from sub-trees
	//create a list of all SELECT columns in sub-trees and compare them with the
	//ones in this selectTree's SELECT statement. If errors in the base_expr are
	//found, correct them accordingly

	//columnList will hold column name as key an corresponding table alias as value

	//due this here again, possible errors might have been induced through tree merger
	//TODO: Fully assess this case

	$columnList = array();

	foreach ($selectTree['FROM'] as $fromNode) {
		if($fromNode['table'] === "DEPENDENT-SUBQUERY") {
			$currSubNode = $fromNode['sub_tree'];
			$currAlias = trim($fromNode['alias'], "`");

			foreach ($currSubNode['SELECT'] as $subCol) {
				$columnList[trim($subCol['alias'], "`")] = $currAlias;
			}
		}
	}

	//go through SELECT of the selectTree and correct all mistakes
	foreach ($selectTree['SELECT'] as &$selNode) {
		if(array_key_exists($selNode['base_expr'], $columnList)) {
			$selNode['base_expr'] = "`" . $columnList[$selNode['base_expr']] . "`.`" . $selNode['base_expr'] . "`";
		}
	}

	foreach ($selectTree['SELECT'] as &$selNode) {
		preg_match("/`?([^`]*)`?\.?(.*)/", $selNode['base_expr'], $tmp);
		if ($tmp[2] !== "") {
			$table = $tmp[2];
			$alias = $tmp[1];
		} else {
			$table = $tmp[1];
			$alias = false;
		}

		if (trim($alias, "`") === trim($tableAlias, "`")) {
			//find column in subquery
			$tmp2 = explode(".", trim($table, "`"));
			if (count($tmp2) == 1) {
				$subTable = $tmp2[0];
			} else {
				$subTable = $tmp2[count($tmp2) - 1];
			}

			foreach ($subQuery['SELECT'] as $node) {
				preg_match("/`?([^`]*)`?\.?(.*)/", $node['base_expr'], $tmp3);
				if ($tmp3[2] !== "") {
					$nodeTable = $tmp3[2];
				} else {
					$nodeTable = $tmp3[1];
				}

				if ($subTable === $nodeTable) {
					$selNode['base_expr'] = "`" . $tableAlias . "`.`" . trim($node['alias'], "`") . "`";
				}
			}
		}

		//check the table only (without alias) cases that they are sane
		if($alias === false) {
			$tmp2 = explode(".", trim($table, "`"));
			if(count($tmp2) == 2 && $tmp2[0] === trim($tableAlias, "`")) {
				//$selNode['base_expr'] = "`" . $tableAlias . "`.`" . trim($selNode['base_expr'], "`") . "`";
			}
		}
	}
}

/**
 * @brief Function that build the nested query, rewriting all implicit joins and all aggregates
 * @param sqlTree input SQL parse tree that needs rewriting
 * @param tableList a list of all tables in the SQL parse tree, sorted by selectivity
 * @param dependantWheres a list of all WHERE statements that are not independant (i.e. dependant on 
 *			  multiple tables)
 * @param recLevel the current level of recursion
 * @return returns a SQL tree rewritten to nest all implicit joins at the given recursive level
 * 
 * This function builds the nested node on the given recursion level. It first starts with building
 * all the nodes for any deeper recursion level. Then it will produce the current subquery node and
 * will populate it with the approperiate SQL statement for the given table in the table list at the
 * current recursion level. 
 * 
 * It will then build all the nodes in the subquery in this order: SELECT, FROM, UPDATE, GROUP, ORDER,
 * WHERE, HAVING, LIMIT, INDEX, OPTIONS. 
 * 
 * Then the tableList is resorted taking the dependant wheres that can now be addressed into account. 
 * 
 * The inner query (i.e. the one with higher recursion level) is linked to the currently build outer
 * query. 
 * 
 */
function PHPSQLbuildNestedQuery(&$sqlTree, &$tableList, &$dependantWheres, $recLevel) {
	//create a copy of the initial sql tree for cleanup work if this is the 0th recursion level
	if($recLevel == 0) {
		$sqlTreeCopy = $sqlTree;
	}
 
	$currInnerNode = false;

	#build recursion tree
	if ($recLevel < count($tableList) - 1) {
		$currInnerNode = PHPSQLbuildNestedQuery($sqlTree, $tableList, $dependantWheres, $recLevel + 1);
	}

	if(empty($tableList[$recLevel])) {
		$table = false;
	} else {
		$table = $tableList[$recLevel];
	}

	#now that we know where to start, build the SELECT tree
	$currDepQueryNode = array();
	$currDepQueryNode['table'] = 'DEPENDENT-SUBQUERY';
	$currDepQueryNode['alias'] = $table['alias'];
	$currDepQueryNode['join_type'] = "JOIN";
	$currDepQueryNode['ref_type'] = '';
	$currDepQueryNode['ref_clause'] = '';
	$currDepQueryNode['base_expr'] = '';
	$currDepQueryNode['sub_tree'] = false;

	$currOuterQuery = array();

	#there is always an inner query and and outer one which will be joined (if exists of course)
	PHPSQLaddOuterQuerySelect($sqlTree, $table, $currOuterQuery, $tableList, $recLevel);
	PHPSQLaddOuterQueryFrom($sqlTree, $table, $currOuterQuery, $tableList, $recLevel);

	#link to the tree
	if ($currInnerNode !== false && $currInnerNode['sub_tree'] !== false) {
		linkInnerQueryToOuter($currOuterQuery, $currInnerNode, $tableList, $recLevel);
	}

	PHPSQLaddOuterQueryUpdate($sqlTree, $table, $currOuterQuery);

	PHPSQLaddOuterQueryGroup($sqlTree, $table, $currOuterQuery, $tableList, $recLevel);
	PHPSQLaddOuterQueryOrder($sqlTree, $table, $currOuterQuery, $tableList, $recLevel);

	PHPSQLaddOuterQueryWhere($sqlTree, $table, $currOuterQuery, $tableList, $recLevel, $currInnerNode);
	PHPSQLaddOuterQueryHaving($sqlTree, $table, $currOuterQuery);

	PHPSQLaddOuterQueryLimit($sqlTree, $table, $currOuterQuery, $recLevel);
	PHPSQLaddOuterQueryIndex($sqlTree, $table, $currOuterQuery);

	PHPSQLaddOuterQueryOptions($sqlTree, $table, $currOuterQuery);
	#check if we have to add some stuff for two-pass aggregation functions such as stddev (like adding an average calculation)
	#we need to add a subquery producing the temporary result here, so we add stuff to FROM and SELECT!
	//PHPSQLaddAggregateTwoPassSubQuery($currOuterQuery);

	if ($recLevel == 0) {
		#only consider these at level 0
		if (!empty($sqlTree['SET']))
			throw new RuntimeException('Inner Query SET: You have hit a not yet supported feature');
		if (!empty($sqlTree['DUPLICATE']))
			throw new RuntimeException('Inner Query DUPLICATE: You have hit a not yet supported feature');
		if (!empty($sqlTree['INSERT']))
			throw new RuntimeException('Inner Query INSERT: You have hit a not yet supported feature');
		if (!empty($sqlTree['REPLACE']))
			throw new RuntimeException('Inner Query REPLACE: You have hit a not yet supported feature');
		if (!empty($sqlTree['DELETE']))
			throw new RuntimeException('Inner Query DELETE: You have hit a not yet supported feature');
	}
	#resort the table list, now taking all the dependant queries for which data is already gathered into account
	#dependant queries will have higher priorities than other constraints
	PHPSQLresortCondTableList($tableList, $dependantWheres, $recLevel);

	$currDepQueryNode['sub_tree'] = $currOuterQuery;

	#the upper most node is not a subquery of course...
	if ($recLevel == 0) {
		cleanSelectToResembleQuery($sqlTreeCopy, $currOuterQuery);
		return $currOuterQuery;
	} else {
		return $currDepQueryNode;
	}
}

/**
 * @brief Makes sure, that only the columns are selected in the new query, which are
 *	  given in the initial input query.
 * @param initialQuery the initial query before start of reprocessing
 * @param newQuery the processed rewritten query
 * 
 * This function gets rid of all the temporary and helper columns added during the 
 * nesting of the queries. This ensures, that the user will only see the columns he
 * requested. This function should only be run if the top of the recursion tree has
 * been reached, otherwise columns will get lost.
 * 
 */
function cleanSelectToResembleQuery(&$initialQuery, &$newQuery) {
	foreach($newQuery['SELECT'] as $key => $node) {
	 $tmp = explode('.', trim($node['alias'], "`"));
	 if (count($tmp) == 1) {
		 $colTable = false;
		 $colName = $tmp[0];
	 } else {
		 $colTable = $tmp[0];
		 $colName = $tmp[1];
	 }

	 $found = false;
	 foreach($initialQuery['SELECT'] as $initNode) {
		 if($initNode['alias'] == $node['alias']) {
			$found = true;
			continue 2;
		}
	}
	
	//check if this column is needed in and ORDER BY or GROUP BY statement
	if(array_key_exists('ORDER', $initialQuery)) {
	 foreach($initialQuery['ORDER'] as $initNode) {
		if(strpos($initNode['base_expr'], $node['base_expr']) === 0) {
			$found = true;
			continue 2;
		} else {
			$tmp2 = explode('.', trim($initNode['base_expr'], "`"));
			if (count($tmp2) == 1) {
			 $colInitTable = false;
			 $colInitName = $tmp2[0];
		 } else {
			 $colInitTable = $tmp2[0];
			 $colInitName = $tmp2[1];
		 }
		 
		 if($colInitName == $colName) {
			 $found = true;
			 continue 2;
		 }
	 }
 }
}

if(array_key_exists('GROUP', $initialQuery)) {
 foreach($initialQuery['GROUP'] as $initNode) {
	if($initNode['base_expr'] == $node['base_expr']) {
		$found = true;
		continue 2;
	} else {
		$tmp2 = explode('.', trim($initNode['base_expr'], "`"));
		if (count($tmp2) == 1) {
		 $colInitTable = false;
		 $colInitName = $tmp2[0];
	 } else {
		 $colInitTable = $tmp2[0];
		 $colInitName = $tmp2[1];
	 }
	 
	 if($colInitName == $colName) {
		 $found = true;
		 continue 2;
	 }
 }
}
}

if($found === false) {
 unset($newQuery['SELECT'][$key]);
}
}

}

/**
 * @brief Links the inner query to the outer one. Basically handling aliasing in the SELECT node and
 *	  passing all needed columns to the outer query which are not yet selected there.
 * @param currOuterQuery the outer query where the subquery gets linked to
 * @param currInnerNode the given subquery
 * @param tableList a list of all tables in the SQL parse tree, sorted by selectivity
 * @param recLevel the current level of recursion
 * 
 * This function links the given subquery to the outer query node. This accounts for the rewriting
 * of aliases in the outer node, adds all the columns that are selected in the subquery and needed
 * in the outer query to process ORDER, WHERE and the like, and it will reformulate the ORDER clause
 * in the outer query for the aggregate/coordination query to properly order things.
 *
 * It will also rewrite aliases to result sets if they are not known to the one of the sub-query.
 * TODO: This is a dirty hack to handle more complicated dependencies, such as in this query:
 * SELECT f3.fofId, p.x,p.y,p.z FROM MDR1.FOFParticles f, MDR1.FOFParticles3 f3, MDR1.particles85 p WHERE f.fofId = 85000000479 AND f.particleId = f3.particleId AND p.particleId = f.particleId ORDER BY f3.fofId ASC
 * 
 */
function linkInnerQueryToOuter(&$currOuterQuery, &$currInnerNode, &$tableList, $recLevel) {
	array_push($currOuterQuery['FROM'], $currInnerNode);

	foreach ($currInnerNode['sub_tree']['SELECT'] as $node) {
		$tmp = false;

		#rewrite lower aggregate result into selectable column and add this to the SELECT clause
		if ($node['expr_type'] != 'colref') {
			#find the column participating in the aggregate
			if (!empty($node['sub_tree'])) { #this is needed to handle order by NUMBERs!
				foreach ($node['sub_tree'] as $agrPartNode) {
					if ($agrPartNode['expr_type'] == 'colref') {
						$tmp = explode('.', trim($agrPartNode['base_expr'], '()'));
					}
				}
			}

			//this expression is now to be treated as a normal column, so apply changes
			$node['expr_type'] = 'colref';
			$node['sub_tree'] = false;
		} else {
			$tmp = explode('.', $node['base_expr']);
		}

		if (count($tmp) > 1) {
			$alias = $tmp[0];
		} else {
			$alias = false;
		}

		#if this is a dependant query, properly form the aliased column name for retrieval...
		if ($alias !== $tableList[$recLevel+1]['alias']) {
			#check if this has already been aliased
			if ($alias === $tmp[0] && strpos($tmp[0], 'agr_')) {
				continue 1;
			} else {
				$node['base_expr'] = $alias . '.`' . trim($node['alias'], '`') . '`';
			}
		} 

		if (!array_key_exists('where_col', $node) && !array_key_exists('order_clause', $node) && !array_key_exists('group_clause', $node)) {
			array_push($currOuterQuery['SELECT'], $node);
		}
	}

	//link order clause
	if (array_key_exists('ORDER', $currInnerNode['sub_tree']) && count($currInnerNode['sub_tree']['ORDER']) > 0) {
		if (!array_key_exists('ORDER', $currOuterQuery)) {
			$currOuterQuery['ORDER'] = array();
		}

		foreach ($currInnerNode['sub_tree']['ORDER'] as $node) {
			array_push($currOuterQuery['ORDER'], $node);
		}
	}

	//check outer WHERES aliases - if an alias is not found, rename it to the first sub query found (usually this is the 
	//nested one)
	//TODO: any idea on how to do this better?? handles cases mentioned above
	//go through FROM - find all aliases and alias of first subquery
	$aliasList = array();
	$firstSubqueryAlias = false;
	foreach($currOuterQuery['FROM'] as $node) {
		array_push($aliasList, $node['alias']);
		if (!empty($node['sub_tree']) && $firstSubqueryAlias === false) {
			$firstSubqueryAlias = $node['alias'];
		}
	}

	//go through all WHERE terms and any alias not foudn in the list, rewrite to the one of the first subquery
	rewriteWHEREAliasToFirstSubquery($currOuterQuery['WHERE'], $aliasList, $firstSubqueryAlias);
}

function rewriteWHEREAliasToFirstSubquery(&$tree, $aliasList, $firstSubqueryAlias) {
	if(empty($tree)) {
		return;
	}

	foreach($tree as &$node) {
		if($node['sub_tree'] !== false) {
			rewriteWHEREAliasToFirstSubquery($node['sub_tree'], $aliasList, $firstSubqueryAlias);
			PHPSQLParseWhereTokens_createBaseExpr($node);
		}

		if($node['expr_type'] == "colref") {
			$tmp = explode(".", $node['base_expr']);
			if($tmp > 1) {
				$currAlias = trim($tmp[0], "` ");
				if(!in_array($currAlias, $aliasList)) {
					unset($tmp[0]);
					$node['base_expr'] = "`" . $firstSubqueryAlias . "`." . implode($tmp, ".");
					break;
				}
			}
		}
	}
}


/**
 * @brief Checks if some aggregation functions need two-pass handling and rewrite query accordingly.
 * @param toThisNode node to check for two-pass need
 * 
 * This function evaulated the given node if a two-pass aggregate handling is needed or not. If yes, 
 * a new subnode resembling the two-pass algorithm will be added to the query. This is however not
 * needed anymore for STDDEV determination, there we use the Welford one-pass algorithm as implemented
 * in MySQL plugins (see the mysql_udf folder for more information).
 */
function PHPSQLaddAggregateTwoPassSubQuery(&$toThisNode) {
		#loop through all SELECT parts to find an aggregate function that needs handling
	$selectQuery = $toThisNode['SELECT'];
	$fromQuery = $toThisNode['FROM'];

	$currAgrParentNode = array();
	$currAgrParentNode['table'] = 'DEPENDENT-SUBQUERY';
	$currAgrParentNode['alias'] = '`agr_' . trim($fromQuery[0]['alias'], '`') . '`';
	$currAgrParentNode['join_type'] = "JOIN";
	$currAgrParentNode['ref_type'] = '';
	$currAgrParentNode['ref_clause'] = '';
	$currAgrParentNode['base_expr'] = '';
	$currAgrParentNode['sub_tree'] = false;

	$currSubNode = array();
	foreach ($selectQuery as $node) {
	 $nodeCpy = false;

	 if ($node['expr_type'] == 'expression') {
			#find the aggregate functions here
		 foreach ($node['sub_tree'] as $key => $subNode) {
			if ($subNode['expr_type'] == 'aggregate_function') {
				#now determine if this is one we need to address with a two-pass algorithm
				switch ($subNode['base_expr']) {
				 case 'STDDEV':
				 case 'STD':
				 case 'STDDEV_POP':
				 case 'STDDEV_SAMP':
				 case 'VARIANCE':
				 case 'VAR_POP':
				 case 'VAR_SAMP':
				 continue 2;
				 break;
					#WE HAVE FIXED PROBLEMS IN HERE WITH CUSTOM MADE UDFS. WE KEEP THIS FOR HISTORICAL REASONS
					#create the average query needed to complete the calculation
				 $nodeCpy = $node;

				 $nodeCpy['sub_tree'][$key]['base_expr'] = "AVG";
				 $nodeCpy['alias'] = '`agr_' . trim($nodeCpy['alias'], '`') . '`';
				 $nodeCpy['base_expr'] = 'avg' . $nodeCpy['sub_tree'][$key + 1]['base_expr'];

				 break;

				 default:
				 continue 2;
				 break;
			 }

				#add first pass to SELECT node
			 if (empty($currSubNode['SELECT'])) {
				 $currSubNode['SELECT'] = array();
			 }

			 array_push($currSubNode['SELECT'], $nodeCpy);

				#add this variable to the upper node
				#build select item
			 $newNode = array();
			 $newNode['alias'] = $nodeCpy['alias'];
			 $newNode['base_expr'] = $currAgrParentNode['alias'] . '.' . $nodeCpy['alias'];
			 $newNode['expr_type'] = 'colref';
			 $newNode['sub_tree'] = false;

			 array_push($toThisNode['SELECT'], $newNode);
		 }
	 }
 }
}

if (!empty($currSubNode['SELECT'])) {
	$currSubNode['FROM'] = $fromQuery;
} else {
	return;
}

		#push down the group by argument:
if (array_key_exists('GROUP', $toThisNode)) {
	$currSubNode['GROUP'] = $toThisNode['GROUP'];

	$joinCond = "";

	#gather the group_by selection columns from the SELECT and add to subnode
	foreach ($toThisNode['SELECT'] as $node) {
	 if (array_key_exists('group_clause', $node)) {
		unset($node['group_clause']);

		array_push($currSubNode['SELECT'], $node);

		if ($joinCond != "") {
			$joinCond .= " AND ";
		}

		$joinCond .= $currAgrParentNode['alias'] . '.' . $node['alias'] . ' = ' . $node['alias'];
	}
}

	#update join type
$currAgrParentNode['join_type'] = "JOIN";
$currAgrParentNode['ref_type'] = 'ON';
$currAgrParentNode['ref_clause'] = $joinCond;
}

$currAgrParentNode['sub_tree'] = $currSubNode;

array_push($toThisNode['FROM'], $currAgrParentNode);
}

/**
 * @brief Resort the table list according to selectiveness
 * @param tableList the table list to resort
 * @param dependantWheres list with all the dependant where
 * @param recLevel current recursion level
 * 
 * This function evaluates the selectiveness again, including all the dependant WHERE
 * statements, that can be included at the given recursion level. The table list is resorted
 * according to the number of WHERE statements that can be processed. We assume, that the number
 * of WHERE statements is directly proportional to the selectiveness. This IS NOT a good assumption
 * in general, but proved ok until now. Resorting of the table is only done for all tables below
 * recLevel.
 */
function PHPSQLresortCondTableList(&$tableList, &$dependantWheres, &$recLevel) {
	#build a list of all possible remaining combinations and count dependant queries
	#is there still something remaining??
	if ($recLevel == 0) {
		return;
	}

	#generate a list of a possible combination that will be passed on to the tree
	#walker that will count the number of dependand where clases addressable with the
	#given test combination - the ones above $recLevel will always remain the same,
	#only the first entry will change:
	$possibleComb = array();
	$possibleComb[0] = -1;

	#add the tables that have already been processed to the current test combination 
	#these will allways remain the same
	$count = 0;
	foreach ($tableList as $key => $table) {
		if ($key >= $recLevel) {
			$count++;
			$possibleComb[$count] = $key;
		}
	}

	$combCountList = array();
	$combPartList = array();
	foreach ($tableList as $key => $table) {
		#skip the ones we already processed
		if ($key >= $recLevel) {
			continue;
		}

		$possibleComb[0] = $key;

		$combPartList[$key] = PHPSQLgetDepWhereConds($tableList, $dependantWheres, $possibleComb);
		$combCountList[$key] = count($combPartList[$key]);
	}

	#now choose the one to take
	$maxKey = -1;
	$maxCount = -1;
	foreach ($combCountList as $key => $value) {
		if ($value >= $maxCount) {
			$maxCount = $value;
			$maxKey = $key;
		}
	}

	#add the dependant queries to the table and remove from the dependant list
	if (!array_key_exists('where_cond', $tableList[$maxKey])) {
		$tableList[$maxKey]['where_cond'] = array();
	}

	$tableList[$maxKey]['where_cond'] = array_merge($tableList[$maxKey]['where_cond'], $combPartList[$maxKey]);
	foreach ($combPartList[$maxKey] as $node) {
		$key = array_search($node, $dependantWheres);
		unset($dependantWheres[$key]);
	}

	#add the columns that make up the dependant wheres to the table
	foreach ($combPartList[$maxKey] as $node) {
		$listOfParticipants = PHPSQLGetListOfParticipants($node);

		#check which one we still need to add to the list of columns
		foreach ($listOfParticipants as $col) {
			$tmp = explode('.', $col['base_expr']);

			if ($tmp[0] == $tableList[$maxKey]['alias']) {
				$tmpCol['expr_type'] = $col['expr_type'];
				$tmpCol['alias'] = '`' . $col['base_expr'] . '`';
				$tmpCol['base_expr'] = $col['base_expr'];
				$tmpCol['sub_tree'] = $col['sub_tree'];

				$col['sub_tree'] = false;
				$key = array_search($tmpCol, $tableList[$maxKey]['sel_columns']);

				if ($key === false) {
					array_push($tableList[$maxKey]['sel_columns'], $col);
				}
			}
		}
	}

	#exchange the two tables
	if ($maxKey != $recLevel - 1) {
		$tmp = $tableList[$recLevel - 1];
		$tableList[$recLevel - 1] = $tableList[$maxKey];
		$tableList[$maxKey] = $tmp;
	}
}

/**
 * @brief Counts the number of dependant WHERE conditions that match a given list of tables
 * @param tableList the table list to resort
 * @param dependantWheres list with all the dependant where
 * @param possibleCombList a list of tables (more precise, the keys in tableList) for which the
 *			   number of dependant WHERE conditions are counted
 * @return array of WHERE conditions that meet the combination list
 * 
 * Taking an array of tables (the keys in tableList), this function counts all the dependant WHERE
 * conditions that can be takeled with the given combination. It will return all the WHERE clauses
 * that match as an array.
 */
function PHPSQLgetDepWhereConds($tableList, $dependantWheres, $possibleCombList) {
	$outArray = array();

	if (empty($dependantWheres)) {
		return $outArray;
	}

	foreach ($dependantWheres as $node) {
		$listOfParticipants = PHPSQLGetListOfParticipants($node);

		$count = 0;
		foreach ($listOfParticipants as $part) {
			$currTable = explode(".", $part['base_expr']);
			$currTable = $currTable[0];
			foreach ($possibleCombList as $key) {
				if (trim($tableList[$key]['alias'], "`") == trim($currTable, "`")) {
					$count++;
					break;
				}
			}
		}

		if ($count == count($listOfParticipants)) {
			array_push($outArray, $node);
		}
	}

	return $outArray;
}

/**
 * @brief Adds the HAVING clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the HAVING clause is added to
 * 
 * Takes the HAVING clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryHaving(&$sqlTree, &$table, &$toThisNode) {
		#only apply the having clause, if all colrefs in here link to the current table. otherwise throw error

	$tblAlias = $table['alias'];
	$tmp = explode('.', $table['name']);
	if (count($tmp) == 1) {
	 $tblDb = false;
	 $tblName = $tmp[0];
 } else {
	 $tblDb = $tmp[0];
	 $tblName = $tmp[1];
 }

 if (!array_key_exists('HAVING', $sqlTree)) {
	 return;
 }

 $countColrefs = 0;
 $countThisColrefs = 0;
 foreach ($sqlTree['HAVING'] as $node) {
	 if ($node['expr_type'] == 'colref') {
		 $tmp = explode('.', $node['base_expr']);
		 if ($tmp[0] == $tblAlias || $tmp[0] == $tblName) {
			$countThisColrefs++;
		}

		$countColrefs++;
	}
}

if ($countColrefs == $countThisColrefs) {
	#construct the HAVING part
	if (!array_key_exists('HAVING', $toThisNode)) {
	 $toThisNode['HAVING'] = array();
 }

 $toThisNode['HAVING'] = $sqlTree['HAVING'];

 unset($sqlTree['HAVING']);
} else {
	throw new RuntimeException('Inner Query Having: You have hit a ot yet supported feature');
}
}

/**
 * @brief Adds the LIMIT clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the LIMIT clause is added to
 * 
 * Takes the LIMIT clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryLimit(&$sqlTree, &$table, &$toThisNode, $recLevel) {
		#only apply the limit clause

	if (!array_key_exists('LIMIT', $sqlTree) || $recLevel > 0) {
		return;
	}

	#construct the LIMIT part
	if (!array_key_exists('LIMIT', $toThisNode)) {
		$toThisNode['LIMIT'] = array();
	}

	$toThisNode['LIMIT'] = $sqlTree['LIMIT'];

	unset($sqlTree['LIMIT']);
}

/**
 * @brief Adds the INDEX clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the INDEX clause is added to
 * 
 * Takes the INDEX clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryIndex(&$sqlTree, &$table, &$toThisNode) {
		#only apply the limit clause

	if (!array_key_exists('USE INDEX', $sqlTree) && !array_key_exists('IGNORE INDEX', $sqlTree)) {
	 return;
 }

 if (array_key_exists('USE INDEX', $sqlTree)) {
	 $toThisNode['USE INDEX'] = $sqlTree['USE INDEX'];
 }

 if (array_key_exists('IGNORE INDEX', $sqlTree)) {
	 $toThisNode['IGNORE INDEX'] = $sqlTree['IGNORE INDEX'];
 }
}

/**
 * @brief Adds the OPTIONS clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the OPTIONS clause is added to
 * 
 * Takes the OPTIONS clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryOptions(&$sqlTree, &$table, &$toThisNode) {
		#only apply the limit clause

	if (!array_key_exists('OPTIONS', $sqlTree)) {
	 return;
 }

		#construct the HAVING part
 if (!array_key_exists('OPTIONS', $toThisNode)) {
	 $toThisNode['OPTIONS'] = array();
 }

 $toThisNode['OPTIONS'] = $sqlTree['OPTIONS'];

 unset($sqlTree['OPTIONS']);
}

/**
 * @brief Rewrites the WHERE tree to reflect proper aliasing of the columns
 * @param node current node to rewrite aliases
 * @param tableList the sorted table list to retrieve the table names
 * @param recLevel current recursive level
 * @param toThisNode the node where the aliases are rewritten to
 * 
 * Rewrites the aliases in the WHERE tree node to properly reflect the table aliases. 
 * Basically this just adds `s around the table names (OK it does slightly more...).
 */
function PHPSQLrewriteAliasWhere(&$node, $tableList, $recLevel, &$toThisNode) {
	$new_base_expr = "";

	$listOfCols = array();
	if ($toThisNode !== false && $toThisNode['sub_tree'] !== false) {
		//if(empty($toThisNode['sub_tree']['SELECT'])) {
		if(!empty($toThisNode['sub_tree']['FROM'][0]['sub_tree'])) {
			//TODO: Solve quick fix - introduced through this query:
			//select `b`.x, `b`.y, `b`.z, `b`.vx, `b`.vy, `b`.vz from Bolshoi.particles416 as `b`, (select x, y, z from Bolshoi.BDMV where snapnum=416 order by Mvir desc limit 1) as `a` where b.x between a.x - 25 and a.x + 25 and b.y between a.y - 25 and a.y + 25 and b.z > a.z-25
			$toThisNode['sub_tree']['SELECT'] = $toThisNode['sub_tree']['FROM'][0]['sub_tree']['SELECT'];
		}

		foreach ($toThisNode['sub_tree']['SELECT'] as $selNode) {
			array_push($listOfCols, $selNode);
		}
	}

	#rewrite node to properly alias involved columns
	if ($node['sub_tree'] !== false && $node['expr_type'] != "subquery") {
		foreach ($node['sub_tree'] as $key => &$subnode) {
			if ($subnode['sub_tree'] !== false) {
				PHPSQLrewriteAliasWhere($subnode, $tableList, $recLevel, $toThisNode);
			}

			if ($subnode['expr_type'] == 'colref') {
				//handle "," that end up here in functions with multiple variables - just ignore them
				if($subnode['base_expr'] === ",") {
					$new_base_expr .= $subnode['base_expr'];
					continue;
				}

				$tmp = explode('.', $subnode['base_expr']);
				if (count($tmp) < 1) {
					$currTable = false;
					$currCol = $tmp[0];
				} else {
					$currTable = $tmp[0];
					$currCol = implode(".", array_slice($tmp, 1));
				}

				if ($currTable !== false) {
					#search for the table in the tablelist
					foreach ($tableList as $tblKey => $currTbl) {
						if (trim($currTbl['alias'], '`') == trim($currTable, "`")) {
							break;
						}
					}

					if ($tblKey > $recLevel) {
						#rewrite name of where_node to properly alias it
						#rewrite name to alias name if needed

//						var_dump($listOfCols);
//						var_dump($currCol);
						foreach ($listOfCols as $selNode) {
							if (trim($selNode['base_expr'], ' ') == $currCol || trim($selNode['alias'], '` ') == $currCol) {
								$tmp = explode(".", trim($selNode['alias'], '`'));
								if (count($tmp) > 1) {
									#search for proper name in the subquery tree
									foreach ($toThisNode['sub_tree']['FROM'] as $subTreeNode) {
										if ($subTreeNode['alias'] == $currTable) {
											#now that the subtree is found, loop through the selects and find the 
											#node we are looking for
											#is this the lowest node? then there is no sub_tree to be found, handle things differntly
											if ($subTreeNode['sub_tree'] !== false) {
												$parseThisNode = $subTreeNode['sub_tree']['SELECT'];
											} else {
												$parseThisNode = $toThisNode['sub_tree']['SELECT'];
											}

											foreach ($parseThisNode as $selectNodes) {
												$selTmp = explode(".", $selectNodes['base_expr']);
												if (count($selTmp) > 1) {
													$selColName = trim($selTmp[1]);
												} else {
													$selColName = trim($selTmp[0]);
												}

												if ($selColName === $tmp[1]) {
													$tmp[0] = $selectNodes['alias'];
													break;
												}
											}
											break;
										}
									}
								}

								$subnode['base_expr'] = trim($tmp[0], "`");

								break;
							}
						}

						$subnode['base_expr'] = '`' . trim($tableList[$tblKey]['alias'], "`") . '`.`' . $subnode['base_expr'] . '`';
						//$subnode['base_expr'] = '`' . $subnode['base_expr'] . '`';
					}
				}
			}
			if($subnode['expr_type'] === 'function' && strpos($subnode['base_expr'], "(") === false) {
					if(!empty($node['sub_tree'][$key+1]) && strpos($node['sub_tree'][$key+1]['base_expr'], "(") === false) {
						$new_base_expr .= " " . $subnode['base_expr'] . "()";
					} else {
						$new_base_expr .= " " . $subnode['base_expr'];
					}
			} else {
				$new_base_expr .= " " . $subnode['base_expr'];
			}
		}

		$node['base_expr'] = "( " . $new_base_expr . " )";
	}
}

/**
 * @brief Adds the WHERE clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the WHERE clause is added to
 * @param tableList the sorted table list
 * @param recLevel current recursion level
 * @param currInnerNode subquery node for rewriting the aliases
 * 
 * Takes the WHERE clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryWhere(&$sqlTree, &$table, &$toThisNode, $tableList, $recLevel, &$currInnerNode) {
	if (empty($table['where_cond'])) {
		$toThisNode['WHERE'] = array();
		unset($toThisNode['WHERE']);
		return;
	}

	#construct the WHERE part
	if (!array_key_exists('WHERE', $toThisNode) || $toThisNode['WHERE'] == NULL) {
		$toThisNode['WHERE'] = array();
	}

	#add the stuff in the where_cond to the query and remove from the complete tree
	$first = true;
	$oldOperatorNode = false;
	foreach ($table['where_cond'] as $key => $node) {
		PHPSQLrewriteAliasWhere($node, $tableList, $recLevel, $currInnerNode);

		#generate an AND node if needed
		if(array_key_exists('oldKey', $node)) {
			if($node['oldKey'] == 0)
				$oldKey = 0;
			else
				$oldKey = $node['oldKey'] - 1;
		} else {
			$oldKey = false;
		}

		$operatorNode = false;
		if (array_key_exists('operator', $node)) {
			$operatorNode = $node['operator'];
		} else if ($node['expr_type'] == "operator" && $key == count($table['where_cond']) - 1) {
			continue;
		} else if ($node['expr_type'] == "operator" && strtolower($node['base_expr']) == 'and') {
			$oldOperatorNode = $node;
			continue;
		}

		if($first !== true && $operatorNode !== false) {
			array_push($toThisNode['WHERE'], $operatorNode);
		} else if ($oldOperatorNode !== false) {
			//in rare cases this is needed, when things mingle too much.
			//TODO: resolve the need for this!
			array_push($toThisNode['WHERE'], $oldOperatorNode);
		}
		array_push($toThisNode['WHERE'], $node);

		$oldOperatorNode = $operatorNode;
		$first = false;
	}
}

/**
 * @brief Adds the ORDER clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the ORDER clause is added to
 * @param tableList the sorted table list
 * @param recLevel current recursion level
	* 
 * Takes the ORDER clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryOrder(&$sqlTree, &$table, &$toThisNode, &$tableList, $recLevel) {
	$tblAlias = $table['alias'];
	$tmp = explode('.', $table['name']);
	if (count($tmp) == 1) {
		$tblDb = false;
		$tblName = $tmp[0];
	} else {
		$tblDb = $tmp[0];
		$tblName = $tmp[1];
	}

	if (!array_key_exists('ORDER', $sqlTree)) {
		return;
	}

	#construct the ORDER part
	if (!array_key_exists('ORDER', $toThisNode)) {
		$toThisNode['ORDER'] = array();
	}

	foreach ($sqlTree['ORDER'] as $count => $node) {
		//fix some stupidity by the parser. we could fix the parser as well, but let's do it here
		$node['base_expr'] = str_replace("ASC", "", $node['base_expr']);
		$node['base_expr'] = str_replace("DESC", "", $node['base_expr']);
		$node['base_expr'] = trim($node['base_expr']);

		#parse the order by (since the parser somehow messes up)
		$phpParse = new PHPSQLParser();
		$parsed = array();
		$parsed[0] = $phpParse->process_select_expr($node['base_expr']);

		$tmpArray = array();
		$countDep = PHPSQLcollectColumns($parsed, $tblDb, $tblName, trim($tblAlias, '`'), $tmpArray, $tableList, $recLevel);

		if ($countDep == 0) {
			$node['expr_type'] = $parsed[0]['expr_type'];

			#it might be, that this column has been aliased, so take care of that
			$find = false;
			foreach ($toThisNode['SELECT'] as $selNode) {
				if (trim($selNode['base_expr'], ' ') == $parsed[0]['base_expr'] ||
							 trim($selNode['alias']) == trim($node['base_expr'])) {
					$node['alias'] = $selNode['alias'];
					$find = true;
					break;
				}
			}

			if ($find === false) {
				//it might be, that this column is actually a column referencing another table than the one beeing processed
				//now. so go through the list of columns selected, and see if this is the case, otherwise add the column
				$currTblAlias = $toThisNode['FROM'][0]['alias'];
				$tmp = explode('.', $currTblAlias);
				if (count($tmp) == 1) {
					$currTblDb = false;
					$currTblName = $tmp[0];
				} else {
					$currTblDb = $tmp[0];
					$currTblName = $tmp[1];
				}

				//find the correspinding column
				$columnArray = array_merge($sqlTree['SELECT'], $toThisNode['SELECT']);
				$node['alias'] = false;
				foreach($columnArray as $column) {
					if($column['base_expr'] === $parsed[0]['base_expr'] || $column['alias'] === $parsed[0]['base_expr']
						 || strpos($column['base_expr'], "*") !== false) {
						//found!
						//compare with currently processed table
						$currColExpr = $column['base_expr'];
						$tmp = explode('.', $currColExpr);
						if (count($tmp) == 1) {
							$currColTbl = false;
							$currColName = $tmp[0];
						} else {
							$currColTbl = $tmp[0];
							$currColName = $tmp[1];
						}

						if($currColTbl === false || trim($currColTbl, "`") === trim($currTblName, "`")) {
							//process this order by...
							$node['alias'] = $parsed[0]['alias'];
						} else if (strpos($column['base_expr'], "*") !== false) {
							//select ALL columns could match the next one as well... continue looking
							continue 1;
						} else {
							continue 2;
						}
					}
				}

				//if we endup here, this is a column that was not aliased and not found in the SELECT list
				//use this if it has no reference to a table
				if(strpos($parsed[0]['alias'], ".") === false) {
					$node['alias'] = $parsed[0]['alias'];
				}
			}

			#check if this column already exists in the select tree
			$findNode = false;
			foreach ($toThisNode['SELECT'] as $selNode) {
				if (trim($selNode['base_expr'], ' ') == trim($node['base_expr'], ' ') ||
						trim($selNode['alias']) == trim($node['base_expr'])) {
					$findNode = $selNode;
					#$selNode['order_clause'] = $node;
					break;
				}
			}

			if ($findNode === false) {
				$tmp = explode(".", $node['base_expr']);
				#this handles a special case, where DBName.Table name comes up. We need to exclude this
				$tmpTblAlias = explode(".", $tblAlias);

				#check on recLevel because unaliased order bys apply to top level table (and sql parser decides which one to take)
				if (count($tmpTblAlias) == 1 && count($tmp) == 1 && !is_numeric($node['base_expr']) && $recLevel == 0) {
					$node['base_expr'] = $tblAlias . "." . $node['base_expr'];
					$node['alias'] = "`" . $tblAlias . "." . trim($node['alias'], "`") . "`";
					$parsed[0]['base_expr'] = $node['base_expr'];
					$parsed[0]['alias'] = $node['alias'];
				} else if (trim($tmp[0], "` ") == $tblDb || trim($tmp[0], "` ") == $tblAlias || count($tmp) == 1) {
					$parsed[0]['order_clause'] = $node;
					array_push($toThisNode['SELECT'], $parsed[0]);
				} else {
					$findNode = true;
					//check if this column applies to the current table that is beeing processed
					foreach($sqlTree['SELECT'] as $selNode) {
						if(trim($selNode['base_expr'], ' ') == trim($node['base_expr'], ' ') ||
							 trim($selNode['alias']) == trim($node['base_expr'])) {

							$tmp1 = explode(".", $selNode['base_expr']);

							if(count($tmp1) > 1 && (trim($tmp1[0], "` ") == $tblDb || trim($tmp1[0], "` ") == $tblAlias)) {
								$parsed[0]['order_clause'] = $node;
								array_push($toThisNode['SELECT'], $parsed[0]);
								$findNode = false;
							}

							break;
						}
					}
				}
			}

			if($findNode !== true) {
				array_push($toThisNode['ORDER'], $node);

				unset($sqlTree['ORDER'][$count]);
			}
		}
	}

	if (empty($sqlTree['ORDER'])) {
		unset($sqlTree['ORDER']);
	}

	if (empty($toThisNode['ORDER'])) {
		unset($toThisNode['ORDER']);
	}
}

/**
 * @brief Adds the GROUP clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the GROUP clause is added to
 * @param tableList the sorted table list
 * @param recLevel current recursion level
	* 
 * Takes the GROUP clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryGroup(&$sqlTree, &$table, &$toThisNode, &$tableList, $recLevel) {
	$tblAlias = $table['alias'];
	$tmp = explode('.', $table['name']);
	if (count($tmp) == 1) {
	 $tblDb = false;
	 $tblName = $tmp[0];
 } else {
	 $tblDb = $tmp[0];
	 $tblName = $tmp[1];
 }

 if (!array_key_exists('GROUP', $sqlTree)) {
	 return;
 }

		#construct the GROUP part
 if (!array_key_exists('GROUP', $toThisNode)) {
	 $toThisNode['GROUP'] = array();
 }

 foreach ($sqlTree['GROUP'] as $count => $node) {
	#parse the group by (since the parser somehow messes up)
	 $phpParse = new PHPSQLParser();
	 $parsed = array();
	 $parsed[0] = $phpParse->process_select_expr($node['base_expr']);

	 $tmpArray = array();
	 $countDep = PHPSQLcollectColumns($parsed, $tblDb, $tblName, trim($tblAlias, '`'), $tmpArray, $tableList, $recLevel);

	 if ($countDep == 0) {
		 $node['expr_type'] = $parsed[0]['expr_type'];
		 $node['alias'] = $parsed[0]['alias'];

		 array_push($toThisNode['GROUP'], $node);

			#check if this column already exists in the select tree
		 $findNode = false;
		 foreach ($toThisNode['SELECT'] as &$selNode) {
			if (trim($selNode['base_expr'], ' ') == trim($node['base_expr'], ' ') ||
					trim($selNode['alias']) == trim($node['base_expr'])) {
				$findNode = $selNode;
				#$selNode['order_clause'] = $node;
				break;
			}
		}

		if ($findNode === false) {
			$parsed[0]['group_clause'] = $node;
			array_push($toThisNode['SELECT'], $parsed[0]);
		}

		unset($sqlTree['GROUP'][$count]);
	}
}

if (empty($sqlTree['GROUP'])) {
	unset($sqlTree['GROUP']);
}

if (empty($toThisNode['GROUP'])) {
	unset($toThisNode['GROUP']);
}
}

/**
 * @brief Adds the UPDATE clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the UPDATE clause is added to
	* 
 * Takes the UPDATE clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryUpdate(&$sqlTree, &$table, &$toThisNode) {
	if (array_key_exists('UPDATE', $sqlTree)) {
	 throw new RuntimeException('Update statement: Not yet handled');
 }
}

/**
 * @brief Adds the FROM clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the FROM clause is added to
 * @param tableList the sorted table list
 * @param recLevel current recursion level
 * 
 * Takes the FROM clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQueryFrom(&$sqlTree, &$table, &$toThisNode, $tableList, $recLevel) {
	$tblAlias = $table['alias'];
	$tmp = explode('.', $table['name']);
	if (count($tmp) == 1) {
		$tblDb = false;
		$tblName = $tmp[0];
	} else {
		$tblDb = $tmp[0];
		$tblName = $tmp[1];
	}

	if (!array_key_exists('FROM', $sqlTree)) {
		return;
	}

	#construct the FROM part
	if (!array_key_exists('FROM', $toThisNode)) {
		$toThisNode['FROM'] = array();
	}

	foreach ($sqlTree['FROM'] as $key => $node) {
		$tmp = explode('.', $node['table']);

		if (count($tmp) == 1) {
			$currDb = false;
			$currTable = $tmp[0];
		} else {
			$currDb = $tmp[0];
			$currTable = $tmp[1];
		}

		#check if this is the right table
		if ($node['alias'] == $tblAlias || (empty($node['alias']) && ($currTable == $tblAlias  || ($currTable == $tblName && $currDb == $tblDb)))) {
			#create a copy of this node
			$nodeCopy = $node;

			#is there a USING flag?
			if ($nodeCopy['ref_type'] == 'USING' || $nodeCopy['ref_type'] == 'ON') {
				#check if this column part of the select clause
				$found = 0;
				foreach ($toThisNode['SELECT'] as $selNode) {
					$tmp = explode('.', $selNode['base_expr']);
					if ($nodeCopy['ref_clause'] == $tmp[0] || $nodeCopy['ref_clause'] == $tmp[1]) {
						$found = 1;
						break;
					}
				}

				if ($found == 0) {
					#not found, add to the select list
					$tmp = explode('.', $nodeCopy['ref_clause']);

					$newSelNode = array();
					$newSelNode['expr_type'] = 'colref';

					if (count($tmp) == 1) {
						$newSelNode['alias'] = '`' . $tblAlias . '.' . $tmp[0] . '`';
						$newSelNode['base_expr'] = $tblAlias . '.' . $tmp[0];
					} else {
						$newSelNode['alias'] = '`' . $tblAlias . '.' . $tmp[1] . '`';
						$newSelNode['base_expr'] = $tblAlias . '.' . $tmp[1];
					}

					$newSelNode['sub_tree'] = false;

					array_push($toThisNode['SELECT'], $newSelNode);
				}

				#remove the reference to the USING from the copy
				$nodeCopy['ref_type'] = '';
				$nodeCopy['ref_clause'] = '';
			}

			$nodeCopy['join_type'] = 'JOIN';
			if ($nodeCopy['alias'] == $nodeCopy['table'])
				$nodeCopy['alias'] = "";

			array_push($toThisNode['FROM'], $nodeCopy);
		}
	}
}

#collect all participating nodes from the sql tree
#this counts in the select tree node all the number of columns that lie in different tables than the given one
/**
 * @brief Collects all participating nodes from the SQL tree for a given table name or alias
 * @param sqlTree the SQL tree which is rewritten
 * @param tblDb database name
 * @param tblName table name
 * @param tblAlias table alias name
 * @param returnArray return all the found columns into this array
 * @param tableList the sorted table list
 * @param recLevel current recursion level
 * @param startBrach true if this is the first call to this function, false if it is in a recursion
 * @return returns the number of columns that lie in a different table than the one given, -1 if none are found
 * 
 * Counts all the columns corresponding to a given table name or alias. The columns that correspond to the table are placed
 * into the returnArray. The function returns the number of columns that are different to the specified table.
 */
function PHPSQLcollectColumns($sqlSelect, $tblDb, $tblName, $tblAlias, &$returnArray, &$tableList, $recLevel, $startBranch = true) {
	$countDiffTables = 0;
	$tblAlias = str_replace("`", "", $tblAlias);

	$workload = array();

	if (array_key_exists('SELECT', $sqlSelect)) {
		$workload = $sqlSelect['SELECT'];
	} else {
		$workload = $sqlSelect;
	}

	foreach ($workload as $node) {
		if ($node['expr_type'] != 'colref' && $node['sub_tree'] !== false) {
			$countDiffTables += PHPSQLcollectColumns($node['sub_tree'], $tblDb, $tblName, $tblAlias, $returnArray, $tableList, $recLevel, false);

			if ($startBranch === true && $countDiffTables == 0) {
				array_push($returnArray, $node);
			}
		} else if ($node['expr_type'] == 'colref' || ($node['base_expr'] === '*' && $startBranch === true)) {
			$tmp = explode('.', trim($node['base_expr'], '()'));
			if (count($tmp) == 1) {
				$currCol = $tmp[0];
				$currTable = false;
				$currDB = false;
			} else if (count($tmp) == 2) {
				$currTable = trim($tmp[0], "`");
				$currCol = $tmp[1];
				$currDB = false;
			} else {
				$currDB = trim($tmp[0], "`");
				$currTable = trim($tmp[1], "`");
				$currCol = $tmp[2];
			}

//	    if ($currTable == $tblAlias || ($tblAlias == $tblDb . "." . $tblName && $currTable === false) || $currTable == $tblName) {
			if ($currTable == $tblAlias || ($currTable === false) || $currTable == $tblName || 
						 $tblAlias == $currDB . "." . $currTable) {
				if ($startBranch === true) {
					array_push($returnArray, $node);
				}
			} else {
				#check if this table has already been selected and processed. if yes, we can already process it and
				#donot need to wait
				$found = false;
				foreach ($tableList as $key => $tblListNode) {
					#skip everything not yet processed...
					if ($key < $recLevel) {
						continue;
					}

					if ($currTable == $tblListNode['alias']) {
						$found = true;
						break;
					}
				}

				if ($found === false) {
					$countDiffTables += 1;
				}
			}
		}
	}

	return $countDiffTables;
}

/**
 * @brief Adds the SELECT clause to a given node from the SQL tree
 * @param sqlTree the SQL tree which is rewritten
 * @param table currently treated table
 * @param toThisNode the node where the SELECT clause is added to
 * @param tableList the sorted table list
 * @param recLevel current recursion level
	* 
 * Takes the SELECT clause from sqlTree and adds it to the current node. 
 */
function PHPSQLaddOuterQuerySelect(&$sqlTree, &$table, &$toThisNode, $tableList, $recLevel) {
	if (!array_key_exists('SELECT', $sqlTree)) {
		return;
	}

	#if the SELECT key does not exist, create it
	if (!array_key_exists('SELECT', $toThisNode)) {
		$toThisNode['SELECT'] = array();
	}

	$tblAlias = $table['alias'];
	$tmp = explode('.', $table['name']);
	if (count($tmp) == 1) {
		$tblDb = false;
		$tblName = $tmp[0];
	} else {
		$tblDb = $tmp[0];
		$tblName = $tmp[1];
	}

	#collect all columns from the sql tree
	$partList = array();
	PHPSQLcollectColumns($sqlTree, $tblDb, $tblName, trim($tblAlias, '`'), $partList, $tableList, $recLevel);

	foreach ($partList as $node) {
		array_push($toThisNode['SELECT'], $node);

		$key = array_search($node, $sqlTree['SELECT']);
		unset($sqlTree['SELECT'][$key]);
	}

	//if this is recLevel = 0, add all remaining columns as well
	if($recLevel == 0) {
		foreach($sqlTree['SELECT'] as $node) {
			array_push($toThisNode['SELECT'], $node);
		}
	
		$sqlTree['SELECT'] = array();
	}

	#in order for the joins to work properly, collect all the variables that
	#somehow participate in the where
	if (empty($sqlTree['WHERE'])) {
		return;
	}

	$tmpList1 = PHPSQLgetAllColsFromWhere($sqlTree['WHERE'], $tblAlias, false);
	$tmpList2 = PHPSQLgetAllColsFromWhere($sqlTree['WHERE'], $tblName, true);

	//this handles (crudely) the case, when no alias is given to the DB
	if($table['name'] == $table['alias']) {
		$tmpList3 = PHPSQLgetAllColsFromWhere($sqlTree['WHERE'], "", true);
		$tmpList2 = array_merge($tmpList2, $tmpList3);
	}
 
	$listOfWhereCols = array_merge($tmpList1, $tmpList2);

	foreach ($listOfWhereCols as $cols) {
		$find = false;
		foreach ($toThisNode['SELECT'] as &$node) {
		if (trim(str_replace("`", "", $node['base_expr']), ' ') == trim(str_replace("`", "", $cols['base_expr']), ' ')) {
			$find = true;
			break;
			}
		}

		if ($find === false) {
			#mark this select as comming from a where
			$cols['where_col'] = true;

			array_push($toThisNode['SELECT'], $cols);
		}
	}
}

/**
 * @brief Extracts all the participating columns from the WHERE tree for a given table
 * @param whereTree the WHERE part of the SQL tree
 * @param table currently treated table
 * @param remove the table identifier infront of the name
 * @return array with participating columns
 * 
 * Extracts all the participating columns from the WHERE tree for a given table and returns
 * an array with all the columns. It also strips the involved columns from the table / alias
 * name.
 */
function PHPSQLgetAllColsFromWhere($whereTree, $table, $removeTableName) {
	$returnArray = array();

	foreach ($whereTree as $node) {
		if (is_array($node['sub_tree']) && $node['expr_type'] != "subquery") {
			$tmpArray = PHPSQLgetAllColsFromWhere($node['sub_tree'], $table, $removeTableName);
			$returnArray = array_merge($returnArray, $tmpArray);
		}

		if($node['expr_type'] != 'colref')
			continue;

		$tmp = explode('.', $node['base_expr']);
		$currTable = $tmp[0];

		#getting rid of the table/alias name in the column description (but only if a table
		#name is provided $table)
		if(!empty($table) && count($tmp) > 1 && $removeTableName === true) {
			$node['base_expr'] = implode(".", array_slice($tmp, 1));
		}

		if ($table == $currTable || (count($tmp) == 1 && $table === "")) {
			$newNode = array();
			$newNode['expr_type'] = $node['expr_type'];
			$newNode['alias'] = "`" . $node['base_expr'] . "`";
			$newNode['base_expr'] = $node['base_expr'];
			$newNode['sub_tree'] = $node['sub_tree'];
			array_push($returnArray, $newNode);
		}
	}

	return $returnArray;
}

/**
 * @brief Determine the starting table for which to start with the recursive building of the nested tree
 * @param tableList list of table
 * @param headNodeTables list of tables that are completely located on the head node and are not sharded
 * @return sorted table list according to the number of independant WHERE statements
 * 
 * This function sorts the table list according to the number of independant WHERE conditions in ascending
 * order (the outer query is assumend to be least selective). The recursive SQL generation algorithm will then
 * go through the list sequentially in the given order.
 */
function PHPSQLdetStartTable($tableList, $headNodeTables = array(), $dependantList = array()) {
	$maxVal = -1;
	$currTable = NULL;


	#sort the table and return
	$condCount = array();
	foreach ($tableList as $key => $table) {
	 if ($table['name'] != "DEPENDENT-SUBQUERY") {
		 $condCount[$key] = $table['cond_count'];

		 //check if this is a table that is only on the head node
		 //Quick fast check for this
 	 	 $found = false;
 	 	 $tableName = str_replace("`", "", $table['name']);
 	 	 $posDot = strpos($tableName, ".");
		 foreach($headNodeTables as $headNodeTable) {
		 	$posTable = strpos($tableName, $headNodeTable);
		 	if($posTable !== false) {
		 		if($posDot === false) {
		 			if(strlen($tableName) === strlen($headNodeTable)) {
		 				$found = true;
		 			}
		 		} else {
		 			if(strlen($tableName) - $posDot - 1 === strlen($headNodeTable)) {
		 				$found = true;
		 			} else if ($posTable == 0 && strlen($headNodeTable) == $posDot) {
		 				$found = true;
		 			}
		 		}

		 		if($found === true) {
					$condCount[$key] = 99999999 + $table['cond_count'];
					break;
		 		}
		 	}
		 }

		 //go through the dependant list and add another point to each table that shows up on the right of a
		 //between query - there it makes sense to rank the table somewhat higher
		 if(!empty($dependantList)) {
		 	$condCount[$key] += PHPSQLdetStartTableCountBetween($dependantList, $table['alias']);
		 	$condCount[$key] += PHPSQLdetStartTableCountBetween($dependantList, $table['name']);
		 }
	 } else {
		 $condCount[$key] = 999999;
	 }
 }

 array_multisort($condCount, SORT_ASC, $tableList);

 return $tableList;
}

function PHPSQLdetStartTableCountBetween($nodes, $tableName) {
	$count = 0;
	$found = false;

	foreach($nodes as $node) {
		if(!empty($node['sub_tree'])) {
			$count += PHPSQLdetStartTableCountBetween($node['sub_tree'], $tableName);
		}

		if($node['expr_type'] === "operator" && $node['base_expr'] === "BETWEEN") {
			$found = true;
		} else if ($found === true) {
			//check if this is a column of the current table
			if(strpos($node['base_expr'], $tableName) !== false) {
				$count += 1;
			}
		}
	}

	return $count;
}

/**
 * @brief Count the number of WHERE conditions in a given array (where_cond in tableList)
 * @param tableList list of table
 * 
 * This function counts all the WHERE conditions in the table list that are within the
 * where_cond attribute of the array. It will add a new key to the array storing the number
 * count.
 */
function PHPSQLCountWhereConditions(&$tableList) {
	foreach ($tableList as &$table) {
	 $table['cond_count'] = 0;

	 if (!array_key_exists('where_cond', $table)) {
		 continue;
	 }

	 $table['cond_count'] = PHPSQLrecursCountWhereCond($table['where_cond']);
 }
}

/**
 * @brief Recursively count the number of WHERE conditions in a given array (where_cond in tableList)
 * @param node current node
 * @return number of nodes
 * 
 * This function counts all the WHERE conditions in the table list that are within the
 * where_cond attribute of the array. 
 */
function PHPSQLrecursCountWhereCond($nodes) {
	$result = 0;

	foreach ($nodes as $currNode) {
		if (is_array($currNode['sub_tree'])) {
			if ($currNode['expr_type'] != 'subquery') {
				$result += PHPSQLrecursCountWhereCond($currNode['sub_tree']);
			}
		} else if ($currNode['expr_type'] == 'colref') {
			$result += 1;
		}
	}

	return $result;
}

/**
 * @brief Function finds all the independant WHERE clauses for each table in the SQL tree
 * @param sqlTree SQL parse tree
 * @param tableList table list where the independant queries will be written to
 * @return array with all dependant queries
 * 
 * This function collects all independant WHERE clauses into tableList and will output all
 * dependant clauses (clauses involving more than one table) into the return array.
 */
function PHPSQLGroupWhereCond($sqlTree, &$tableList) {
	if (empty($sqlTree['WHERE'])) {
		return;
	}

	$whereTree = $sqlTree['WHERE'];

	$dependantWheres = array();

	#loop through all where nodes and link the non operator nodes with the preceding operator
	$oldNode = false;
	foreach ($whereTree as $key => $node) {
		if ($node['expr_type'] === "operator") {
			$oldNode['operator'] = $node;
			continue;
		}

		$oldNode = &$whereTree[$key];
	}

	foreach ($whereTree as $key => $node) {
		#look through all the terms and add the independant ones to the table list
		#if we hit recursion, we check for the topmost terms
		#TODO: strategy to move deeper into the expression trees and collect terms that
		#can be moved out

		if ($node['expr_type'] === "operator") {
			continue;
		}

		$node['oldKey'] = $key;

		$currParticipants = PHPSQLGetListOfParticipants($node);

		#check if this is a condition with one or more participants
		#trim () because we could be dealing with a column in a function
		if(empty($currParticipants)) {
			$table = false;
		} else {
			$table = PHPSQLParseColumnName(trim($currParticipants[0]['base_expr'], "()"));
		}
		if (count($table) > 1) {
			$table = $table[0];
		} else {
			$table = false;
		}

		foreach ($currParticipants as $currCol) {
			#we could be dealing with a column in a function
			$currTable = explode(".", trim($currCol['base_expr'], "()"));
			if (count($currTable) > 1) {
				$currTable = $currTable[0];
			} else {
				$currTable = false;
			}

			if ($table != $currTable && $table !== false && $currTable !== false) {
				array_push($dependantWheres, $node);
				continue 2;
			}
		}

		#find this table in the table list and add condition there
		foreach ($tableList as &$currTableInList) {
			if (trim($table, '`') == trim($currTableInList['alias'], '`') || $table === false) {
				if (!array_key_exists('where_cond', $currTableInList)) {
					$currTableInList['where_cond'] = array();
				}

				if (array_key_exists('operator', $node)) {
					$operator = $node['operator'];
					unset($node['operator']);
					array_push($currTableInList['where_cond'], $node);
					array_push($currTableInList['where_cond'], $operator);
				} else {
					array_push($currTableInList['where_cond'], $node);
			}
			break;
		}
	}
}

return $dependantWheres;
}

/**
 * @brief Get all columns in the given node
 * @param node node to retrieve the columns from
 * @return array with all participating columns
 * 
 * This function collects all columns that are included in the given node of the tree.
 */
function PHPSQLGetListOfParticipants($node) {
	$partArray = array();

	if (is_array($node['sub_tree'])) {
		#handle subqueries in WHERE statements differntly
		if ($node['expr_type'] == 'subquery') {

		} else {
			foreach ($node['sub_tree'] as $subTreeNode) {
				$currParticipants = PHPSQLGetListOfParticipants($subTreeNode);

				if (is_array($currParticipants)) {
					foreach($currParticipants as $currNode) {
						//handle "," that can show up in functions terms and skip them
						if($currNode['base_expr'] === ",") {
							continue;
						}

						$found = false;
						foreach($partArray as $currPartNode) {
							if($currNode['base_expr'] === $currPartNode['base_expr']) {
								$found = true;
								break;
							}
						}

						if($found === false) {
							array_push($partArray, $currNode);
						}
					}
				}
			}
		}
	} else if ($node['expr_type'] === "colref") {
		array_push($partArray, $node);
	} else {
		$partArray = NULL;
	}

	return $partArray;
}

/**
 * @brief Find all the columns in a SQL query and save them in the tableList with the according table
 * @param sqlTree SQL query tree
 * @param listOfTables list of tables to save the columns to
 * 
 * Find all the columns in a SQL query and save them in the tableList with the according table.
 */
function PHPSQLGroupTablesAndCols($sqlTree, &$listOfTables) {
	$selectTree = $sqlTree['SELECT'];

	if(empty($sqlTree['FROM'])) {
		return;
	}

	$fromTree = $sqlTree['FROM'];

	foreach ($fromTree as $currTable) {
		$table = array();
		$table['name'] = $currTable['table'];
		$table['alias'] = $currTable['alias'];
		$table['sel_columns'] = array();
		array_push($listOfTables, $table);
	}

	#put dependant queries at the end of the list
	$currIndex = count($listOfTables) - 1;
	foreach ($listOfTables as $key => $node) {
		if ($node['name'] == 'DEPENDENT-SUBQUERY' && $key < $currIndex) {
			$tmpNode = $listOfTables[$currIndex];
			$listOfTables[$currIndex] = $node;
			$listOfTables[$key] = $tmpNode;

			$currIndex--;
		}
	}

	#link the columns with the tables
	foreach ($selectTree as $currColumn) {
		$fields = PHPSQLParseColumnName($currColumn['alias']);

		$currAlias = trim($fields[0], ' `');
		foreach ($listOfTables as &$currTable) {
			if ($currTable['alias'] === $currAlias || $currTable['alias'] === $currTable['name']) {
				array_push($currTable['sel_columns'], $currColumn);
				break;
			}
		}
	}
}

/**
 * @brief This function takes the parse tree, looks at the where clause and puts brackets around each logical element.
 * @param sqlTree SQL query tree
 * @return rewritten SQL Tree
 * 
 * This function takes the parse tree, looks at the where clause and puts brackets around each logical element.
 */
function PHPSQLGroupWhereTerms($sqlTree) {

	if (empty($sqlTree['WHERE'])) {
		return $sqlTree;
	}

	$whereTree = $sqlTree['WHERE'];

	$newTree = array();

	PHPSQLParseWhereTokens($whereTree, $newTree);

	$sqlTree['WHERE'] = $newTree;

	return $sqlTree;
}


/**
 * @brief This function takes the parse tree, looks at the where clause and puts brackets around each logical element.
 * @param tree SQL query tree node
 * @param newTree where the rewritten tree is written to
 * 
 * This function takes the parse tree, looks at the where clause and puts brackets around each logical element.
 */
function PHPSQLParseWhereTokens($tree, &$newTree) {
	//first we walk the tree and gather terms. These are BETWEEN statements and anything that has a
	//equal sign inside (or in other words, everything that is delimited by an OR or AND)
	$groupedTree = array();

	PHPSQLParseWhereTokens_groupTerms($tree, $groupedTree);

	//we now gather all AND expressions and put brackets around them.
	$groupedAndTree = array();
	PHPSQLParseWhereTokens_groupANDExpressions($groupedTree['sub_tree'], $groupedAndTree);

	//all nodes have been gathered into one sub-tree above, that we actually don't need
	//move all nodes one level up
	foreach($groupedAndTree['sub_tree'] as $node) {
		array_push($newTree, $node);
	}
}

/**
 * @brief This function takes a tree with already grouped arithmetric terms and groups all
 *        AND terms
 * @param tree SQL query tree node
 * @param newTree where the rewritten tree is written to
 * 
 * This function takes a tree with grouped arithmetric terms and groups all AND terms
 * Example: IN:  ( x = 0.998373 ) or ( ( y = SIN (0.998373) ) and ( z = 0.998373 ) ) 
 *                  and ( z = 43 ) or ( ( ( z = 23 ) and ( z = 4 ) ) or ( x = 1 ) ) 
 *                  or ( y = 34 ) and ( x between 1 and 2 ) or ( z = 1 + 5 * 87.2134 )
 *          OUT: ( x = 0.998373 ) or ( ( ( y = SIN (0.998373) ) and ( z = 0.998373 ) ) and ( z = 43 ) ) 
 *                  or ( ( ( z = 23 ) and ( z = 4 ) ) or ( x = 1 ) ) 
 *                  or ( ( y = 34 ) and ( x between 1 and 2 ) ) or ( z = 1 + 5 * 87.2134 )
 */
function PHPSQLParseWhereTokens_groupANDExpressions($tree, &$newTree) {
	$previousNode = array();
	$foundAnd = false;
	$foundOr = false;

	if(!array_key_exists("sub_tree", $newTree)) {
		$newTree['expr_type'] = "expression";
		$newTree['base_expr'] = "";
		$newTree['sub_tree'] = array();
	}

	foreach ($tree as $term) {
		$processSub = false;
		$currNode = $term;

		//only process sub_trees, that have more than 3 elements inside, otherwise a possible
		//AND term has already been grouped.
		if(array_key_exists("sub_tree", $term) && count($term['sub_tree']) > 3) {
			//only process sub_trees, that have further subtrees (i.e. no end nodes that are
			//already bracketed anyways)
			foreach ($term['sub_tree'] as $node) {
				if(array_key_exists("sub_tree", $node) && $node['sub_tree'] !== false) {
					$processSub = true;
					break;
				}
			}
		}

		if($processSub === true) {
			$currSubTree = array();
			PHPSQLParseWhereTokens_groupANDExpressions($term['sub_tree'], $currSubTree);

			PHPSQLParseWhereTokens_createBaseExpr($currSubTree, $newTree);

			$currNode = $currSubTree;
		}

		//only process if AND is preceeded by a OR
		if($currNode['expr_type'] === "operator" && 
				(strtolower($currNode['base_expr']) === "or" || $currNode['base_expr'] === "||")) {

			$foundOr = true;
		}

		//check if this is an AND
		if($foundOr === true && $currNode['expr_type'] === "operator" && 
				(strtolower($currNode['base_expr']) === "and" || $currNode['base_expr'] === "&&")) {

			$foundAnd = true;
			$foundOr = false;

			$newNode = array();
			$newNode['expr_type'] = "expression";
			$newNode['base_expr'] = "";
			$newNode['sub_tree'] = array();

			array_push($newNode['sub_tree'], $previousNode);
			array_push($newNode['sub_tree'], $currNode);

			$previousNode = $newNode;

			continue;
		}

		//if we are not in a AND clause, save this node in previous node and add any previous node
		//to the array
		if(!empty($previousNode) && $foundAnd === true) {
			array_push($previousNode['sub_tree'], $currNode);

			PHPSQLParseWhereTokens_createBaseExpr($previousNode);

			$foundAnd = false;
			$foundOr = false;
		} else {
			if(!empty($previousNode)) {
				array_push($newTree['sub_tree'], $previousNode);
			}

			$previousNode = $currNode;
		}
	}

	array_push($newTree['sub_tree'], $previousNode);

	if(empty($newTree['base_expr'])) {
		PHPSQLParseWhereTokens_createBaseExpr($newTree);
	}
}

/**
 * @brief This function takes a tree with an arithmetric expression and brackets each term
 * @param tree SQL query tree node
 * @param newTree where the rewritten tree is written to
 * 
 * This function takes a tree with an arithmetric expression and brackets each term.
 * Example: IN:  x=0.998373 or (y=sin(0.998373) and z=0.998373) and z=43 or 
 *                          ((z=23 and z=4) or x=1) or y=34 and x between 1 and 2
 *          OUT: ( ( x = 0.998373 ) or ( ( y = SIN (0.998373) ) and ( z = 0.998373 ) ) 
 *                    and ( z = 43 ) or ( ( ( z = 23 ) and ( z = 4 ) ) or ( x = 1 ) ) 
 *                    or ( y = 34 ) and ( x between 1 and 2 ) )
 */
function PHPSQLParseWhereTokens_groupTerms($tree, &$newTree) {
	$currLeaf = array();
	$isBetween = false;

	$currLeaf['expr_type'] = "expression";
	$currLeaf['base_expr'] = "";
	$currLeaf['sub_tree'] = array();

	if(!array_key_exists("sub_tree", $newTree)) {
		$newTree['expr_type'] = "expression";
		$newTree['base_expr'] = "";
		$newTree['sub_tree'] = array();
	}

	foreach ($tree as $token) {
		//NOTE: Subqueries are treated as normal terms in this context.

		#a normal node - if this node is an AND or OR, split there
		if(array_key_exists('expr_type', $token) && 
				$token['expr_type'] === "operator" &&
				(strtolower($token['base_expr']) === "or" || strtolower($token['base_expr']) === "and")) {

			//is this the and in the between clause? if yes, ignore this
			if(strtolower($token['base_expr']) === "and" && $isBetween === true) {
				$isBetween = false;
			} else {
				//it could be that currLeaf is empty - this is the case if a sub_tree has been added
				//before - just add the operator then
				if(!empty($currLeaf['sub_tree'])) {
					PHPSQLParseWhereTokens_createBaseExpr($currLeaf);

					array_push($newTree['sub_tree'], $currLeaf);
				}

				array_push($newTree['sub_tree'], $token);

				//create new leaf
				$currLeaf['expr_type'] = "expression";
				$currLeaf['base_expr'] = "";
				$currLeaf['sub_tree'] = array();

				continue;
			}
		}

		//is this a between case?
		if(array_key_exists('expr_type', $token) && 
				$token['expr_type'] === "operator" &&
				strtolower($token['base_expr']) === "between") {
			
			$isBetween = true;
		}

		array_push($currLeaf['sub_tree'], $token);
	}

	if(!empty($currLeaf['sub_tree'])) {
		//the last leaf is remaining and wants to be added to the tree as well
		PHPSQLParseWhereTokens_createBaseExpr($currLeaf);

		array_push($newTree['sub_tree'], $currLeaf);
	}

	PHPSQLParseWhereTokens_createBaseExpr($newTree);
}

function PHPSQLParseWhereTokens_createBaseExpr(&$currLeaf) {
	//create the base expression by looping over all nodes (no recursion needed here,
	//since recursive elements already processed above)
	$currLeaf['base_expr'] = "( ";
	foreach ($currLeaf['sub_tree'] as $node) {
		if($node['expr_type'] === 'function') {
			if(strpos($node['base_expr'], "(") === false) {
				$currLeaf['base_expr'] .= $node['base_expr'] . "() ";
			} else {
				$currLeaf['base_expr'] .= $node['base_expr'] . " ";
			}
		} else {
			$currLeaf['base_expr'] .= $node['base_expr'] . " ";
		}
	}
	$currLeaf['base_expr'] .= ")";
}

function PHPSQLParseColumnName($colName) {
	$fields = explode(".", $colName);

	//go through the result and put all quoted elements back together
	$oldKey = false;
	foreach($fields as $key => $field) {
		$posQuote = strpos($field, "`");

		if($posQuote !== false && $posQuote > 0) {
			$fields[$oldKey] .= "." . $field;
			unset($fields[$key]);
		} else {
			$oldKey = $key;
		}
	}

	return $fields;
}

?>
