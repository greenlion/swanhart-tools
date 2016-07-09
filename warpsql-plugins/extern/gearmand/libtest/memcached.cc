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

#include "libtest/common.h"

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

#include <libtest/server.h>
#include <libtest/wait.h>

#include <libtest/memcached.h>

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

namespace libtest {

class Memcached : public libtest::Server
{
  std::string _username;
  std::string _password;

public:
  Memcached(const std::string& host_arg,
            const in_port_t port_arg,
            const bool is_socket_arg,
            const std::string& username_arg,
            const std::string& password_arg) :
    libtest::Server(host_arg, port_arg, 
                    memcached_binary(), false, is_socket_arg),
    _username(username_arg),
    _password(password_arg)
  { }

  Memcached(const std::string& host_arg, const in_port_t port_arg, const bool is_socket_arg) :
    libtest::Server(host_arg, port_arg,
                    memcached_binary(), false, is_socket_arg)
  {
    set_pid_file();
  }

  virtual const char *sasl() const
  {
    return NULL;
  }

  const std::string& password() const
  {
    return _password;
  }

  const std::string& username() const
  {
    return _username;
  }

  bool wait_for_pidfile() const
  {
    Wait wait(pid(), 4);

    return wait.successful();
  }

  bool ping()
  {
    if (out_of_ban_killed())
    {
      return false;
    }

    if (is_socket())
    {
      return _app.check();
    }

    SimpleClient client(_hostname, _port);

    std::string response;
    return client.send_message("version", response);
  }

  const char *name()
  {
    return "memcached";
  };

  const char *executable()
  {
    return memcached_binary();
  }

  bool is_libtool()
  {
    return false;
  }

  virtual void pid_file_option(Application& app, const std::string& arg)
  {
    if (arg.empty() == false)
    {
      app.add_option("-P", arg);
    }
  }

  const char *socket_file_option() const
  {
    return "-s ";
  }

  virtual void port_option(Application& app, in_port_t arg)
  {
    char buffer[30];
    snprintf(buffer, sizeof(buffer), "%d", int(arg));
    app.add_option("-p", buffer); 
  }

  bool has_port_option() const
  {
    return true;
  }

  bool has_socket_file_option() const
  {
    return has_socket();
  }

  void socket_file_option(Application& app, const std::string& socket_arg)
  {
    if (socket_arg.empty() == false)
    {
      app.add_option("-s", socket_arg);
    }
  }

  bool broken_socket_cleanup()
  {
    return true;
  }

  bool is_valgrind() const
  {
    return false;
  }

  // Memcached's pidfile is broken
  bool broken_pid_file()
  {
    return true;
  }

  bool build();
};


#include <sstream>

bool Memcached::build()
{
  if (getuid() == 0 or geteuid() == 0)
  {
    add_option("-u", "root");
  }

  add_option("-l", "localhost");
#ifdef __APPLE__
#else
  add_option("-m", "128");
  add_option("-M");
#endif

  if (sasl())
  {
    add_option(sasl());
  }

  return true;
}

libtest::Server *build_memcached(const std::string& hostname, const in_port_t try_port)
{
  if (has_memcached())
  {
    return new Memcached(hostname, try_port, false);
  }

  return NULL;
}

libtest::Server *build_memcached_socket(const std::string& socket_file, const in_port_t try_port)
{
  if (has_memcached())
  {
    return new Memcached(socket_file, try_port, true);
  }

  return NULL;
}

} // namespace libtest
