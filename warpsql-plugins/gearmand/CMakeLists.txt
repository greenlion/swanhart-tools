# Copyright (c) 2006, 2011, Oracle and/or its affiliates. All rights reserved.
# 
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; version 2 of the License.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301 USA

#MYSQL_ADD_PLUGIN(q q.cc server.cc
#  MODULE_ONLY MODULE_OUTPUT_NAME "libq" COMPONENT Test)
#
#INSTALL(FILES q.ini DESTINATION ${INSTALL_PLUGINDIR} COMPONENT Test)
IF(NOT EXISTS ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/gearmand/Makefile)

execute_process(
COMMAND bash config.sh
WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/gearmand
RESULT_VARIABLE CONF_RESULT
)

if(${CONF_RESULT} GREATER 0)
  message( FATAL_ERROR "gearmand could not be configured." )
endif()
endif()

IF(NOT EXISTS ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/gearmand/gearmand/gearmand)
execute_process(
COMMAND bash build.sh
WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/gearmand/
RESULT_VARIABLE MAKE_RESULT
)

if(${MAKE_RESULT} GREATER 0)
  message( FATAL_ERROR "gearmand could not be built.") 
endif()

endif()

set(CMAKE_MODULE_PATH ${CMAKE_MODULE_PATH} "${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/cmake")
find_package(LibEvent REQUIRED)

add_definitions(-std=c++11) 
set(CMAKE_INSTALL_RPATH "$ORIGIN/")
FIND_LIBRARY(LOC gearman PATHS ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/gearmand/libgearman/.libs)
ADD_LIBRARY(gearman SHARED IMPORTED)
ADD_LIBRARY(gearman-server STATIC IMPORTED)
ADD_LIBRARY(libtest STATIC IMPORTED)
SET_TARGET_PROPERTIES(gearman PROPERTIES IMPORTED_LOCATION ${LOC})
set_target_properties(gearman-server PROPERTIES IMPORTED_LOCATION ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/gearmand/libgearman-server/.libs/libgearman-server.a)
set_target_properties(libtest PROPERTIES IMPORTED_LOCATION ${CMAKE_SOURCE_DIR}/plugin/warpsql-plugins/extern/gearmand/libtest/.libs/libtest.a)
INCLUDE_DIRECTORIES(../extern/gearmand/)
MYSQL_ADD_PLUGIN(q q.cc server.cc client.c MODULE_ONLY MODULE_OUTPUT_NAME "libq")
target_link_libraries(q gearman gearman-server ${LIBEVENT_LIB} libtest)
