# vim:ft=automake
#
# Gearman server and library
# Copyright (C) 2013 Data Differential, http://datadifferential.com/
# All rights reserved.
#
# Use and distribution licensed under the BSD license.  See
# the COPYING file in the parent directory for full text.

t_protocol_SOURCES=
t_protocol_LDADD=

t_protocol_SOURCES+= tests/protocol.cc
t_protocol_LDADD+= ${LIBGEARMAN_1_0_CLIENT_LDADD}
t_protocol_LDADD+= libgearman/libgearmancore.la
check_PROGRAMS+= t/protocol
noinst_PROGRAMS+= t/protocol

test-protocol: t/protocol gearmand/gearmand
	@t/protocol

gdb-protocol: t/protocol gearmand/gearmand
	@$(GDB_COMMAND) t/protocol

valgrind-protocol: t/protocol gearmand/gearmand
	@$(VALGRIND_COMMAND) t/protocol

