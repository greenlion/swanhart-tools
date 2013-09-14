<?php

$file = "create_mvlog";
$fh = fopen("../procs/" . $file . ".sql", 'r');

$in_comment=false;
$in_str=false;
$in_create = false;
$proc_name = "";
$proc_handler_var = "";
$depth = 0;

global $stmt;
$stmt = "";

while($line = fgets($fh)) {
  $line = trim($line);
  $pos = strstr($line, "-- ");
  if($pos) $line = substr($line, 0, $pos);
  if(substr($line,-2) == ";;") {
  	if(substr($line,0,3) != "END") {
  		continue;
  	}
  }
  if(!$line || substr($line, 0, 3) == "-- ") {
  	if($line) echo "// $line \n";
  	continue;
  }  
  preg_match('/^\s*([^ ]+)/', $line, $matches);
  
  if ($matches[0] == '/*') {
  	$in_comment = true;
  	continue;
  }
  
  if ($in_comment && $matches[0] == '*/') {
  	$in_comment = false;
  	continue;
  }
  
  if($in_comment) continue;
  $matches[0] = strtoupper($matches[0]);
  
  if($matches[0] == 'DELIMITER') continue;
  
  /* we found the start of a function or procedure */
  if($matches[0] == 'CREATE' && $proc_name == "") {
  	++$depth;
  	$in_create = true;
  	$stmt = $line;
  	continue;
  }
  
  /* the end of the CREATE function or procedure statement (only if $in_create) */
  if($in_create) {
    if($matches[0] == "BEGIN") {
  	  echo "# $stmt\n";
 
  	  $in_create = false;
  	  if($proc_name) {
  	  	/* we need to start a new procedure */
  	  	echo "}\n";
  	  }
  	  
  	  preg_match('/\.`([^`]+)/', $stmt, $matches);
  	  $proc_name = $matches[1];
  	  preg_match_all('/(?:IN|OUT)\s+([^\s]+)/', $stmt, $matches);
  	  $proc_args = $matches[1];
	  
  	  echo "function $proc_name(" . implode(",", $proc_args) . ") { \n";
      $stmt = "";
  	  continue;
    } else {
    	if($stmt) $stmt .= " ";
    	$stmt .= $line;
    	continue;
    }
  }
  
  if(substr($line,-1) == ';' 
     || substr($line, -4) == 'LOOP' 
     || substr($line, -4) == 'THEN'
     || substr($line, -4) == 'ELSE'
     ) {
  	
  	if($stmt) $stmt .= " ";
  	$stmt .= $line;

  	$tokens = tokenize($stmt, true);

  	switch(strtoupper($tokens[0])) {

  		#declare a variable or a cursor
  		case 'DECLARE':

  			#just a regular variable or an error handler
  			if (strtoupper($tokens[2]) != 'CURSOR') {
  				if($tokens[1] == 'CONTINUE') {
  					
  					if($tokens[4] == 'SQLSTATE' && $tokens[5] == "'02000'" && $tokens[6] == 'SET') {
						indent("#NOTICE:: Found continue handler for SQLSTATE 02000\n", $depth);
  						$proc_handler_var = '$' . $tokens[7];
  					} else {
  						print_r($tokens);
  						exit;
  					}	
  					
  				} else {
  					$def = 'NULL';
  					$collect = 0;
  					for($i=0;$i<count($tokens);++$i) {
  						if($tokens[$i] == 'DEFAULT') {
  							$collect = 1;
  							$def = "";
  							continue;
  						}
  						if($collect) { 
  							$def .= $tokens[$i];
  						}
  					}
            		
  					indent("\${$tokens[1]}={$def};\n",$depth);
  				}
  				break;
  			} else {
  				/* HANDLE CURSOR DECLARATIONS */
  				$select = "";
  				$curs_name = $tokens[1];
  				$proc_cursors[$curs_name]['vars'] = array();
  				$tokens = tokenize($stmt, false); #we need to preserve format for the cursor SQL
			                                  
  				foreach($tokens as $token) {
  					if($token != 'SELECT' && !$select) continue;
  					#variables in the cursor get turned into PHP variables
  					#TODO look in the list of args and vars for the token just in case
  					if(substr($token,0,2) == "v_") { 
  					  	$proc_cursors[$curs_name]['vars'][] =  $token;
  						$token = "\${$token}";	
  					}
  					$select .= $token;
  				}
  				$proc_cursors[$curs_name]['sql'] = $select;
//print_r($proc_cursors);
				$stmt = "";
				break;  				
  			}
  			
  		case 'SET':
  			//print_r($tokens);
  			//echo $stmt . "\n";
  			preg_match('/^SET\s+([^:=]+):*=\s*(.*$)/i', $stmt, $matches);
  			//print_r($matches);
  			#replace session vars with 
  			$var = trim(str_replace("@", "_", $matches[1]));
  			$new_stmt = "";
  			if($var[0] == "_") { 
  				indent("global \${$var};\n", $depth);
  			}
  			$new_stmt = $matches[2];
  			$new_stmt = str_replace(array('"', '\\'), array('\\"','\\\\'), $new_stmt);
  			
  			if(!$new_stmt) continue;
  			$new_stmt = str_replace('v_', '$v_', $new_stmt);
  			//if($tokens[3] != 'CONCAT') {
  				indent('$' . $var . ' = ' . $new_stmt . "\n", $depth);
  			/*} else {
  				$concat_depth = 0;
  				$out = "";
  				$tokens = tokenize($new_stmt, false);
  				print_r($tokens);
  				for($i=0;$i<count($tokens);++$i) {

  					if($tokens[$i] == '(') {
  						++$concat_depth;
  						//if($concat_depth == 1) continue;
  					}
  					
  					if($concat_depth < 1) {
  						continue;
  					}
  					
  					if($tokens[$i] == ')' || substr($tokens[$i], -1) == ')') {
  						--$concat_depth;
  						  					
  					}
  					
  					if($concat_depth == 1) {
  						if($tokens[$i] == ',') {
  							$tokens[$i] = ' . ';
  						}
  					}
  					$out .= $tokens[$i];
  				}
  				indent('$' . $var . ' = ' . $out . "\n", $depth);
  			}*/
  			$stmt = "";
  			break;
  			
  		case 'PREPARE':  			
  			indent("\${$tokens[1]} = mysql_query(\$" . trim(str_replace("@", "_", $tokens[3])) . ");\n", $depth);
  			break;
  		/* TODO:
  		 * we actually execute at prepare, which won't always work, but 
  		 * we'll worry about that when we encounter it.  Flexviews only uses
  		 * USING in one UNMAINTAINED stored procedure so it isn't that important to me
  		 * right now.
  		 */	
  		case 'EXECUTE':
  		case 'DEALLOCATE':
  			break;
  			
  		case 'OPEN':
  			//print_r($proc_cursors);
  			//eval("\$sql = \"$str\";");
  			indent("#mysql cursors evaluate cursor variables at run time - emulate that:\n",$depth);
  			$cursor_sql = str_replace(array('"', '\\'), array('\\"','\\\\'), $proc_cursors[$tokens[1]]['sql']);
  			indent("\$cursor_sql = \"{$proc_cursors[$tokens[1]]['sql']}\";\n", $depth);
  			
  			indent("\${$tokens[1]} = mysql_query(\$cursor_sql);", $depth);
  			break; 	
  			
  		case 'IF':

  			foreach($tokens as $val) {
  				switch($val) {
  					case 'IF':		
  						indent("if(", $depth, false);
  						break;
  					case 'THEN':	
  						echo " ) {\n";
  						break;
  					default:  						
  						if(substr($val,0,2) == 'v_' ) {
  							$val = '$' . $val;
  						}
  						if($val == "=") $val = "==";
  						
  						if($val[0] != "(") echo " "; 
  						echo $val;
  				}
  			}
  			++$depth;
  			break;
  		case 'ELSE':
  			indent("} else {", $depth-1);
  			break;
  				
  		case 'END':
  			--$depth;
  			//print_r($tokens);
  			if(count($tokens) == 1) {
  				echo "}\n";
  				continue;
  			}
  			
  			switch($tokens[1]) {
  				case 'IF':
  				case 'LOOP':
  					indent("}\n", $depth);
  					break;
  						
  				default:
  					echo "$stmt\n";
  					print_r($tokens);
  					exit;
  			}	
  			break;
  			
  		case 'FETCH':
  			indent("\$row = mysql_fetch_array(\${$tokens[1]});\n", $depth);
  			if($proc_handler_var) {
  				indent("if( \$row === false ) {$proc_handler_var} = true;\n", $depth);
  			}
  			indent("if( \$row ) {\n", $depth);
  			$i = 0;
  			foreach($tokens as $token) {
  				if(substr($token, 0,2) == 'v_') {
  					indent("\${$token} = \$row[{$i}];\n", $depth + 1);
  					++$i;
  				}
  			}
  			indent("}\n", $depth);

  			break;	

  		case 'CLOSE':
  			indent("mysql_free_result(\${$tokens[1]});\n", $depth);
  			break;

  		case 'LEAVE':
  			indent("break;", $depth);
  			break;
  			
  		case 'ITERATE':
  			indent("continue;", $depth);	
  			
  		case 'CALL':
  			indent(substr($stmt,5), $depth);

  			break;
  			  				
  		default:
  			if($tokens[1] == ':' && $tokens[2] == 'LOOP') {
  				#HANDLE loop declarations
  				indent("# $stmt\n", $depth);
  				indent("while(1) {\n", $depth);
  				++$depth;
  				continue;
  			}
  			if($tokens[0] == "SELECT") {
  				echo "##### UNHANDLED SELECT\n";
  				continue;
  			}
  			if($tokens[0] == "INSERT" ||
  			   $tokens[0] == "DELETE" ||
  			   $tokens[0] == "UPDATE" ||
  			   $tokens[0] == "CREATE" ||
  			   $tokens[0] == "REPLACE" ||
  			   $tokens[0] == "DROP") {
  			   	
  			   	$sql = "";
  			   	foreach($tokens as $token) {
  			   		if(substr($token, 0, 2) == "v_") {
  			   		  $sql .= "'$" . $token . "'";	
  			   		}	else {
  			   			if($token[0] != "(") $sql .= " ";
  			   			$sql .= $token;
  			   		}
  			   	}
  						   	
  			   	indent("\$exec_sql = \"{$sql}\";\n", $depth);
  			    indent("mysql_query(\$exec_sql);\n", $depth);
  			   	continue;
  			}
  			
  			#unknown syntax
	  		print_r($tokens);
	  		exit;
  		
  	}
  	
  	$stmt = "";

  } else {
    if($stmt) $stmt .= " ";
    $stmt .= $line;
  }
}

function indent($text, $depth, $newline = true) {
	static $last_stmt = NULL;	
	global $stmt;
	if($stmt == $last_stmt) {
		$stmt2 = "";	
	} else {
		$stmt2 = str_replace("\n", "# \n", $stmt);
		$last_stmt = $stmt;
	}
	
	$indent = "";
	for($i=0;$i<$depth;++$i) {
		$indent .= "\t";
	}
	if($stmt2) echo $indent . "#" . $stmt2 . "\n";
	
	echo $indent . $text;
	if ($newline) echo "\n";
}

function tokenize($sql, $reduce_whitespace=true) {
	if(!$sql) return;
	$sql = trim($sql, ';');
  
	$regex = <<<END_OF_REGEX
	/ 
	  \:
	  |[LOOP]{4}
	  |[A-Za-z_.]+
	  |!=
	  |&&
	  |\|\|
	  |[(),=\-+*|!%]
	  |\(.*?\)   # Match FUNCTION(...) OR BAREWORDS
	  |"[^"](?:|\"|"")*?"+
	  |'[^'](?:|\'|'')*?'+
	  |`(?:[^`]|``)*`+
	  |[^ ,]+
	  |\s*
	  |,

	/x
END_OF_REGEX;

  preg_match_all($regex, $sql, $matches);
  if ($reduce_whitespace) {
  	$out = array();
  	foreach($matches[0] as $val) {
  		if(trim($val)) $out[] = $val;
  	}
  	return($out);
  	
  } else {
  	return $matches[0];
  }

}