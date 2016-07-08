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

#include <libgearman-server/io.h>
#include <libgearman-server/packet.h>

#include <libgearman-server/struct/io.h>

struct gearman_server_job_st;

#ifdef __cplusplus
extern "C" {
#endif

/**
 * @addtogroup gearman_server_con Connection Declarations
 * @ingroup gearman_server
 *
 * This is a low level interface for gearman server connections. This is used
 * internally by the server interface, so you probably want to look there first.
 *
 * @{
 */

/**
 * Add a connection to a server thread. This goes into a list of connections
 * that is used later with server_thread_run, no socket I/O happens here.
 * @param thread Thread structure previously initialized with
 *        gearman_server_thread_init.
 * @param fd File descriptor for a newly accepted connection.
 * @param data Application data pointer.
 * @return Gearman server connection pointer.
 */
GEARMAN_API
gearman_server_con_st *gearman_server_con_add(gearman_server_thread_st *thread, gearmand_con_st *dcon,
                                              gearmand_error_t& ret);

/**
 * Attempt to free a server connection structure.
 */
GEARMAN_API
void gearman_server_con_attempt_free(gearman_server_con_st *con);

/**
 * Actually free a server connection structure.
 */
GEARMAN_API
void gearman_server_con_free(gearman_server_con_st *con);

/**
 * Get gearman connection pointer the server connection uses.
 */
GEARMAN_API
gearmand_io_st *gearman_server_con_con(gearman_server_con_st *con);

/**
 * Get application data pointer.
 */
GEARMAN_API
gearmand_con_st *gearman_server_con_data(gearman_server_con_st *con);

/**
 * Get client id.
 */
GEARMAN_API
const char *gearman_server_con_id(gearman_server_con_st *con);

/**
 * Set client id.
 */
GEARMAN_API
void gearman_server_con_set_id(gearman_server_con_st *con, const char *id,
                               const size_t size);

/**
 * Free server worker struction with name for a server connection.
 */
GEARMAN_API
void gearman_server_con_free_worker(gearman_server_con_st *con,
                                    char *function_name,
                                    size_t function_name_size);

/**
 * Free all server worker structures for a server connection.
 */
GEARMAN_API
void gearman_server_con_free_workers(gearman_server_con_st *con);

/**
 * Add connection to the to_be_freed thread list.
 */
GEARMAN_API
void gearman_server_con_to_be_freed_add(gearman_server_con_st *con);

/**
 * Pick out the next connection to free
 */
GEARMAN_API
gearman_server_con_st *
gearman_server_con_to_be_freed_next(gearman_server_thread_st *thread);

/**
 * Add connection to the io thread list.
 */
GEARMAN_API
void gearman_server_con_io_add(gearman_server_con_st *con);

/**
 * Remove connection from the io thread list.
 */
GEARMAN_API
void gearman_server_con_io_remove(gearman_server_con_st *con);

/**
 * Get next connection from the io thread list.
 */
GEARMAN_API
gearman_server_con_st *
gearman_server_con_io_next(gearman_server_thread_st *thread);

/**
 * Add connection to the proc thread list.
 */
GEARMAN_API
void gearman_server_con_proc_add(gearman_server_con_st *con);

/**
 * Remove connection from the proc thread list.
 */
GEARMAN_API
void gearman_server_con_proc_remove(gearman_server_con_st *con);

/**
 * Get next connection from the proc thread list.
 */
GEARMAN_API
gearman_server_con_st *
gearman_server_con_proc_next(gearman_server_thread_st *thread);

/**
 * Set protocol context pointer.
 * Add worker timeout for a connection tied to a job
 */
GEARMAN_API
gearmand_error_t gearman_server_con_add_job_timeout(gearman_server_con_st *con, gearman_server_job_st *job);

/**
 * Delete timeout event for a server con
 */
GEARMAN_API
void gearman_server_con_delete_timeout(gearman_server_con_st *con);

void gearman_server_con_protocol_release(gearman_server_con_st *con);

gearman_server_con_st* build_gearman_server_con_st(void);

void destroy_gearman_server_con_st(gearman_server_con_st* arg);

void gearmand_connection_list_init(gearmand_connection_list_st *source, gearmand_event_watch_fn *watch_fn, void *watch_context);

gearman_server_con_st *gearmand_ready(gearmand_connection_list_st *gearman);

/** @} */

#ifdef __cplusplus
}
#endif
