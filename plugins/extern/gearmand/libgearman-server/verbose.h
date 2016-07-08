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

#pragma once

#include <syslog.h>

/**
 * Verbosity levels.
 */
enum gearmand_verbose_t
{
  // Logging this will cause gearmand to shutdown
  GEARMAND_VERBOSE_FATAL= LOG_EMERG, // syslog:LOG_EMERG

  // Next two are currently unused
  GEARMAND_VERBOSE_ALERT= LOG_ALERT, // syslog:LOG_ALERT
  GEARMAND_VERBOSE_CRITICAL= LOG_CRIT, //  syslog:LOG_CRIT

  GEARMAND_VERBOSE_ERROR= LOG_ERR, // syslog:LOG_ERR

  GEARMAND_VERBOSE_WARN= LOG_WARNING, // syslog:LOG_WARNING

  // Notice should only be used for job creation/completion
  GEARMAND_VERBOSE_NOTICE= LOG_NOTICE, // syslog:LOG_NOTICE

  // Info is used for state of the system (i.e. startup, shutdown, etc)
  GEARMAND_VERBOSE_INFO= LOG_INFO, // syslog:LOG_INFO

  // @todo Fix it so that these will not be compiled in by default
  GEARMAND_VERBOSE_DEBUG= LOG_DEBUG // syslog:LOG_DEBUG
};

#ifndef __cplusplus
typedef enum gearmand_verbose_t gearmand_verbose_t;
#endif

