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

#include <libtest/cmdline.h>

#include <cassert>
#include <cstdio>
#include <cstring>
#include <netdb.h>
#include <netinet/in.h>
#include <string>
#include <unistd.h>
#include <vector>

namespace libtest {

struct Server {
private:
  typedef std::vector< std::pair<std::string, std::string> > Options;

private:
  uint64_t _magic;
  bool _is_socket;
  std::string _socket;
  std::string _sasl;
  std::string _pid_file;
  std::string _log_file;
  std::string _base_command; // executable command which include libtool, valgrind, gdb, etc
  std::string _running; // Current string being used for system()

protected:
  in_port_t _port;
  std::string _hostname;
  std::string _extra_args;

public:
  Server(const std::string& hostname, const in_port_t port_arg,
         const std::string& executable, const bool _is_libtool,
         const bool is_socket_arg= false);

  virtual ~Server();

  virtual const char *name()= 0;
  virtual bool is_libtool()= 0;

  virtual bool has_socket_file_option() const
  {
    return false;
  }

  virtual void socket_file_option(Application& app, const std::string& socket_arg)
  {
    if (socket_arg.empty() == false)
    {
      std::string buffer("--socket=");
      buffer+= socket_arg;
      app.add_option(buffer);
    }
  }

  virtual bool has_log_file_option() const
  {
    return false;
  }

  virtual void log_file_option(Application& app, const std::string& arg)
  {
    if (arg.empty() == false)
    {
      std::string buffer("--log-file=");
      buffer+= arg;
      app.add_option(buffer);
    }
  }

  virtual void pid_file_option(Application& app, const std::string& arg)
  {
    if (arg.empty() == false)
    {
      std::string buffer("--pid-file=");
      buffer+= arg;
      app.add_option(buffer);
    }
  }

  virtual bool has_port_option() const
  {
    return false;
  }

  virtual void port_option(Application& app, in_port_t arg)
  {
    if (arg > 0)
    {
      char buffer[1024];
      snprintf(buffer, sizeof(buffer), "--port=%d", int(arg));
      app.add_option(buffer);
    }
  }

  virtual bool broken_socket_cleanup()
  {
    return false;
  }

  virtual bool broken_pid_file()
  {
    return false;
  }

  const std::string& pid_file() const
  {
    return _pid_file;
  }

  const std::string& base_command() const
  {
    return _base_command;
  }

  const std::string& log_file() const
  {
    return _log_file;
  }

  const std::string& hostname() const
  {
    return _hostname;
  }

  const std::string& socket() const
  {
    return _socket;
  }

  bool has_socket() const
  {
    return _is_socket;
  }

  bool cycle();

  virtual bool ping()= 0;

  bool init(const char *argv[]);
  virtual bool build()= 0;

  void add_option(const std::string&);
  void add_option(const std::string&, const std::string&);

  in_port_t port() const
  {
    return _port;
  }

  bool has_port() const
  {
    return (_port != 0);
  }

  virtual bool has_syslog() const
  {
    return false;
  }

  // Reset a server if another process has killed the server
  void reset()
  {
    _pid_file.clear();
    _log_file.clear();
  }

  pid_t pid() const;

  bool has_pid() const;

  virtual bool has_pid_file() const
  {
    return true;
  }

  const std::string& error()
  {
    return _error;
  }

  void error(const char* file_, int line_, const std::string& error_)
  {
    _error_file= file_;
    _error_line= line_;
    _error= error_;
  }

  const char* error_file() const
  {
    return _error_file;
  }

  int error_line() const
  {
    return _error_line;
  }

  void error(std::string arg)
  {
    _error= arg;
  }

  void reset_error()
  {
    _error.clear();
  }

  virtual bool wait_for_pidfile() const;

  bool check_pid(pid_t pid_arg) const
  {
    return (pid_arg > 1);
  }

  bool is_socket() const
  {
    return _is_socket;
  }

  const std::string running() const
  {
    return _running;
  }

  bool check();

  std::string log_and_pid();

  bool kill();
  bool start();
  bool command(libtest::Application& app);

  bool validate();

  void out_of_ban_killed(bool arg)
  {
    out_of_ban_killed_= arg;
  }

  bool out_of_ban_killed()
  {
    return out_of_ban_killed_;
  }

  void timeout(uint32_t timeout_)
  {
    _timeout= timeout_;
  }

protected:
  bool set_pid_file();
  Options _options;
  Application _app;

private:
  bool is_helgrind() const;
  virtual bool is_valgrind() const;
  bool is_debug() const;
  bool set_log_file();
  bool set_socket_file();
  void reset_pid();
  bool out_of_ban_killed_;
  bool args(Application&);

  std::string _error;
  const char* _error_file;
  int _error_line;
  uint32_t _timeout; // This number should be high enough for valgrind startup (which is slow)
};

std::ostream& operator<<(std::ostream& output, const libtest::Server &arg);

} // namespace libtest


