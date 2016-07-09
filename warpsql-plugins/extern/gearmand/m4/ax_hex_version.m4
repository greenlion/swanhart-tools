# ===========================================================================
# https://github.com/BrianAker/ddm4
# ===========================================================================
#
# SYNOPSIS
#
#   AX_HEX_VERSION(VARIABLE_NAME, VERSION)
#
# DESCRIPTION
#
#   Generate version information in HEX and STRING format.
#
# LICENSE
#
#   Copyright (c) 2012 Brian Aker <brian@tangent.org>
#
#   Copying and distribution of this file, with or without modification, are
#   permitted in any medium without royalty provided the copyright notice
#   and this notice are preserved. This file is offered as-is, without any
#   warranty.

#serial 3
 
AC_DEFUN([AX_HEX_VERSION],
    [AC_PREREQ([2.63])dnl

    string_version_$1=`echo $2 | sed 's|[\-a-z0-9]*$||' | awk -F. '{printf "%d.%d.%d", $[]1, $[]2, $[]3}'`
    hex_version_$1=`echo $2 | sed 's|[\-a-z0-9]*$||' | awk -F. '{printf "0x%0.2d%0.3d%0.3d", $[]1, $[]2, $[]3}'`

    AC_SUBST([$1_VERSION_STRING],["$string_version_$1"])
    AC_SUBST([$1_VERSION_HEX],["$hex_version_$1"])
    ])

