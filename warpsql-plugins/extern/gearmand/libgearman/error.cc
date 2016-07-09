/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2013 Data Differential, http://datadifferential.com/
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

#include "gear_config.h"
#include <libgearman/common.h>

#include "libgearman/assert.hpp"

#include "libgearman/log.hpp"

#include <cerrno>
#include <cstdarg>
#include <cstdio>
#include <cstring>

#include <sys/types.h>
#include <unistd.h>

static void correct_from_errno(gearman_universal_st& universal)
{
  if (universal.error_code() == GEARMAN_ERRNO)
  {
    switch (universal.last_errno())
    {
    case EFAULT:
    case ENOMEM:
      universal.error_code(GEARMAN_MEMORY_ALLOCATION_FAILURE);
      break;

    case EINVAL:
      universal.error_code(GEARMAN_INVALID_ARGUMENT);
      break;

    case ECONNRESET:
    case EHOSTDOWN:
    case EPIPE:
      universal.error_code(GEARMAN_LOST_CONNECTION);
      break;

    case ECONNREFUSED:
    case ENETUNREACH:
    case ETIMEDOUT:
      universal.error_code(GEARMAN_COULD_NOT_CONNECT);
      break;

    default:
      break;
    }
  }
  else
  {
    universal.last_errno(0);
  }
}

#ifdef __clang__
# pragma clang diagnostic push
#else
# pragma GCC diagnostic push
#endif
#pragma GCC diagnostic ignored "-Wswitch-enum"
static bool no_error_message(gearman_return_t rc)
{
  switch (rc)
  {
    case GEARMAN_SUCCESS:
    case GEARMAN_IO_WAIT:
    case GEARMAN_INVALID_SERVER_OPTION:
    case GEARMAN_WORK_EXCEPTION:
    case GEARMAN_WORK_FAIL:
    case GEARMAN_SHUTDOWN_GRACEFUL:
    case GEARMAN_SHUTDOWN:
      return true;

    default:
      break;
  }

  return false;
}
#pragma GCC diagnostic pop

#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wformat-nonliteral"
gearman_return_t gearman_universal_set_error(gearman_universal_st& universal, 
                                             gearman_return_t rc,
                                             const char *function,
                                             const char *position,
                                             const char *format, ...)
{
  if (no_error_message(rc))
  {
    return universal.error_code(rc);
  }

  va_list args;

  universal.error_code(rc);
  correct_from_errno(universal);

  char last_error[GEARMAN_MAX_ERROR_SIZE];
  va_start(args, format);
  int length= vsnprintf(last_error, GEARMAN_MAX_ERROR_SIZE, format, args);
  va_end(args);

  if (length > int(GEARMAN_MAX_ERROR_SIZE) or length < 0)
  {
    assert(length > int(GEARMAN_MAX_ERROR_SIZE));
    assert(length < 0);
    last_error[GEARMAN_MAX_ERROR_SIZE -1]= 0;
  }

  if (rc == GEARMAN_GETADDRINFO)
  {
    universal._error.error("%s pid(%u)", last_error, getpid());
  }
  else
  {
    universal._error.error("%s(%s) %s -> %s pid(%u)", 
                           function, gearman_strerror(universal._error.error_code()), last_error, position, getpid());
  }

  gearman_log_error(universal,
                    universal._error.error_code() == GEARMAN_MEMORY_ALLOCATION_FAILURE ? GEARMAN_VERBOSE_FATAL : GEARMAN_VERBOSE_ERROR);

  return universal._error.error_code();
}
#pragma GCC diagnostic pop

#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wformat-nonliteral"
gearman_return_t gearman_universal_set_gerror(gearman_universal_st& universal, 
                                              gearman_return_t rc,
                                              const char *func,
                                              const char *position)
{
  if (no_error_message(rc))
  {
    return universal.error_code(rc);
  }

  universal.error_code(rc);
  correct_from_errno(universal);

  universal._error.error("%s(%s) -> %s pid(%u)", func, gearman_strerror(rc), position, getpid());

  gearman_log_error(universal,
                    universal.error_code() == GEARMAN_MEMORY_ALLOCATION_FAILURE ? GEARMAN_VERBOSE_FATAL : GEARMAN_VERBOSE_ERROR);

  return rc;
}
#pragma GCC diagnostic pop

#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wformat-nonliteral"
gearman_return_t gearman_universal_set_perror(gearman_universal_st &universal,
                                              const gearman_return_t rc,
                                              const int _system_errno,
                                              const char *function, const char *position, 
                                              const char *format, ...)
{
  if (_system_errno)
  {
    switch (_system_errno)
    {
      case ENOMEM:
        universal._error.error_code(GEARMAN_MEMORY_ALLOCATION_FAILURE);
        break;

      default:
        universal._error.error_code(rc);
        break;
    }
    universal._error.system_error(_system_errno);

    correct_from_errno(universal);

    const char *errmsg_ptr;
    char errmsg[GEARMAN_MAX_ERROR_SIZE]; 
    errmsg[0]= 0; 

#ifdef STRERROR_R_CHAR_P
    errmsg_ptr= strerror_r(universal._error.system_error(), errmsg, sizeof(errmsg));
#else
    strerror_r(universal._error.system_error(), errmsg, sizeof(errmsg));
    errmsg_ptr= errmsg;
#endif

    int length;
    if (format)
    {
      char last_error[GEARMAN_MAX_ERROR_SIZE];

      va_list args;
      va_start(args, format);
      errno= 0;
      length= vsnprintf(last_error, GEARMAN_MAX_ERROR_SIZE, format, args);
      va_end(args);

      if (length > int(GEARMAN_MAX_ERROR_SIZE) or length < 0)
      {
        assert(length > int(GEARMAN_MAX_ERROR_SIZE));
        assert(length < 0);
        last_error[GEARMAN_MAX_ERROR_SIZE -1]= 0;
      }

      universal._error.error("%s(%s) %s -> %s pid(%u)", function, errmsg_ptr, last_error, position, getpid());
    }
    else
    {
      universal._error.error("%s(%s) -> %s pid(%d)", function, errmsg_ptr, position, getpid());
    }

    gearman_log_error(universal,
                      universal._error.error_code() == GEARMAN_MEMORY_ALLOCATION_FAILURE ? GEARMAN_VERBOSE_FATAL : GEARMAN_VERBOSE_ERROR);

    return universal._error.error_code();
  }

  return GEARMAN_SUCCESS;
}
#pragma GCC diagnostic pop

#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wformat-nonliteral"
const char* error_st::error(const char * __restrict format__, ...)
{
  if (format__)
  {
    va_list args;

    va_start(args, format__);
    vsnprintf(_last_error, GEARMAN_MAX_ERROR_SIZE, format__, args);
    va_end(args);

    _last_error[GEARMAN_MAX_ERROR_SIZE -1]= 0;

    return _last_error;
  }
  _last_error[0]= 0;

  return NULL;
}
#pragma GCC diagnostic pop
