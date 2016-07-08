#  Copyright (C) 2011 Brian Aker (brian@tangent.org)
#
# serial 2

AC_DEFUN([_SEARCH_HIREDIS],
    [AC_REQUIRE([AX_CHECK_LIBRARY])

    AS_IF([test "x$ac_enable_hiredis" = "xyes"],
      [hiredis_header="hiredis/hiredis.h"],
      [hiredis_header="does_not_exist"])

    AX_CHECK_LIBRARY([HIREDIS],[$hiredis_header],[hiredis],,
                     [AC_DEFINE([HAVE_HIREDIS],[0],[Define to 1 if HIREDIS is found])])

    AS_IF([test "x$ax_cv_have_HIREDIS" = xno],[ac_enable_hiredis="no"])
    ])

  AC_DEFUN([AX_ENABLE_LIBHIREDIS],
      [AC_ARG_ENABLE([hiredis],
        [AS_HELP_STRING([--disable-hiredis],
          [Build with hiredis support @<:@default=on@:>@])],
        [ac_enable_hiredis="$enableval"],
        [ac_enable_hiredis="yes"])

      _SEARCH_HIREDIS
      ])
