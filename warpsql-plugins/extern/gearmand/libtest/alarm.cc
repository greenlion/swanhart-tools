/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 *
 *  Data Differential YATL (i.e. libtest)  library
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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

#include "libtest/yatlcon.h"

#include <libtest/common.h>

#include <sys/time.h>
#include <cstdlib>

namespace libtest {

#ifdef __APPLE__
static const struct timeval default_it_value= { 2400, 0 };
#else
static const struct timeval default_it_value= { 600, 0 };
#endif
static const struct timeval default_it_interval= { 0, 0 };
static const struct itimerval defualt_timer= { default_it_interval, default_it_value };

static const struct itimerval cancel_timer= { default_it_interval, default_it_interval };


void set_alarm()
{
  if (gdb_is_caller() == false)
  {
    struct itimerval old_timer;
    if (setitimer(ITIMER_VIRTUAL, &defualt_timer, &old_timer) == -1)
    {
      Error << "setitimer() failed";
    }

    if (old_timer.it_interval.tv_sec != 0 or old_timer.it_value.tv_sec != 0)
    {
      Error << "setitimer() return an old_timer structure which wasn't zero";
    }
  }
}

void set_alarm(long tv_sec, long tv_usec)
{
  // For the moment use any value to YATL_ALARM to cancel alarming.
  if (gdb_is_caller() == false)
  {
    if (getenv("YATL_ALARM"))
    {
      errno= 0;
      tv_sec= strtol(getenv("YATL_ALARM"), (char **) NULL, 10);
      if (errno != 0)
      {
        FATAL("Bad value for YATL_ALARM");
      }
      else if (tv_sec == 0)
      {
        cancel_alarm();
      }
    }

#ifdef __APPLE__
    struct timeval it_value= { time_t(tv_sec), suseconds_t(tv_usec) };
#else
    struct timeval it_value= { tv_sec, tv_usec };
#endif

    struct itimerval timer= { default_it_interval, it_value };

    if (setitimer(ITIMER_VIRTUAL, &timer, NULL) == -1)
    {
      Error << "setitimer() failed";
    }
  }
}

void cancel_alarm()
{
  if (gdb_is_caller() == false)
  {
    if (setitimer(ITIMER_VIRTUAL, &cancel_timer, NULL) == -1)
    {
      Error << "setitimer() failed";
    }
  }
}

} // namespace libtest

