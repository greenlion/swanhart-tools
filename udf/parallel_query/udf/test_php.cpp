#pragma GCC diagnostic ignored "-Wwrite-strings"
#include "presto.h"
#include "myphp.h"
#include <iostream>
#include "rewriteengine.h"

#define DEBUG 1

#define LOGGER(msg) \
	if(DEBUG) std::cerr << msg << "\n"; else ;


/* This function hooks rewrite related PHP output to the general log*/ 
void php_log_output(const char *str){
	std::cout << str;
}

bool eval_compressed_header(php &p,char *hdr) {
	LOGGER("Enter uncompress_header");
	char script[] =  
R"end(
function uncompress_header($hdr) {
  $hdr = explode(',',$hdr);
  $data = "";
  for($i=0;$i<count($hdr);++$i) {
    $c = $hdr[$i];
    $data .= chr($c);
  }
  $data = gzuncompress($data);
  return($data);  
}
)end";
	p.eval_string("%s", script);
	char* ce = p.call_c_string( "uncompress_header", "s", hdr );
	if(ce == NULL) return false;
	return (!p.eval_string("%s", ce));
}

/* You must delete out_plan when done only if the function returns true */
bool rewrite_engine(php &p, std::string sql, std::string partjson,  php_array *out_plan) {
	LOGGER("Enter rewrite_engine"); 

	std::string s;
	s  = "set_include_path('../scripts');\n"; 
        s += "require_once('rewriteengine/rewriteengine.php'); \n"; 
	s += "require_once('rewriteengine/util.php'); \n"; 
	s += "$sql=<<<EOSQL\n"; 
        s +=  sql ; 
        s += "\nEOSQL;\n";
	LOGGER("SCRIPT:\n----------------------------\n" << s << "----------------------------")

	/* Initialize the rewrite script */
	php_ret retval = p.eval_string("%s", s.c_str());
 
	if(SUCCESS != retval) { 
		printf("%s", "Could not eval PHP!\n"); 
		return false;
	}
	/*GET_GLOBAL is in rewriteengine/utils.php*/
	unsigned len=0;
	char vararg[]="s";
	char vname[] = "sql";
	char fn[] = "GET_GLOBAL";
//	std::unique_ptr<char> ce (p.call_c_string_ex( fn, &len, vararg, vname));
	std::unique_ptr<char> ce (p.call_c_string_ex( "GET_GLOBAL", &len, vararg, vname));
	std::cout << ce.get() << "\n";

	return(true);

	//php_array settings;
	//php_array process_info;
	//print_r(EXEC_RULES($sql, $table_info, $process_info,$SETTINGS,'RewriteParallelRule'));
}


int main(int argc, char**argv) {

	std::string json=
R"eod({
  "query_block": {
    "select_id": 1,
    "table": {
      "table_name": "test",
      "partitions": [
        "p0",
        "p1",
        "p2",
        "p3"
      ],
      "access_type": "ALL",
      "rows": 4,
      "filtered": 100
    }
  }
})eod"; 

	php php_ctx(true);
	std::cout << "RESULT: " << eval_compressed_header(php_ctx,RWENGINE) << "\n";

	
exit(1);
	std::string sql = "select a, count(*) from tX where a = 30 group by 1";
	php_array *plan=NULL;
	php_ctx.set_message_function(php_log_output);
	php_ctx.set_output_function(php_log_output);
	php_ctx.set_error_function(php_log_output);

	bool ok = rewrite_engine(php_ctx,sql,json,plan); 
	if(!ok) {
		std::cout << "GOT ERROR\n";
	} else {
		std::cout << "OK\n";
		php_ctx.call_void("print_r", "s", sql.c_str());
		if(plan) { 
			php_ctx.call_void("print_r", "a", plan);
			delete plan;
		}
	}
	
	return 0;

}
