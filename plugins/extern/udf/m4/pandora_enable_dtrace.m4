dnl  Copyright (C) 2009 Sun Microsystems
dnl This file is free software; Sun Microsystems
dnl gives unlimited permission to copy and/or distribute it,
dnl with or without modifications, as long as this notice is preserved.

dnl ---------------------------------------------------------------------------
dnl Macro: PANDORA_ENABLE_DTRACE
dnl ---------------------------------------------------------------------------
AC_DEFUN([PANDORA_ENABLE_DTRACE],[
  AC_ARG_ENABLE([dtrace],
    [AS_HELP_STRING([--enable-dtrace],
            [Build with support for the DTRACE. @<:@default=off@:>@])],
    [ac_cv_enable_dtrace="yes"],
    [ac_cv_enable_dtrace="no"])

  AS_IF([test "$ac_cv_enable_dtrace" = "yes"],[
    AC_CHECK_PROGS([DTRACE], [dtrace])
    AS_IF([test "x$ac_cv_prog_DTRACE" = "xdtrace"],[
      AC_DEFINE([HAVE_DTRACE], [1], [Enables DTRACE Support])
      AC_MSG_CHECKING([if dtrace should instrument object files])
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
      AC_MSG_RESULT($ac_cv_dtrace_needs_objects)
      AC_SUBST(DTRACEFLAGS) dnl TODO: test for -G on OSX
      ac_cv_have_dtrace=yes
    ])])

AM_CONDITIONAL([HAVE_DTRACE], [test "x$ac_cv_have_dtrace" = "xyes"])
AM_CONDITIONAL([DTRACE_NEEDS_OBJECTS],
               [test "x$ac_cv_dtrace_needs_objects" = "xyes"])

])
dnl ---------------------------------------------------------------------------
dnl End Macro: PANDORA_ENABLE_DTRACE
dnl ---------------------------------------------------------------------------
