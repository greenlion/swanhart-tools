/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2010-2013 Data Differential, http://datadifferential.com/
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

#include "gear_config.h"
#include <libtest/test.hpp>

using namespace libtest;

#include <cassert>
#include <cerrno>
#include <cstdio>
#include <cstdlib>
#include <cstring>

#include <libgearman-1.0/gearman.h>

#include <libtest/test.hpp>

#include "libgearman/interface/task.hpp"
#include "libgearman/client.hpp"
using namespace org::gearmand;

#include <tests/start_worker.h>

#define DEFAULT_WORKER_NAME "burnin"

#define HARD_CODED_EXCEPTION "my test exception"

static gearman_return_t worker_fn(gearman_job_st* job, void*)
{
  if (random() % 10)
  {
    if (random() % 3)
    {
      gearman_return_t ret;
      if (gearman_failed(ret= gearman_job_send_exception(job, test_literal_param(HARD_CODED_EXCEPTION))))
      {
        Error << "gearman_job_send_exception(" << gearman_strerror(ret) << ")";
        return GEARMAN_WORK_ERROR;
      }
      else
      {
        return ret;
      }
    }

    // We will pass back wrong responses from time to time.
    if (random() % 3)
    {
      return GEARMAN_WORK_FAIL;
    }
  }

  return GEARMAN_SUCCESS;
}

struct client_test_st {
  libgearman::Client _client;
  worker_handle_st *handle;

  client_test_st():
    _client(libtest::default_port()),
    handle(NULL)
  {
    gearman_function_t func_arg= gearman_function_create(worker_fn);
    handle= test_worker_start(libtest::default_port(), NULL, DEFAULT_WORKER_NAME, func_arg, NULL, gearman_worker_options_t());
    ASSERT_TRUE(handle->check());
  }

  ~client_test_st()
  {
    delete handle;
  }

  gearman_client_st* client()
  {
    return &_client;
  }
};

struct client_context_st {
  int latch;
  const size_t min_size;
  const size_t max_size;
private:
  size_t _num_tasks;
  size_t _count;
public:
  char *blob;

  client_context_st():
    latch(0),
    min_size(1024),
    max_size(1024 *2),
    _num_tasks(1),
    _count(20),
    blob(NULL)
  {
    blob= (char *)malloc(max_size);
    ASSERT_TRUE(blob);
    memset(blob, 'x', max_size); 
  }

  void next()
  {
    _num_tasks= _num_tasks *2;
    _count= _count *2;
  }

  size_t count()
  {
    return _count;
  }

  size_t num_tasks() const
  {
    return _num_tasks;
  }

  ~client_context_st()
  {
    if (blob)
    {
      free(blob);
    }
  }
};

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

static client_test_st *test_client_context= NULL;

static test_return_t echo_TEST(void*)
{
  gearman_client_st *client= test_client_context->client();
  fatal_assert(client);

  gearman_string_t value= { test_literal_param("This is my echo test") };

  if (GEARMAN_SUCCESS !=  gearman_client_echo(client, gearman_string_param(value)))
  {
    Error << gearman_client_error(client);
  }
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_client_echo(client, gearman_string_param(value)));

  return TEST_SUCCESS;
}

static test_return_t burnin_TEST(void*)
{
  gearman_client_st *client= test_client_context->client();
  fatal_assert(client);

  client_context_st *context= (client_context_st *)gearman_client_context(client);
  fatal_assert(context);

  // This sketchy, don't do this in your own code.
  ASSERT_TRUE(context->num_tasks() > 0);
  std::vector<gearman_task_st> tasks;
  try {
    tasks.resize(context->num_tasks());
  }
  catch (...)
  { }
  ASSERT_EQ(tasks.size(), context->num_tasks());

  ASSERT_EQ(gearman_client_echo(client, test_literal_param("echo_test")), GEARMAN_SUCCESS);

  size_t count= context->count();
  do
  {
    for (uint32_t x= 0; x < context->num_tasks(); x++)
    {
      size_t blob_size= 0;

      if (context->min_size == context->max_size)
      {
        blob_size= context->max_size;
      }
      else
      {
        blob_size= (size_t)rand();

        if (context->max_size > RAND_MAX)
        {
          blob_size*= (size_t)(rand() + 1);
        }

        blob_size= (blob_size % (context->max_size - context->min_size)) + context->min_size;
      }

      gearman_task_st *task_ptr;
      gearman_return_t ret;
      if (context->latch)
      {
        task_ptr= gearman_client_add_task_background(client, &(tasks[x]),
                                                     NULL, DEFAULT_WORKER_NAME, NULL,
                                                     (void *)context->blob, blob_size, &ret);
      }
      else
      {
        task_ptr= gearman_client_add_task(client, &(tasks[x]), NULL,
                                          DEFAULT_WORKER_NAME, NULL, (void *)context->blob, blob_size,
                                          &ret);
      }

      ASSERT_EQ(ret, GEARMAN_SUCCESS);
      ASSERT_TRUE(task_ptr);
    }

    gearman_return_t ret= gearman_client_run_tasks(client);

    do {
      for (uint32_t x= 0; x < context->num_tasks(); x++)
      {
        ASSERT_EQ(GEARMAN_TASK_STATE_FINISHED, tasks[x].impl()->state);
        if (tasks[x].impl()->error_code() == GEARMAN_UNKNOWN_STATE)
        {
          gearman_client_wait(client);
        }
        else if (tasks[x].impl()->error_code() == GEARMAN_WORK_FAIL)
        {
          if (gearman_task_has_exception(&tasks[x]))
          {
            ASSERT_TRUE(gearman_task_has_exception(&tasks[x]));
            gearman_string_t exception_string= gearman_task_exception(&tasks[x]);
            test_strcmp(HARD_CODED_EXCEPTION, gearman_c_str(exception_string));
          }
          else
          {
#if 0
            Error << "error was " << gearman_task_error(&tasks[x]);
#endif
          }
        }
        else if (tasks[x].impl()->error_code() != GEARMAN_SUCCESS)
        {
          ASSERT_EQ(GEARMAN_SUCCESS, tasks[x].impl()->error_code());
        }
        else
        {
          ASSERT_EQ(GEARMAN_SUCCESS, tasks[x].impl()->error_code());
        }
      }
    } while (client->impl()->new_tasks);
    test_zero(client->impl()->new_tasks);

    ASSERT_EQ(ret, GEARMAN_SUCCESS);

    for (uint32_t x= 0; x < context->num_tasks(); x++)
    {
      gearman_task_free(&(tasks[x]));
    }
  } while (--count);

  context->latch++;

  return TEST_SUCCESS;
}

static test_return_t burnin_setup(void* obj)
{
  client_context_st* context= (client_context_st *)obj;
  context->next();

  test_client_context= new client_test_st;

  gearman_client_set_context(test_client_context->client(), context);

  return TEST_SUCCESS;
}

static test_return_t burnin_cleanup(void*)
{
  delete test_client_context;
  test_client_context= NULL;

  return TEST_SUCCESS;
}

/*********************** World functions **************************************/

static void *world_create(server_startup_st& servers, test_return_t&)
{
  ASSERT_TRUE(server_startup(servers, "gearmand", libtest::default_port(), NULL));

  client_context_st *context= new client_context_st;

  return context;
}

static bool world_destroy(void *object)
{
  client_context_st* context= (client_context_st*)object;
  delete context;

  return TEST_SUCCESS;
}

test_st burnin_TESTS[] ={
  {"burnin", 0, burnin_TEST },
  {"burnin", 0, burnin_TEST },
  {0, 0, 0}
};

test_st echo_TESTS[] ={
  {"echo", 0, echo_TEST },
  {0, 0, 0}
};

collection_st collection[] ={
  {"echo", burnin_setup, burnin_cleanup, echo_TESTS },
  {"burnin", burnin_setup, burnin_cleanup, burnin_TESTS },
  {0, 0, 0, 0}
};

void get_world(libtest::Framework *world)
{
  world->collections(collection);
  world->create(world_create);
  world->destroy(world_destroy);
}
