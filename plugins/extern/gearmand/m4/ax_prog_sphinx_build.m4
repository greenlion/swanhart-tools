# ===========================================================================
# https://github.com/BrianAker/ddm4
# ===========================================================================
#
# SYNOPSIS
#
#   AX_PROG_SPHINX_BUILD([ACTION-IF-FOUND], [ACTION-IF-NOT_FOUND])
#
# DESCRIPTION
#
#   Look for sphinx-build and make sure it is a recent version of it.
#
# LICENSE
#
#   Copyright (c) 2012-2013 Brian Aker <brian@tangent.org>
#
#   Copying and distribution of this file, with or without modification, are
#   permitted in any medium without royalty provided the copyright notice
#   and this notice are preserved. This file is offered as-is, without any
#   warranty.

#serial 5

AC_DEFUN([AX_PROG_SPHINX_BUILD],
         [AX_WITH_PROG([SPHINXBUILD],[sphinx-build],[:])
         AS_IF([test x"SPHINXBUILD" = x":"],
               [SPHINXBUILD=],
               [AS_IF([test -x "$SPHINXBUILD"],
                      [AC_MSG_CHECKING([Checking to see if $SPHINXBUILD is recent])
                      junk=`$SPHINXBUILD --version &> version_file`
                      AS_IF([test $? -eq 0],
                            [ax_sphinx_build_version=`head -1 version_file`],
                            [junk=`$SPHINXBUILD &> version_file`
                            ax_sphinx_build_version=`head -1 version_file`
                            rm version_file
                            AC_MSG_RESULT([$SPHINXBUILD is version "$ax_sphinx_build_version"])
                            $SPHINXBUILD -Q -C -b man -d conftest.d . . >/dev/null 2>&1
                            AS_IF([test $? -eq 0], ,[SPHINXBUILD=])
                            rm -rf conftest.d ])
                      ])
                      rm -f version_file
               ])

         AS_IF([test -n "${SPHINXBUILD}"],
               [AC_SUBST([SPHINXBUILD])
               ifelse([$1], , :, [$1])],
               [ifelse([$2], , :, [$2])])
         ])
