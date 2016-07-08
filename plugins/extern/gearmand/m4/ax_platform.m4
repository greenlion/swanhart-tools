# ===========================================================================
#       http://
# ===========================================================================
#
# SYNOPSIS
#
#   AX_PLATFORM
#
# DESCRIPTION
#
#   Provide target and host defines.
#
# LICENSE
#
#   Copyright (c) 2012-2013 Brian Aker <brian@tangent.org>
#
#   Copying and distribution of this file, with or without modification, are
#   permitted in any medium without royalty provided the copyright notice
#   and this notice are preserved. This file is offered as-is, without any
#   warranty.

#serial 4
#
  AC_DEFUN([AX_PLATFORM],
      [AC_REQUIRE([AC_CANONICAL_HOST])

      AC_DEFINE_UNQUOTED([HOST_VENDOR],["$host_vendor"],[Vendor of Build System])
      AC_DEFINE_UNQUOTED([HOST_OS],["$host_os"], [OS of Build System])
      AC_DEFINE_UNQUOTED([HOST_CPU],["$host_cpu"], [CPU of Build System])

      AS_CASE([$host_os],
        [*mingw*],
        [HOST_WINDOWS="true"
        AC_DEFINE([HOST_OS_WINDOWS], [1], [Whether we are building for Windows])
        AC_DEFINE([EAI_SYSTEM], [11], [Another magical number])
        AH_BOTTOM([
#ifndef HAVE_SYS_SOCKET_H
# define SHUT_RD SD_RECEIVE
# define SHUT_WR SD_SEND
# define SHUT_RDWR SD_BOTH
#endif
          ])],
        [*freebsd*],[AC_DEFINE([HOST_OS_FREEBSD],[1],[Whether we are building for FreeBSD])
        AC_DEFINE([__APPLE_CC__],[1],[Workaround for bug in FreeBSD headers])],
        [*solaris*],[AC_DEFINE([HOST_OS_SOLARIS],[1],[Whether we are building for Solaris])],
        [*darwin*],
        [HOST_OSX="true"],
        [*linux*],
        [HOST_LINUX="true"
        AC_DEFINE([HOST_OS_LINUX],[1],[Whether we build for Linux])])

  AM_CONDITIONAL([BUILD_WIN32],[test "x${HOST_WINDOWS}" = "xtrue"])
  AM_CONDITIONAL([HOST_OSX],[test "x${HOST_OSX}" = "xtrue"])
  AM_CONDITIONAL([HOST_LINUX],[test "x${HOST_LINUX}" = "xtrue"])
  AM_CONDITIONAL([HOST_FREEBSD],[test "x${HOST_OS_FREEBSD}" = "xtrue"])
  ])
