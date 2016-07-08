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

#include <cassert>
#include <cstring>
#include <libgearman/gearman.h>
#include <tests/unique.h>

#include <tests/start_worker.h>

#include "libgearman/client.hpp"
#include "libgearman/worker.hpp"
using namespace org::gearmand;

#include "tests/libgearman-1.0/client_test.h"
#include "tests/workers/v1/unique.h"
#include "tests/workers/v2/sleep_return_random.h"
#include "tests/workers/v2/echo_or_react.h"

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

#define WORKER_UNIQUE_FUNCTION_NAME "unique_test"

test_return_t unique_SETUP(void *object)
{
  client_test_st *test= (client_test_st *)object;

  test->set_worker_name(WORKER_UNIQUE_FUNCTION_NAME);

  gearman_function_t unique_worker_arg= gearman_function_create_v1(unique_worker);
  test->push(test_worker_start(libtest::default_port(), NULL,
                               test->worker_name(),
                               unique_worker_arg, NULL, GEARMAN_WORKER_GRAB_UNIQ));

  return TEST_SUCCESS;
}


test_return_t coalescence_TEST(void *object)
{
  gearman_client_st *client_one= (gearman_client_st *)object;
  ASSERT_TRUE(client_one);

  libgearman::Client client_two(client_one);

  const char* unique_handle= "local_handle";

  gearman_function_t sleep_return_random_worker_FN= gearman_function_create(sleep_return_random_worker);
  std::unique_ptr<worker_handle_st> handle(test_worker_start(libtest::default_port(),
                                                             NULL,
                                                             __func__,
                                                             sleep_return_random_worker_FN,
                                                             NULL,
                                                             gearman_worker_options_t(),
                                                             0)); // timeout

  // First task
  gearman_return_t ret;
  gearman_task_st *first_task= gearman_client_add_task(client_one,
                                                       NULL, // preallocated task
                                                       NULL, // context 
                                                       __func__, // function
                                                       unique_handle, // unique
                                                       NULL, 0, // workload
                                                       &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(first_task);
  ASSERT_TRUE(gearman_task_unique(first_task));
  ASSERT_EQ(strlen(unique_handle), strlen(gearman_task_unique(first_task)));
 
  // Second task
  gearman_task_st *second_task= gearman_client_add_task(&client_two,
                                                        NULL, // preallocated task
                                                        NULL, // context 
                                                        __func__, // function
                                                        unique_handle, // unique
                                                        NULL, 0, // workload
                                                        &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(second_task);
  ASSERT_TRUE(gearman_task_unique(second_task));
  ASSERT_EQ(strlen(unique_handle), strlen(gearman_task_unique(second_task)));
  
  test_strcmp(gearman_task_unique(first_task), gearman_task_unique(second_task));

  do {
    ret= gearman_client_run_tasks(client_one);
    gearman_client_run_tasks(&client_two);
  } while (gearman_continue(ret));

  do {
    ret= gearman_client_run_tasks(&client_two);
  } while (gearman_continue(ret));

  gearman_result_st* first_result= gearman_task_result(first_task);
  gearman_result_st* second_result= gearman_task_result(second_task);

  ASSERT_EQ(GEARMAN_SUCCESS, gearman_task_return(first_task));
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_task_return(second_task));

  ASSERT_EQ(gearman_result_value(first_result), gearman_result_value(second_result));

  gearman_task_free(first_task);
  gearman_task_free(second_task);

  return TEST_SUCCESS;
}

test_return_t coalescence_by_data_hash_TEST(void *object)
{
  gearman_client_st *client_one= (gearman_client_st *)object;
  ASSERT_TRUE(client_one);

  libgearman::Client client_two(client_one);

  const char* unique_handle= "#";

  gearman_function_t sleep_return_random_worker_FN= gearman_function_create(sleep_return_random_worker);
  std::unique_ptr<worker_handle_st> handle(test_worker_start(libtest::default_port(),
                                                             NULL,
                                                             __func__,
                                                             sleep_return_random_worker_FN,
                                                             NULL,
                                                             gearman_worker_options_t(),
                                                             0)); // timeout

  // First task
  gearman_return_t ret;
  gearman_task_st *first_task= gearman_client_add_task(client_one,
                                                       NULL, // preallocated task
                                                       NULL, // context 
                                                       __func__, // function
                                                       unique_handle, // unique
                                                       test_literal_param(__func__), // workload
                                                       &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(first_task);
  ASSERT_TRUE(gearman_task_unique(first_task));
  ASSERT_EQ(strlen("2285535048"), strlen(gearman_task_unique(first_task)));
 
  // Second task
  gearman_task_st *second_task= gearman_client_add_task(&client_two,
                                                        NULL, // preallocated task
                                                        NULL, // context 
                                                        __func__, // function
                                                        unique_handle, // unique
                                                        test_literal_param(__func__), // workload
                                                        &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(second_task);
  ASSERT_TRUE(gearman_task_unique(second_task));
  ASSERT_EQ(strlen("2285535048"), strlen(gearman_task_unique(second_task)));
  
  test_strcmp(gearman_task_unique(first_task), gearman_task_unique(second_task));
  test_strcmp("2285535048", gearman_task_unique(second_task));

  do {
    ret= gearman_client_run_tasks(client_one);
    gearman_client_run_tasks(&client_two);
  } while (gearman_continue(ret));

  do {
    ret= gearman_client_run_tasks(&client_two);
  } while (gearman_continue(ret));

  gearman_result_st* first_result= gearman_task_result(first_task);
  gearman_result_st* second_result= gearman_task_result(second_task);

  ASSERT_EQ(GEARMAN_SUCCESS, gearman_task_return(first_task));
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_task_return(second_task));

  ASSERT_EQ(gearman_result_value(first_result), gearman_result_value(second_result));

  gearman_task_free(first_task);
  gearman_task_free(second_task);

  return TEST_SUCCESS;
}

test_return_t coalescence_by_data_TEST(void *object)
{
  gearman_client_st *client_one= (gearman_client_st *)object;
  ASSERT_TRUE(client_one);

  libgearman::Client client_two(client_one);

  const char* unique_handle= "-";

  gearman_function_t sleep_return_random_worker_FN= gearman_function_create(sleep_return_random_worker);
  std::unique_ptr<worker_handle_st> handle(test_worker_start(libtest::default_port(),
                                                             NULL,
                                                             __func__,
                                                             sleep_return_random_worker_FN,
                                                             NULL,
                                                             gearman_worker_options_t(),
                                                             0)); // timeout

  // First task
  gearman_return_t ret;
  gearman_task_st *first_task= gearman_client_add_task(client_one,
                                                       NULL, // preallocated task
                                                       NULL, // context 
                                                       __func__, // function
                                                       unique_handle, // unique
                                                       NULL, 0, // workload
                                                       &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(first_task);
  ASSERT_TRUE(gearman_task_unique(first_task));
  ASSERT_EQ(strlen(unique_handle), strlen(gearman_task_unique(first_task)));
 
  // Second task
  gearman_task_st *second_task= gearman_client_add_task(&client_two,
                                                        NULL, // preallocated task
                                                        NULL, // context 
                                                        __func__, // function
                                                        unique_handle, // unique
                                                        NULL, 0, // workload
                                                        &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(second_task);
  ASSERT_TRUE(gearman_task_unique(second_task));
  ASSERT_EQ(strlen(unique_handle), strlen(gearman_task_unique(second_task)));
  
  test_strcmp(gearman_task_unique(first_task), gearman_task_unique(second_task));

  do {
    ret= gearman_client_run_tasks(client_one);
    gearman_client_run_tasks(&client_two);
  } while (gearman_continue(ret));

  do {
    ret= gearman_client_run_tasks(&client_two);
  } while (gearman_continue(ret));

  gearman_result_st* first_result= gearman_task_result(first_task);
  gearman_result_st* second_result= gearman_task_result(second_task);

  ASSERT_EQ(GEARMAN_SUCCESS, gearman_task_return(first_task));
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_task_return(second_task));

  ASSERT_EQ(gearman_result_value(first_result), gearman_result_value(second_result));

  gearman_task_free(first_task);
  gearman_task_free(second_task);

  return TEST_SUCCESS;
}

test_return_t coalescence_by_data_FAIL_TEST(void *object)
{
  gearman_client_st *client_one= (gearman_client_st *)object;
  ASSERT_TRUE(client_one);

  libgearman::Client client_two(client_one);

  const char* unique_handle= "-";

  gearman_function_t sleep_return_random_worker_FN= gearman_function_create(sleep_return_random_worker);
  std::unique_ptr<worker_handle_st> handle(test_worker_start(libtest::default_port(),
                                                             NULL,
                                                             __func__,
                                                             sleep_return_random_worker_FN,
                                                             NULL,
                                                             gearman_worker_options_t(),
                                                             0)); // timeout

  // First task
  gearman_return_t ret;
  gearman_task_st *first_task= gearman_client_add_task(client_one,
                                                       NULL, // preallocated task
                                                       NULL, // context 
                                                       __func__, // function
                                                       unique_handle, // unique
                                                       NULL, 0, // workload
                                                       &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(first_task);
  ASSERT_TRUE(gearman_task_unique(first_task));
  ASSERT_EQ(strlen(unique_handle), strlen(gearman_task_unique(first_task)));
 
  // Second task
  gearman_task_st *second_task= gearman_client_add_task(&client_two,
                                                        NULL, // preallocated task
                                                        NULL, // context 
                                                        __func__, // function
                                                        unique_handle, // unique
                                                        test_literal_param("mine"), // workload
                                                        &ret);
  ASSERT_EQ(GEARMAN_SUCCESS, ret);
  ASSERT_TRUE(second_task);
  ASSERT_TRUE(gearman_task_unique(second_task));
  ASSERT_EQ(strlen(unique_handle), strlen(gearman_task_unique(second_task)));
  
  test_strcmp(gearman_task_unique(first_task), gearman_task_unique(second_task));

  do {
    ret= gearman_client_run_tasks(client_one);
    gearman_client_run_tasks(&client_two);
  } while (gearman_continue(ret));

  do {
    ret= gearman_client_run_tasks(&client_two);
  } while (gearman_continue(ret));

  gearman_result_st* first_result= gearman_task_result(first_task);
  gearman_result_st* second_result= gearman_task_result(second_task);

  ASSERT_EQ(GEARMAN_SUCCESS, gearman_task_return(first_task));
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_task_return(second_task));

  ASSERT_EQ(gearman_result_value(first_result), gearman_result_value(second_result));

  gearman_task_free(first_task);
  gearman_task_free(second_task);

  return TEST_SUCCESS;
}

test_return_t unique_compare_test(void *object)
{
  gearman_return_t rc;
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);
  size_t job_length;

  gearman_string_t unique= { test_literal_param("my little unique") };

  void *job_result= gearman_client_do(client,
                                      worker_function, // function
                                      gearman_c_str(unique),  // unique
                                      gearman_string_param(unique), //workload
                                      &job_length, // result size
                                      &rc);

  ASSERT_EQ(rc, GEARMAN_SUCCESS);
  ASSERT_EQ(gearman_size(unique), job_length);
  test_memcmp(gearman_c_str(unique), job_result, job_length);

  free(job_result);

  return TEST_SUCCESS;
}

test_return_t gearman_client_unique_status_TEST(void *object)
{
  gearman_client_st *original_client= (gearman_client_st *)object;

  libgearman::Client status_client(original_client);

  libgearman::Client client_one(original_client);
  libgearman::Client client_two(original_client);
  libgearman::Client client_three(original_client);

  const char* unique_handle= "local_handle4";

  gearman_return_t ret;
  // First task
  gearman_task_st *first_task= gearman_client_add_task_background(&client_one,
                                                                  NULL, // preallocated task
                                                                  NULL, // context 
                                                                  __func__, // function
                                                                  unique_handle, // unique
                                                                  test_literal_param("first_task"), // workload
                                                                  &ret);
  ASSERT_TRUE(first_task);

  gearman_task_st *second_task= gearman_client_add_task_background(&client_two,
                                                                   NULL, // preallocated task
                                                                   NULL, // context 
                                                                   __func__, // function
                                                                   unique_handle, // unique
                                                                   test_literal_param("second_task"), // workload
                                                                   &ret);
  ASSERT_TRUE(second_task);

  gearman_task_st *third_task= gearman_client_add_task_background(&client_three,
                                                                  NULL, // preallocated task
                                                                  NULL, // context 
                                                                  __func__, // function
                                                                  unique_handle, // unique
                                                                  test_literal_param("third_task"), // workload
                                                                  &ret);
  ASSERT_TRUE(third_task);

  {
    ASSERT_EQ(gearman_client_run_tasks(&client_one), GEARMAN_SUCCESS);
    ASSERT_EQ(gearman_client_run_tasks(&client_two), GEARMAN_SUCCESS);
    ASSERT_EQ(gearman_client_run_tasks(&client_three), GEARMAN_SUCCESS);
  }

  ASSERT_EQ(gearman_client_job_status(&status_client,
                                         gearman_task_job_handle(third_task), // job handle
                                         NULL, // is_known
                                         NULL, // is_running
                                         NULL, // numerator
                                         NULL // denominator
                                         ), GEARMAN_JOB_EXISTS);

  {
    libgearman::Client unique_client(original_client);
    gearman_status_t status= gearman_client_unique_status(&unique_client,
                                                          unique_handle, strlen(unique_handle));
    ASSERT_EQ(GEARMAN_SUCCESS, gearman_status_return(status));
  }

  {
    gearman_status_t status= gearman_client_unique_status(&client_one,
                                                          unique_handle, strlen(unique_handle));
    ASSERT_EQ(GEARMAN_SUCCESS, gearman_status_return(status));
    ASSERT_EQ(true, gearman_status_is_known(status));
    ASSERT_EQ(false, gearman_status_is_running(status));
    test_zero(gearman_status_numerator(status));
    test_zero(gearman_status_denominator(status));
  }

  gearman_function_t func= gearman_function_create_v2(echo_or_react_worker_v2);
  std::unique_ptr<worker_handle_st> handle(test_worker_start(libtest::default_port(), NULL,
                                                             __func__,
                                                             func, NULL, gearman_worker_options_t()));

  {
    ASSERT_EQ(gearman_client_run_tasks(&client_one), GEARMAN_SUCCESS);
    ASSERT_EQ(gearman_client_run_tasks(&client_two), GEARMAN_SUCCESS);
    ASSERT_EQ(gearman_client_run_tasks(&client_three), GEARMAN_SUCCESS);
  }

  gearman_task_free(first_task);
  gearman_task_free(second_task);
  gearman_task_free(third_task);

  return TEST_SUCCESS;
}

test_return_t gearman_client_unique_status_NOT_FOUND_TEST(void *object)
{
  gearman_client_st *original_client= (gearman_client_st *)object;

  libgearman::Client status_client(original_client);
  const char* unique_handle= YATL_UNIQUE;

  gearman_function_t func= gearman_function_create_v2(echo_or_react_worker_v2);
  std::unique_ptr<worker_handle_st> handle(test_worker_start(libtest::default_port(), NULL,
                                                             __func__,
                                                             func, NULL, gearman_worker_options_t()));


  {
    gearman_status_t status= gearman_client_unique_status(&status_client,
                                                          unique_handle, strlen(unique_handle));
    ASSERT_EQ(GEARMAN_SUCCESS, gearman_status_return(status));
    ASSERT_EQ(false, gearman_status_is_known(status));
    ASSERT_EQ(false, gearman_status_is_running(status));
    test_zero(gearman_status_numerator(status));
    test_zero(gearman_status_denominator(status));
  }

  return TEST_SUCCESS;
}
