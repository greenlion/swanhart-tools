# https://github.com/BrianAker/ddm4
# ===========================================================================
#
# SYNOPSIS
#
#   AX_ENDIAN()
#
# DESCRIPTION
#
#   Generate to defines describing endian.
#
# LICENSE
#
#   Copyright (c) 2012 Brian Aker <brian@tangent.org>
#
#   Copying and distribution of this file, with or without modification, are
#   permitted in any medium without royalty provided the copyright notice
#   and this notice are preserved. This file is offered as-is, without any
#   warranty.

#serial 1
 
AC_DEFUN([AX_ENDIAN],[
    AC_C_BIGENDIAN([
      AC_DEFINE([WORDS_BIGENDIAN],[1],[machine is big-endian])
      AC_DEFINE([ENDIAN_BIG],[1],[machine is big-endian])
      AC_DEFINE([ENDIAN_LITTLE],[0],[machine is little-endian])],[ 
      ],[ 
      AC_DEFINE([WORDS_BIGENDIAN],[0],[machine is big-endian])
      AC_DEFINE([ENDIAN_BIG],[0],[machine is big-endian])
      AC_DEFINE([ENDIAN_LITTLE],[1],[machine is little-endian])
      ],[ 
      AC_MSG_ERROR([unable to determine endian])
      ]) 
    ])
