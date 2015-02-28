cmake_minimum_required(VERSION 2.6)
project(parallel_udf)
list(APPEND CMAKE_MODULE_PATH "${CMAKE_CURRENT_LIST_DIR}/cmake")
include(ExternalProject)
ExternalProject_Add(
  php
  INSTALL_DIR ${CMAKE_CURRENT_SOURCE_DIR} 
  SOURCE_DIR ${CMAKE_CURRENT_SOURCE_DIR}/php
  CONFIGURE_COMMAND ${CMAKE_CURRENT_SOURCE_DIR}/php/configure --prefix=${CMAKE_CURRENT_SOURCE_DIR} --disable-libxml --disable-dom --disable-simplexml --disable-xml --disable-xmlreader --disable-xmlwriter --disable-pear --enable-embed --with-zlib
  BUILD_COMMAND make -j   
  BUILD_IN_SOURCE 1
)
find_package(ZLIB REQUIRED)
add_subdirectory(phpembed)
add_subdirectory(udf)
