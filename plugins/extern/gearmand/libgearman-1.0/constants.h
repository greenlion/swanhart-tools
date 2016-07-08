/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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

#include <libgearman-1.0/priority.h>
#include <libgearman-1.0/protocol.h>
#include <libgearman-1.0/return.h>
#include <libgearman-1.0/limits.h>

#ifdef __cplusplus
extern "C" {
#endif

/**
 * @addtogroup gearman_constants Constants
 * @ingroup gearman_universal
 * @ingroup gearman_client
 * @ingroup gearman_worker
 * @{
 */

/**
 * Verbosity levels.
 */
typedef enum
{
  GEARMAN_VERBOSE_NEVER,
  GEARMAN_VERBOSE_FATAL,
  GEARMAN_VERBOSE_ERROR,
  GEARMAN_VERBOSE_INFO,
  GEARMAN_VERBOSE_DEBUG,
  GEARMAN_VERBOSE_CRAZY,
  GEARMAN_VERBOSE_MAX
} gearman_verbose_t;

/** @} */

/**
 * @ingroup gearman_universal
 * Options for gearman_universal_st.
 */
typedef enum
{
  GEARMAN_NON_BLOCKING,
  GEARMAN_DONT_TRACK_PACKETS,
  GEARMAN_MAX
} gearman_universal_options_t;

/**
 * @ingroup gearman_con
 * Options for gearman_connection_st.
 */
typedef enum
{
  GEARMAN_CON_READY,
  GEARMAN_CON_PACKET_IN_USE,
  GEARMAN_CON_EXTERNAL_FD,
  GEARMAN_CON_IGNORE_LOST_CONNECTION,
  GEARMAN_CON_CLOSE_AFTER_FLUSH,
  GEARMAN_CON_MAX
} gearman_connection_options_t;

/**
 * @ingroup gearman_client
 * Options for gearman_client_st.
 */
typedef enum
{
  GEARMAN_CLIENT_ALLOCATED=         (1 << 0),
  GEARMAN_CLIENT_NON_BLOCKING=      (1 << 1),
  GEARMAN_CLIENT_TASK_IN_USE=       (1 << 2),
  GEARMAN_CLIENT_UNBUFFERED_RESULT= (1 << 3),
  GEARMAN_CLIENT_NO_NEW=            (1 << 4),
  GEARMAN_CLIENT_FREE_TASKS=        (1 << 5),
  GEARMAN_CLIENT_GENERATE_UNIQUE=   (1 << 6),
  GEARMAN_CLIENT_EXCEPTION=         (1 << 7),
  GEARMAN_CLIENT_SSL=               (1 << 8),
  GEARMAN_CLIENT_MAX=               (1 << 9)
} gearman_client_options_t;

/**
 * @ingroup gearman_worker
 * Options for gearman_worker_st.
 */
typedef enum
{
  GEARMAN_WORKER_ALLOCATED=        (1 << 0),
  GEARMAN_WORKER_NON_BLOCKING=     (1 << 1),
  GEARMAN_WORKER_PACKET_INIT=      (1 << 2),
  GEARMAN_WORKER_GRAB_JOB_IN_USE=  (1 << 3),
  GEARMAN_WORKER_PRE_SLEEP_IN_USE= (1 << 4),
  GEARMAN_WORKER_WORK_JOB_IN_USE=  (1 << 5),
  GEARMAN_WORKER_CHANGE=           (1 << 6),
  GEARMAN_WORKER_GRAB_UNIQ=        (1 << 7),
  GEARMAN_WORKER_TIMEOUT_RETURN=   (1 << 8),
  GEARMAN_WORKER_GRAB_ALL=         (1 << 9),
  GEARMAN_WORKER_SSL=              (1 << 10),
  GEARMAN_WORKER_IDENTIFIER=       (1 << 11),
  GEARMAN_WORKER_MAX=   (1 << 12)
} gearman_worker_options_t;

/* Types. */
typedef struct gearman_task_st gearman_task_st;
typedef struct gearman_client_st gearman_client_st;
typedef struct gearman_job_st gearman_job_st;
typedef struct gearman_worker_st gearman_worker_st;
typedef struct gearman_allocator_t gearman_allocator_t;
typedef struct gearman_task_attr_t gearman_task_attr_t;
typedef struct gearman_result_st gearman_result_st;
typedef struct gearman_string_t gearman_string_t;
typedef struct gearman_argument_t gearman_argument_t;
typedef struct gearman_status_t gearman_status_t;

// Custom allocators
typedef void* (gearman_malloc_fn)(size_t size, void *context);
typedef void* (gearman_realloc_fn)(void *ptr, size_t size, void *context);
typedef void* (gearman_calloc_fn)(size_t nelm, size_t size, void *context);
typedef void (gearman_free_fn)(void *ptr, void *context);


typedef gearman_return_t (gearman_parse_server_fn)(const char *host, in_port_t port, void *context);

typedef void (gearman_task_context_free_fn)(gearman_task_st *task, void *context);

typedef void (gearman_log_fn)(const char *line, gearman_verbose_t verbose, void *context);


// Worker types

typedef void* (gearman_worker_fn)(gearman_job_st *job, void *context,
                                  size_t *result_size,
                                  gearman_return_t *ret_ptr);

typedef gearman_return_t (gearman_function_fn)(gearman_job_st *job, void *worker_context);

typedef struct gearman_aggregator_st gearman_aggregator_st;
typedef gearman_return_t (gearman_aggregator_fn)(gearman_aggregator_st *, gearman_task_st *, gearman_result_st *);

/** @} */

#ifdef __cplusplus
}
#endif
