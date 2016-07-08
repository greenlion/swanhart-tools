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

#include <tests/limits.h>
#include "tests/start_worker.h"


test_return_t function_name_limit_test(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  ASSERT_TRUE(client);

  std::vector<char> function_name;
  function_name.resize(GEARMAN_FUNCTION_MAX_SIZE +2);
  memset(&function_name[0], 'b', GEARMAN_FUNCTION_MAX_SIZE +1);

  size_t result_length;
  gearman_return_t rc;
  char *job_result= (char*)gearman_client_do(client, &function_name[0],
                                             NULL, 
                                             test_literal_param("reset"),
                                             &result_length, &rc);
  ASSERT_EQ(GEARMAN_INVALID_ARGUMENT, rc);
  test_null(job_result);

  return TEST_SUCCESS;
}

test_return_t unique_name_limit_test(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;
  ASSERT_TRUE(client);

  std::vector<char> function_name;
  function_name.resize(GEARMAN_FUNCTION_MAX_SIZE +1);
  memset(&function_name[0], 'b', GEARMAN_FUNCTION_MAX_SIZE);

  std::vector<char> unique_name;
  unique_name.resize(GEARMAN_MAX_UNIQUE_SIZE +2);
  memset(&unique_name[0], 'b', GEARMAN_MAX_UNIQUE_SIZE +1);

  size_t result_length;
  gearman_return_t rc;
  char *job_result= (char*)gearman_client_do(client, &function_name[0],
                                             &unique_name[0],
                                             test_literal_param("reset"),
                                             &result_length, &rc);
  ASSERT_EQ(GEARMAN_INVALID_ARGUMENT, rc);
  test_null(job_result);

  return TEST_SUCCESS;
}
