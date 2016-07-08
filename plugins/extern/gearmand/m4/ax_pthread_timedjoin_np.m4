# ===========================================================================
# http://
# ===========================================================================
#
# SYNOPSIS
#
#   AX_PTHREAD_TIMEDJOIN_NP()
#
# DESCRIPTION
#
#   Check for pthread_timedjoin_np support.
#
# LICENSE
#
#   Copyright (c) 2012 Brian Aker <brian@tangent.org>
#
#   Copying and distribution of this file, with or without modification, are
#   permitted in any medium without royalty provided the copyright notice
#   and this notice are preserved. This file is offered as-is, without any
#   warranty.

#serial 6

AC_DEFUN([AX_PTHREAD_TIMEDJOIN_NP],
    [AC_PREREQ([2.63])dnl
    AC_REQUIRE([AX_PTHREAD])
    AC_CACHE_CHECK([check for pthread_timedjoin_np], [ax_cv_pthread_timedjoin_np],
      [AX_SAVE_FLAGS
      CFLAGS="$PTHREAD_CFLAGS"
      LIBS="$PTHREAD_LIBS"
      AC_LANG_PUSH([C])
      AC_LINK_IFELSE(
        [AC_LANG_PROGRAM(
          [
#include <pthread.h>
#include <stdlib.h>
          ],[
          pthread_t thread;
          pthread_timedjoin_np(thread, NULL, NULL);
          ])],
        [ax_cv_pthread_timedjoin_np=yes],[])

      AC_LANG_POP
      AX_RESTORE_FLAGS
      ])

    AS_IF([test "$ax_cv_pthread_timedjoin_np" = yes],
        [AC_DEFINE(HAVE_PTHREAD_TIMEDJOIN_NP,[1],[Define if pthread_timedjoin_np is present in pthread.h.])],
        [AC_DEFINE(HAVE_PTHREAD_TIMEDJOIN_NP,[0],[Define if pthread_timedjoin_np is present in pthread.h.])])
    ])
