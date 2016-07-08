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

#pragma once

#include <unistd.h>
#include <string>
#include <signal.h>

#include <libtest/dream.h>

namespace libtest {

class Wait 
{
public:

  Wait(const std::string &filename, uint32_t timeout= 6) :
    _successful(false)
  {
    uint32_t waited;
    uint32_t this_wait;
    uint32_t retry;

    if (filename.empty())
    {
      _successful= false;
      return;
    }

    for (waited= 0, retry= 1; ; retry++, waited+= this_wait)
    {
      if (access(filename.c_str(), R_OK) == 0)
      {
        _successful= true;
        break;
      }
      else if (waited >= timeout)
      {
        break;
      }

      this_wait= retry * retry / 3 + 1;
      libtest::dream(this_wait, 0);
    }
  }

  Wait(const pid_t &_pid_arg, uint32_t timeout= 6) :
    _successful(false)
  {
    uint32_t waited;
    uint32_t this_wait;
    uint32_t retry;

    for (waited= 0, retry= 1; ; retry++, waited+= this_wait)
    {
      if (kill(_pid_arg, 0) == 0)
      {
        _successful= true;
        break;
      }
      else if (waited >= timeout)
      {
        break;
      }

      this_wait= retry * retry / 3 + 1;
      libtest::dream(this_wait, 0);
    }
  }

  bool successful() const
  {
    return _successful;
  }

private:
  bool _successful;
};

} // namespace libtest
