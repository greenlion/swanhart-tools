/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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

#include <netdb.h>
#include <assert.h>

struct gearmand_port_st
{
  char port[NI_MAXSERV];
  uint32_t listen_count;

private:
  gearmand_connection_add_fn *_add_fn;
  gearmand_connection_remove_fn *_remove_fn;

public:
  int *listen_fd;
  struct event *listen_event;

  gearmand_port_st() :
    listen_count(0),
    _add_fn(NULL),
    listen_fd(NULL),
    listen_event(NULL)
  {
    port[0]= 0;
  }

  ~gearmand_port_st()
  {
    if (listen_fd)
    {
      free(listen_fd);
    }

    if (listen_event)
    {
      free(listen_event);
    }
  }

  gearmand_error_t add_fn(gearman_server_con_st* con)
  {
    assert(_add_fn);
    return (*_add_fn)(con);
  }

  gearmand_error_t remove_fn(gearman_server_con_st* con)
  {
    assert(_remove_fn);
    return (*_remove_fn)(con);
  }

  void add_fn(gearmand_connection_add_fn* add_fn_)
  {
    _add_fn= add_fn_;
  }

  void remove_fn(gearmand_connection_remove_fn* remove_fn_)
  {
    _remove_fn= remove_fn_;
  }
};

