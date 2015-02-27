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
/*	my_bool fb_create_init(UDF_INIT *initid, UDF_ARGS *args, char* message);
	long long fb_create(UDF_INIT *initid, UDF_ARGS *args, char *is_null, char *error);
	void fb_create_deinit(UDF_INIT *initid);

	char* fb_file_get_contents(UDF_INIT *initid, UDF_ARGS *args,char *result, long long *length, char *is_null, char *error);
*/
}

#endif
