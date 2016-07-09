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

/*
  @note This header is internal, and should not be used by external programs.
*/

#pragma once

#include <stdexcept>
#include <libgearman-1.0/gearman.h>
#include <stdio.h>

namespace {

inline void all_client_PRINTER(const char *line, gearman_verbose_t verbose, void*)
{
  fprintf(stderr, "%s:%d %s(%s)\n", __FILE__, __LINE__, gearman_verbose_name(verbose), line);
}

}

namespace org { namespace gearmand { namespace libgearman {

class Client {
public:
  Client()
  {
    _client= gearman_client_create(NULL);

    if (_client == NULL)
    {
      throw std::runtime_error("gearman_client_create() failed");
    }
    enable_logging();
    enable_ssl();
  }

  Client(const gearman_client_st* arg)
  {
    _client= gearman_client_clone(NULL, arg);

    if (_client == NULL)
    {
      throw std::runtime_error("gearman_client_create() failed");
    }
    enable_logging();
    enable_ssl();
  }

  Client(in_port_t arg)
  {
    _client= gearman_client_create(NULL);

    if (_client == NULL)
    {
      throw std::runtime_error("gearman_client_create() failed");
    }
    enable_logging();
    enable_ssl();
    gearman_client_add_server(_client, "localhost", arg);
  }

  gearman_client_st* operator&() const
  { 
    return _client;
  }

  gearman_client_st* operator->() const
  { 
    return _client;
  }

  ~Client()
  {
    gearman_client_free(_client);
  }

  void enable_logging()
  { 
    if (getenv("YATL_CLIENT_LOGGING"))
    {
      gearman_log_fn *func= all_client_PRINTER;
      gearman_client_set_log_fn(_client, func, NULL, GEARMAN_VERBOSE_ERROR);
    }
  }

  void enable_ssl()
  { 
    if (getenv("GEARMAND_CA_CERTIFICATE"))
    {
      gearman_client_add_options(_client, GEARMAN_CLIENT_SSL);
    }
  }

private:
  gearman_client_st *_client;

};

} /* namespace libgearman */ } /* namespace gearmand */ } /* namespace org */ 
