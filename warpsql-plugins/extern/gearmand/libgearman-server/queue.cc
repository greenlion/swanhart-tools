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

#include "gear_config.h"

#include <iostream>
#include <algorithm>
#include <string>

#include "libgearman-server/common.h"
#include <libgearman-server/queue.h>
#include <libgearman-server/plugins/queue/base.h>
#include <libgearman-server/queue.hpp>
#include <libgearman-server/log.h>

#include <assert.h>

gearmand_error_t gearman_queue_add(gearman_server_st *server,
                                   const char *unique,
                                   size_t unique_size,
                                   const char *function_name,
                                   size_t function_name_size,
                                   const void *data,
                                   size_t data_size,
                                   gearman_job_priority_t priority,
                                   int64_t when)
{
  assert(server->state.queue_startup == false);
  gearmand_error_t ret;
  if (server->queue_version == QUEUE_VERSION_NONE)
  {
    return GEARMAND_SUCCESS;
  }
  else if (server->queue_version == QUEUE_VERSION_FUNCTION)
  {
    assert(server->queue.functions->_add_fn);
    ret= (*(server->queue.functions->_add_fn))(server,
                                               (void *)server->queue.functions->_context,
                                               unique, unique_size,
                                               function_name,
                                               function_name_size,
                                               data, data_size, priority, 
                                               when);
  }
  else
  {
    assert(server->queue.object);
    ret= server->queue.object->store(server,
                                     unique, unique_size,
                                     function_name,
                                     function_name_size,
                                     data, data_size, priority, 
                                     when);
  }

  if (gearmand_success(ret))
  {
    ret= gearman_queue_flush(server);
  }

  return ret;
}

gearmand_error_t gearman_queue_flush(gearman_server_st *server)
{
  if (server->queue_version != QUEUE_VERSION_NONE)
  {
    if (server->queue_version == QUEUE_VERSION_FUNCTION)
    {
      assert(server->queue.functions->_flush_fn);
      return (*(server->queue.functions->_flush_fn))(server, (void *)server->queue.functions->_context);
    }

    assert(server->queue.object);
    return server->queue.object->flush(server);
  }

  return GEARMAND_SUCCESS;
}

gearmand_error_t gearman_queue_done(gearman_server_st *server,
                                    const char *unique,
                                    size_t unique_size,
                                    const char *function_name,
                                    size_t function_name_size)
{
  if (server->queue_version == QUEUE_VERSION_NONE)
  {
    return GEARMAND_SUCCESS;
  }
  else if (server->queue_version == QUEUE_VERSION_FUNCTION)
  {
    assert(server->queue.functions->_done_fn);
    return (*(server->queue.functions->_done_fn))(server,
                                                  (void *)server->queue.functions->_context,
                                                  unique, unique_size,
                                                  function_name,
                                                  function_name_size);
  }
  else
  {
    assert(server->queue.object);
    return server->queue.object->done(server,
                                      unique, unique_size,
                                      function_name,
                                      function_name_size);
  }
}

void gearman_server_save_job(gearman_server_st& server,
                             const gearman_server_job_st* server_job)
{
  if (server.queue_version == QUEUE_VERSION_CLASS)
  {
    assert(server.queue.object);
    server.queue.object->save_job(server, server_job);
  }
}

void gearman_server_set_queue(gearman_server_st& server,
                              void *context,
                              gearman_queue_add_fn *add,
                              gearman_queue_flush_fn *flush,
                              gearman_queue_done_fn *done,
                              gearman_queue_replay_fn *replay)
{
  delete server.queue.functions;
  server.queue.functions= NULL;
  delete server.queue.object;
  server.queue.object= NULL;

  if (add)
  {
    server.queue_version= QUEUE_VERSION_FUNCTION;
    server.queue.functions= new queue_st();
    if (server.queue.functions)
    {
      server.queue.functions->_context= context;
      server.queue.functions->_add_fn= add;
      server.queue.functions->_flush_fn= flush;
      server.queue.functions->_done_fn= done;
      server.queue.functions->_replay_fn= replay;
    }
    assert(server.queue.functions);
  }
  else
  {
    server.queue_version= QUEUE_VERSION_NONE;
  }
}

void gearman_server_set_queue(gearman_server_st& server,
                              gearmand::queue::Context* context)
{
  delete server.queue.functions;
  server.queue.functions= NULL;
  delete server.queue.object;
  server.queue.object= NULL;
  assert(context);
  {
    server.queue_version= QUEUE_VERSION_CLASS;
    server.queue.object= context;
  }
}

namespace gearmand {

namespace queue {

plugins::Queue::vector all_queue_modules;

void add(plugins::Queue* arg)
{
  all_queue_modules.push_back(arg);
}

gearmand_error_t initialize(gearmand_st *, std::string name)
{
  bool launched= false;

  if (name.empty())
  {
    return GEARMAND_SUCCESS;
  }

  std::transform(name.begin(), name.end(), name.begin(), ::tolower);

  for (plugins::Queue::vector::iterator iter= all_queue_modules.begin();
       iter != all_queue_modules.end();
       ++iter)
  {
    if ((*iter)->compare(name) == 0)
    {
      if (launched)
      {
        return gearmand_gerror("Attempt to initialize multiple queues", GEARMAND_UNKNOWN_OPTION);
      }

      gearmand_error_t rc;
      if (gearmand_failed(rc= (*iter)->initialize()))
      {
        return gearmand_log_gerror(GEARMAN_DEFAULT_LOG_PARAM, rc,
                                   "Failed to initialize %s: %s", name.c_str(), (*iter)->error_string().c_str());
      }

      launched= true;
    }
  }

  if (launched == false)
  {
    return gearmand_log_gerror(GEARMAN_DEFAULT_LOG_PARAM, GEARMAND_UNKNOWN_OPTION, "Unknown queue %s", name.c_str());
  }

  return GEARMAND_SUCCESS;
}

} // namespace queue
} // namespace gearmand
