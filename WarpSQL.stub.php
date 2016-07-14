<?php
	Phar::interceptFileFuncs();
	function __autoload(\$classname) {
		\$classpath = str_replace('\\\\','/',\$classname);
		\$filename = "phar://WarpSQL.phar/\${classpath}.php";
		include_once(\$filename);
	}

	class WarpSQL\Utility {
		static var \$parser = false;
		static function parse(\$sql) {
	  		require_once('phar://warp.phar/PHPSQLParser/PHPSQLParser.php');
	  		if(WarpSQL::\$parser === false) WarpSQL::\$parser = new PHPSQLParser\PHPSQLParser;
	  		WarpSQL::\$parser->parse(\$sql);
			return(\$parser->parsed);
		}

		static function parse_json(\$sql) {
	  		return(json_encode(\$this->parse(\$sql)));
		}

		static function parse_serialized(\$sql) {
	  		return(serialize(\$this->parse(\$sql)));
		}

	}

?>
