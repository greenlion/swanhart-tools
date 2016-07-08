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


/**
 * @file
 * @brief Gearman Declarations
 */

#pragma once

/* This seems to be required for older compilers @note http://stackoverflow.com/questions/8132399/how-to-printf-uint64-t  */
#ifndef __STDC_FORMAT_MACROS
#  define __STDC_FORMAT_MACROS
#endif

#ifdef __cplusplus
#  include <cinttypes>
#  include <cstddef>
#  include <cstdlib>
#  include <ctime>
#else
#  include <inttypes.h>
#  include <stdbool.h>
#  include <stddef.h>
#  include <stdlib.h>
#  include <time.h>
#endif

#include <netinet/in.h>
#include <sys/socket.h>
#include <netdb.h>
#include <arpa/inet.h>
#include <poll.h>
#include <sys/uio.h>

#include <libgearman-1.0/visibility.h>
#include <libgearman-1.0/version.h>
#include <libgearman-1.0/constants.h>
#include <libgearman-1.0/signal.h>
#include <libgearman-1.0/kill.h>
#include <libgearman-1.0/job_handle.h>
#include <libgearman-1.0/client_callbacks.h>
#include <libgearman-1.0/strerror.h>
#include <libgearman-1.0/function.h>
#include <libgearman-1.0/string.h>

#include <libgearman-1.0/actions.h>
#include <libgearman-1.0/string.h>

#include <libgearman-1.0/aggregator.h>

// Everything above this line must be in the order specified.
#include <libgearman-1.0/argument.h>
#include <libgearman-1.0/task_attr.h>
#include <libgearman-1.0/core.h>
#include <libgearman-1.0/task.h>
#include <libgearman-1.0/job.h>
#include <libgearman-1.0/string.h>
#include <libgearman-1.0/result.h>
#include <libgearman-1.0/execute.h>
#include <libgearman-1.0/util.h>
#include <libgearman-1.0/interface/status.h>
#include <libgearman-1.0/status.h>

#include <libgearman-1.0/worker.h>
#include <libgearman-1.0/client.h>
#include <libgearman-1.0/connection.h>
#include <libgearman-1.0/parse.h>
#include <libgearman-1.0/cancel.h>
