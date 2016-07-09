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

ACLOCAL_AMFLAGS = -I m4

lib_LTLIBRARIES = libgearman_mysql_udf.la

libgearman_mysql_udf_la_SOURCES= libgearman_mysql_udf/client.c
noinst_HEADERS= libgearman_mysql_udf/common.h
libgearman_mysql_udf_la_CFLAGS= $(AM_CFLAGS) $(MYSQL_INC)
libgearman_mysql_udf_la_LDFLAGS= -module
libgearman_mysql_udf_la_LIBADD= $(LTLIBGEARMAN)
