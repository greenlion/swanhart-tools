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

#include <libtest/timer.hpp>

#include <ctime>
#include <iomanip>

#ifdef __APPLE__
# include <mach/clock.h>
# include <mach/mach.h>
#else
# include <sys/time.h>
#endif

namespace libtest {

Timer::Timer()
{
  _begin.tv_sec= 0;
  _begin.tv_nsec= 0;
  _end.tv_sec= 0;
  _end.tv_nsec= 0;
}

void Timer::reset()
{
  _end.tv_sec= 0;
  _end.tv_nsec= 0;
  _time(_begin);
}

void Timer::sample()
{
  _time(_end);
}

void Timer::offset(int64_t minutes_arg, int64_t seconds_arg, int64_t nanoseconds)
{
  reset();
  _end= _begin;
  _end.tv_sec+= (minutes_arg * 60) +seconds_arg;
  _end.tv_nsec+= nanoseconds;
}

int64_t Timer::minutes()
{
  struct timespec result;
  difference(result);
  return int64_t(result.tv_sec / 60);
}

uint64_t Timer::elapsed_milliseconds() const
{
  struct timespec temp;
  difference(temp);

  return temp.tv_sec*1000 +temp.tv_nsec/1000000;
}

void Timer::difference(struct timespec& arg) const
{
  if ((_end.tv_nsec -_begin.tv_nsec) < 0)
  {
    arg.tv_sec= _end.tv_sec -_begin.tv_sec -1;
    arg.tv_nsec= 1000000000 +_end.tv_nsec -_begin.tv_nsec;

  }
  else
  {
    arg.tv_sec= _end.tv_sec -_begin.tv_sec;
    arg.tv_nsec= _end.tv_nsec -_begin.tv_nsec;
  }
}

void Timer::_time(struct timespec& ts)
{
#ifdef __APPLE__ // OSX lacks clock_gettime()
  clock_serv_t _clock_serv;
  mach_timespec_t _mach_timespec;
  host_get_clock_service(mach_host_self(), CALENDAR_CLOCK, &_clock_serv);
  clock_get_time(_clock_serv, &_mach_timespec);
  mach_port_deallocate(mach_task_self(), _clock_serv);
  ts.tv_sec= _mach_timespec.tv_sec;
  ts.tv_nsec= _mach_timespec.tv_nsec;
#elif defined(_WIN32)
  ts.tv_sec= time(NULL);
  ts.tv_nsec= 0;
#else
  clock_gettime(CLOCK_REALTIME, &ts);
#endif
}

std::ostream& operator<<(std::ostream& output, const libtest::Timer& arg)
{
  struct timespec temp;
  arg.difference(temp);

  if (temp.tv_sec > 60)
  {
    output << temp.tv_sec / 60;
    output << "." << temp.tv_sec % 60;
  }
  else
  {
    output << temp.tv_sec;
  }

  output << ":";
  output << std::setfill('0') << std::setw(9) << temp.tv_nsec;

  return output;
}

} // namespace libtest
