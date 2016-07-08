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
 * @brief Defines, typedefs, and enums
 */

#pragma once

#ifdef __cplusplus
#include <cstdlib>
#else
#include <stdlib.h>
#endif

#include <libgearman-1.0/limits.h>
#include <libgearman-1.0/priority.h>
#include <libgearman-server/verbose.h>

/**
 * @addtogroup gearman_server_constants Constants
 * @ingroup gearman_server
 * @{
 */

/* Defines. */
#define GEARMAND_ARGS_BUFFER_SIZE 128
#define GEARMAND_CONF_DISPLAY_WIDTH 80
#define GEARMAND_CONF_MAX_OPTION_SHORT 128
#define GEARMAND_DEFAULT_BACKLOG 64
#define GEARMAND_DEFAULT_MAX_QUEUE_SIZE 0
#define GEARMAND_DEFAULT_SOCKET_RECV_SIZE 32768
#define GEARMAND_DEFAULT_SOCKET_SEND_SIZE 32768
#define GEARMAND_DEFAULT_SOCKET_TIMEOUT 10
#define GEARMAND_JOB_HANDLE_SIZE 64
#define GEARMAND_DEFAULT_HASH_SIZE 991
#define GEARMAND_MAX_COMMAND_ARGS 8
#define GEARMAND_MAX_FREE_SERVER_CLIENT 1000
#define GEARMAND_MAX_FREE_SERVER_CON 1000
#define GEARMAND_MAX_FREE_SERVER_JOB 1000
#define GEARMAND_MAX_FREE_SERVER_PACKET 2000
#define GEARMAND_MAX_FREE_SERVER_WORKER 1000
#define GEARMAND_OPTION_SIZE 64
#define GEARMAND_PACKET_HEADER_SIZE 12
#define GEARMAND_PIPE_BUFFER_SIZE 256
#define GEARMAND_RECV_BUFFER_SIZE 8192
#define GEARMAND_SEND_BUFFER_SIZE 8192
#define GEARMAND_SERVER_CON_ID_SIZE 128
#define GEARMAND_TEXT_RESPONSE_SIZE 8192
#define GEARMAN_MAGIC_MEMORY (void*)(0x000001)

/** @} */

/** @} */

enum gearmand_connection_options_t
{
  GEARMAND_CON_PACKET_IN_USE,
  GEARMAND_CON_EXTERNAL_FD,
  GEARMAND_CON_CLOSE_AFTER_FLUSH,
  GEARMAND_CON_MAX
};


struct gearman_server_thread_st;
struct gearman_server_st;
struct gearman_server_con_st;
struct gearmand_io_st;

#ifdef __cplusplus
extern "C" {
#endif

/* Function types. */
typedef void (gearman_server_thread_run_fn)(gearman_server_thread_st *thread,
                                            void *context);

typedef gearmand_error_t (gearman_queue_add_fn)(gearman_server_st *server,
                                                void *context,
                                                const char *unique,
                                                size_t unique_size,
                                                const char *function_name,
                                                size_t function_name_size,
                                                const void *data,
                                                size_t data_size,
                                                gearman_job_priority_t priority,
                                                int64_t when);

typedef gearmand_error_t (gearman_queue_flush_fn)(gearman_server_st *server,
                                                 void *context);

typedef gearmand_error_t (gearman_queue_done_fn)(gearman_server_st *server,
                                                 void *context,
                                                 const char *unique,
                                                 size_t unique_size,
                                                 const char *function_name,
                                                 size_t function_name_size);
typedef gearmand_error_t (gearman_queue_replay_fn)(gearman_server_st *server,
                                                   void *context,
                                                   gearman_queue_add_fn *add_fn,
                                                   void *add_context);

typedef gearmand_error_t (gearmand_connection_add_fn)(gearman_server_con_st *con);
typedef gearmand_error_t (gearmand_connection_remove_fn)(gearman_server_con_st *con);

typedef void (gearman_log_server_fn)(const char *line, gearmand_verbose_t verbose,
                                     struct gearmand_thread_st *context);

typedef gearmand_error_t (gearmand_event_watch_fn)(gearmand_io_st *con,
                                                   short events, void *context);

typedef void (gearmand_connection_protocol_context_free_fn)(gearman_server_con_st *con,
                                                            void *context);

typedef void (gearmand_log_fn)(const char *line, gearmand_verbose_t verbose,
                              void *context);


/** @} */

/**
 * @addtogroup gearman_server_protocol Protocol Plugins
 * @ingroup gearman_server
 */

/**
 * @addtogroup gearman_server_queue Queue Plugins
 * @ingroup gearman_server
 */

#ifdef __cplusplus
}
#endif
