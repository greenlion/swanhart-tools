cmake_minimum_required(VERSION 2.6)
SET(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -std=c++11 -Wno-literal-sufix -Wno-write-strings")
SET(presto_LIB_SRCS
  php.cpp
  presto.cpp
  rewrite.cpp
  presto.h
)

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
APPEND_CMAKE_INSTALL_RPATH(".;/usr/local/lib")

find_package(MySQL REQUIRED)
link_directories(${MYSQL_LIB_DIR} ${PROJECT_SOURCE_DIR}/lib)
link_libraries(${MYSQL_CLIENT_LIBS})

add_library(presto SHARED ${presto_LIB_SRCS})
set_target_properties(PROPERTIES VERSION 1.0.0 SOVERSION 1)

target_link_libraries(presto general php5)
target_link_libraries(presto general phpembed)

install(TARGETS presto DESTINATION ${PROJECT_SOURCE_DIR}/lib)
