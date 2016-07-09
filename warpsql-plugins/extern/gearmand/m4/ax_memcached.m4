# vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
# ===========================================================================
#      https://github.com/BrianAker/ddm4/
# ===========================================================================
#
# SYNOPSIS
#
#   AX_PROG_MEMCACHED
#
# DESCRIPTION
#
#   Check for memcached and list version
#
# LICENSE
#
#  Copyright (C) 2013 Brian Aker
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

#serial 4

AC_DEFUN([AX_PROG_MEMCACHED],
         [AX_WITH_PROG([MEMCACHED_BINARY],[memcached],[unknown])
         ax_memcached_success=
         AS_IF([test x"$MEMCACHED_BINARY" != xunknown],
               [AS_IF([test -x "$MEMCACHED_BINARY"],
                      [ax_memcached_version=`$MEMCACHED_BINARY -h | sed 1q | awk '{print \$ 2 }' | sed 's|[\-a-z0-9]*$||' | awk -F. '{printf "%d.%d.%d", $[]1, $[]2, $[]3}'`
                      AS_IF([test -n "$ax_memcached_version"],
                            [ax_memcached_success='ok'
                            AC_MSG_RESULT([memcached version "$ax_memcached_version"])
                            AC_DEFINE_UNQUOTED([MEMCACHED_VERSION],"$ax_memcached_version",[Memcached binary version])])
                      ])
               ])

         AS_IF([test -n "$ax_memcached_success"], 
               [AC_DEFINE([HAVE_MEMCACHED_BINARY], [1], [If Memcached binary is available])
               AC_DEFINE_UNQUOTED([MEMCACHED_BINARY],"$MEMCACHED_BINARY",[Name of the memcached binary used in make test])
               ],
               [AC_DEFINE([HAVE_MEMCACHED_BINARY], [0], [If Memcached binary is available])
               MEMCACHED_BINARY=
               ])
         ])
