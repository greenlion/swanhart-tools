# ===========================================================================
#     http://www.gnu.org/software/autoconf-archive/ax_check_cyassl.html
# ===========================================================================
#
# SYNOPSIS
#
#   AX_CHECK_CYASSL([action-if-found],[action-if-not-found])
#
# DESCRIPTION
#
#   Look for OpenSSL in a number of default spots, or in a user-selected
#   spot (via --with-cyassl).  Sets
#
#     CYASSL_CPPFLAGS
#     CYASSL_LIB
#     CYASSL_LDFLAGS
#
#   and calls ACTION-IF-FOUND or ACTION-IF-NOT-FOUND appropriately
#
# LICENSE
#
#   Copyright (c) 2013 Brian Aker. <brian@tangent.com>
#
#   Copying and distribution of this file, with or without modification, are
#   permitted in any medium without royalty provided the copyright notice
#   and this notice are preserved. This file is offered as-is, without any
#   warranty.

#serial 1

AC_DEFUN([AX_CHECK_CYASSL],
         [AC_PREREQ([2.63])dnl
         m4_define([_CYASSL_ENABLE_DEFAULT], [m4_if($1, yes, yes, yes)])dnl
         AC_ARG_ENABLE([cyassl],
                       [AS_HELP_STRING([--enable-cyassl],
                                       [Enable ssl support for Gearman @<:@default=]_CYASSL_ENABLE_DEFAULT[@:>@])],
                       [AS_CASE([$enableval],
                                [yes],[enable_cyassl=yes],
                                [no],[enable_cyassl=no],
                                [enable_cyassl=no])
                       ],
                       [enable_cyassl=]_CYASSL_ENABLE_DEFAULT)

         AS_IF([test "x${enable_cyassl}" = "xyes"],
               [AX_CHECK_LIBRARY([CYASSL],[cyassl/ssl.h],[cyassl],[],
                                 [enable_cyassl=no])])

         AS_IF([test "x${enable_cyassl}" = "xyes"],
               [AC_MSG_RESULT([yes])
               $1],
               [AC_MSG_RESULT([no])
               $2
               ])
         ])
