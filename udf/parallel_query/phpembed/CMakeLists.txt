cmake_minimum_required(VERSION 2.6)
configure_file(${CMAKE_CURRENT_SOURCE_DIR}/phpembedVersion.h.cmake ${CMAKE_CURRENT_BINARY_DIR}/phpembedVersion.h)
add_subdirectory(src)
