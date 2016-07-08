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
#include <tests/do.h>

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

test_return_t gearman_client_do_huge_unique(void *object)
{
  gearman_return_t rc;
  gearman_client_st *client= (gearman_client_st *)object;
  size_t job_length;
  char buffer[GEARMAN_UNIQUE_SIZE +10];
  memset(&buffer, 'x', sizeof(buffer));
  buffer[sizeof(buffer) -1]= 0;

  const char *worker_function= (const char *)gearman_client_context(client);
  void *job_result= gearman_client_do(client, worker_function, 
                                      buffer, 
                                      test_literal_param("gearman_client_do_huge_unique"),
                                      &job_length,
                                      &rc);
  ASSERT_EQ(GEARMAN_INVALID_ARGUMENT, rc);
  test_null(job_result);
  test_zero(job_length);

  return TEST_SUCCESS;
}

test_return_t gearman_client_do_with_active_background_task(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  const char *worker_function= (const char *)gearman_client_context(client);
  
  gearman_task_st *epoch_task;
  { // Start up epoch_task
    gearman_task_attr_t work_description= gearman_task_attr_init_epoch(time(NULL) +5, GEARMAN_JOB_PRIORITY_NORMAL);
    gearman_argument_t value= gearman_argument_make(0, 0, test_literal_param("test load"));
    ASSERT_TRUE(epoch_task= gearman_execute(client, test_string_make_from_cstr(worker_function), NULL, 0, &work_description, &value, 0));
    ASSERT_TRUE(epoch_task);
    ASSERT_TRUE(gearman_task_job_handle(epoch_task));
  }

  gearman_string_t value= { test_literal_param("submit_job_test") };
  size_t result_length;
  gearman_return_t rc;
  void *job_result= gearman_client_do(client, worker_function, NULL, gearman_string_param(value), &result_length, &rc);

  ASSERT_EQ(GEARMAN_SUCCESS, rc);
  ASSERT_TRUE(job_result);
  ASSERT_EQ(gearman_size(value), result_length);

  test_memcmp(gearman_c_str(value), job_result, gearman_size(value));

  free(job_result);
  gearman_task_free(epoch_task);

  return TEST_SUCCESS;
}
