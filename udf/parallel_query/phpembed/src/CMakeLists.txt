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

include_directories("${PROJECT_SOURCE_DIR}/include" "${PROJECT_SOURCE_DIR}/include/php" "${PROJECT_SOURCE_DIR}/include/php/sapi" "${PROJECT_SOURCE_DIR}/include/php/sapi/embed" "${PROJECT_SOURCE_DIR}/include/php/ext" "${PROJECT_SOURCE_DIR}/include/php/ext/date" "${PROJECT_SOURCE_DIR}/include/php/ext/date/lib" "${PROJECT_SOURCE_DIR}/include/php/ext/ereg" "${PROJECT_SOURCE_DIR}/include/php/ext/ereg/regex" "${PROJECT_SOURCE_DIR}/include/php/ext/pcre" "${PROJECT_SOURCE_DIR}/include/php/ext/pcre/pcrelib" "${PROJECT_SOURCE_DIR}/include/php/ext/sqlite3" "${PROJECT_SOURCE_DIR}/include/php/ext/sqlite3/libsqlite" "${PROJECT_SOURCE_DIR}/include/php/ext/filter" "${PROJECT_SOURCE_DIR}/include/php/ext/hash" "${PROJECT_SOURCE_DIR}/include/php/ext/iconv" "${PROJECT_SOURCE_DIR}/include/php/ext/json" "${PROJECT_SOURCE_DIR}/include/php/ext/pdo" "${PROJECT_SOURCE_DIR}/include/php/ext/session" "${PROJECT_SOURCE_DIR}/include/php/ext/spl" "${PROJECT_SOURCE_DIR}/include/php/ext/sqlite" "${PROJECT_SOURCE_DIR}/include/php/ext/sqlite/libsqlite" "${PROJECT_SOURCE_DIR}/include/php/ext/sqlite/libsqlite/src" "${PROJECT_SOURCE_DIR}/include/php/ext/standard" "${PROJECT_SOURCE_DIR}/include/php/Zend" "${PROJECT_SOURCE_DIR}/include/php/TSRM" "${PROJECT_SOURCE_DIR}/include/php/include" "${PROJECT_SOURCE_DIR}/include/php/main" "${PROJECT_SOURCE_DIR}/include/php/main/streams")

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


FIND_LIBRARY(PHP5_LIBRARY php5 ${PROJECT_SOURCE_DIR}/lib)
target_link_libraries(phpembed ${PHP5_LIBRARY})

install(TARGETS phpembed DESTINATION ${PROJECT_SOURCE_DIR}/lib)
