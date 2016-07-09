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

#include <libtest/blobslap_worker.h>

#include <cassert>
#include <cerrno>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <iostream>
#include <signal.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <unistd.h>

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

namespace libtest {

class BlobslapWorker : public Server
{
private:
public:
  BlobslapWorker(in_port_t port_arg) :
    Server("localhost", port_arg, "benchmark/blobslap_worker", true)
  { 
    set_pid_file();
  }

  pid_t get_pid(bool error_is_ok)
  {
    if (pid_file().empty())
    {
      Error << "pid_file was empty";
      return -1;
    }

    Wait wait(pid_file(), 0);

    if (error_is_ok and not wait.successful())
    {
      Error << "Pidfile was not found:" << pid_file();
      return -1;
    }

    std::stringstream error_message;
    pid_t ret= get_pid_from_file(pid_file(), error_message);

    if (error_is_ok and is_pid_valid(ret) == false)
    {
      Error << error_message.str();
    }

    return ret;
  }

  bool ping()
  {
    if (pid_file().empty())
    {
      Error << "No pid file available";
      return false;
    }

    Wait wait(pid_file(), 0);
    if (not wait.successful())
    {
      Error << "Pidfile was not found:" << pid_file();
      return false;
    }

    std::stringstream error_message;
    pid_t local_pid= get_pid_from_file(pid_file(), error_message);
    if (is_pid_valid(local_pid) == false)
    {
      Error << error_message.str();
      return false;
    }

    // Use kill to determine is the process exist
    if (::kill(local_pid, 0) == 0)
    {
      return true;
    }

    return false;
  }

  const char *name()
  {
    return "blobslap_worker";
  };

  bool has_port_option() const
  {
    return true;
  }

  bool has_log_file_option() const
  {
    return true;
  }

  bool is_libtool()
  {
    return true;
  }

  bool build();
};


#include <sstream>

bool BlobslapWorker::build()
{
  return true;
}

Server *build_blobslap_worker(in_port_t try_port)
{
  return new BlobslapWorker(try_port);
}

} // namespace libtest
