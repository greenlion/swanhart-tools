# ===========================================================================
#      https://github.com/BrianAker/ddm4/
# ===========================================================================
#
# SYNOPSIS
#
#   AX_ASSERT()
#
# DESCRIPTION
#
#   --enable-assert
#
# LICENSE
#
#  Copyright (C) 2012 Brian Aker
#  All rights reserved.
#  
#  Redistribution and use in source and binary forms, with or without
#  modification, are permitted provided that the following conditions are
#  met:
#  
#      * Redistributions of source code must retain the above copyright
#  notice, this list of conditions and the following disclaimer.
#  
#      * Redistributions in binary form must reproduce the above
#  copyright notice, this list of conditions and the following disclaimer
#  in the documentation and/or other materials provided with the
#  distribution.
#  
#      * The names of its contributors may not be used to endorse or
#  promote products derived from this software without specific prior
#  written permission.
#  
#  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
#  "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
#  LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
#  A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
#  OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
#  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
#  LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
#  DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
#  THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
#  (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
#  OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

#serial 6

AC_DEFUN([AX_ASSERT],
    [AC_PREREQ([2.63])dnl
    AC_REQUIRE([AX_DEBUG])
    AC_REQUIRE([AX_VCS_CHECKOUT])
    AC_ARG_ENABLE([assert],
      [AS_HELP_STRING([--enable-assert],
        [Enable assert, this will be overridden by --enable-debug (yes|no) @<:@default=no@:>@])],
      [ax_enable_assert=yes],
      [ax_enable_assert=no])

    AS_IF([ test "$ax_enable_assert" = "yes" -o "$ax_enable_debug" = "yes" -o "$ac_cv_vcs_checkout" = "yes" ],
      [ax_enable_assert="yes"],
      [ax_enable_assert="no"
      AC_DEFINE(NDEBUG,[1],[Define to 1 to disable assert'ing code.])])

    AC_MSG_CHECKING([for assert])
    AC_MSG_RESULT([$ax_enable_assert])
    ])

