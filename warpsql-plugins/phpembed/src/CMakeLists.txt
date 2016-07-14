cmake_minimum_required(VERSION 2.6)
########### next target ###############

SET(phpembed_LIB_SRCS
   php_cxx.cpp
   php_arr.cpp
   php_stl.cpp
   php_tok.cpp
   php_arr.h
   php_cxx.h
   php_stl.h
   php_tok_consts.h
   php_tok.h
)

ADD_DEFINITIONS(-D ZTS)

FIND_LIBRARY(PHP_LOC php5 PATHS ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/libs ${PLUGINDIR})

INCLUDE_DIRECTORIES(
  ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/phpembed/src
  ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/
  ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/include
  ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/main
  ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/sapi/embed
  ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/Zend
  ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/php/TSRM
) 

ADD_LIBRARY(php5 SHARED IMPORTED)
SET_TARGET_PROPERTIES(php5 PROPERTIES IMPORTED_LOCATION ${PHP_LOC} )

add_library(phpembed SHARED ${phpembed_LIB_SRCS})
set_target_properties(phpembed PROPERTIES VERSION 4.2.0 SOVERSION 4)
target_link_libraries(phpembed php5)


install(TARGETS phpembed DESTINATION ${PLUGINDIR})
