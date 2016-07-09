#  Copyright (C) 2009 Sun Microsystems, Inc.
#  This file is free software; Sun Microsystems, Inc.
#  gives unlimited permission to copy and/or distribute it,
#  with or without modifications, as long as this notice is preserved.
 
#  Provides support for finding libtokyocabinet.
#
# serial 2

AC_DEFUN([_PANDORA_SEARCH_LIBTOKYOCABINET],
    [AC_REQUIRE([AX_CHECK_LIBRARY])

    AS_IF([test "x$ac_enable_libtokyocabinet" = "xyes"],
      [tokyocabinet_header="tcadb.h"],
      [tokyocabinet_header="does_not_exist"])

    AX_CHECK_LIBRARY([TOKYOCABINET],[$tokyocabinet_header],[tokyocabinet],,
                     [AC_DEFINE([HAVE_TOKYOCABINET],[0],[Define to 1 if TOKYOCABINET is found])])

    AS_IF([test "x$ax_cv_have_TOKYOCABINET" = xno],[ac_enable_libtokyocabinet="no"])
    ])

AC_DEFUN([PANDORA_HAVE_LIBTOKYOCABINET],
    [AC_ARG_ENABLE([libtokyocabinet],
      [AS_HELP_STRING([--disable-libtokyocabinet],
        [Build with libtokyocabinet support @<:@default=on@:>@])],
      [ac_enable_libtokyocabinet="$enableval"],
      [ac_enable_libtokyocabinet="yes"])

    _PANDORA_SEARCH_LIBTOKYOCABINET
    AM_CONDITIONAL([HAVE_TOKYOCABINET],[test "x${ac_enable_libtokyocabinet}" = "xyes"])
    ])
