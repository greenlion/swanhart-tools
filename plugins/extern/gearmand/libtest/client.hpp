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

#include "libgearman/ssl.h"

#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wunknown-pragmas"
#pragma GCC diagnostic ignored "-Wpragmas"
#pragma GCC diagnostic ignored "-Wunused-private-field"

namespace libtest {

class SimpleClient {
public:
  SimpleClient(const std::string& hostname_, in_port_t port_);
  ~SimpleClient();

  bool send_data(const libtest::vchar_t&, libtest::vchar_t&);
  bool send_message(const std::string&);
  bool send_message(const std::string&, std::string&);
  bool response(std::string&);
  bool response(libtest::vchar_t&);

private:
  bool is_valid();
public:

  const std::string& error() const
  {
    return _error;
  }

  bool is_error() const
  {
    return _error.size() ? true : false;
  }

  const char* error_file() const
  {
    return _error_file;
  }

  int error_line() const
  {
    return _error_line;
  }

private:
  void free_addrinfo()
  {
    freeaddrinfo(_ai);
    _ai= NULL;
  }

private: // Methods
  void error(const char* file, int line, const std::string& error_);
  void close_socket();
  bool instance_connect();
  struct addrinfo* lookup();
  bool message(const char* ptr, const size_t len);
  bool ready(int event_);
  void init_ssl();

private:
  bool _is_connected;
  bool _is_ssl;
  std::string _hostname;
  in_port_t _port;
  int sock_fd;
  std::string _error;
  const char* _error_file;
  int _error_line;
  int requested_message;
  SSL_CTX* _ctx_ssl;
  SSL* _ssl;
  struct addrinfo *_ai;
};

} // namespace libtest
#pragma GCC diagnostic pop
