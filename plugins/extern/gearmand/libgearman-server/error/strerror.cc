/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2010-2012 Data Differential, http://datadifferential.com/
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
 * @brief gearman_strerror()
 */

#include "gear_config.h"
#include <libgearman-server/error/strerror.h>

const char *gearmand_strerror(gearmand_error_t rc)
{
  switch (rc)
  {
  case GEARMAND_SUCCESS:
    return "SUCCESS";
  case GEARMAND_IO_WAIT:
    return "IO_WAIT";
  case GEARMAND_SHUTDOWN:
    return "SHUTDOWN";
  case GEARMAND_SHUTDOWN_GRACEFUL:
    return "SHUTDOWN_GRACEFUL";
  case GEARMAND_ERRNO:
    return "ERRNO";
  case GEARMAND_EVENT:
    return "EVENT";
  case GEARMAND_TOO_MANY_ARGS:
    return "TOO_MANY_ARGS";
  case GEARMAND_NO_ACTIVE_FDS:
    return "NO_ACTIVE_FDS";
  case GEARMAND_INVALID_MAGIC:
    return "INVALID_MAGIC";
  case GEARMAND_INVALID_COMMAND:
    return "INVALID_COMMAND";
  case GEARMAND_INVALID_PACKET:
    return "INVALID_PACKET";
  case GEARMAND_UNEXPECTED_PACKET:
    return "UNEXPECTED_PACKET";
  case GEARMAND_GETADDRINFO:
    return "GETADDRINFO";
  case GEARMAND_NO_SERVERS:
    return "NO_SERVERS";
  case GEARMAND_LOST_CONNECTION:
    return "LOST_CONNECTION";
  case GEARMAND_MEMORY_ALLOCATION_FAILURE:
    return "MEMORY_ALLOCATION_FAILURE";
  case GEARMAND_JOB_EXISTS:
    return "JOB_EXISTS";
  case GEARMAND_JOB_QUEUE_FULL:
    return "JOB_JOB_QUEUE_FULL";
  case GEARMAND_SERVER_ERROR:
    return "SERVER_ERROR";
  case GEARMAND_WORK_ERROR:
    return "WORK_ERROR";
  case GEARMAND_WORK_DATA:
    return "WORK_DATA";
  case GEARMAND_WORK_WARNING:
    return "WORK_WARNING";
  case GEARMAND_WORK_STATUS:
    return "WORK_STATUS";
  case GEARMAND_WORK_EXCEPTION:
    return "WORK_EXCEPTION";
  case GEARMAND_WORK_FAIL:
    return "WORK_FAIL";
  case GEARMAND_NOT_CONNECTED:
    return "NOT_CONNECTED";
  case GEARMAND_COULD_NOT_CONNECT:
    return "COULD_NOT_CONNECT";
  case GEARMAND_SEND_IN_PROGRESS:
    return "SEND_IN_PROGRESS";
  case GEARMAND_RECV_IN_PROGRESS:
    return "RECV_IN_PROGRESS";
  case GEARMAND_NOT_FLUSHING:
    return "NOT_FLUSHING";
  case GEARMAND_DATA_TOO_LARGE:
    return "DATA_TOO_LARGE";
  case GEARMAND_INVALID_FUNCTION_NAME:
    return "INVALID_FUNCTION_NAME";
  case GEARMAND_INVALID_WORKER_FUNCTION:
    return "INVALID_WORKER_FUNCTION";
  case GEARMAND_NO_REGISTERED_FUNCTION:
    return "NO_REGISTERED_FUNCTION";
  case GEARMAND_NO_REGISTERED_FUNCTIONS:
    return "NO_REGISTERED_FUNCTIONS";
  case GEARMAND_NO_JOBS:
    return "NO_JOBS";
  case GEARMAND_ECHO_DATA_CORRUPTION:
    return "ECHO_DATA_CORRUPTION";
  case GEARMAND_NEED_WORKLOAD_FN:
    return "NEED_WORKLOAD_FN";
  case GEARMAND_PAUSE:
    return "PAUSE";
  case GEARMAND_UNKNOWN_STATE:
    return "UNKNOWN_STATE";
  case GEARMAND_PTHREAD:
    return "PTHREAD";
  case GEARMAND_PIPE_EOF:
    return "PIPE_EOF";
  case GEARMAND_QUEUE_ERROR:
    return "QUEUE_ERROR";
  case GEARMAND_FLUSH_DATA:
    return "FLUSH_DATA";
  case GEARMAND_SEND_BUFFER_TOO_SMALL:
    return "SEND_BUFFER_TOO_SMALL";
  case GEARMAND_IGNORE_PACKET:
    return "IGNORE_PACKET";
  case GEARMAND_UNKNOWN_OPTION:
    return "UNKNOWN_OPTION";
  case GEARMAND_TIMEOUT:
    return "TIMEOUT";
  case GEARMAND_ARGUMENT_TOO_LARGE:
    return "The argument was too large for Gearman to handle.";
  case GEARMAND_INVALID_ARGUMENT:
    return "An invalid argument was passed to a function.";
  case GEARMAND_MAX_RETURN:
  default:
    return "Gibberish returned!";
  }
}
