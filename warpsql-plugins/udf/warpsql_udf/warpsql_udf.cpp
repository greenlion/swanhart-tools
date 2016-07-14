#include "warpsql_udf.h"

/* This function hooks rewrite related PHP output to the general log*/ 
void php_log_output(const char *str) {
	std::cerr << str;
}


/* Start the PHP interpreter and install a few useful 
 * functions into it
 */
php* new_PHP() {
	php* p = new php(true);
	p->set_message_function(php_log_output);
	p->set_output_function(php_log_output);
	p->set_error_function(php_log_output);

	return p;
}

php_ret load_helpers(php *p) {
	return p->eval_string(R"(

	function GET_INDEX($ary, $idx) { 
		return($ary[$idx]); 
	}; 

	function GET_GLOBAL($var) {
		if(!isset($GLOBALS[$var])) return false;
		return $GLOBALS[$var];
	}

	function SET_GLOBAL($var,$val) {
		if(!$var) return false;
		$GLOBALS[$var] = $val;
		return true;
	}


	)");

}
