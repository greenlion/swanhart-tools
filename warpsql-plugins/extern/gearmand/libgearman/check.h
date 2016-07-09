/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2013 Data Differential, http://datadifferential.com/
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

class Check {
public:
  virtual gearman_return_t success(gearman_connection_st*)= 0;

  virtual ~Check() {};
};

class EchoCheck : public Check {
public:
  EchoCheck(gearman_universal_st& universal_,
            const void *workload_, const size_t workload_size_);

  gearman_return_t success(gearman_connection_st* con);

private:
  gearman_universal_st& _universal;
  const void *_workload;
  const size_t _workload_size;
};

class OptionCheck : public Check {
public:
  OptionCheck(gearman_universal_st& universal_, gearman_string_t& option_):
    _universal(universal_),
    _option(option_)
  {
  }

  const gearman_string_t& option() const
  {
    return _option;
  }

  gearman_return_t success(gearman_connection_st* con);

private:
    gearman_universal_st& _universal;
    gearman_string_t& _option;
};

class CancelCheck : public Check {
public:
  CancelCheck(gearman_universal_st& universal_):
    _universal(universal_)
    {
    }

  gearman_return_t success(gearman_connection_st* con);

private:
  gearman_universal_st& _universal;
};

