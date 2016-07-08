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

#pragma once

#include <libgearman-server/struct/thread.h>

#ifdef __cplusplus
extern "C" {
#endif

#include <pthread.h>

/**
 * @addtogroup gearman_server_thread Thread Declarations
 * @ingroup gearman_server
 *
 * This is the interface gearman servers should use for creating threads.
 *
 * @{
 */

/**
 * Initialize a thread structure. This cannot fail if the caller supplies a
 * thread structure.
 * @param server Server structure previously initialized with
 *        gearman_server_create.
 * @param thread Caller allocated thread structure, or NULL to allocate one.
 * @return Pointer to an allocated thread structure if thread parameter was
 *         NULL, or the thread parameter pointer if it was not NULL.
 */
GEARMAN_API
bool gearman_server_thread_init(gearman_server_st *server,
                                gearman_server_thread_st *thread,
                                gearman_log_server_fn *function,
                                gearmand_thread_st *context,
                                gearmand_event_watch_fn *event_watch);

/**
 * Free resources used by a thread structure.
 * @param thread Thread structure previously initialized with
 *        gearman_server_thread_init.
 */
GEARMAN_API
void gearman_server_thread_free(gearman_server_thread_st *thread);

/**
 * Process server thread connections.
 * @param thread Thread structure previously initialized with
 *        gearman_server_thread_init.
 * @param ret_ptr Pointer to hold a standard gearman return value.
 * @return On error, the server connection that encountered the error.
 */
GEARMAN_API
gearmand_con_st *
gearman_server_thread_run(gearman_server_thread_st *thread,
                          gearmand_error_t *ret_ptr);

/** @} */

#ifdef __cplusplus
}
#endif
