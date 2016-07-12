cmake_minimum_required(VERSION 2.6)
configure_file(${CMAKE_CURRENT_SOURCE_DIR}/phpembed/phpembedVersion.h.cmake ${CMAKE_CURRENT_BINARY_DIR}/phpembed/phpembedVersion.h)


ADD_DEFINITIONS( -D WITH_WARPSQL=1 )
ADD_SUBDIRECTORY(phpembed)
INSTALL(FILES extern/php/libs/libphp5.so DESTINATION ${PLUGINDIR})
INSTALL(FILES extern/gearmand/libgearman/.libs/libgearman.so DESTINATION ${PLUGINDIR})
INSTALL(FILES extern/gearmand/libgearman/.libs/libgearman.so.8.0.0 DESTINATION ${PLUGINDIR})
INSTALL(FILES extern/gearmand/libgearman/.libs/libgearman.so.8 DESTINATION ${PLUGINDIR})

ADD_SUBDIRECTORY(gearmand)

#ADD_SUBDIRECTORY(shard_split)
