dnl  Copyright (C) 2012 Data Differential LLC
dnl  Copyright (C) 2011 Keyur Govande
dnl This file is free software; Keyur Govande
dnl gives unlimited permission to copy and/or distribute it,
dnl with or without modifications, as long as this notice is preserved.
dnl ---------------------------------------------------------------------------
dnl Macro: FCNTL_FLAGS
dnl ---------------------------------------------------------------------------

AC_DEFUN([FCNTL_FLAGS],
[
  AC_CACHE_CHECK([for O_CLOEXEC], [ac_cv_o_cloexec], [
    AC_LANG_PUSH([C])
    save_CFLAGS="$CFLAGS"
    CFLAGS="$CFLAGS -I${srcdir}"

    AC_COMPILE_IFELSE([AC_LANG_PROGRAM([#include <fcntl.h>], [ int flags= O_CLOEXEC])], [ac_cv_o_cloexec="yes"], [ac_cv_o_cloexec="no"])
    AC_LANG_POP
    CFLAGS="$save_CFLAGS"
  ])

  AS_IF([test "x$ac_cv_o_cloexec" = "xyes"],[ AC_DEFINE(HAVE_O_CLOEXEC, 1, [Define to 1 if you have O_CLOEXEC defined])])
])

dnl ---------------------------------------------------------------------------
dnl End Macro: FCNTL_FLAGS
dnl ---------------------------------------------------------------------------
