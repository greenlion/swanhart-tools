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
 * @brief Job Declarations
 */

#pragma once

#include <libgearman-server/struct/job.h>

/** @addtogroup gearman_server_job Job Declarations @ingroup gearman_server
 *
 * This is a low level interface for gearman server jobs. This is used
 * internally by the server interface, so you probably want to look there first.
 *
 * @{
 */


#ifdef __cplusplus
extern "C" {
#endif

/**
 * Add a new job to a server instance.
 */
GEARMAN_API
gearman_server_job_st *
gearman_server_job_add(gearman_server_st *server,
                       const char *function_name, size_t function_name_size,
                       const char *unique, size_t unique_size,
                       const void *data, size_t data_size,
                       gearman_job_priority_t priority,
                       gearman_server_client_st *server_client,
                       gearmand_error_t *ret_ptr,
                       int64_t when);

GEARMAN_API
gearman_server_job_st *
gearman_server_job_add_reducer(gearman_server_st *server,
                               const char *function_name, size_t function_name_size, 
                               const char *unique, size_t unique_size, 
                               const char *reducer, size_t reducer_name_size, 
                               const void *data, size_t data_size,
                               gearman_job_priority_t priority,
                               gearman_server_client_st *server_client,
                               gearmand_error_t *ret_ptr,
                               int64_t when);



/**
 * Initialize a server job structure.
 */
GEARMAN_API
gearman_server_job_st *
gearman_server_job_create(gearman_server_st *server);

/**
 * Free a server job structure.
 */
GEARMAN_API
void gearman_server_job_free(gearman_server_job_st *server_job);

/**
 * Get a server job structure from the job handle.
 */
GEARMAN_API
gearman_server_job_st *gearman_server_job_get(gearman_server_st *server,
                                              const char *job_handle,
                                              const size_t job_handle_length,
                                              gearman_server_con_st *worker_con);

/**
 * See if there are any jobs to be run for the server worker connection.
 */
GEARMAN_API
gearman_server_job_st *
gearman_server_job_peek(gearman_server_con_st *server_con);

/**
 * Start running a job for the server worker connection.
 */
GEARMAN_API
gearman_server_job_st *
gearman_server_job_take(gearman_server_con_st *server_con);

/**
 * Queue a job to be run.
 */
GEARMAN_API
gearmand_error_t gearman_server_job_queue(gearman_server_job_st *server_job);

uint32_t _server_job_hash(const char *key, size_t key_size);

void *_proc(void *data);

void _server_con_worker_list_append(gearman_server_worker_st *list,
                                    gearman_server_worker_st *worker);

gearman_server_job_st *gearman_server_job_get_by_unique(gearman_server_st *server,
                                                        const char *unique,
                                                        const size_t unique_length,
                                                        gearman_server_con_st *worker_con);

/** @} */

void destroy_gearman_server_job_st(gearman_server_job_st *);

#ifdef __cplusplus
}
#endif
