/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2012 Data Differential, http://datadifferential.com/
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

/**
 * @file
 * @brief Server worker definitions
 */

#include "gear_config.h"
#include "libgearman-server/common.h"

#include <memory>

static gearman_server_worker_st* gearman_server_worker_create(gearman_server_con_st *con, gearman_server_function_st *function)
{
  gearman_server_worker_st *worker;

  if (Server->free_worker_count > 0)
  {
    worker= Server->free_worker_list;
    GEARMAND_LIST_DEL(Server->free_worker, worker, con_);
  }
  else
  {
    worker= new (std::nothrow) gearman_server_worker_st;
    if (worker == NULL)
    {
      gearmand_merror("new", gearman_server_worker_st, 1);
      return NULL;
    }
  }

  worker->job_count= 0;
  worker->timeout= -1;
  worker->con= con;
  GEARMAND_LIST_ADD(con->worker, worker, con_);
  worker->function= function;

  /* Add worker to the function list, which is a double-linked circular list. */
  if (function->worker_list == NULL)
  {
    function->worker_list= worker;
    worker->function_next= worker;
    worker->function_prev= worker;
  }
  else
  {
    worker->function_next= function->worker_list;
    worker->function_prev= function->worker_list->function_prev;
    worker->function_next->function_prev= worker;
    worker->function_prev->function_next= worker;
  }
  function->worker_count++;

  worker->job_list= NULL;

  return worker;
}

/*
 * Public definitions
 */

gearman_server_worker_st *
gearman_server_worker_add(gearman_server_con_st *con, const char *function_name,
                          size_t function_name_size, long timeout)
{
  gearman_server_function_st *function= gearman_server_function_get(Server, function_name,
                                                                    function_name_size);
  if (function == NULL)
  {
    return NULL;
  }

  gearman_server_worker_st* worker= gearman_server_worker_create(con, function);
  if (worker == NULL)
  {
    return NULL;
  }

  worker->timeout= timeout;

  return worker;
}

void gearman_server_worker_free(gearman_server_worker_st *worker)
{
  /* If the worker was in the middle of a job, requeue it. */
  while (worker->job_list != NULL)
  {
    gearmand_error_t ret= gearman_server_job_queue(worker->job_list);
    if (ret != GEARMAND_SUCCESS)
    {
      gearmand_gerror_warn("gearman_server_job_queue", ret);
    }
  }

  GEARMAND_LIST_DEL(worker->con->worker, worker, con_);

  if (worker == worker->function_next)
  {
    worker->function->worker_list= NULL;
  }
  else
  {
    worker->function_next->function_prev= worker->function_prev;
    worker->function_prev->function_next= worker->function_next;

    if (worker == worker->function->worker_list)
    {
      worker->function->worker_list= worker->function_next;
    }
  }
  worker->function->worker_count--;

  if (Server->free_worker_count < GEARMAND_MAX_FREE_SERVER_WORKER)
  {
    GEARMAND_LIST_ADD(Server->free_worker, worker, con_);
  }
  else
  {
    gearmand_debug("delete");
    delete worker;
  }
}
