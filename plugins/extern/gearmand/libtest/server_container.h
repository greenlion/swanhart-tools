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

#include <cassert>
#include <cstdio>
#include <cstring>
#include <netdb.h>
#include <netinet/in.h>
#include <string>
#include <unistd.h>
#include <vector>

namespace libtest {

class server_startup_st
{
private:
  uint64_t _magic;
  std::string server_list;
  bool _socket;
  bool _sasl;
  std::string _username;
  std::string _password;

public:

  uint8_t udp;
private:
  std::vector<Server *> servers;

public:
  server_startup_st();
  ~server_startup_st();

  bool validate();

  bool start_socket_server(const std::string& server_type, const in_port_t try_port, const char *argv[]);
  bool start_server(const std::string& server_type, const in_port_t try_port, const char *argv[]);

  uint32_t count() const
  {
    return uint32_t(servers.size());
  }

  void restart();

  std::string option_string() const;

  const std::string& password() const
  {
    return _password;
  }

  const std::string& username() const
  {
    return _username;
  }

  bool socket()
  {
    return _socket;
  }

  bool sasl()
  {
    return _sasl;
  }

  void set_socket()
  {
    _socket= true;
  }

  void set_sasl(const std::string& username_arg, const std::string& password_arg)
  {
    _sasl= true;
    _username= username_arg;
    _password= password_arg;
  }


  // Just remove everything after shutdown
  void clear();

  bool shutdown();
  bool shutdown(uint32_t number_of_host);

  bool check() const;

  void push_server(Server *);
  Server* last();
  Server *pop_server();

  Server* create(const std::string& server_type, in_port_t try_port, const bool is_socket);

  unsigned long int servers_to_run() const
  {
    return _servers_to_run;
  }

  void set_servers_to_run(unsigned long int arg)
  {
    _servers_to_run= arg;
  }

private:
  bool _start_server(const bool is_socket,
                     const std::string& server_type,
                     const in_port_t try_port,
                     const char *argv[]);

private:
  unsigned long int _servers_to_run;
};

bool server_startup(server_startup_st&, const std::string&, in_port_t try_port, const char *argv[]);

} // namespace libtest
