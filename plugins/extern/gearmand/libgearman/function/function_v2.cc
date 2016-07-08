/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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

#include "gear_config.h"
#include <libgearman/common.h>

#include <libgearman/packet.hpp>
#include <libgearman/function/base.hpp>
#include <libgearman/function/function_v2.hpp>

/*
  FunctionV2 function
*/
gearman_function_error_t FunctionV2::callback(gearman_job_st* job_shell, void *context_arg)
{
  Job* job= job_shell->impl();
  if (gearman_job_is_map(job))
  {
    gearman_job_build_reducer(job, NULL);
  }

  gearman_return_t error= _function(job_shell, context_arg);
  switch (error)
  {
  case GEARMAN_SHUTDOWN:
    job->_error_code= GEARMAN_SUCCESS;
    return GEARMAN_FUNCTION_SHUTDOWN;

  case GEARMAN_WORK_EXCEPTION:
  case GEARMAN_FATAL:
    job->_error_code= GEARMAN_FATAL;
    return GEARMAN_FUNCTION_FATAL;

  case GEARMAN_ERROR:
    job->_error_code= GEARMAN_ERROR;
    return GEARMAN_FUNCTION_ERROR;

  case GEARMAN_SUCCESS:
    job->_error_code= GEARMAN_SUCCESS;
    return GEARMAN_FUNCTION_SUCCESS;

  case GEARMAN_IO_WAIT:
  case GEARMAN_SHUTDOWN_GRACEFUL:
  case GEARMAN_ERRNO:
  case GEARMAN_EVENT:
  case GEARMAN_TOO_MANY_ARGS:
  case GEARMAN_NO_ACTIVE_FDS:
  case GEARMAN_INVALID_MAGIC:
  case GEARMAN_INVALID_COMMAND:
  case GEARMAN_INVALID_PACKET:
  case GEARMAN_UNEXPECTED_PACKET:
  case GEARMAN_GETADDRINFO:
  case GEARMAN_NO_SERVERS:
  case GEARMAN_LOST_CONNECTION:
  case GEARMAN_MEMORY_ALLOCATION_FAILURE:
  case GEARMAN_JOB_EXISTS:
  case GEARMAN_JOB_QUEUE_FULL:
  case GEARMAN_SERVER_ERROR:
  case GEARMAN_WORK_DATA:
  case GEARMAN_WORK_WARNING:
  case GEARMAN_WORK_STATUS:
  case GEARMAN_NOT_CONNECTED:
  case GEARMAN_COULD_NOT_CONNECT:
  case GEARMAN_SEND_IN_PROGRESS:
  case GEARMAN_RECV_IN_PROGRESS:
  case GEARMAN_NOT_FLUSHING:
  case GEARMAN_DATA_TOO_LARGE:
  case GEARMAN_INVALID_FUNCTION_NAME:
  case GEARMAN_INVALID_WORKER_FUNCTION:
  case GEARMAN_NO_REGISTERED_FUNCTION:
  case GEARMAN_NO_REGISTERED_FUNCTIONS:
  case GEARMAN_NO_JOBS:
  case GEARMAN_ECHO_DATA_CORRUPTION:
  case GEARMAN_NEED_WORKLOAD_FN:
  case GEARMAN_PAUSE:
  case GEARMAN_UNKNOWN_STATE:
  case GEARMAN_PTHREAD:
  case GEARMAN_PIPE_EOF:
  case GEARMAN_QUEUE_ERROR:
  case GEARMAN_FLUSH_DATA:
  case GEARMAN_SEND_BUFFER_TOO_SMALL:
  case GEARMAN_IGNORE_PACKET:
  case GEARMAN_UNKNOWN_OPTION:
  case GEARMAN_TIMEOUT:
  case GEARMAN_ARGUMENT_TOO_LARGE:
  case GEARMAN_INVALID_ARGUMENT:
  case GEARMAN_IN_PROGRESS:
  case GEARMAN_INVALID_SERVER_OPTION:
  case GEARMAN_JOB_NOT_FOUND:
  case GEARMAN_MAX_RETURN:
    break;
  }

  gearman_universal_set_error(job->universal(), GEARMAN_INVALID_ARGUMENT, GEARMAN_AT,
                              "Worker returned invalid gearman_return_t:  %s",
                              gearman_strerror(error));
  return GEARMAN_FUNCTION_ERROR;
}
