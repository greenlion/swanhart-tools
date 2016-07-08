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

#include <libtest/test.hpp>

using namespace libtest;

#include <cstring>
#include <cassert>

#include <libgearman/gearman.h>

#include <tests/basic.h>
#include <tests/context.h>
#include <tests/start_worker.h>

#include "libgearman/client.hpp"
#include "libgearman/worker.hpp"
using namespace org::gearmand;

#include "tests/workers/v2/called.h"
#include "libgearman/interface/client.hpp"

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

test_return_t client_echo_fail_test(void *object)
{
  Context *test= (Context *)object;
  ASSERT_TRUE(test);

  libgearman::Client client(in_port_t(20));

  gearman_return_t rc= gearman_client_echo(&client, test_literal_param("This should never work"));
  ASSERT_TRUE(gearman_failed(rc));

  return TEST_SUCCESS;
}

test_return_t client_echo_test(void *object)
{
  Context *test= (Context *)object;
  ASSERT_TRUE(test);

  libgearman::Client client(test->port());

  ASSERT_EQ(gearman_client_echo(&client, test_literal_param("This is my echo test")), GEARMAN_SUCCESS);

  return TEST_SUCCESS;
}

test_return_t worker_echo_test(void *object)
{
  Context *test= (Context *)object;
  ASSERT_TRUE(test);

#if 0
  gearman_worker_st *worker= test->worker;
  ASSERT_TRUE(worker);
#endif
  libgearman::Worker worker(test->port());

  ASSERT_EQ(gearman_worker_echo(&worker, test_literal_param("This is my echo test")),
            GEARMAN_SUCCESS);

  return TEST_SUCCESS;
}

test_return_t queue_clean(void *object)
{
  Context *test= (Context *)object;
  ASSERT_TRUE(test);
  gearman_worker_st *worker= test->worker();
  ASSERT_TRUE(worker);

  gearman_worker_set_timeout(worker, 3000);

  Called called;
  gearman_function_t counter_function= gearman_function_create(called_worker);
  ASSERT_EQ(gearman_worker_define_function(worker,
                                           test->worker_function_name(), strlen(test->worker_function_name()),
                                           counter_function,
                                           5, &called), GEARMAN_SUCCESS);

  // Clean out any jobs that might still be in the queue from failed tests.
  while (GEARMAN_SUCCESS == gearman_worker_work(worker)) {};

  return TEST_SUCCESS;
}

test_return_t queue_add(void *object)
{
  Context *test= (Context *)object;
  ASSERT_TRUE(test);

  test->run_worker= false;

  libgearman::Client client(test->port());

  {
    gearman_return_t rc= gearman_client_echo(&client, test_literal_param("echo test message"));
    ASSERT_EQ(rc, GEARMAN_SUCCESS);
  }

  {
    gearman_job_handle_t job_handle= {};
    gearman_return_t ret= gearman_client_do_background(&client, test->worker_function_name(), NULL,
                                                       test->worker_function_name(), strlen(test->worker_function_name()),
                                                       job_handle);
    ASSERT_EQ(ret, GEARMAN_SUCCESS);
    ASSERT_TRUE(job_handle[0]);

    gearman_return_t rc;
    do {
      rc= gearman_client_job_status(&client, job_handle, NULL, NULL, NULL, NULL);
      ASSERT_TRUE(rc != GEARMAN_IN_PROGRESS);
    } while (gearman_continue(rc) and rc != GEARMAN_JOB_EXISTS); // We need to exit on these values since the job will never run
    ASSERT_TRUE(rc == GEARMAN_JOB_EXISTS or rc == GEARMAN_SUCCESS);
  }

  test->run_worker= true;

  return TEST_SUCCESS;
}

test_return_t queue_worker(void *object)
{
  Context *test= (Context *)object;
  ASSERT_TRUE(test);

  // Fetch worker
  libgearman::Worker worker(test->port());

  // Test worker
  ASSERT_EQ(gearman_worker_echo(&worker, test_literal_param("This is my echo test")), GEARMAN_SUCCESS);

  // Setup job
  ASSERT_EQ(TEST_SUCCESS, queue_add(object));

  ASSERT_TRUE(test->run_worker);

  Called counter;
  gearman_function_t counter_function= gearman_function_create(called_worker);
  ASSERT_EQ(gearman_worker_define_function(&worker,
                                              test->worker_function_name(), strlen(test->worker_function_name()),
                                              counter_function,
                                              0, &counter), GEARMAN_SUCCESS);

  gearman_return_t rc= gearman_worker_work(&worker);
  ASSERT_EQ(rc, GEARMAN_SUCCESS);

  ASSERT_TRUE(counter.count());

  return TEST_SUCCESS;
}

#define NUMBER_OF_WORKERS 10
#define NUMBER_OF_JOBS 40
#define JOB_SIZE 100
test_return_t lp_734663(void *object)
{
  Context *test= (Context *)object;
  ASSERT_TRUE(test);

  const char *worker_function_name= "drizzle_queue_test";

  uint8_t value[JOB_SIZE]= { 'x' };
  memset(&value, 'x', sizeof(value));

  libgearman::Client client(in_port_t(test->port()));

  ASSERT_EQ(gearman_client_echo(&client, value, sizeof(value)), GEARMAN_SUCCESS);

  for (uint32_t x= 0; x < NUMBER_OF_JOBS; x++)
  {
    gearman_job_handle_t job_handle= {};
    ASSERT_EQ(gearman_client_do_background(&client, worker_function_name, NULL, value, sizeof(value), job_handle), GEARMAN_SUCCESS);
    ASSERT_TRUE(job_handle[0]);
  }

  struct worker_handle_st *worker_handle[NUMBER_OF_WORKERS];

  Called called;
  gearman_function_t counter_function_fn= gearman_function_create(called_worker);
  for (uint32_t x= 0; x < NUMBER_OF_WORKERS; x++)
  {
    worker_handle[x]= test_worker_start(test->port(), NULL, worker_function_name, counter_function_fn, &called, gearman_worker_options_t());
    ASSERT_TRUE(worker_handle[x]);
  }

  while (called.count() < NUMBER_OF_JOBS)
  {
    libtest::dream(0, static_cast<long>(gearman_timeout(&client) *1000));
  }

  for (uint32_t x= 0; x < NUMBER_OF_WORKERS; x++)
  {
    worker_handle[x]->shutdown();
  }

  for (uint32_t x= 0; x < NUMBER_OF_WORKERS; x++)
  {
    delete worker_handle[x];
  }

  return TEST_SUCCESS;
}
