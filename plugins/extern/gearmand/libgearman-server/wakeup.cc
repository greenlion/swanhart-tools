/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2012 Data Differential, http://datadifferential.com/
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

#include "libgearman/assert.hpp"
#include "libgearman-server/log.h"

#include <libgearman-server/wakeup.h>

const char *gearmand_strwakeup(gearmand_wakeup_t arg)
{
  switch (arg)
  {
  case GEARMAND_WAKEUP_PAUSE:
    return "GEARMAND_WAKEUP_PAUSE";

  case GEARMAND_WAKEUP_SHUTDOWN:
      return "GEARMAND_WAKEUP_SHUTDOWN";

  case GEARMAND_WAKEUP_SHUTDOWN_GRACEFUL:
      return "GEARMAND_WAKEUP_SHUTDOWN_GRACEFUL";

  case GEARMAND_WAKEUP_CON:
      return "GEARMAND_WAKEUP_CON";

  case GEARMAND_WAKEUP_RUN:
      return "GEARMAND_WAKEUP_RUN";
  }

  assert_msg(false, "Invalid gearmand_verbose_t used.");
  gearmand_fatal("Invalid gearmand_verbose_t used.");

  return "";
}

