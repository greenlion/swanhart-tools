# vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
# ===========================================================================
# https://github.com/BrianAker/ddm4
# ===========================================================================
#
# SYNOPSIS
#
#   AX_UUID([ACTION-IF-FOUND[, ACTION-IF-NOT-FOUND]])
#   AX_UUID_GENERATE_TIME([ACTION-IF-FOUND[, ACTION-IF-NOT-FOUND]])
#   AX_UUID_GENERATE_TIME_SAFE([ACTION-IF-FOUND[, ACTION-IF-NOT-FOUND]])
#
# DESCRIPTION
#
#   Check for uuid, uuid_generate_time, and uuid_generate_time_safe support.
#
# LICENSE
#
#   Copyright (c) 2012-2013 Brian Aker <brian@tangent.org>
#
#   Copying and distribution of this file, with or without modification, are
#   permitted in any medium without royalty provided the copyright notice
#   and this notice are preserved. This file is offered as-is, without any
#   warranty.

#serial 8

AC_DEFUN([AX_UUID],
    [AC_PREREQ([2.63])dnl
    AC_CHECK_HEADER([uuid/uuid.h],[

      AC_CACHE_CHECK([check to see if -luuid is needed], [ax_cv_libuuid_is_required],
        [AC_LANG_PUSH([C])
        AC_RUN_IFELSE(
          [AC_LANG_PROGRAM([#include <uuid/uuid.h>], [
            uuid_t out;
            uuid_generate(out);
            ])],
          [ax_cv_libuuid_is_required=no],
          [ax_cv_libuuid_is_required=yes],
          [AC_MSG_WARN([test program execution failed])])
        AC_LANG_POP
        ])

      AS_IF([test "$ax_cv_libuuid_is_required" = yes],
        [AC_CACHE_CHECK([check to see if -luuid is needed], [ax_cv_libuuid_works],
          [AX_SAVE_FLAGS
          LIBS="-luuid $LIBS"
          AC_LANG_PUSH([C])
          AC_RUN_IFELSE(
            [AC_LANG_PROGRAM([#include <uuid/uuid.h>], [
              uuid_t out;
              uuid_generate(out);
              ])],
            [ax_cv_libuuid_works=yes],
            [ax_cv_libuuid_works=no],
            [AC_MSG_WARN([test program execution failed])])
          AC_LANG_POP
          AX_RESTORE_FLAGS])
        ])

      AS_IF([test "$ax_cv_libuuid_is_required" = yes],
          [AS_IF([test "$ax_cv_libuuid_works" = yes],[ax_libuuid=yes])],
          [ax_libuuid=yes])
      ],[ax_libuuid=no])

      AS_IF([test "x$ax_libuuid" = xyes],
          [AC_DEFINE([HAVE_UUID_UUID_H],[1],[Have uuid/uuid.h])
          AS_IF([test "x$ax_cv_libuuid_is_required" = xyes],[ LIBUUID_LIB='-luuid' ])],
          [AC_DEFINE([HAVE_UUID_UUID_H],[0],[Have uuid/uuid.h])
          ])

  AC_SUBST([LIBUUID_LIB])
  AM_CONDITIONAL([HAVE_LIBUUID],[test "x$ax_libuuid" = xyes])

# Finally, execute ACTION-IF-FOUND/ACTION-IF-NOT-FOUND:
  AS_IF([test "x$ax_libuuid" = xyes],
        [$1],
        [$2])
  ])

  AC_DEFUN([AX_UUID_GENERATE_TIME],
           [AC_PREREQ([2.63])dnl
           AX_UUID([$1],[$2])
           AC_CACHE_CHECK([for uuid_generate_time],
                          [ax_cv_uuid_generate_time],
                          [AX_SAVE_FLAGS
                          LIBS="$LIBUUID_LIB $LIBS"
                          AC_LANG_PUSH([C])
                          AC_RUN_IFELSE([
                                        AC_LANG_PROGRAM([#include <uuid/uuid.h>],[
                                                        uuid_t out;
                                                        uuid_generate_time(out);
                                                        ])],
                                        [ax_cv_uuid_generate_time=yes],
                                        [ax_cv_uuid_generate_time=no],
                                        [AC_MSG_WARN([test program execution failed])])
                          AC_LANG_POP
                          AX_RESTORE_FLAGS
                          ])

           AS_IF([test "$ax_cv_uuid_generate_time" = yes],
                 [AC_DEFINE([HAVE_UUID_GENERATE_TIME],[1],[Define if uuid_generate_time is present in uuid/uuid.h.])],
                 [AC_DEFINE([HAVE_UUID_GENERATE_TIME],[0],[Define if uuid_generate_time is present in uuid/uuid.h.])])
  AS_IF([test "x$ax_cv_uuid_generate_time" = xyes],
        [$1],
        [$2])
  ])

  AC_DEFUN([AX_UUID_GENERATE_TIME_SAFE],
           [AC_PREREQ([2.63])dnl
           AX_UUID([$1],[$2])
           AC_CACHE_CHECK([for uuid_generate_time_safe],
                          [ax_cv_uuid_generate_time_safe],
                          [AX_SAVE_FLAGS
                          LIBS="$LIBUUID_LIB $LIBS"
                          AC_LANG_PUSH([C])
                          AC_RUN_IFELSE([
                                        AC_LANG_PROGRAM([#include <uuid/uuid.h>],[
                                                        uuid_t out;
                                                        uuid_generate_time_safe(out);
                                                        ])],
                                        [ax_cv_uuid_generate_time_safe=yes],
                                        [ax_cv_uuid_generate_time_safe=no],
                                        [AC_MSG_WARN([test program execution failed])])
                          AC_LANG_POP
                          AX_RESTORE_FLAGS
                          ])

           AS_IF([test "$ax_cv_uuid_generate_time_safe" = yes],
                 [AC_DEFINE([HAVE_UUID_GENERATE_TIME_SAFE],[1],[Define if uuid_generate_time_safe is present in uuid/uuid.h.])],
                 [AC_DEFINE([HAVE_UUID_GENERATE_TIME_SAFE],[0],[Define if uuid_generate_time_safe is present in uuid/uuid.h.])])

  AS_IF([test "x$ax_cv_uuid_generate_time_safe" = xyes],
        [$1],
        [$2])
  ])
