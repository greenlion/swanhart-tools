cmake_minimum_required(VERSION 2.6)
#configure_file(${CMAKE_CURRENT_SOURCE_DIR}/phpembed/phpembedVersion.h.cmake ${CMAKE_CURRENT_BINARY_DIR}/phpembed/phpembedVersion.h)
set(CMAKE_INSTALL_RPATH "$ORIGIN/")

IF(NOT EXISTS ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/libs/libphp5.so)

execute_process(
COMMAND ./configure --prefix=${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins --enable-embed=shared --enable-bcmath --enable-calendar --with-mysql=mysqlnd --with-mysqli --enable-embedded-mysqli --enable-mysqlnd --enable-maintainer-zts --enable-zend-multibyte --with-tsrm-pthreads
WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/
RESULT_VARIABLE PHP_CONF_RESULT
)

if(${PHP_CONF_RESULT} GREATER 0)
  message( FATAL_ERROR "PHP could not be configured." )
endif()

execute_process(
COMMAND make -j 
WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/
RESULT_VARIABLE PHP_MAKE_RESULT
)

if(${PHP_MAKE_RESULT} GREATER 0)
  message( FATAL_ERROR "PHP could not be built. ") 
endif()

ENDIF()

ADD_DEFINITIONS( -D WITH_WARPSQL=1 )
ADD_SUBDIRECTORY(phpembed)
INSTALL(FILES extern/php/libs/libphp5.so DESTINATION ${PLUGINDIR})
INSTALL(FILES extern/gearmand/libgearman/.libs/libgearman.so DESTINATION ${PLUGINDIR})
INSTALL(FILES extern/gearmand/libgearman/.libs/libgearman.so.8.0.0 DESTINATION ${PLUGINDIR})
INSTALL(FILES extern/gearmand/libgearman/.libs/libgearman.so.8 DESTINATION ${PLUGINDIR})

ADD_SUBDIRECTORY(gearmand)
ADD_SUBDIRECTORY(udf)

#ADD_SUBDIRECTORY(shard_split)
