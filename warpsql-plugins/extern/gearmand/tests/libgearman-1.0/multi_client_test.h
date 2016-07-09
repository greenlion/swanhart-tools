/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2013 Keyur Govande
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
 * This client takes in 2 gearmand server definitions and support the 4
 * combinations possible of of gearman_client(s).
 */


#pragma once

#include <vector>

#define ARRAY_LENGTH 4
struct multi_client_test_st
{
  // index 0: no connected clients index 1: only connected to server 1 index 2:
  // only connected to server 2 index 3: connected to both servers
  gearman_client_st* _client_array[ARRAY_LENGTH];
  gearman_client_st* _clone_array[ARRAY_LENGTH];

  multi_client_test_st(server_startup_st& container_, int timeout= 0) :
    _server_container(container_)
  { 
    bool has_failed= false;
    for (size_t x= 0; (x < ARRAY_LENGTH) and has_failed == false; x++)
    {
      _clone_array[x]= NULL;
      _client_array[x]= gearman_client_create(NULL);
      has_failed= (_client_array[x] == NULL);
      if (has_failed == false and (timeout > 0))
      {
        gearman_client_set_timeout(_client_array[x], timeout);
      }
    }

    if (has_failed)
    {
      FATAL("new gearman_client_create() failed");
    }
  }

  ~multi_client_test_st()
  {
    for (size_t x= 0; x < ARRAY_LENGTH; ++x)
    {
      if (_client_array[x])
      {
        gearman_client_free(_client_array[x]);
        _client_array[x]= NULL;
      }
      if (_clone_array[x])
      {
        gearman_client_free(_clone_array[x]);
        _clone_array[x]= NULL;
      }
    }
  }

  void add_server(const char* hostname_1, in_port_t port_arg_1, const char* hostname_2, in_port_t port_arg_2)
  {
    gearman_client_add_server(_client_array[1], hostname_1, port_arg_1);
    gearman_client_add_server(_client_array[2], hostname_2, port_arg_2);
    gearman_client_add_server(_client_array[3], hostname_1, port_arg_1);
    gearman_client_add_server(_client_array[3], hostname_2, port_arg_2);
  }

  server_startup_st& server_container(void) const
  {
    return _server_container;
  }

  void push_port(in_port_t arg)
  {
    _ports.push_back(arg);
  }

  in_port_t port(size_t pos)
  {
    FATAL_IF(pos >= _ports.size());
    return _ports[pos];
  }


#define CLIENT_METHODS(NAME, I) \
  gearman_client_st* NAME##_client() \
  { \
    return client(I); \
  } \
  void reset_##NAME##_clone() \
  { \
    reset_clone(I); \
  } \
  void clear_##NAME##_clone() \
  { \
    clear_clone(I); \
  }

  CLIENT_METHODS(unconnected, 0);
  CLIENT_METHODS(connected_to_1, 1);
  CLIENT_METHODS(connected_to_2, 2);
  CLIENT_METHODS(connected_to_both, 3);

private:
  gearman_client_st *client(int index)
  {
    if (_clone_array[index] == NULL)
    {
      _clone_array[index]= gearman_client_clone(NULL, _client_array[index]);
    }

    return _clone_array[index];

  }

  void clear_clone(int index)
  {
    if (_clone_array[index])
    {
      gearman_client_free(_clone_array[index]);
    }

    _clone_array[index]= gearman_client_create(NULL);
  }

  void reset_clone(int index)
  {
    if (_clone_array[index])
    {
      gearman_client_free(_clone_array[index]);
    }

    _clone_array[index]= gearman_client_clone(NULL, _client_array[index]);
  }

private:
  server_startup_st& _server_container;
  std::vector<in_port_t> _ports;
};
