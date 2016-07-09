dnl Copyright (C) 2012 Data Differential, LLC.
dnl This file is free software; Data Differential, LLC.
dnl gives unlimited permission to copy and/or distribute it,
dnl with or without modifications, as long as this notice is preserved.

AC_SUBST([LIBTEST_VERSION],[1.0])
AC_CONFIG_FILES([libtest/version.h])

m4_include([libtest/m4/mysql.m4])

YATL_MYSQL

AC_CONFIG_FILES([libtest/yatlcon.h])
AC_CHECK_HEADERS([valgrind/valgrind.h])
