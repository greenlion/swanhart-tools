dnl  Copyright (C) 2009 Sun Microsystems
dnl This file is free software; Sun Microsystems
dnl gives unlimited permission to copy and/or distribute it,
dnl with or without modifications, as long as this notice is preserved.

dnl If the user is on a Mac and didn't ask for a specific compiler
dnl You're gonna get 4.2.
AC_DEFUN([PANDORA_MAC_GCC42],
  [AS_IF([test "$GCC" = "yes"],[
    AS_IF([test "$host_vendor" = "apple" -a "x${ac_cv_env_CC_set}" = "x"],[
      host_os_version=`echo ${host_os} | perl -ple 's/^\D+//g;s,\..*,,'`
      AS_IF([test "$host_os_version" -lt 10],[
        AS_IF([test -f /usr/bin/gcc-4.2],
        [
          CPP="/usr/bin/gcc-4.2 -E"
          CC=/usr/bin/gcc-4.2
          CXX=/usr/bin/g++-4.2
        ])
      ])
    ])
  ])
])

dnl 
AC_DEFUN([PANDORA_ENSURE_GCC_VERSION],[
  AC_REQUIRE([PANDORA_MAC_GCC42])
  AC_CACHE_CHECK([if GCC is recent enough], [ac_cv_gcc_recent],
    [AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#if !defined(__GNUC__) || (__GNUC__ < 4) || ((__GNUC__ >= 4) && (__GNUC_MINOR__ < 1))
# error GCC is Too Old!
#endif
      ]])],
      [ac_cv_gcc_recent=yes],
      [ac_cv_gcc_recent=no])])
  AS_IF([test "$ac_cv_gcc_recent" = "no" -a "$host_vendor" = "apple"],
    AC_MSG_ERROR([Your version of GCC is too old. At least version 4.2 is required on OSX. You may need to install a version of XCode >= 3.1.2]))
  AS_IF([test "$ac_cv_gcc_recent" = "no"],
    AC_MSG_ERROR([Your version of GCC is too old. At least version 4.1 is required]))
])
