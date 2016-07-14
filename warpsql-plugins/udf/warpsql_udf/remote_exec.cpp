#include "warpsql_udf.h" 

extern "C" {

// JSON remote_exec(host, port, user, pass, db, query)
my_bool remote_exec_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
	if(args->arg_count != 6) {
		strcpy(message,"usage: remote_exec(host,port,user,pass,db,query)");
		return 1;
	}
	for(unsigned int i=0;i<args->arg_count;i++) {
        	if(args->arg_type[i] != STRING_RESULT) {
			strcpy(message,"All arguments must be strings");
			return 1;
	        }
	}
	return 0;
}

char* remote_exec(UDF_INIT *initid, UDF_ARGS *args, char *result, long long *length, char *is_null, char *error) {
	php* p = new_PHP();

	if(SUCCESS == p->eval_string(
		R"(
		function JSON_QUERY($host,$port,$user,$pass,$db,$query) {
			try {
				$conn = mysqli_connect($host . ':' . $port,$user,$pass,$db);
                		if(!$conn) return("NOT_OK " . mysqli_connect_errno() . ': ' . mysqli_connect_error());
				$stmt = mysqli_query($conn, $query);
				if($stmt === true) {
					return("OK");
					
				}
				if($stmt === false) {
					return("NOT_OK " . mysqli_errno($conn) . ':'. mysqli_error($conn));
					
				}

				$res['fields']=mysqli_fetch_fields($stmt);
				$res['rs']=mysqli_fetch_all($stmt, MYSQLI_NUM);
				mysqli_free_result($stmt);
				return("RESULTSET " . json_encode($res));
			} catch (Exception $e) {
				return("NOT_OK 99999:" . trim(print_r($e,true)));
			}
		}
		)"
	)) {;
		initid->ptr = p->call_c_string_ex("JSON_QUERY",(unsigned int *)length,"SSSSSS",args->args[0], args->lengths[0],args->args[1], args->lengths[1],args->args[2], args->lengths[2],args->args[3], args->lengths[3],args->args[4], args->lengths[4],args->args[5], args->lengths[5]);
	};

	delete p;

	return initid->ptr;

}

void remote_exec_deinit(UDF_INIT *initid) { 
	free(initid->ptr);
}


} /* extern C */
