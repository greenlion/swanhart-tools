# Gearman User Defined Functions for MySQL
# Copyright (C) 2008 Eric Day
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

AC_INIT(gearman-mysql-udf, [0.6], [https://launchpad.net/gearman-mysql-udf])
AC_CONFIG_SRCDIR(libgearman_mysql_udf/client.c)
AC_CONFIG_AUX_DIR(config)
AC_CONFIG_MACRO_DIR([m4])

PANDORA_CANONICAL_TARGET(warnings-always-on, skip-visibility)
PANDORA_REQUIRE_LIBGEARMAN

MYSQL_CONFIG_TEST
AC_SUBST(MYSQL_CONFIG)
AC_SUBST(MYSQL_INC)
AC_SUBST(MYSQL_LIB)

AC_OUTPUT(Makefile)
