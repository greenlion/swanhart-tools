/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
 *  Copyright (C) 2008 Brian Aker, Eric Day
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

struct client_test_st;
#include "tests/start_worker.h"
#include "libgearman/client.hpp"

struct client_test_st
{
  org::gearmand::libgearman::Client _client;
  gearman_client_st *_clone;
  std::vector<worker_handle_st *> workers;
  const char *_worker_name;
  const char *_session_namespace;

  client_test_st() :
    _clone(NULL),
    _worker_name(NULL),
    _session_namespace(NULL)
  { 
  }

  ~client_test_st()
  {
    clear();

    if (_clone)
    {
      gearman_client_free(_clone);
      _clone= NULL;
    }
  }

  void clear()
  {
    for (std::vector<worker_handle_st *>::iterator iter= workers.begin(); iter != workers.end(); ++iter)
    {
      delete *iter;
    }
    workers.clear();
    set_worker_name(NULL);
    session_namespace(NULL);
    reset_clone();
  }

  void push(worker_handle_st *arg)
  {
    workers.push_back(arg);
  }

  void add_server(const char* hostname, in_port_t port_arg)
  {
    ASSERT_EQ(GEARMAN_SUCCESS, gearman_client_add_server(&_client, hostname, port_arg));
  }

  const char *worker_name() const
  {
    return _worker_name;
  }

  void log_fn(gearman_log_fn *function, void *context, gearman_verbose_t verbose)
  {
    gearman_client_set_log_fn(&_client, function, context, verbose);
  }

  void set_worker_name(const char *arg)
  {
    _worker_name= arg;
  }

  void session_namespace(const char *arg)
  {
    _session_namespace= arg;
  }

  const char* session_namespace()
  {
    return _session_namespace;
  }

  gearman_client_st *client()
  {
    if (_clone == NULL)
    {
      _clone= gearman_client_clone(NULL, &_client);
    }

    return _clone;
  }

  void clear_clone()
  {
    if (_clone)
    {
      gearman_client_free(_clone);
    }
    _clone= gearman_client_create(NULL);
  }

  void reset_clone()
  {
    if (_clone)
    {
      gearman_client_free(_clone);
    }
    _clone= gearman_client_clone(NULL, &_client);
  }
};
