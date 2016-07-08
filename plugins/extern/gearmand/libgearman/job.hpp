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

#pragma once

#include "libgearman/interface/worker.hpp"

class Job
{
public:
  Job(gearman_job_st* shell_, Worker& worker_);
  ~Job();

  gearman_job_st* shell()
  {
    return _shell;
  }

  struct Options {
    bool assigned_in_use;
    bool work_in_use;
    bool finished;

    Options():
      assigned_in_use(false),
      work_in_use(false),
      finished(false)
    { }
  } options;

  bool finished() const
  {
    return options.finished;
  }

  void finished(const bool finished_)
  {
    options.finished= finished_;
  }

  Worker& _worker;
  gearman_client_st* _client;
  Job *next;
  Job *prev;
  gearman_connection_st *con;
  gearman_packet_st assigned;
  gearman_packet_st work;
  struct gearman_job_reducer_st *reducer;
  gearman_return_t _error_code;

  gearman_universal_st& universal()
  {
    return _worker.universal;
  }

  gearman_client_st* client();

  gearman_universal_st& universal() const
  {
    return _worker.universal;
  }

  gearman_return_t error_code() const
  {
    return universal().error_code();
  }

private:
  gearman_job_st* _shell;
  gearman_job_st _owned_shell;
};
