#ifndef INCLUDED_WARPSQL_UDF
#undef PACKAGE_VERSION
#undef PACKAGE_STRING
#undef PACKAGE_TARNAME
#undef PACKAGE_NAME
#undef HAVE_MBSTATE_T

#pragma GCC diagnostic ignored "-Wwrite-strings"
#include <my_global.h>
#include <mysql_version.h>
#include <mysql/plugin.h>
#include <mysql_com.h>

#undef PACKAGE_VERSION
#undef PACKAGE_STRING
#undef PACKAGE_TARNAME
#undef PACKAGE_NAME
#undef HAVE_MBSTATE_T

#include <php.h>
#include <php_cxx.h>

#include <sstream>	// std::ostringstream
#include <iostream>
#include <algorithm>	// std::sort
#include <memory>	// std::unique_ptr
#include <iomanip>	// std::setprecision

#include <fstream>
#include <ftw.h>
#include <unistd.h>

#ifndef MAXSTR
#define MAXSTR 766
#endif

#ifndef LOGGER
#define LOGGER(msg) \
	if(DEBUG) std::cerr << msg << "\n"; else ;
#endif

extern void php_log_output(const char *);
extern php* new_PHP();
extern php_ret load_helpers();

/* setting global variables in the PHP environment is 
 * made easier using these helper macros
 */
#define sets(p,k,v) ((p)->call_void("SET_GLOBAL","ss", k, static_cast<const char*>(v)));
#define seti(p,k,v) ((p)->call_void("SET_GLOBAL","si", k, v));

#endif

