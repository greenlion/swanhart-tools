# serial 1
AC_DEFUN([_WITH_LIBDRIZZLE],
    [AC_ARG_ENABLE([libdrizzle],
      [AS_HELP_STRING([--disable-libdrizzle],
        [Build with libdrizzle support @<:@default=on@:>@])],
      [ac_enable_libdrizzle="$enableval"],
      [ac_enable_libdrizzle="yes"])

    AS_IF([test "x$ac_enable_libdrizzle" = "xyes"],
      [AX_CHECK_LIBRARY([LIBDRIZZLE],[libdrizzle-5.1/drizzle_client.h],[drizzle],,
        [AC_DEFINE([HAVE_LIBDRIZZLE],[0],[Have libdrizzle])])],
      [AC_DEFINE([HAVE_LIBDRIZZLE],[0],[Have libdrizzle])])
    ])

AC_DEFUN([WITH_LIBDRIZZLE], [ AC_REQUIRE([_WITH_LIBDRIZZLE]) ])
