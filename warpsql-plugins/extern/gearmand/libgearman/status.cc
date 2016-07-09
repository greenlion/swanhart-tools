/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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

void gearman_init(gearman_status_t& status)
{
  status.version_= GEARMAN_STATUS_V1;
  status.status_.mesg_.result_rc= GEARMAN_UNKNOWN_STATE;
  status.status_.mesg_.is_known= false;
  status.status_.mesg_.is_running= false;
  status.status_.mesg_.numerator= 0;
  status.status_.mesg_.denominator= 0;
  status.status_.mesg_.client_count= 0;
}

void gearman_status_set_return(gearman_status_t& arg, const gearman_return_t ret)
{
  arg.status_.mesg_.result_rc= ret;
}

void gearman_status_set(gearman_status_t& arg,
                        const bool is_known,
                        const bool is_running,
                        const uint32_t numerator,
                        const uint32_t denominator,
                        const uint32_t client_count)
{
  arg.status_.mesg_.is_known= is_known;
  arg.status_.mesg_.is_running= is_running;
  arg.status_.mesg_.numerator= numerator;
  arg.status_.mesg_.denominator= denominator;
  arg.status_.mesg_.client_count= client_count;
}

gearman_return_t gearman_status_return(const struct gearman_status_t arg)
{
  return arg.status_.mesg_.result_rc;
}

bool gearman_status_is_known(const struct gearman_status_t arg)
{
  return arg.status_.mesg_.is_known;
}

bool gearman_status_is_running(const struct gearman_status_t arg)
{
  return arg.status_.mesg_.is_running;
}

uint32_t gearman_status_numerator(const struct gearman_status_t arg)
{
  return arg.status_.mesg_.numerator;
}

uint32_t gearman_status_denominator(const struct gearman_status_t arg)
{
  return arg.status_.mesg_.denominator;
}
