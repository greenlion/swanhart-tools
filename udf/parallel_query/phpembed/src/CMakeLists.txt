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

include_directories("../include" "../include/php" "../include/php/sapi" "../include/php/sapi/embed" "../include/php/ext" "../include/php/ext/date" "../include/php/ext/date/lib" "../include/php/ext/ereg" "../include/php/ext/ereg/regex" "../include/php/ext/pcre" "../include/php/ext/pcre/pcrelib" "../include/php/ext/sqlite3" "../include/php/ext/sqlite3/libsqlite" "../include/php/ext/filter" "../include/php/ext/hash" "../include/php/ext/iconv" "../include/php/ext/json" "../include/php/ext/pdo" "../include/php/ext/session" "../include/php/ext/spl" "../include/php/ext/sqlite" "../include/php/ext/sqlite/libsqlite" "../include/php/ext/sqlite/libsqlite/src" "../include/php/ext/standard" "../include/php/Zend" "../include/php/TSRM" "../include/php/include" "../include/php/main" "../include/php/main/streams")

# =========================================================
MACRO (APPEND_CMAKE_INSTALL_RPATH RPATH_DIRS)
   IF (NOT ${ARGC} EQUAL 1)
     MESSAGE(SEND_ERROR "APPEND_CMAKE_INSTALL_RPATH takes 1 argument")
   ENDIF (NOT ${ARGC} EQUAL 1)
   FOREACH ( RPATH_DIR ${RPATH_DIRS} )
     IF ( NOT ${RPATH_DIR} STREQUAL "" )
        FILE( TO_CMAKE_PATH ${RPATH_DIR} RPATH_DIR )
        STRING( SUBSTRING ${RPATH_DIR} 0 1 RPATH_FIRST_CHAR )
        IF ( NOT ${RPATH_FIRST_CHAR} STREQUAL "/" )
          # relative path; CMake handling for these is unclear,
          # add them directly to the linker line. Add both $ORIGIN
          # and $$ORIGIN to ensure correct behavior for exes and
          # shared libraries.
          SET ( RPATH_DIR "$ORIGIN/${RPATH_DIR}:$$ORIGIN/${RPATH_DIR}" )
          SET ( CMAKE_EXE_LINKER_FLAGS
                "${CMAKE_EXE_LINKER_FLAGS} -Wl,-rpath,'${RPATH_DIR}'" )
          SET ( CMAKE_SHARED_LINKER_FLAGS
                "${CMAKE_SHARED_LINKER_FLAGS} -Wl,-rpath,'${RPATH_DIR}'" )
        ELSE ( NOT ${RPATH_FIRST_CHAR} STREQUAL "/" )
          # absolute path
          SET ( CMAKE_INSTALL_RPATH "${CMAKE_INSTALL_RPATH}:${RPATH_DIR}" )
        ENDIF ( NOT ${RPATH_FIRST_CHAR} STREQUAL "/" )
     ENDIF ( NOT ${RPATH_DIR} STREQUAL "" )
   ENDFOREACH ( RPATH_DIR )
ENDMACRO ( APPEND_CMAKE_INSTALL_RPATH )

#libphp5.so is going to be in the MySQL plugin directory so rpath must be set
APPEND_CMAKE_INSTALL_RPATH(".;/usr/local/lib")

add_library(phpembed SHARED ${phpembed_LIB_SRCS})
set_target_properties(phpembed PROPERTIES VERSION 4.2.0 SOVERSION 4)

target_link_libraries(phpembed general php5)

install(TARGETS phpembed DESTINATION lib)
