dnl  Copyright (C) 2009 Sun Microsystems, Inc.
dnl This file is free software; Sun Microsystems, Inc.
dnl gives unlimited permission to copy and/or distribute it,
dnl with or without modifications, as long as this notice is preserved.

dnl ---------------------------------------------------------------------------
dnl Macro: PANDORA_ENABLE_DTRACE
dnl ---------------------------------------------------------------------------
AC_DEFUN([PANDORA_ENABLE_DTRACE],[
  AC_ARG_ENABLE([dtrace],
    [AS_HELP_STRING([--enable-dtrace],
            [Build with support for the DTRACE. @<:@default=no@:>@])],
    [ac_cv_enable_dtrace="$enableval"],
    [ac_cv_enable_dtrace="no"])

  AS_IF([test "$ac_cv_enable_dtrace" = "yes"],[
    AC_CHECK_PROGS([DTRACE], [dtrace])
    AC_CHECK_HEADERS(sys/sdt.h)

    AS_IF([test "x$ac_cv_prog_DTRACE" = "xdtrace" -a "x${ac_cv_header_sys_sdt_h}" = "xyes"],[

      AC_CACHE_CHECK([if dtrace works],[ac_cv_dtrace_works],[
        cat >conftest.d <<_ACEOF
provider Example {
  probe increment(int);
};
_ACEOF
        $DTRACE -h -o conftest.h -s conftest.d 2>/dev/zero
        AS_IF([test $? -eq 0],[ac_cv_dtrace_works=yes],
          [ac_cv_dtrace_works=no])
        rm -f conftest.h conftest.d
      ])
      AS_IF([test "x$ac_cv_dtrace_works" = "xyes"],[
        AC_DEFINE([HAVE_DTRACE], [1], [Enables DTRACE Support])
      ])
      AC_CACHE_CHECK([if dtrace should instrument object files],
        [ac_cv_dtrace_needs_objects],[
          dnl DTrace on MacOSX does not use -G option
          cat >conftest.d <<_ACEOF
provider Example {
  probe increment(int);
};
_ACEOF
          $DTRACE -G -o conftest.d.o -s conftest.d 2>/dev/zero
          AS_IF([test $? -eq 0],[ac_cv_dtrace_needs_objects=yes],
            [ac_cv_dtrace_needs_objects=no])
          rm -f conftest.d.o conftest.d
      ])
      AC_SUBST(DTRACEFLAGS) dnl TODO: test for -G on OSX
      ac_cv_have_dtrace=yes
    ])])

AM_CONDITIONAL([HAVE_DTRACE], [test "x$ac_cv_dtrace_works" = "xyes"])
AM_CONDITIONAL([DTRACE_NEEDS_OBJECTS],
               [test "x$ac_cv_dtrace_needs_objects" = "xyes"])

])
dnl ---------------------------------------------------------------------------
dnl End Macro: PANDORA_ENABLE_DTRACE
dnl ---------------------------------------------------------------------------
