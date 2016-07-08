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

/*
  All logging facilities within the server.
*/

#include <cstdio>

#pragma once

#include "libgearman-1.0/string.h"
#include "libgearman-server/error.h"

#ifdef __cplusplus
extern "C" {
#endif

#ifndef __PRETTY_FUNCTION__
# define __PRETTY_FUNCTION__ __func__
#endif

#define STRINGIFY(x) #x
#define TOSTRING(x) STRINGIFY(x)
#define GEARMAND_AT __FILE__ ":" TOSTRING(__LINE__)

#define GEARMAN_DEFAULT_LOG_PARAM GEARMAND_AT, __PRETTY_FUNCTION__

gearmand_error_t gearmand_initialize_thread_logging(const char *identity);

/**
 * Log a fatal message, see gearmand_log() for argument details.
 */
gearmand_error_t gearmand_log_fatal(const char *position, const char *func, const char *format, ...);
#define gearmand_fatal(_mesg) gearmand_log_fatal(GEARMAN_DEFAULT_LOG_PARAM, (_mesg))

gearmand_error_t gearmand_log_fatal_perror(const char *position, const char *function, const int local_errno, const char *format, ...);
#define gearmand_fatal_perror(__local_errno, __mesg) gearmand_log_fatal_perror(GEARMAN_DEFAULT_LOG_PARAM, (__local_errno), (__mesg))


/**
 * Log an error message, see gearmand_log() for argument details.
 */
gearmand_error_t gearmand_log_error(const char *position, const char *function, const char *format, ...);
#define gearmand_error(_mesg) gearmand_log_error(GEARMAN_DEFAULT_LOG_PARAM, (_mesg))

gearmand_error_t gearmand_log_perror(const char *position, const char *function, const int local_errno, const char *format, ...);
#define gearmand_perror(__local_errno, __mesg) gearmand_log_perror(GEARMAN_DEFAULT_LOG_PARAM, (__local_errno), (__mesg))

gearmand_error_t gearmand_log_gerror(const char *position, const char *function, const gearmand_error_t rc, const char *format, ...);
#define gearmand_gerror(_mesg, _gearmand_errot_t) gearmand_log_gerror(GEARMAN_DEFAULT_LOG_PARAM, (_gearmand_errot_t), (_mesg))

void gearmand_log_perror_warn(const char *position, const char *function, const int local_errno, const char *format, ...);

void gearmand_log_gerror_warn(const char *position, const char *function, const gearmand_error_t rc, const char *format, ...);
#define gearmand_gerror_warn(_mesg, _gearmand_errot_t) gearmand_log_gerror_warn(GEARMAN_DEFAULT_LOG_PARAM, (_gearmand_errot_t), (_mesg))

gearmand_error_t gearmand_log_gai_error(const char *position, const char *function, const int rc, const char *message);
#define gearmand_gai_error(_mesg, _gai_int) gearmand_log_gai_error(GEARMAN_DEFAULT_LOG_PARAM, (_gai_int), (_mesg))

gearmand_error_t gearmand_log_memory_error(const char *position, const char *function, const char *allocator, const char *type, size_t count, size_t size);
#define gearmand_merror(__allocator, __object_type, __count) gearmand_log_memory_error(GEARMAN_DEFAULT_LOG_PARAM, (__allocator), (#__object_type), (__count), (sizeof(__object_type)))


void gearmand_log_notice(const char *position, const char *function, const char *format, ...);

/**
 * Log an info message, see gearmand_log() for argument details.
 */
void gearmand_log_info(const char *position, const char *function, const char *format, ...);
#define gearmand_info(_mesg) gearmand_log_info(GEARMAN_DEFAULT_LOG_PARAM, (_mesg))

/**
 * Log an info message, see gearmand_log() for argument details.
 */
void gearmand_log_warning(const char *position, const char *function, const char *format, ...);
#define gearmand_warning(_mesg) gearmand_log_warning(GEARMAN_DEFAULT_LOG_PARAM, (_mesg))

/**
 * Log a debug message, see gearmand_log() for argument details.
 */
void gearmand_log_debug(const char *position, const char *function, const char *format, ...);
#define gearmand_debug(_mesg) gearmand_log_debug(GEARMAN_DEFAULT_LOG_PARAM, (_mesg))

#ifdef __cplusplus
}
#endif
