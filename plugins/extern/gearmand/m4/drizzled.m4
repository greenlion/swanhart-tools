AX_WITH_PROG(DRIZZLED_BINARY,drizzled)
AS_IF([test -f "$ac_cv_path_DRIZZLED_BINARY"],
      [
        AC_DEFINE([HAVE_DRIZZLED_BINARY], [1], [If Memcached binary is available])
        AC_DEFINE_UNQUOTED([DRIZZLED_BINARY], "$ac_cv_path_DRIZZLED_BINARY", [Name of the drizzled binary used in make test])
       ],
       [
        AC_DEFINE([HAVE_DRIZZLED_BINARY], [0], [If Memcached binary is available])
        AC_DEFINE([DRIZZLED_BINARY], [0], [Name of the drizzled binary used in make test])
      ])
