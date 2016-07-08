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

/**
 * Return codes.
 */
enum gearman_return_t
{
  GEARMAN_SUCCESS,
  GEARMAN_IO_WAIT,
  GEARMAN_SHUTDOWN,
  GEARMAN_SHUTDOWN_GRACEFUL,
  GEARMAN_ERRNO,
  GEARMAN_EVENT, // DEPRECATED, SERVER ONLY
  GEARMAN_TOO_MANY_ARGS,
  GEARMAN_NO_ACTIVE_FDS, // No servers available
  GEARMAN_INVALID_MAGIC,
  GEARMAN_INVALID_COMMAND,
  GEARMAN_INVALID_PACKET,
  GEARMAN_UNEXPECTED_PACKET,
  GEARMAN_GETADDRINFO,
  GEARMAN_NO_SERVERS,
  GEARMAN_LOST_CONNECTION,
  GEARMAN_MEMORY_ALLOCATION_FAILURE,
  GEARMAN_JOB_EXISTS, // see gearman_client_job_status()
  GEARMAN_JOB_QUEUE_FULL,
  GEARMAN_SERVER_ERROR,
  GEARMAN_WORK_ERROR,
  GEARMAN_WORK_DATA,
  GEARMAN_WORK_WARNING,
  GEARMAN_WORK_STATUS,
  GEARMAN_WORK_EXCEPTION,
  GEARMAN_WORK_FAIL,
  GEARMAN_NOT_CONNECTED,
  GEARMAN_COULD_NOT_CONNECT,
  GEARMAN_SEND_IN_PROGRESS, // DEPRECATED, SERVER ONLY
  GEARMAN_RECV_IN_PROGRESS, // DEPRECATED, SERVER ONLY
  GEARMAN_NOT_FLUSHING,
  GEARMAN_DATA_TOO_LARGE,
  GEARMAN_INVALID_FUNCTION_NAME,
  GEARMAN_INVALID_WORKER_FUNCTION,
  GEARMAN_NO_REGISTERED_FUNCTION,
  GEARMAN_NO_REGISTERED_FUNCTIONS,
  GEARMAN_NO_JOBS,
  GEARMAN_ECHO_DATA_CORRUPTION,
  GEARMAN_NEED_WORKLOAD_FN,
  GEARMAN_PAUSE, // Used only in custom application for client return based on work status, exception, or warning.
  GEARMAN_UNKNOWN_STATE,
  GEARMAN_PTHREAD, // DEPRECATED, SERVER ONLY
  GEARMAN_PIPE_EOF, // DEPRECATED, SERVER ONLY
  GEARMAN_QUEUE_ERROR, // DEPRECATED, SERVER ONLY
  GEARMAN_FLUSH_DATA, // Internal state, should never be seen by either client or worker.
  GEARMAN_SEND_BUFFER_TOO_SMALL,
  GEARMAN_IGNORE_PACKET, // Internal only
  GEARMAN_UNKNOWN_OPTION, // DEPRECATED
  GEARMAN_TIMEOUT,
  GEARMAN_ARGUMENT_TOO_LARGE,
  GEARMAN_INVALID_ARGUMENT,
  GEARMAN_IN_PROGRESS, // See gearman_client_job_status()
  GEARMAN_INVALID_SERVER_OPTION, // Bad server option sent to server
  GEARMAN_JOB_NOT_FOUND, // Job did not exist on server
  GEARMAN_MAX_RETURN, /* Always add new error code before */
  GEARMAN_FAIL= GEARMAN_WORK_FAIL,
  GEARMAN_FATAL= GEARMAN_WORK_FAIL,
  GEARMAN_ERROR= GEARMAN_WORK_ERROR
};

static inline bool gearman_continue(enum gearman_return_t rc)
{
  return rc == GEARMAN_IO_WAIT || rc == GEARMAN_IN_PROGRESS ||  rc == GEARMAN_PAUSE || rc == GEARMAN_JOB_EXISTS;
}

static inline bool gearman_failed(enum gearman_return_t rc)
{
  return rc != GEARMAN_SUCCESS;
}

static inline bool gearman_success(enum gearman_return_t rc)
{
  return rc == GEARMAN_SUCCESS;
}

#ifndef __cplusplus
typedef enum gearman_return_t gearman_return_t;
#endif
