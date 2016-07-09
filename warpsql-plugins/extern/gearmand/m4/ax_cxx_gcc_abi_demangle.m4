# ===========================================================================
#  http://www.gnu.org/software/autoconf-archive/ax_cxx_gcc_abi_demangle.html
# ===========================================================================
#
# SYNOPSIS
#
#   AX_CXX_GCC_ABI_DEMANGLE
#
# DESCRIPTION
#
#   If the compiler supports GCC C++ ABI name demangling (has header
#   cxxabi.h and abi::__cxa_demangle() function), define
#   HAVE_GCC_ABI_DEMANGLE
#
#   Adapted from AX_CXX_RTTI by Luc Maisonobe
#
# LICENSE
#
#   Copyright (c) 2012 Brian Aker <brian@tangent.org>
#   Copyright (c) 2008 Neil Ferguson <nferguso@eso.org>
#
#   Copying and distribution of this file, with or without modification, are
#   permitted in any medium without royalty provided the copyright notice
#   and this notice are preserved. This file is offered as-is, without any
#   warranty.

#serial 10

AC_DEFUN([AX_CXX_GCC_ABI_DEMANGLE],
    [AC_PREREQ([2.63])dnl
    AC_CACHE_CHECK([whether the compiler supports GCC C++ ABI name demangling],
      [ax_cv_cxx_gcc_abi_demangle],
      [AC_LANG_PUSH([C++])
      AC_RUN_IFELSE([AC_LANG_PROGRAM([[#include <typeinfo>
#include <cxxabi.h>
#include <cstdlib>
#include <string>
          template<typename TYPE>
          class A {};]],
          [[A<int> instance;
#if defined(_WIN32) 
          return EXIT_FAILURE; 
#endif
          int status = 0;
          char* c_name = abi::__cxa_demangle(typeid(instance).name(), 0, 0, &status);

          std::string name(c_name);
          ::free(c_name);

          if (name.compare("A<int>") != 0)
          { 
          return EXIT_FAILURE;
          }]])],
        [ax_cv_cxx_gcc_abi_demangle=yes],
        [ax_cv_cxx_gcc_abi_demangle=no],
        [ax_cv_cxx_gcc_abi_demangle=no])
      AC_LANG_POP])
      AC_MSG_CHECKING([checking for cxx_gcc_abi_demangle])
  AC_MSG_RESULT(["$ax_cv_cxx_gcc_abi_demangle"])
  AS_IF([test "x$ax_cv_cxx_gcc_abi_demangle" = xyes],
      [AC_DEFINE([HAVE_GCC_ABI_DEMANGLE],[1],[define if the compiler supports GCC C++ ABI name demangling])])
  ])
