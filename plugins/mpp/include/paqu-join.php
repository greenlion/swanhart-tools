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

	require_once 'PHP-SQL-Parser/src/PHPSQLParser.php';

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

	//before we start with anything, we are going to rewrite any possitional argument in order by 
	//with the corresponding given column
	$sqlTree = PHPSQLresolvePositionalArguments($sqlTree);

	$subQueries = array();
	if(!empty($sqlTree['FROM'])) {
		$subQueries = collectNodes($sqlTree['FROM'], "subquery");
	}
	if (!empty($sqlTree['WHERE'])) {
		$subQueries = array_merge($subQueries, collectNodes($sqlTree['WHERE'], "subquery"));
	}

	foreach ($subQueries as &$subQuery) {
		$subQuery['table'] = "DEPENDENT-SUBQUERY";
		$subQuery['sub_tree'] = PHPSQLbuildShardQuery($subQuery['sub_tree']);
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
 */
function linkNestedWheresToTree(&$nestedQuery, &$subQueries) {
	if (array_key_exists('WHERE', $nestedQuery)) {
		linkNestedWheresToTree_r($nestedQuery['WHERE'], $subQueries);
	}
}

function linkNestedWheresToTree_r(&$nodes, &$subQueries) {
	foreach($nodes as &$node) {
		if(isSubquery($node)) {
			#find subquery
			foreach ($subQueries as $subQueryNode) {
				if (!array_key_exists('expr_type', $subQueryNode))
					continue;

				if (isSubquery($subQueryNode) && $node['base_expr'] == $subQueryNode['base_expr']) {
					$node['sub_tree'] = $subQueryNode['sub_tree'];
					break;
				}
			}			
		} else if(hasSubtree($node)) {
			linkNestedWheresToTree_r($node['sub_tree'], $subQueries);
		}
	}
}

/**
 * @brief Links the user defined subqueries in the FROM node with the nested tree
 * @param nestedQuery the nested query tree as produced by the implicit join algorithm
 * @param subQueries an array containing all the processed user defined subqueries from WHERE and FROM
 * $subQueryNode['base_expr']
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
		if (isSubquery($subQuery)) {
			#we need to descend further down.
			linkSubqueriesToTree($subQuery['sub_tree'], $subQueries);

			#find subquery
			foreach ($subQueries as $subNode) {
				if (hasAlias($subQuery) && aliasIsEqual($subQuery['alias'], $subNode['alias'])) {
					//push this further down if needed
					foreach($subQuery['sub_tree']['FROM'] as &$currQueryNode) {
						if(isSubquery($currQueryNode)) {
							linkSubqueriesToTree($currQueryNode['sub_tree'], $subQueries);
						}
					}

					$subQuery['sub_tree'] = $subNode['sub_tree'];
					fixSelectsInAliasedSubquery($nestedQuery, $subQuery['sub_tree']);
					break;
				}
			}
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
function fixSelectsInAliasedSubquery(&$selectTree, &$subQuery) {
	//fix possible errors in the aliases when comming from sub-trees
	//create a list of all SELECT columns in sub-trees and compare them with the
	//ones in this selectTree's SELECT statement. If errors in the base_expr are
	//found, correct them accordingly

	//columnList will hold column name as key an corresponding table alias as value

	//due this here again, possible errors might have been induced through tree merger

	$columnList = array();
	$columnAliasList = array();

	$subQueries = collectNodes($selectTree['FROM'], "subquery");

	foreach ($subQueries as $subquery) {
		$currSubNode = $subquery['sub_tree'];
		$currAlias = extractTableAlias($subquery);

		foreach ($currSubNode['SELECT'] as $subCol) {
			$columnList[extractColumnName($subCol)] = $currAlias;
			$columnAliasList[extractColumnName($subCol)] = extractColumnAlias($subCol);
		}
	}

	//go through SELECT of the selectTree and correct all mistakes
	foreach ($selectTree['SELECT'] as &$selNode) {
		$selNodeColName = extractColumnName($selNode);

		if($selNodeColName !== false && array_key_exists($selNodeColName, $columnList) && 
					$columnList[$selNodeColName] === extractTableName($selNode)) {
			setNoQuotes($selNode, array($columnList[$selNodeColName], $columnAliasList[$selNodeColName]));
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
	$currDepQueryNode['expr_type'] = "subquery";
	if(hasAlias($table)) {
		$currDepQueryNode['alias'] = $table['alias'];
		//createAliasNode
	} else {
		$currDepQueryNode['alias'] = createAliasNode(array($table['name']));
	}
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
	if ($currInnerNode !== false && hasSubtree($currInnerNode)) {
		linkInnerQueryToOuter($currOuterQuery, $currInnerNode, $tableList, $recLevel);
	}

	PHPSQLaddOuterQueryUpdate($sqlTree, $table, $currOuterQuery);

	PHPSQLaddOuterQueryGroup($sqlTree, $table, $currOuterQuery, $tableList, $recLevel);
	PHPSQLaddOuterQueryOrder($sqlTree, $table, $currOuterQuery, $tableList, $recLevel);

	PHPSQLaddOuterQueryWhere($sqlTree, $table, $currOuterQuery, $tableList, $recLevel, $currInnerNode);

	PHPSQLaddOuterQueryLimit($sqlTree, $table, $currOuterQuery, $recLevel);
	PHPSQLaddOuterQueryIndex($sqlTree, $table, $currOuterQuery);

	PHPSQLaddOuterQueryOptions($sqlTree, $table, $currOuterQuery);

	if ($recLevel == 0) {
		#only consider these at level 0
		if (!empty($sqlTree['HAVING']))
			throw new RuntimeException('Inner Query HAVING: You have hit a not yet supported feature');
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
	foreach($newQuery['SELECT'] as $key => &$node) {
		if(hasAlias($node)) {
			$colTable = extractTableAlias($node);
			$colName = extractColumnAlias($node);
		} else {
			$colTable = extractTableName($node);
			$colName = extractColumnName($node);
		}

		//first check thoroughly if a column might exist
		foreach($initialQuery['SELECT'] as $initNode) {
			if(columnIsEqual($initNode, $node) ||
					(hasAlias($node) && hasAlias($initNode) &&
					  aliasIsEqual($initNode['alias'], $node['alias'])) ||
					//as in test 35
					(hasAlias($node) && aliasIsEqual(createAliasNode(array(buildEscapedString(array($initNode)))), $node['alias']))) {

				//alias the column to resemble the original requested name
				if(!hasAlias($node)) {
					if(isColref($node)) {
						//if this is a "*" (as in SELECT * FROM) node, no_quotes is not set
						if(isset($initNode['no_quotes'])) {
							$node['alias'] = createAliasNode(array(implodeNoQuotes($initNode['no_quotes'])));
						}
					} else {
						$node['alias'] = createAliasNode(array(buildEscapedString(array($node))));
					}
				}

				continue 2;
			}
		}

		//now do a fuzzy check
		foreach($initialQuery['SELECT'] as $initNode) {
			if(columnIsEqual($initNode, $node, true) ||
					(hasAlias($node) && hasAlias($initNode) &&
					  aliasIsEqual($initNode['alias'], $node['alias'], true))) {

				//alias the column to resemble the original requested name
				if(!hasAlias($node)) {
					if(isColref($node)) {
						//if this is a "*" (as in SELECT * FROM) node, no_quotes is not set
						if(isset($initNode['no_quotes'])) {
							$node['alias'] = createAliasNode(array(implodeNoQuotes($initNode['no_quotes'])));
						}
					} else {
						$node['alias'] = createAliasNode(array(buildEscapedString(array($node))));
					}
				}

				continue 2;
			}
		}
	
		//check if this column is needed in and ORDER BY or GROUP BY statement
		if(array_key_exists('ORDER', $initialQuery)) {
			foreach($initialQuery['ORDER'] as $initNode) {
				if(columnIsEqual($initNode, $node, true)) {
					continue 2;
				} else {
					$colInitTable = extractTableName($initNode);
					$colInitName = extractColumnName($initNode);

					if($colInitName == $colName) {
						continue 2;
					}
				}
			}
		}

		if(array_key_exists('GROUP', $initialQuery)) {
			foreach($initialQuery['GROUP'] as $initNode) {
				if(columnIsEqual($initNode, $node, true)) {
					continue 2;
				} else {
					$colInitTable = extractTableName($initNode);
					$colInitName = extractColumnName($initNode);

					if($colInitName == $colName) {
						continue 2;
					}
				}
			}
		}

		unset($newQuery['SELECT'][$key]);
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
 */
function linkInnerQueryToOuter(&$currOuterQuery, &$currInnerNode, &$tableList, $recLevel) {
	array_push($currOuterQuery['FROM'], $currInnerNode);

	foreach ($currInnerNode['sub_tree']['SELECT'] as $node) {
		$tblAlias = false;

		if(hasAlias($currInnerNode)) {
			$tblAlias = $currInnerNode['alias'];
		}

		//rewrite lower aggregate result into selectable column and add this to the SELECT clause
		//as in cases where the inner query has an expression or function and the outer should just
		//query the results from that expression/function and not evaluate the expression/function again
		if ($node['expr_type'] != 'colref') {
			#find the column participating in the expression/function
			if (!empty($node['sub_tree'])) { #this is needed to handle order by NUMBERs (refering to expression/function)!
				if(!hasAlias($node)) {
					$node['alias'] = createAliasNode(array(buildEscapedString(array($node))));
				}

				//this node can only be available in the "table/subquery" that was issued in $recLevel + 1,
				//otherwise it would not be there. So set the alias to that table
				$tblAlias = $tableList[$recLevel + 1]['node']['alias'];
			}

			//this expression is now to be treated as a normal column, so apply changes
			$node['expr_type'] = 'colref';
			$node['sub_tree'] = false;
			$node['no_quotes'] = array("delim" => ".", "parts" => array(trim($tblAlias['name'], "`"), extractColumnAlias($node)));
			$node['base_expr'] = getBaseExpr($node);
		}

		#if this is a dependant query, properly form the aliased column name for retrieval...
		#check if this has already been aliased
		if ($tblAlias !== false && strpos($tblAlias['no_quotes']['parts'][0], 'agr_')) {
			continue 1;
		} else if(hasAlias($node)) {
			setNoQuotes($node, array(trim($tblAlias['name'], "`"), implodeNoQuotes($node['alias']['no_quotes'])));
		} else {
			//if this is a node with a subtree, create an alias for it
			if(hasSubtree($node)) {
				setNoQuotes($node, array(trim($tblAlias['name'], "`"), buildEscapedString(array($node))));
			} else {
				setNoQuotes($node, array(trim($tblAlias['name'], "`"), implodeNoQuotes($node['no_quotes'])));
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
}


function rewriteWHEREAliasToFirstSubquery(&$tree, $aliasList, $firstSubqueryAlias) {
	foreach($tree as &$node) {
		if(hasSubtree($node)) {
			rewriteWHEREAliasToFirstSubquery($node['sub_tree'], $aliasList, $firstSubqueryAlias);
			$node['base_expr'] = getBaseExpr($node);
		}

		if(isColref($node)) {
			if(count($node['no_quotes']['parts']) > 1) {
				$currTableAlias = $node['no_quotes']['parts'];
				if(!in_array($currTableAlias[0], $aliasList)) {
					unset($currTableAlias[0]);
					setNoQuotes($node, array($firstSubqueryAlias['name'], implode(".", $currTableAlias)));
				}
			}
		}
	}
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
			if (extractTableName($col) == $tableList[$maxKey]['alias']['name']) {
				$tmpCol['expr_type'] = $col['expr_type'];
				$tmpCol['alias'] = createAliasNode(array($col['base_expr']));
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
			$currTable = extractTableName($part);
			foreach ($possibleCombList as $key) {
				if(extractTableName($tableList[$key]['node']) === $currTable ||
						extractTableAlias($tableList[$key]['node']) === $currTable) {
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
	#only apply the USE INDEX clause

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
	#only apply the options clause

	if (!array_key_exists('OPTIONS', $sqlTree)) {
		return;
	}

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
	//TODO: THIS FUNCTION HAS BEEN SOLEMNY CHOSEN FOR A COMPLETE REWRITE
	$new_base_expr = "";

	$listOfCols = array();
	if ($toThisNode !== false && hasSubtree($toThisNode)) {
		if(hasSubtree($toThisNode['sub_tree']['FROM'][0])) {
			//TODO: Solve quick fix - introduced through this query:
			//select `b`.x, `b`.y, `b`.z, `b`.vx, `b`.vy, `b`.vz from Bolshoi.particles416 as `b`, (select x, y, z from Bolshoi.BDMV where snapnum=416 order by Mvir desc limit 1) as `a` where b.x between a.x - 25 and a.x + 25 and b.y between a.y - 25 and a.y + 25 and b.z > a.z-25
			$toThisNode['sub_tree']['SELECT'] = $toThisNode['sub_tree']['FROM'][0]['sub_tree']['SELECT'];
		}

		foreach ($toThisNode['sub_tree']['SELECT'] as $selNode) {
			array_push($listOfCols, $selNode);
		}
	}

	#rewrite node to properly alias involved columns
	if (hasSubtree($node) && !isSubquery($node)) {
		if($node['expr_type'] === "function") {
			$new_base_expr .= $node['base_expr'] . "(";
		}

		foreach ($node['sub_tree'] as $key => &$whereSubNode) {
			if (hasSubtree($whereSubNode)) {
				PHPSQLrewriteAliasWhere($whereSubNode, $tableList, $recLevel, $toThisNode);
			}

			if (isColref($whereSubNode)) {
				$currTable = extractTableName($whereSubNode);
				$currCol = extractColumnName($whereSubNode);

				if ($currTable !== false) {
					#search for the table in the tablelist
					foreach ($tableList as $tblKey => $currTbl) {
						if (extractTableName($currTbl['node']) === $currTable ||
								extractTableAlias($currTbl['node']) === $currTable) {
							break;
						}
					}

					if ($tblKey > $recLevel) {
						#rewrite name of where_node to properly alias it
						#rewrite name to alias name if needed
						foreach ($listOfCols as $selNode) {
							if (extractColumnName($selNode) === $currCol || 
									extractColumnAlias($selNode) === $currCol) {

								if(hasAlias($selNode)) {
									$colName = extractColumnAlias($selNode);
								} else {
									$colName = implodeNoQuotes($selNode['no_quotes']);
								}

								if (count($selNode['alias']['no_quotes']['parts']) > 1) {

									#search for proper name in the subquery tree
									foreach ($toThisNode['sub_tree']['FROM'] as $subTreeNode) {
										if (extractTableName($subTreeNode) === $currTable ||
												extractTableAlias($subTreeNode) === $currTable) {
											#now that the subtree is found, loop through the selects and find the 
											#node we are looking for
											#is this the lowest node? then there is no sub_tree to be found, handle things differntly
											if (hasSubtree($subTreeNode)) {
												$parseThisNode = $subTreeNode['sub_tree']['SELECT'];
											} else {
												$parseThisNode = $toThisNode['sub_tree']['SELECT'];
											}

											foreach ($parseThisNode as $selectNodes) {
												$selColName = extractColumnName($selectedNodes);

												if ($selColName === extractColumnName($selNode)) {
													$colName = $selectNodes['alias']['name'];
													break;
												}
											}
											break;
										}
									}
								}

								setNoQuotes($whereSubNode, array(trim($colName, "`")));
								break;
							}
						}

						if(hasAlias($tableList[$tblKey]['node'])) {
							$tableName = extractTableAlias($tableList[$tblKey]['node']);
						} else {
							$tableName = extractTableName($tableList[$tblKey]['node']);
						}

						setNoQuotes($whereSubNode, array_merge(array($tableName), $whereSubNode['no_quotes']['parts']));
					}
				}
			}

			if($whereSubNode['expr_type'] === 'function' && strpos($whereSubNode['base_expr'], "(") === false) {
					if(!empty($node['sub_tree'][$key+1]) && strpos($node['sub_tree'][$key+1]['base_expr'], "(") === false) {
						$new_base_expr .= " " . $whereSubNode['base_expr'] . "()";
					} else {
						$new_base_expr .= " " . $whereSubNode['base_expr'];
					}
			} else {
				$new_base_expr .= " " . $whereSubNode['base_expr'];
			}
		}

		$new_base_expr = getBaseExpr($node);
		$node['base_expr'] = $new_base_expr;
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
		} else if (isOperator($node) && $key == count($table['where_cond']) - 1) {
			continue;
		} else if (isOperator($node) && strtolower($node['base_expr']) == 'and') {
			$oldOperatorNode = $node;
			continue;
		}

		if($first !== true && $operatorNode !== false) {
			array_push($toThisNode['WHERE'], $operatorNode);
		} else if ($oldOperatorNode !== false) {
			//in rare cases this is needed, when things mingle too much.
			//TODO: resolve the need for this! WHEN IS THIS NEEDED? Reevaluate
			array_push($toThisNode['WHERE'], $oldOperatorNode);
		}
		array_push($toThisNode['WHERE'], $node);

		$oldOperatorNode = $operatorNode;
		$first = false;
	}


	//check outer WHERES aliases - if an alias is not found, rename it to the first sub query found (usually this is the 
	//nested one)
	//go through FROM - find all aliases and alias of first subquery
	$aliasList = array();
	$firstSubqueryAlias = false;
	foreach($toThisNode['FROM'] as $node) {
		if(hasAlias($node)) {
			array_push($aliasList, extractTableAlias($node));
		} else {
			array_push($aliasList, extractTableName($node));
		}
		if (hasSubtree($node) && $firstSubqueryAlias === false) {
			$firstSubqueryAlias = $node['alias'];
		}
	}

	if($firstSubqueryAlias !== false) {
		//go through all WHERE terms and any alias not found in the list, rewrite to the one of the first subquery
		rewriteWHEREAliasToFirstSubquery($toThisNode['WHERE'], $aliasList, $firstSubqueryAlias);
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
	$tblDb = extractDbName($table['node']);
	$tblName = extractTableName($table['node']);

	if(hasAlias($table)) {
		$tblAlias = extractTableAlias($table['node']);
	} else {
		$tblAlias = $tblName;
	}

	if (!array_key_exists('ORDER', $sqlTree)) {
		return;
	}

	#construct the ORDER part
	if (!array_key_exists('ORDER', $toThisNode)) {
		$toThisNode['ORDER'] = array();
	}

	foreach ($sqlTree['ORDER'] as $count => $node) {
		$nodeCopy = $node;

		$tmpArray = array();
		$countDep = PHPSQLcollectColumns(array($nodeCopy), $tblDb, $tblName, $tblAlias, $tmpArray, $tableList, $recLevel);

		if ($countDep == 0) {
			#it might be, that this column has been aliased, so take care of that
			$find = false;
			foreach ($toThisNode['SELECT'] as $selNode) {
				if (columnIsEqual($selNode['base_expr'], $node['base_expr']) || ( hasAlias($selNode) &&
							 trim($selNode['alias']['name'], "`") == trim($node['base_expr'], "`") ) ) {

					$find = true;
					break;
				}
			}

			$node['origParse'] = $nodeCopy;
			$node['base_expr'] = getBaseExpr($nodeCopy);

			if ($find === false) {
				//it might be, that this column is actually a column referencing another table than the one beeing processed
				//now. so go through the list of columns selected, and see if this is the case, otherwise add the column
				$currTblDb = extractDbName($toThisNode['FROM'][0]);
				$currTblName = extractTableName($toThisNode['FROM'][0]);

				//find the correspinding column
				$columnArray = array_merge($sqlTree['SELECT'], $toThisNode['SELECT']);
				$node['alias'] = false;
				foreach($columnArray as $column) {
					if(columnIsEqual($column, $node['origParse']) || ( hasAlias($column) &&
							trim($column['alias']['name'], "`") === trim($node['origParse']['base_expr'], "`") )
						 || strpos($column['base_expr'], "*") !== false) {
						//found!
						//compare with currently processed table
						$currColTbl = extractTableName($column);
						$currColName = extractColumnName($column);

						if($currColTbl === false || $currColTbl === $currTblName) {
							//process this order by...
							if(hasAlias($node['origParse'])) {
								$node['alias']['name'] = $node['origParse']['alias']['name'];
							}
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
				if(hasAlias($node['origParse']) && count($node['origParse']['alias']) === 1) {
					$node['alias'] = $node['origParse']['alias'];
				}
			}

			#check if this column already exists in the select tree
			$findNode = false;
			foreach ($toThisNode['SELECT'] as $selNode) {
				if (columnIsEqual($selNode, $node) || ( hasAlias($selNode) &&
						trim($selNode['alias']['name'], "`") == trim($node['base_expr'], "`")) ) {
					$findNode = $selNode; 
					break;
				}
			}

			if ($findNode === false) {
				$tmp = explode(".", $node['base_expr']);
				#this handles a special case, where DBName.Table name comes up. We need to exclude this
				$tmpTblAlias = explode(".", $tblAlias);

				//create a new node for the SELECT statement
				$newNode = array();
				$newNode['expr_type'] = "colref";
				$newNode['base_expr'] = getBaseExpr($node['origParse']);

				if(isColref($node['origParse'])) {
					$newNode['alias'] = createAliasNode(array(implodeNoQuotes($node['origParse']['no_quotes'])));
					$newNode['no_quotes'] = $node['origParse']['no_quotes'];
				} else {
					$newNode['alias'] = createAliasNode(array(buildEscapedString(array($node['origParse']))));
				}
				
				$newNode['order_clause'] = $node;
				$newNode['sub_tree'] = false;
				if(!empty($node['origParse']['alias'])) {
					$newNode['alias'] = $node['origParse']['alias'];
				}

				array_push($toThisNode['SELECT'], $newNode);
			}

			array_push($toThisNode['ORDER'], $node);

			unset($sqlTree['ORDER'][$count]);
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
	$tblDb = extractDbName($table['node']);
	$tblName = extractTableName($table['node']);

	if(hasAlias($table)) {
		$tblAlias = extractTableAlias($table['node']);
	} else {
		$tblAlias = $tblName;
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
		$nodeCopy = $node;

		$tmpArray = array();
		$countDep = PHPSQLcollectColumns(array($nodeCopy), $tblDb, $tblName, $tblAlias, $tmpArray, $tableList, $recLevel);

		if ($countDep == 0) {
			$node['origParse'] = $nodeCopy;

			array_push($toThisNode['GROUP'], $node);

			#check if this column already exists in the select tree
			$findNode = false;
			foreach ($toThisNode['SELECT'] as &$selNode) {
				if (columnIsEqual($selNode, $node) || ( hasAlias($selNode) &&
						trim($selNode['alias']['name'], "`") == trim($node['base_expr'], "`") ) ) {
					$findNode = $selNode;
					break;
				}
			}

			if ($findNode === false) {
				$node['group_clause'] = $nodeCopy;
				array_push($toThisNode['SELECT'], $node);
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
	if(hasAlias($table)) {
		$tblAlias = extractTableAlias($table['node']);
	} else {
		$tblAlias = false;
	}

	$tblDb = extractDbName($table['node']);
	$tblName = extractTableName($table['node']);

	if (!array_key_exists('FROM', $sqlTree)) {
		return;
	}

	#construct the FROM part
	if (!array_key_exists('FROM', $toThisNode)) {
		$toThisNode['FROM'] = array();
	}

	foreach ($sqlTree['FROM'] as $key => $node) {
		if(isSubquery($node)) {
			$node['table'] = "DEPENDENT-SUBQUERY";
		}

		$currDb = extractDbName($node);
		$currTable = extractTableName($node);

		#check if this is the right table
		if ((hasAlias($node) && extractTableAlias($node) === $tblAlias) || 
				(!hasAlias($node) && ($currTable === $tblAlias  || ($currTable === $tblName && $currDb === $tblDb)))) {
			#create a copy of this node
			$nodeCopy = $node;

			#is there a USING flag?
			if ($nodeCopy['ref_type'] == 'USING' || $nodeCopy['ref_type'] == 'ON') {
				//TODO: WE ARE NOT CURRENTLY SUPPORTING THIS FEATURE
				//IMPLEMENTATION WOULD BE FAILRY EASY THOUGH, GO THROUGH THE TREE BEFORE PROCESSING WITH PAQU
				//AND TRANSFORM EVERYTHING INTO IMPLICIT JOINS USING WHERE, THEN THE REMAINING CODE CAN BE
				//USED. ONLY WAY TO DO IT AS I SEE IT...

				throw new RuntimeException('USING or ON statement: Not yet handled');
			}

			$nodeCopy['join_type'] = 'JOIN';
			if ($nodeCopy['alias']['name'] == $nodeCopy['table'])
				$nodeCopy['alias']['name'] = "";

			array_push($toThisNode['FROM'], $nodeCopy);
		}
	}
}

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
	$workload = array();
	if (array_key_exists('SELECT', $sqlSelect)) {
		$workload = $sqlSelect['SELECT'];
	} else {
		$workload = $sqlSelect;
	}

	foreach ($workload as $node) {
		if (!isColref($node) && !isOperator($node)) {
			$currCountDiffTables = false;
			$currColArray = array();
			if(hasSubtree($node))	 {
				$currCountDiffTables = PHPSQLcollectColumns($node['sub_tree'], $tblDb, $tblName, $tblAlias, $currColArray, $tableList, $recLevel, false);
				$countDiffTables += $currCountDiffTables;
			} else if ($recLevel === 0) {
				//if this has no subtree (as do functions without parameters like foo()), then add it at the outermost possible 
				//place (i.e. recLeve = 0)
				$currCountDiffTables = 0;
			}

			if ($startBranch === true && $currCountDiffTables === 0) {
				if(!hasAlias($node)) {
					$node['alias'] = createAliasNode(array(buildEscapedString(array($node))));
				}

				//add the correct table names to all columns that are not in the current column
				if(hasSubtree($node) && count($tableList) > 0 && $recLevel !== max(array_keys($tableList))) {
					$toThisTableName = extractTableAlias($tableList[$recLevel + 1]['node']);

					if($toThisTableName === false) {
						$toThisTableName = extractTableName($tableList[$recLevel + 1]['node']);
					}

					if(hasAlias($tableList[$recLevel])) {
						rewriteTableNameInSubqueries($node['sub_tree'], $toThisTableName, extractTableAlias($tableList[$recLevel]['node']));
					} else {
						rewriteTableNameInSubqueries($node['sub_tree'], $toThisTableName, extractTableName($tableList[$recLevel]['node']));
					}

					if($node['expr_type'] !== "aggregate_function") {
						$node['base_expr'] = getBaseExpr($node);
					}
				}

				//if this is a function/aggregate that applies to a '*' "column", then only apply this at the
				//outermost level (i.e. reclevel=0) and not before (see Test40 for case where this applies)
				$canAddThisNode = true;
				foreach($currColArray as $column) {
					if($column['base_expr'] === "*" && $recLevel != 0) {
						$canAddThisNode = false;
						break;
					}
				}

				if($canAddThisNode === true) {
					array_push($returnArray, $node);
				}
			}

			//add columns if not yet added, but only if there are dependant columns evolved
			if($currCountDiffTables !== false && $currCountDiffTables > 0) {
				foreach($currColArray as $column) {
					$found = false;
					//go through the already selected columns
					foreach($returnArray as $returnColumn) {
						if(columnIsEqual($returnColumn, $column)) {
							$found = true;
							break;
						}
					}

					if($found === false) {
						$column['alias'] = createAliasNode(array(implodeNoQuotes($column['no_quotes'])));
						array_push($returnArray, $column);
					}
				}
			}
		} else if (isColref($node) || 
					($node['base_expr'] === '*' && $startBranch === true)) {

			$currCol = extractColumnName($node);
			$currTable = extractTableName($node);
			$currDB = extractDbName($node);

			if ($currTable === $tblAlias || ($currTable === false) || $currTable === $tblName || 
						 $tblAlias === $currDB . "." . $currTable) {
					array_push($returnArray, $node);
			} else {
				#check if this table has already been selected and processed. if yes, we can already process it and
				#donot need to wait
				$found = false;
				foreach ($tableList as $key => $tblListNode) {
					#skip everything not yet processed...
					if ($key < $recLevel) {
						continue;
					}

					if ($currTable === extractTableAlias($tblListNode['node'])) {
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

	#if the SELECT key does not exist in the target tree, create it
	if (!array_key_exists('SELECT', $toThisNode)) {
		$toThisNode['SELECT'] = array();
	}

	if(hasAlias($table)) {
		$tblAlias = extractTableAlias($table['node']);
	} else {
		$tblAlias = false;
	}

	$tblDb = extractDbName($table['node']);
	$tblName = extractTableName($table['node']);

	#collect all columns from the sql tree
	$partList = array();
	PHPSQLcollectColumns($sqlTree, $tblDb, $tblName, trim($tblAlias, '`'), $partList, $tableList, $recLevel);

	foreach ($partList as $node) {
		$nodeCopy = $node;
		$nodeCopy['base_expr'] = getBaseExpr($nodeCopy);
		
		if(!hasAlias($nodeCopy) && isColref($nodeCopy) && $nodeCopy['base_expr'] !== "*") {
			$nodeCopy['alias'] = createAliasNode(array(implodeNoQuotes($nodeCopy['no_quotes'])));
		}
		
		array_push($toThisNode['SELECT'], $nodeCopy);
		$key = array_search($node, $sqlTree['SELECT']);
		unset($sqlTree['SELECT'][$key]);
	}

	//if this is recLevel = 0, add all remaining columns as well
	if($recLevel == 0) {
		foreach($sqlTree['SELECT'] as $node) {
			//only colrefs are not yet considered here, remaining stuff has been caputred by collectColumns
			if(isColref($node)) {
				array_push($toThisNode['SELECT'], $node);
			}
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
	if($table['name'] === extractTableAlias($table['node'])) {
		$tmpList3 = PHPSQLgetAllColsFromWhere($sqlTree['WHERE'], "", true);
		$tmpList2 = array_merge($tmpList2, $tmpList3);
	}

	if($tblAlias === $tblName) {
	 	$listOfWhereCols = $tmpList1;
	} else {
	 	$listOfWhereCols = array_merge($tmpList1, $tmpList2);
	}

	foreach ($listOfWhereCols as $cols) {
		$find = false;
		foreach ($toThisNode['SELECT'] as &$node) {
			if(columnIsEqual($node, $cols)) {
				$find = true;
				break;
			}
		}

		if ($find === false) {
			#mark this select as comming from a where
			$cols['where_col'] = true;

			$cols['base_expr'] = getBaseExpr($cols);
			
			if(!hasAlias($cols) && isColref($cols) && $cols['base_expr'] !== "*") {
				$cols['alias'] = createAliasNode(array(implodeNoQuotes($cols['no_quotes'])));
			}

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

	//if we are looking for nothing, we should return nothing
	if($table === false) {
		return $returnArray;
	}

	$columns = collectNodes($whereTree, "colref");

	foreach($columns as $column) {
		$currTable = extractTableName($column);

		if ($table !== $currTable && !(count($column['no_quotes']['parts']) === 1 && $table === "")) {
			continue;
		}

		//getting rid of the table/alias name in the column description (but only if a table
		//name is provided $table)
		if(count($column['no_quotes']['parts']) > 1 && $removeTableName === true) {
			unset($column['no_quotes']['parts'][0]);
			setNoQuotes($column, array_values($column['no_quotes']['parts']));
		}

		$column['alias'] = createAliasNode(array(implodeNoQuotes($column['no_quotes'])));

		array_push($returnArray, $column);
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
				$condCount[$key] += PHPSQLdetStartTableCountBetween($dependantList, $table['alias']['name']);
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
		if(hasSubtree($node)) {
			$count += PHPSQLdetStartTableCountBetween($node['sub_tree'], $tableName);
		}

		if(isOperator($node) && $node['base_expr'] === "BETWEEN") {
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
		if (hasSubtree($currNode)) {
			if (!isSubquery($currNode)) {
				$result += PHPSQLrecursCountWhereCond($currNode['sub_tree']);
			}
		} else if (isColref($currNode)) {
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
		if (isOperator($node)) {
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

		if (isOperator($node)) {
			continue;
		}

		$node['oldKey'] = $key;

		$currParticipants = PHPSQLGetListOfParticipants($node);

		#check if this is a condition with one or more participants
		#trim () because we could be dealing with a column in a function
		if(empty($currParticipants)) {
			$table = false;
		} else {
			$table = $currParticipants[0]['no_quotes']['parts'];
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

			if ($table !== $currTable && $table !== false && $currTable !== false) {
				array_push($dependantWheres, $node);
				continue 2;
			}
		}

		#find this table in the table list and add condition there
		foreach ($tableList as &$currTableInList) {
			if (trim($table, '`') == extractTableAlias($currTableInList['node']) || $table === false) {
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

	if (hasSubtree($node)) {
		#handle subqueries in WHERE statements differntly
		if (!isSubquery($node)) {
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
							if(columnIsEqual($currNode, $currPartNode)) {
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
	} else if (isColref($node)) {
		array_push($partArray, $node);
	} else {
		$partArray = array();
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

		if(isTable($currTable)) {
			$table['name'] = $currTable['table'];
			$table['no_quotes'] = $currTable['no_quotes'];
		} else if(isSubquery($currTable)) {
			$table['name'] = "DEPENDENT-SUBQUERY";
			$table['expr_type'] = "subquery";
			$table['no_quotes'] = false;
		} else {
			throw new Exception("Unsupported clause in FROM");
		}

		$table['alias'] = $currTable['alias'];
		$table['node'] = $currTable;
		$table['sel_columns'] = array();
		array_push($listOfTables, $table);
	}

	//put dependant queries at the end of the list
	$currIndex = count($listOfTables) - 1;
	foreach ($listOfTables as $key => $node) {
		if ($node['name'] == 'DEPENDENT-SUBQUERY' && $key < $currIndex) {
			$tmpNode = $listOfTables[$currIndex];
			$listOfTables[$currIndex] = $node;
			$listOfTables[$key] = $tmpNode;

			$currIndex--;
		}
	}

	//link the columns with the tables
	foreach ($selectTree as $node) {
		$columnsInNode = collectNodes($node, "colref");

		foreach($columnsInNode as $column) {
			foreach($listOfTables as &$table) {
				if(isColumnInTable($column, $table)) {
					array_push($table['sel_columns'], $column);
					break;
				}
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

	//remove the bracket that was added around the whole expression
	$groupedTree = $groupedTree['sub_tree'];

	//we now gather all AND expressions and put brackets around them.
	$groupedAndTree = array();
	PHPSQLParseWhereTokens_groupANDExpressions($groupedTree, $groupedAndTree);

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

	if(!hasSubtree($newTree)) {
		$newTree['expr_type'] = "bracket_expression";
		$newTree['base_expr'] = "";
		$newTree['sub_tree'] = array();
	}

	foreach ($tree as $term) {
		$processSub = false;
		$currNode = $term;

		//only process sub_trees, that have more than 3 elements inside, otherwise a possible
		//AND term has already been grouped.
		if(hasSubtree($term) && count($term['sub_tree']) > 3) {
			//only process sub_trees, that have further subtrees (i.e. no end nodes that are
			//already bracketed anyways)
			foreach ($term['sub_tree'] as $node) {
				if(hasSubtree($node)) {
					$processSub = true;
					break;
				}
			}
		}

		if($processSub === true) {
			$currSubTree = array();
			PHPSQLParseWhereTokens_groupANDExpressions($term['sub_tree'], $currSubTree);

			$currSubTree['base_expr'] = getBaseExpr($currSubTree);

			$currNode = $currSubTree;
		}

		//only process if AND is preceeded by a OR
		if(isOperator($currNode) && 
				(strtolower($currNode['base_expr']) === "or" || $currNode['base_expr'] === "||")) {

			$foundOr = true;
		}

		//check if this is an AND
		if($foundOr === true && isOperator($currNode) && 
				(strtolower($currNode['base_expr']) === "and" || $currNode['base_expr'] === "&&")) {

			$foundAnd = true;
			$foundOr = false;

			$newNode = array();
			$newNode['expr_type'] = "bracket_expression";
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

			$previousNode['base_expr'] = getBaseExpr($previousNode);

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
		$newTree['base_expr'] = getBaseExpr($newTree);
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
 *          OUT: ( ( x = 0.998373 ) or ( ( ( y = SIN (0.998373) ) and ( z = 0.998373 ) ) 
 *                    and ( z = 43 ) ) or ( ( ( z = 23 ) and ( z = 4 ) ) or ( x = 1 ) ) 
 *                    or ( ( y = 34 ) and ( x between 1 and 2 ) ) )
 */
function PHPSQLParseWhereTokens_groupTerms($tree, &$newTree) {
	$currLeaf = array();
	$isBetween = false;

	$currLeaf['expr_type'] = "bracket_expression";
	$currLeaf['base_expr'] = "";
	$currLeaf['sub_tree'] = array();

	if(!hasSubtree($newTree)) {
		$newTree['expr_type'] = "bracket_expression";
		$newTree['base_expr'] = "";
		$newTree['sub_tree'] = array();
	}

	foreach ($tree as $token) {
		//NOTE: Subqueries are treated as normal terms in this context.
		if(!empty($token['sub_tree']) && $token['expr_type'] !== "function" && !isSubquery($token)) {
			$tmpArray = array();
			PHPSQLParseWhereTokens_groupTerms($token['sub_tree'], $tmpArray);

			$token = $tmpArray;

			$token['base_expr'] = getBaseExpr($token);
		}

		#a normal node - if this node is an AND or OR, split there
		if(array_key_exists('expr_type', $token) && 
				isOperator($token) &&
				(strtolower($token['base_expr']) === "or" || strtolower($token['base_expr']) === "and")) {

			//is this the and in the between clause? if yes, ignore this
			if(strtolower($token['base_expr']) === "and" && $isBetween === true) {
				$isBetween = false;
			} else {
				//it could be that currLeaf is empty - this is the case if a sub_tree has been added
				//before - just add the operator then
				if(hasSubtree($currLeaf)) {
					if(count($currLeaf['sub_tree']) === 1 && $currLeaf['sub_tree'][0]['expr_type'] === "bracket_expression") {
						//if there is only one entry in sub_tree, we don't need to bracket this
						$currLeaf = $currLeaf['sub_tree'][0];
					}

					array_push($newTree['sub_tree'], $currLeaf);
				}

				array_push($newTree['sub_tree'], $token);

				//create new leaf
				$currLeaf['expr_type'] = "bracket_expression";
				$currLeaf['base_expr'] = "";
				$currLeaf['sub_tree'] = array();

				continue;
			}
		}

		//is this a between case?
		if(array_key_exists('expr_type', $token) && 
				isOperator($token) &&
				strtolower($token['base_expr']) === "between") {
			
			$isBetween = true;
		}

		//if this is a bracket_expression, see what is inside - if there is just another bracket_expressing
		//in there, remove the outer one, we don't want to have ( ( foo ) ) type bracketing
		if($token['expr_type'] === "bracket_expression" && count($token['sub_tree']) === 1) {
			$token = $token['sub_tree'][0];
			$token['base_expr'] = getBaseExpr($token);
		}

		array_push($currLeaf['sub_tree'], $token);
	}

	if(hasSubtree($currLeaf)) {
		//the last leaf is remaining and wants to be added to the tree as well
		$currLeaf['base_expr'] = getBaseExpr($currLeaf);

		//if this is a bracket_expression, see what is inside - if there is just another bracket_expressing
		//in there, remove the outer one, we don't want to have ( ( foo ) ) type bracketing
		if($currLeaf['expr_type'] === "bracket_expression" && count($currLeaf['sub_tree']) === 1) {
			$currLeaf = $currLeaf['sub_tree'][0];
			$currLeaf['base_expr'] = getBaseExpr($currLeaf);
		}

		array_push($newTree['sub_tree'], $currLeaf);
	} 

	$newTree['base_expr'] = getBaseExpr($newTree);
}

function PHPSQLresolvePositionalArguments($sqlTree) {
	$returnTree = $sqlTree;

	//subqueries can be in FROM and WHERE
	if(!empty($returnTree['FROM'])) {
		foreach($returnTree['FROM'] as &$fromNode) {
			if(isSubquery($fromNode)) {
				$fromNode['sub_tree'] = PHPSQLresolvePositionalArguments($fromNode['sub_tree']);
				$fromNode['base_expr'] = getBaseExpr($fromNode);
			}
		}
	}

	if(!empty($returnTree['WHERE'])) {
		foreach($returnTree['WHERE'] as &$whereNode) {
			if(isSubquery($whereNode)) {
				$whereNode['sub_tree'] = PHPSQLresolvePositionalArguments($whereNode['sub_tree']);
				$whereNode['base_expr'] = getBaseExpr($whereNode);
			}
		}
	}

	//only do something if there is an ORDER BY
	if(!empty($returnTree['ORDER'])) {
		foreach($returnTree['ORDER'] as &$orderNode) {
			if($orderNode['expr_type'] === "pos") {
				$selNode = $returnTree['SELECT'][(int)$orderNode['base_expr'] - 1];

				//rewrite things
				if(!isColref($selNode)) {
					$orderNode['expr_type'] = "colref";

					if(hasAlias($selNode)) {
						$orderNode['base_expr'] = extractColumnAlias($selNode);
					} else {
						$orderNode['base_expr'] = buildEscapedString(array($selNode));
					}
					
					$orderNode['no_quotes'] = array("delim" => ".", "parts" => array($orderNode['base_expr']));
				} else {
					$orderNode['expr_type'] = $selNode['expr_type'];
					$orderNode['base_expr'] = $selNode['base_expr'];
					if(!empty($selNode['no_quotes'])) {
						$orderNode['no_quotes'] = $selNode['no_quotes'];
					}
				}
			}
		}
	}

	return $returnTree;
}

function collectNodes($node, $expr_type) {
	$return = array();

	//is this a leave, wrap it in an array, so that the code below
	//can be kept simple
	if(array_key_exists("expr_type", $node)) {
		$node = array($node);
	} 
 
	foreach($node as $subNode) {
		if(!is_array($subNode)) {
			continue;
		}

		if(!isset($subNode['expr_type'])) {
			$return = array_merge($return, collectNodes($subNode, $expr_type));
			continue;
		}

		if(!empty($subNode['sub_tree'])) {
			$return = array_merge($return, collectNodes($subNode['sub_tree'], $expr_type));
		}

		if($subNode['expr_type'] === $expr_type) {
			$return[] = $subNode;
		}
	}
	

	return $return;
}

//this function checks if the column name and table name match, checking also
//the aliases. IF the column has no table name specified, this function will return
//TRUE!
function isColumnInTable($column, $table) {
	//check if this is a lonely '*'
	if(!isset($column['no_quotes']) && $column['base_expr'] === "*") {
		return true;
	}

	//check for alias
	if($table['alias'] !== false) {
		$columnNamePartCount = count($column['no_quotes']['parts']);

		//case: Database.Table.Column
		switch ($columnNamePartCount) {
			case 3:
				//if the column name is made up of 3 parts, it cannot refere to 
				//an alias
				break;
			case 2:
				if($column['no_quotes']['parts'][0] === $table['alias']['no_quotes']['parts'][0]) {
					return true;
				}
				break;
			default:
				break;
		}
	} 

	//if no alias is present
	$tableNamePartCount = count($table['no_quotes']['parts']);
	$columnNamePartCount = count($column['no_quotes']['parts']);

	//case: Database.Table.Column
	switch ($columnNamePartCount) {
		case 3:
			//this only has a chance, if the table name is made up of 2 entries
			if($tableNamePartCount == 2) {
				if($column['no_quotes']['parts'][0] === $table['no_quotes']['parts'][0] &&
					$column['no_quotes']['parts'][1] === $table['no_quotes']['parts'][1]) {
					return true;
				}
			}
			break;
		case 2:
			//table could be just the table or Database.Table
			if($tableNamePartCount == 2) {
				if($column['no_quotes']['parts'][0] === $table['no_quotes']['parts'][1]) {
					return true;
				}
			} else if ($tableNamePartCount == 1) {
				if($column['no_quotes']['parts'][0] === $table['no_quotes']['parts'][0]) {
					return true;
				}
			}
			break;
		case 1:
			//no table name is given, thus return true
			return true;
		default:
			break;
	}

	return false;
}

/**
 * Function that will recursively go through the branch at the function to
 * construct the escaped column name
 * @param array $inNode SQL parse tree node
 * @return string parts of the escaped function name
 */
function buildEscapedString($inNode, $addInitialPrefix = true) {
	$str = "";

    if($addInitialPrefix === true) {
	    $str = "_";
    }

    foreach ($inNode as $currNode) {
        $partStr = "";

        if (array_key_exists("sub_tree", $currNode) && $currNode["sub_tree"] !== false) {
            $partStr = buildEscapedString($currNode["sub_tree"], false);
        }

        $partStr = str_replace(".", "__", $partStr);
        $partStr = str_replace("`", "", $partStr);

        if ($currNode["expr_type"] === "aggregate_function" ||
                $currNode['expr_type'] === "function") {
            $str .= $currNode["base_expr"] . "_" . $partStr;        #last "_" already added below
        } else if ($partStr === "") {
            $str .= $currNode["base_expr"] . "_";
        } else { 
        	$str .= $partStr;
        }
    }

    return $str;
}

function getBaseExpr($node) {
	$return = "";

	if($node['expr_type'] === "function" || $node['expr_type'] === "aggregate_function") {
		//check if the function base_expression is already fully resolved or is a vanilla
		//base_expr from the parser - parser does not include the brackets
		//if this is already resolved, we need to remove everything until the first bracket
		//to retrieve the function name
		if(strpos($node['base_expr'], "(") === false) {
			$return = $node['base_expr'];
		} else {
			$tmp = explode("(", $node['base_expr']);
			$return = $tmp[0];
		}

		//aggregate base_expr just consist of the function name in upper case
		if($node['expr_type'] === "aggregate_function") {
			$return = strtoupper($return);
			return $return;
		}

		$return .= "( ";
	} else if ($node['expr_type'] === "bracket_expression") {
		$return .= "( ";
	}

	if(isset($node['sub_tree']) && $node['sub_tree'] !== false && $node['expr_type'] !== 'subquery') {
		$tmp = "";
		
		$count = 0;
		$max = count($node['sub_tree']);
		foreach($node['sub_tree'] as $subNode) {
			$tmp .= getBaseExpr($subNode);
			$count++;

			if($count < $max &&
					($node['expr_type'] === "function" || $node['expr_type'] === "aggregate_function")) {
				$tmp .= ", ";
			} else if($count < $max) {
				$tmp .= " ";
			}
		}

		//could be, that we gathered the stuff here and it was already there... then don't append
		if($tmp !== $return) {
			$return .= $tmp;
		}
	}

	if($node['expr_type'] === "function" || $node['expr_type'] === "aggregate_function" || 
				$node['expr_type'] === "bracket_expression") {
		$return .= " )";
	} else if (isColref($node) && $node['base_expr'] !== "*") {
		$start = true;
		
		foreach($node['no_quotes']['parts'] as $part) {
			if($start === false && array_key_exists("delim", $node['no_quotes'])) {
				$return .= $node['no_quotes']['delim'];
			}

			if($part === "*") {
				$return .= $part;
			} else {
				$return .= "`" . $part . "`";
			}

			$start = false;
		}
	} else if ($node['expr_type'] !== "expression" && $node['expr_type'] !== "bracket_expression") {
		$return = $node['base_expr'];
	}

	return $return;
}

//takes an array and creates an alias out of the given parts
function createAliasNode($partArray, $delim = ".") {
	$alias = array();

	$alias['as'] = true;
	$alias['no_quotes'] = array();
	$alias['no_quotes']['parts'] = array();

	if(count($partArray) > 1) {
		$alias['no_quotes']['delim'] = $delim;
	} else {
		$alias['no_quotes']['delim'] = false;
	}

	$name = "";

	foreach($partArray as $part) {
		$alias['no_quotes']['parts'][] = $part;
		if($name === "") {
			$name .= "`" . $part . "`";
		} else {
			$name .= $delim . "`" . $part . "`";
		}
	}

	$alias['name'] = $name;
	$alias['base_expr'] = "as " . $name;

	return $alias;
}

function aliasIsEqual($aliasA, $aliasB, $fuzzyMatch = false) {
	if(count($aliasA['no_quotes']) !== count($aliasB['no_quotes'])) {
		return false;
	}

	foreach($aliasA['no_quotes'] as $key => $part) {
		if($part !== $aliasB['no_quotes'][$key]) {
			if($fuzzyMatch === true) {
				$nameA = implode(".", $aliasA['no_quotes']['parts']);
				$nameB = implode(".", $aliasB['no_quotes']['parts']);

				if(strpos($nameA, $nameB) === false && strpos($nameB, $nameA) === false) {
					return false;
				}
			} else {
				return false;
			}
		}
	}

	return true;
}

//fuzzyMatch implodes the unquoted column parts and checks if A is contained in B
//or vice versa
function columnIsEqual($colA, $colB, $fuzzyMatch = false) {
	if($colA['expr_type'] !== $colB['expr_type']) {
		return false;
	}

	if(!isColref($colA) && !isColref($colB)) {
		if($colA === $colB) {
			return true;
		} else {
			return false;
		}
	}

	//if this is a "*" node, as in SELECT * FROM, then the no_quotes part is not present
	//and it does not make sense to extract anything anyways
	if(!isset($colA['no_quotes']) || !isset($colA['no_quotes'])) {
		if($colA === $colB) {
			return true;
		} else {
			return false;
		}
	}

	if(count($colA['no_quotes']) !== count($colB['no_quotes'])) {
		return false;
	}

	foreach($colA['no_quotes']['parts'] as $key => $part) {
		if($part !== $colB['no_quotes']['parts'][$key]) {
			if($fuzzyMatch === true) {
				$nameA = implode(".", $colA['no_quotes']['parts']);
				$nameB = implode(".", $colB['no_quotes']['parts']);

				if(strpos($nameA, $nameB) === false && strpos($nameB, $nameA) === false) {
					return false;
				}
			} else {
				return false;
			}
		}
	}

	return true;
}

function extractDbName($node) {
	//is this a table type or something else
	if(isTable($node)) {
		$partCounts = count($node['no_quotes']['parts']);

		//a table node
		if($partCounts > 1) {
			return $node['no_quotes']['parts'][ $partCounts - 2 ];
		} else {
			return false;
		}
	} else if(isColref($node)) {
		//if this is a "*" node, as in SELECT * FROM, then the no_quotes part is not present
		//and it does not make sense to extract anything anyways
		if(!isset($node['no_quotes'])) {
			return false;
		}

		$partCounts = count($node['no_quotes']['parts']);

		if($partCounts > 2) {
			return $node['no_quotes']['parts'][ 0 ];
		} else {
			return false;
		}
	} else {
		//don't know what to do
		return false;
	}
}

function extractTableAlias($node) {
	if((isTable($node) || isSubquery($node))
		&& isset($node['alias']['as'])) {
		$partCounts = count($node['alias']['no_quotes']['parts']);

		//a table node
		return $node['alias']['no_quotes']['parts'][ $partCounts - 1 ];
	} else if ( isColref($node) &&
				 isset($node['alias']['as']) ) {

		$partCounts = count($node['alias']['no_quotes']['parts']);

		if($partCounts > 1) {
			return $node['alias']['no_quotes']['parts'][ $partCounts - 2 ];
		} else {
			return false;
		}

	} else {
		//don't know what to do
		return false;
	}
}

function extractTableName($node) {
	//is this a table type or colref/alias?
	if(isTable($node)) {
		$partCounts = count($node['no_quotes']['parts']);
	
		//a table node
		return $node['no_quotes']['parts'][ $partCounts - 1 ];
	} else if ( isColref($node) || isset($node['as']) ) {

		//if this is a "*" node, as in SELECT * FROM, then the no_quotes part is not present
		//and it does not make sense to extract anything anyways
		if(!isset($node['no_quotes'])) {
			return false;
		}

		$partCounts = count($node['no_quotes']['parts']);

		if($partCounts > 1) {
			return $node['no_quotes']['parts'][ $partCounts - 2 ];
		} else {
			return false;
		}

	} else {
		//don't know what to do
		return false;
	}
}

function extractColumnAlias($node) {
	//is this a table type or colref/alias?
	if ( (isColref($node) || isFunction($node) || isExpression($node))  &&
				 isset($node['alias']['as']) ) {

		$partCounts = count($node['alias']['no_quotes']['parts']);

		return $node['alias']['no_quotes']['parts'][ $partCounts - 1 ];
	} else {
		//don't know what to do
		return false;
	}
}

function extractColumnName($node) {
	//is this a table type or colref/alias?
	if ( isColref($node) || isset($node['as']) ) {

		//if this is a "*" node, as in SELECT * FROM, then the no_quotes part is not present
		//and it does not make sense to extract anything anyways
		if(!isset($node['no_quotes'])) {
			return false;
		}

		$partCounts = count($node['no_quotes']['parts']);

		return $node['no_quotes']['parts'][ $partCounts - 1 ];
	} else {
		//don't know what to do
		return false;
	}
}

function hasAlias($node) {
	if(isset($node['alias']) && $node['alias'] !== false) {
		return true;
	} else {
		return false;
	}
}

function hasSubtree($node) {
	if(isset($node['sub_tree']) && $node['sub_tree'] !== false) {
		return true;
	} else {
		return false;
	}
}

function isSubquery($node) {
	if(isset($node['expr_type']) && $node['expr_type'] === 'subquery') {
		return true;
	} else {
		return false;
	}
}

function isOperator($node) {
	if(isset($node['expr_type']) && $node['expr_type'] === 'operator') {
		return true;
	} else {
		return false;
	}
}

function isColref($node) {
	if(isset($node['expr_type']) && $node['expr_type'] === 'colref') {
		return true;
	} else {
		return false;
	}
}

function isTable($node) {
	if(isset($node['expr_type']) && $node['expr_type'] === 'table') {
		return true;
	} else {
		return false;
	}
}

function isFunction($node) {
	if(isset($node['expr_type']) && ($node['expr_type'] === 'function' || $node['expr_type'] === 'aggregate_function')) {
		return true;
	} else {
		return false;
	}
}

function isExpression($node) {
	if(isset($node['expr_type']) && $node['expr_type'] === 'expression') {
		return true;
	} else {
		return false;
	}
}

function implodeNoQuotes($node) {
	if(array_key_exists("delim", $node)) {
		if($node['delim'] !== false) {
			return implode($node['delim'], $node['parts']);
		} else {
			return implode("", $node['parts']);
		}
	} else {
		return implode(".", $node['parts']);
	}

}

function rewriteTableNameInSubqueries(&$subTree, $toThisTableName, $exceptThisTableName) {
	foreach($subTree as &$node) {
		if(hasSubtree($node)) {
			rewriteTableNameInSubqueries($node['sub_tree'], $toThisTableName, $exceptThisTableName);
		}

		if(isColref($node)) {
			$currTable = extractTableName($node);

			if($currTable !== $exceptThisTableName) {
				//if there is a * in the subquery somewhere
				if(!array_key_exists("no_quotes", $node)) {
					$node['no_quotes'] = array("delim" => ".", "parts" => array());
				}

				$node['no_quotes']['parts'] = array($toThisTableName, implodeNoQuotes($node['no_quotes']));
				$node['base_expr'] = getBaseExpr($node);
			}
		}
	}
}

function setNoQuotes(&$node, array $parts, $delim = false) {
	if($delim !== false) {
		$node['no_quotes']['delim'] = $delim;
	} else if (count($parts) > 1) {
		//apply a default delimiter
		$node['no_quotes']['delim'] = ".";
	}

	$node['no_quotes']['parts'] = $parts;

	$node['base_expr'] = getBaseExpr($node);
}

?>
