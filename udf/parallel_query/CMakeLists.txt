cmake_minimum_required(VERSION 2.6)
include(ExternalProject)
ExternalProject_Add(
  php
  SOURCE_DIR ${CMAKE_CURRENT_SOURCE_DIR}/php
  CONFIGURE_COMMAND ${CMAKE_CURRENT_SOURCE_DIR}/php/configure --prefix=${CMAKE_CURRENT_SOURCE_DIR}
  PREFIX ${CMAKE_CURRENT_SOURCE_DIR}/
  BUILD_COMMAND make -j 8  && make install
  BUILD_IN_SOURCE 1
)
add_subdirectory(phpembed)
add_subdirectory(udf)
