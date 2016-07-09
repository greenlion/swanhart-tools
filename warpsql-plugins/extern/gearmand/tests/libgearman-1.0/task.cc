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

#include "gear_config.h"
#include <libtest/test.hpp>

using namespace libtest;

#include <libgearman-1.0/gearman.h>
#include "libgearman/actions.hpp"
#include "tests/task.h"

#include "tests/workers/v2/call_exception.h"
#include "tests/start_worker.h"

#include "libgearman/client.hpp"
#include "libgearman/worker.hpp"
using namespace org::gearmand;

#include <cassert>

#include "libgearman/interface/task.hpp"

#include <iostream>
#include <memory>

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

test_return_t gearman_client_add_task_status_by_unique_NOT_FOUND_TEST(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  const char* unique_key= __func__;

  fatal_assert(worker_function);

  gearman_return_t ret;

  gearman_task_st* unique_task= gearman_client_add_task_status_by_unique(client,
                                                                         NULL, // context
                                                                         unique_key,
                                                                         &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(unique_task);
  ASSERT_TRUE(gearman_task_unique(unique_task));
  ASSERT_EQ(strlen(unique_key), strlen(gearman_task_unique(unique_task)));

  libtest::dream(1, 0);
  test_false(gearman_task_is_running(unique_task));
  test_false(gearman_task_is_known(unique_task));

  gearman_task_free(unique_task);

  return TEST_SUCCESS;
}

test_return_t gearman_client_add_task_status_by_unique_TEST(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  const char* unique_key= __func__;

  fatal_assert(worker_function);

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task(client,
                                                 NULL, // preallocated task
                                                 NULL, // context 
                                                 worker_function, // function
                                                 unique_key, // unique
                                                 test_literal_param("sleep"), // workload
                                                 &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(task);
  ASSERT_TRUE(gearman_task_unique(task));
  ASSERT_EQ(strlen(unique_key), strlen(gearman_task_unique(task)));

  do
  {
    // just for async IO
    size_t limit= 10;
    do {
      if (--limit)
      {
        break;
      }
      ret= gearman_client_run_tasks(client);
    } while (gearman_continue(ret));

    if (limit)
    {
      ASSERT_EQ(ret, GEARMAN_SUCCESS);
    }

    // If the task has been built to be freed, we won't have it to test
    if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
    {
      return TEST_SUCCESS;
    }

  } while (gearman_task_is_running(task));

  gearman_task_st* unique_task= gearman_client_add_task_status_by_unique(client,
                                                                         NULL, // context
                                                                         unique_key,
                                                                         &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(unique_task);
  ASSERT_TRUE(gearman_task_unique(unique_task));
  ASSERT_EQ(strlen(unique_key), strlen(gearman_task_unique(unique_task)));

  gearman_task_free(unique_task);
  gearman_task_free(task);

  return TEST_SUCCESS;
}

test_return_t gearman_client_add_task_test(void *object)
{
  SKIP_IF(HAVE_UUID_UUID_H != 1);

  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  fatal_assert(worker_function);

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task(client,
                                                 NULL, // preallocated task
                                                 NULL, // context 
                                                 worker_function, // function
                                                 NULL, // unique
                                                 test_literal_param("dog"), // workload
                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);
  ASSERT_TRUE(gearman_task_unique(task));
  ASSERT_EQ(size_t(GEARMAN_MAX_UUID_SIZE), strlen(gearman_task_unique(task)));

  do
  {

    // just for async IO
    do {
      ret= gearman_client_run_tasks(client);
    } while (gearman_continue(ret));

    ASSERT_EQ(ret, GEARMAN_SUCCESS);

    // If the task has been built to be freed, we won't have it to test
    if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
    {
      return TEST_SUCCESS;
    }

  } while (gearman_task_is_running(task));

  gearman_task_free(task);

  return TEST_SUCCESS;
}


test_return_t gearman_client_add_task_test_fail(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  fatal_assert(worker_function);

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task(client, NULL, NULL,
                                                 worker_function, NULL,
                                                 test_literal_param("fail"),
                                                 &ret);
  ASSERT_EQ(GEARMAN_SUCCESS,ret);
  ASSERT_TRUE(task);
  ASSERT_TRUE(task->impl()->client);

  do {
    ret= gearman_client_run_tasks(client);
  } while (gearman_continue(ret));

  ASSERT_EQ(ret, GEARMAN_SUCCESS);

  // If the task has been free() then we can't check anything about it
  if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
  {
    return TEST_SUCCESS;
  }

  ASSERT_TRUE(task->impl()->client);

  ASSERT_EQ(gearman_task_return(task), GEARMAN_WORK_FAIL);

  ASSERT_TRUE(task->impl()->client);
  gearman_task_free(task);

  return TEST_SUCCESS;
}

test_return_t gearman_client_add_task_test_bad_workload(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  fatal_assert(worker_function);

  gearman_return_t ret;

  // We test for pointer with zero size
  gearman_task_st *task= gearman_client_add_task(client, NULL, NULL,
                                                 worker_function, NULL, "fail", 0,
                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_INVALID_ARGUMENT);
  test_false(task);

  // We test for NULL with size
  task= gearman_client_add_task(client, NULL, NULL,
                                worker_function, NULL, NULL, 5,
                                &ret);
  ASSERT_EQ(ret, GEARMAN_INVALID_ARGUMENT);
  test_false(task);

  return TEST_SUCCESS;
}

static gearman_return_t gearman_exception_test_function(gearman_task_st *task)
{
  bool *success= (bool *)gearman_task_context(task);
  if (not success)
  {
    return GEARMAN_WORK_FAIL;
  }

  *success= true;
  return GEARMAN_SUCCESS;
}

test_return_t gearman_client_add_task_exception(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  fatal_assert(worker_function);

  if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
  {
    return TEST_SKIPPED;
  }

  gearman_return_t ret;

  ASSERT_TRUE(gearman_client_set_server_option(client, test_literal_param("exceptions")));

  gearman_client_set_exception_fn(client, gearman_exception_test_function);

  bool exception_success= false;
  gearman_task_st *task= gearman_client_add_task(client, NULL, &exception_success,
                                                 worker_function, NULL,
                                                 test_literal_param("exception"),
                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);

  do {
    ret= gearman_client_run_tasks(client);
  } while (gearman_continue(ret));
  ASSERT_EQ(ret, GEARMAN_SUCCESS);

  ASSERT_TRUE(exception_success);

  gearman_client_set_exception_fn(client, NULL);

  // If the task has been free() then we can't check anything about it
  if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
  {
    return TEST_SUCCESS;
  }

  fatal_assert(client->impl()->task_list);
  gearman_task_free(task);

  return TEST_SUCCESS;
}

static gearman_return_t check_exception_function(gearman_task_st *task)
{
  std::string *exception_str= (std::string *)gearman_task_context(task);
  if (exception_str == NULL)
  {
    Error << "Programmer error, null std::string passed";
    return GEARMAN_WORK_FAIL;
  }

  assert(task);
#if 0
  exception_str->append(task->recv->arg[1], task->recv->arg_size[1]);
#endif

  return GEARMAN_SUCCESS;
}

test_return_t gearman_client_add_task_check_exception_TEST(void*)
{
  libgearman::Client client(libtest::default_port());

  if (gearman_client_has_option(&client, GEARMAN_CLIENT_FREE_TASKS))
  {
    return TEST_SKIPPED;
  }

  gearman_client_set_exception_fn(&client, check_exception_function);

  gearman_function_t func= gearman_function_create_v2(call_exception_WORKER);
  std::unique_ptr<worker_handle_st> call_exception_worker(test_worker_start(libtest::default_port(), NULL,
                                                                            __func__,
                                                                            func,
                                                                            NULL, gearman_worker_options_t()));

  std::string exception_string;
  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task(&client, NULL, &exception_string,
                                                 __func__, NULL,
                                                 test_literal_param("exception test"),
                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);

  do {
    ret= gearman_client_run_tasks(&client);
  } while (gearman_continue(ret));
  ASSERT_EQ(ret, GEARMAN_SUCCESS);

  // This is a defect, from what I understand we should be passing along the
  // exception.
#if 0
  ASSERT_TRUE(exception_string.compare("exception test") == 0);
#endif

  // If the task has been free() then we can't check anything about it
  if (gearman_client_has_option(&client, GEARMAN_CLIENT_FREE_TASKS))
  {
    return TEST_SUCCESS;
  }

  fatal_assert(&client->impl()->task_list);
  gearman_task_free(task);

  return TEST_SUCCESS;
}

test_return_t gearman_client_add_task_background_test(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  fatal_assert(worker_function);

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task_background(client, NULL, NULL,
                                                            worker_function, NULL, "dog", 3,
                                                            &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);

  do
  {

    // just for async IO
    do {
      ret= gearman_client_run_tasks(client);
    } while (gearman_continue(ret));

    ASSERT_EQ(ret, GEARMAN_SUCCESS);

    // If the task has been built to be freed, we won't have it to test
    if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
    {
      return TEST_SUCCESS;
    }

  } while (gearman_task_is_running(task));

  fatal_assert(client->impl()->task_list);
  gearman_task_free(task);

  return TEST_SUCCESS;
}

test_return_t gearman_client_add_task_high_background_test(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  fatal_assert(worker_function);

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task_high_background(client, NULL, NULL,
                                                                 worker_function, NULL, "dog", 3,
                                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);

  do
  {

    // just for async IO
    do {
      ret= gearman_client_run_tasks(client);
    } while (gearman_continue(ret));
    ASSERT_EQ(ret, GEARMAN_SUCCESS);

    // If the task has been built to be freed, we won't have it to test
    if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
    {
      return TEST_SUCCESS;
    }
  } while (gearman_task_is_running(task));

  gearman_task_free(task);

  return TEST_SUCCESS;
}

test_return_t gearman_client_add_task_low_background_test(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  fatal_assert(worker_function);

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task_high_background(client, NULL, NULL,
                                                                 worker_function, NULL, "dog", 3,
                                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);

  do
  {

    // just for async IO
    do {
      ret= gearman_client_run_tasks(client);
    } while (gearman_continue(ret));

    ASSERT_EQ(ret, GEARMAN_SUCCESS);

    // If the task has been built to be freed, we won't have it to test
    if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
    {
      return TEST_SUCCESS;
    }

  } while (gearman_task_is_running(task));

  if (not gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
    gearman_task_free(task);

  return TEST_SUCCESS;
}

static gearman_return_t gearman_warning_test_function(gearman_task_st *task)
{
  bool *success= (bool *)gearman_task_context(task);
  if (not success)
  {
    return GEARMAN_WORK_FAIL;
  }

  *success= true;
  return GEARMAN_SUCCESS;
}

test_return_t gearman_client_add_task_warning(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);

  fatal_assert(worker_function);

  gearman_return_t ret;

  gearman_client_set_warning_fn(client, gearman_warning_test_function);

  bool warning_success= false;
  gearman_task_st *task= gearman_client_add_task(client, NULL, &warning_success,
                                                 worker_function, NULL,
                                                 test_literal_param("warning"),
                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);

  ASSERT_EQ(GEARMAN_SUCCESS, 
               gearman_client_run_tasks(client));
  ASSERT_TRUE(warning_success);

  gearman_client_set_warning_fn(client, NULL);
  if (not gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
  {
    gearman_task_free(task);
  }

  return TEST_SUCCESS;
}

test_return_t gearman_client_add_task_no_servers(void *)
{
  gearman_client_st *client= gearman_client_create(NULL);
  ASSERT_TRUE(client);

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task(client, NULL, NULL,
                                                 "does not exist", NULL,
                                                 test_literal_param("dog"),
                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);

  ASSERT_EQ(GEARMAN_NO_SERVERS, 
               gearman_client_run_tasks(client));

  ASSERT_EQ(GEARMAN_NO_SERVERS, 
               gearman_client_run_tasks(client));

  gearman_client_free(client);

  return TEST_SUCCESS;
}

test_return_t gearman_client_add_task_pause_test(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  fatal_assert(client);

  const char *worker_function= (const char *)gearman_client_context(client);
  fatal_assert(worker_function);

  // Don't do this.
  gearman_actions_t pause_actions= gearman_actions_pause();
  client->impl()->actions= pause_actions;

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task(client, NULL, NULL,
                                                 worker_function, NULL, "dog", 3,
                                                 &ret);
  ASSERT_TRUE(client->impl()->actions.data_fn == pause_actions.data_fn);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);

  ASSERT_TRUE(task);
  ASSERT_TRUE(gearman_task_unique(task));
  ASSERT_EQ(size_t(GEARMAN_MAX_UUID_SIZE), strlen(gearman_task_unique(task)));

  do
  {
    // just for async IO
    uint32_t count= 0;
    do {
      count++;
      ASSERT_TRUE(client->impl()->actions.data_fn == pause_actions.data_fn);
      ret= gearman_client_run_tasks(client);
      ASSERT_TRUE(client->impl()->actions.data_fn == pause_actions.data_fn);
    } while (gearman_continue(ret));

    ASSERT_EQ(ret, GEARMAN_SUCCESS);
    ASSERT_TRUE(count > 1);

    // If the task has been built to be freed, we won't have it to test
    if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
    {
      return TEST_SUCCESS;
    }

  } while (gearman_task_is_running(task));

  gearman_task_free(task);

  return TEST_SUCCESS;
}

struct _task_free_st {
  int64_t count;

  _task_free_st() :
    count(0)
  { }

  void add()
  {
    count++;
  }

  bool test()
  {
    return true;
  }

  void reset()
  {
    count= 0;
  }

  bool success()
  {
    if (count)
      return true;

    return false;
  }
};

static void test_task_free_fn(gearman_task_st *task, void *context)
{
  fatal_assert(task);

  _task_free_st *foo= (_task_free_st *)context;
  fatal_assert(foo->test());

  foo->add();
}

test_return_t gearman_client_set_task_context_free_fn_test(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  fatal_assert(client);

  const char *worker_function= (const char *)gearman_client_context(client);
  fatal_assert(worker_function);

  struct _task_free_st task_free_foo;


  gearman_client_set_task_context_free_fn(client, test_task_free_fn);

  gearman_return_t ret;
  gearman_task_st *task= gearman_client_add_task(client, NULL, NULL,
                                                 worker_function, NULL, "dog", 3,
                                                 &ret);
  ASSERT_EQ(ret, GEARMAN_SUCCESS);
  ASSERT_TRUE(task);
  ASSERT_TRUE(gearman_task_unique(task));
  ASSERT_EQ(size_t(GEARMAN_MAX_UUID_SIZE), strlen(gearman_task_unique(task)));
  gearman_task_set_context(task, &task_free_foo);

  do
  {

    // just for async IO
    do {
      ret= gearman_client_run_tasks(client);
    } while (gearman_continue(ret));

    ASSERT_EQ(ret, GEARMAN_SUCCESS);

    // If the task has been built to be freed, we won't have it to test
    if (gearman_client_has_option(client, GEARMAN_CLIENT_FREE_TASKS))
    {
      ASSERT_TRUE(task_free_foo.success());
      return TEST_SUCCESS;
    }

  } while (gearman_task_is_running(task));

  gearman_task_free(task);

  ASSERT_TRUE(task_free_foo.success());

  return TEST_SUCCESS;
}
