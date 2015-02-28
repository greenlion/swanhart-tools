#pragma GCC diagnostic ignored "-Wwrite-strings"
#include "presto.h"
#include "myphp.h"
#include <iostream>
#include <string>
extern std::string rwenginephar;
#define DEBUG 1

#define LOGGER(msg) \
	if(DEBUG) std::cerr << msg << "\n"; else ;

#define echo std::cout << 


/* This function hooks rewrite related PHP output to the general log*/ 
void php_log_output(const char *str){
	std::cout << str;
}



/* You must delete out_plan when done only if the function returns true */
bool rewrite_engine(php &p, std::string sql, std::string partjson,  php_array *out_plan) {
std::cout << "STRLEN: " << strlen(rwenginephar.c_str());
exit(3);
//	LOGGER("Enter rewrite_engine"); 

//	LOGGER("Initialize engine with eval")

	// rewritephar is defined in rewrite.cpp
	if(p.eval_string("$b64 = \"%s\";", rwenginephar.c_str())) return false;
	if(p.eval_string("echo 'LEN:' . strlen($b64);")) exit(2);
exit(1);

	if(p.eval_string("eval($data);")) { echo "BLARGH\n"; exit(1); }
	if(p.eval_string("$sql = '%s';", sql.c_str())) {
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
