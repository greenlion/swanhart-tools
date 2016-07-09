dnl  Copyright (C) 2011 Brian Aker (brian@tangent.org)

AC_DEFUN([AX_HAVE_LIBPQ],[
  AC_ARG_ENABLE([libpq],
    [AS_HELP_STRING([--disable-libpq],
    [Build with libpq, ie Postgres, support @<:@default=on@:>@])],
      [ac_cv_libpq="$enableval"],
      [ac_cv_libpq="yes"])

  AC_REQUIRE([AX_LIB_POSTGRESQL])

  AS_IF([test "x$ac_cv_libpq" = "xyes" -a "x$found_postgresql" = "xyes"],
    [
      AC_DEFINE([HAVE_LIBPQ], [ 1 ], [Enable libpq support])
    ],
    [
      AC_DEFINE([HAVE_LIBPQ], [ 0 ], [Enable libpq support])
      # if --enable-libpq, but no Postgres, force --disable-libpq
      ac_cv_libpq="no"
    ])

  AM_CONDITIONAL(HAVE_LIBPQ, [test "x$ac_cv_libpq" = "xyes"])
])
