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
#include <iostream>

#include <libgearman-server/plugins.h>

#include <libgearman-server/queue.hpp>
#include <libgearman-server/plugins/queue.h>

namespace gearmand {
namespace plugins {

void initialize()
{
  queue::initialize_default();

#if defined(HAVE_LIBDRIZZLE) && HAVE_LIBDRIZZLE
  if (HAVE_LIBDRIZZLE)
  {
    queue::initialize_drizzle();
  }
#endif

#if defined(HAVE_LIBMEMCACHED) && HAVE_LIBMEMCACHED
  if (HAVE_LIBMEMCACHED)
  {
    queue::initialize_libmemcached();
  }
#endif

#if defined(HAVE_LIBSQLITE3) && HAVE_LIBSQLITE3
  if (HAVE_LIBSQLITE3)
  {
    queue::initialize_sqlite();
  }
#endif

#if defined(HAVE_LIBPQ) && HAVE_LIBPQ
  if (HAVE_LIBPQ)
  {
    queue::initialize_postgres();
  }
#endif

#if defined(HAVE_HIREDIS) && HAVE_HIREDIS
  if (HAVE_HIREDIS)
  {
    queue::initialize_redis();
  }
#endif

#if defined(HAVE_TOKYOCABINET) && HAVE_TOKYOCABINET
  if (HAVE_TOKYOCABINET)
  {
    queue::initialize_tokyocabinet();
  }
#endif

#if defined(HAVE_LIBMYSQL_BUILD) && HAVE_LIBMYSQL_BUILD
  if (HAVE_LIBMYSQL_BUILD)
  {
    queue::initialize_mysql();
  }
#endif

}

} //namespace plugins
} //namespace gearmand
