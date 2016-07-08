# ===========================================================================
#     http://tangent.org/
# ===========================================================================
#
# SYNOPSIS
#
#   AX_CXX_CINTTYPES()
#
# DESCRIPTION
#
#  Example:
#
# LICENSE
#
#   Copyright (c) 2012 Brian Aker` <brian@tangent.org>
#
#   This program is free software: you can redistribute it and/or modify it
#   under the terms of the GNU General Public License as published by the
#   Free Software Foundation, either version 3 of the License, or (at your
#   option) any later version.
#
#   This program is distributed in the hope that it will be useful, but
#   WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
#   Public License for more details.
#
#   You should have received a copy of the GNU General Public License along
#   with this program. If not, see <http://www.gnu.org/licenses/>.
#
#   As a special exception, the respective Autoconf Macro's copyright owner
#   gives unlimited permission to copy, distribute and modify the configure
#   scripts that are the output of Autoconf when processing the Macro. You
#   need not follow the terms of the GNU General Public License when using
#   or distributing such scripts, even though portions of the text of the
#   Macro appear in them. The GNU General Public License (GPL) does govern
#   all other use of the material that constitutes the Autoconf Macro.
#
#   This special exception to the GPL applies to versions of the Autoconf
#   Macro released by the Autoconf Archive. When you make and distribute a
#   modified version of the Autoconf Macro, you may extend this special
#   exception to the GPL to apply to your modified version as well.

#serial 3

AC_DEFUN([AX_CXX_CINTTYPES], [
    AC_REQUIRE([AC_PROG_CXX])
    AC_REQUIRE([AC_PROG_CXXCPP])
    AC_REQUIRE([AX_CXX_CSTDINT])

    AC_CACHE_CHECK([for location of cinttypes], [ac_cv_cxx_cinttypes], [
      AX_SAVE_FLAGS
      CXXFLAGS="${CXX_STANDARD} ${CXXFLAGS}"
      AC_LANG_PUSH([C++])

      AC_COMPILE_IFELSE([
        AC_LANG_PROGRAM([#include <cinttypes>], [
          uint32_t foo= UINT32_C(1); 
          ])],
        [ac_cxx_cinttypes_cinttypes="<cinttypes>"])

# Look for tr1/cinttypes
      AS_IF([test -z "$ac_cxx_cinttypes_cinttypes"],[
            AC_COMPILE_IFELSE([
                              AC_LANG_PROGRAM([#include <tr1/cinttypes>], [
                                              uint32_t foo= UINT32_C(1);
                                              ])],
                              [ac_cxx_cinttypes_tr1_cinttypes="<tr1/cinttypes>"])

# Look for boost/cinttypes.hpp
            AS_IF([test -z "$ac_cxx_cinttypes_tr1_cinttypes"],[
                  AC_COMPILE_IFELSE([
                                    AC_LANG_PROGRAM([#include <boost/cinttypes.hpp>], [
                                                    uint32_t foo= UINT32_C(1); 
                                                    ])],
                                    [ac_cxx_cinttypes_boost_cinttypes_hpp="<boost/cinttypes.hpp>"])
                  ])
            ])

      AC_LANG_POP
      AX_RESTORE_FLAGS

      AS_IF([test -n "$ac_cxx_cinttypes_cinttypes"], [ac_cv_cxx_cinttypes=$ac_cxx_cinttypes_cinttypes],
          [test -n "$ac_cxx_cinttypes_tr1_cinttypes"], [ac_cv_cxx_cinttypes=$ac_cxx_cinttypes_tr1_cinttypes],
          [test -n "$ac_cxx_cinttypes_boost_cinttypes_hpp"], [ac_cv_cxx_cinttypes=$ac_cxx_cinttypes_boost_cinttypes_hpp])
  ])

  AS_IF([ test -n "$ac_cv_cxx_cinttypes"], [
      AC_MSG_RESULT([$ac_cv_cxx_cinttypes])
      ],[
      ac_cv_cxx_cinttypes="<inttypes.h>"
      AC_MSG_WARN([Could not find a cinttypes header.])
      AC_MSG_RESULT([$ac_cv_cxx_cinttypes])
      ])

  AC_DEFINE([__STDC_LIMIT_MACROS],[1],[Use STDC Limit Macros in C++])
  AC_DEFINE_UNQUOTED([CINTTYPES_H],[$ac_cv_cxx_cinttypes],[the location of <cinttypes>])
  ])
