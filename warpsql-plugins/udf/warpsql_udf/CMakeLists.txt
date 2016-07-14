# Copyright (c) 2016, Oracle and/or its affiliates. All rights reserved.
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
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

SET(warpsql_udf_LIB_SRCS
  warpsql_udf.cpp remote_exec.cpp

  warpsql_udf.h
)

SET(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -std=c++11")
	
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

MYSQL_ADD_PLUGIN(warpsql_udf ${warpsql_udf_LIB_SRCS} MODULE_ONLY)
TARGET_LINK_LIBRARIES(warpsql_udf php5 phpembed)
