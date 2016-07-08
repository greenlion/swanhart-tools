/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2012 Data Differential, http://datadifferential.com/
 *  Copyright (C) 2008 Brian Aker, Eric Day
 *  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are
 *  met:
 *
 *      * Redistributions of source code must retain the above copyright
 *  notice, this list of conditions and the following disclaimer.
 *
 *      * Redistributions in binary form must reproduce the above
 *  copyright notice, this list of conditions and the following disclaimer
 *  in the documentation and/or other materials provided with the
 *  distribution.
 *
 *      * The names of its contributors may not be used to endorse or
 *  promote products derived from this software without specific prior
 *  written permission.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 *  "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 *  LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 *  A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 *  OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 *  LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 *  DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 *  THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 *  (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 *  OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

/**
 * @file
 * @brief Connection Declarations
 */

#pragma once

#include <sys/types.h>
#include <sys/socket.h>
#include <netdb.h>

#include <libgearman-server/struct/gearmand_con.h>

#ifdef __cplusplus
extern "C" {
#endif

/**
 * @addtogroup gearmand_con Connection Declarations
 * @ingroup gearmand
 *
 * Connection handling for gearmand.
 *
 * @{
 */

/**
 * Create a new gearmand connection.
 * @param gearmand Server instance structure previously initialized with
 *        gearmand_create.
 * @param fd File descriptor of new connection.
 * @param host Host of peer connection.
 * @param port Port of peer connection.
 * @param add_fn Optional callback to use when adding the connection to an
          I/O thread.
 * @return Pointer to an allocated gearmand structure.
 */
GEARMAN_API
gearmand_error_t gearmand_con_create(gearmand_st *gearmand, int&,
                                     const char *host, const char*,
                                     struct gearmand_port_st*);

GEARMAN_API
gearmand_error_t gearman_server_job_cancel(gearman_server_st& server,
                                           const char *job_handle,
                                           const size_t job_handle_length);

/**
 * Free resources used by a connection.
 * @param dcon Connection previously initialized with gearmand_con_create.
 */
GEARMAN_API
void gearmand_con_free(gearmand_con_st *dcon);

/**
 * Check connection queue for a thread.
 */
GEARMAN_API
void gearmand_con_check_queue(gearmand_thread_st *thread);

void _con_ready(int fd, short events, void *arg);

/** @} */

#ifdef __cplusplus
}
#endif
