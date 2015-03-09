#ifndef PRESTO_H
#define PRESTO_H 1
#include <mysql.h>
#include <sstream>	// std::ostringstream
#include <algorithm>	// std::sort
#include <memory>	// std::unique_ptr
#include <iomanip>	// std::setprecision

#include <plugin.h>
#include <fstream>
#include <ftw.h>
#include <unistd.h>

#ifndef MAXSTR
#define MAXSTR 766
#endif

/* to remind me of the signatures */
extern "C" { 
	my_bool rapid_extract_all_init(UDF_INIT *initid, UDF_ARGS *args, char* message);
	char* rapid_extract_all(UDF_INIT *initid, UDF_ARGS *args,char *result, long long *length, char *is_null, char *error);
	void rapid_extract_all_deinit(UDF_INIT *initid);
	my_bool rapid_flatten_json_init(UDF_INIT *initid, UDF_ARGS *args, char* message);
	char* rapid_flatten_json(UDF_INIT *initid, UDF_ARGS *args,char *result, long long *length, char *is_null, char *error);
	void rapid_flatten_json_deinit(UDF_INIT *initid);
	//char* fb_file_get_contents(UDF_INIT *initid, UDF_ARGS *args,char *result, long long *length, char *is_null, char *error);
	//long long flatten_json(UDF_INIT *initid, UDF_ARGS *args, char *is_null, char *error);
}

#endif
