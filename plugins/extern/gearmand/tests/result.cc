/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Test memcached_result_st
 *
 *  Copyright (C) 2013 Data Differential, http://datadifferential.com/
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
  Test that we are cycling the servers we are creating during testing.
*/

#include "gear_config.h"

#include <libtest/test.hpp>
using namespace libtest;

#include "libgearman/result.hpp"
#include "libgearman/assert.hpp"

#include "libgearman-1.0/visibility.h"
#include "libgearman-1.0/result.h"

#include <memory>

static test_return_t declare_result_TEST(void*)
{
  gearman_result_st result;
  ASSERT_EQ(0, result.size());
  ASSERT_EQ(0, result.capacity());

  return TEST_SUCCESS;
}

static test_return_t new_result_TEST(void*)
{
  gearman_result_st* result= new gearman_result_st;
  ASSERT_EQ(0, result->size());
  ASSERT_EQ(0, result->capacity());

  delete result;

  return TEST_SUCCESS;
}

static test_return_t declare_result_size_TEST(void*)
{
  gearman_result_st result(2048);
  ASSERT_EQ(0, result.size());
  ASSERT_TRUE(result.capacity() >= 2048);

  return TEST_SUCCESS;
}

static test_return_t new_result_size_TEST(void*)
{
  gearman_result_st* result= new gearman_result_st(2023);

  ASSERT_EQ(0, result->size());
  ASSERT_TRUE(result->capacity() >= 2023);

  delete result;

  return TEST_SUCCESS;
}

static test_return_t zero_resize_TEST(void*)
{
  gearman_result_st result(89);
  ASSERT_EQ(0, result.size());
  ASSERT_TRUE(result.capacity() >= 89);

  result.resize(0);
  ASSERT_EQ(0, result.size());
  ASSERT_EQ(0, result.capacity());

  return TEST_SUCCESS;
}

static test_return_t smaller_resize_TEST(void*)
{
  gearman_result_st result(89);
  ASSERT_EQ(0, result.size());
  ASSERT_TRUE(result.capacity() >= 89);

  result.resize(20);
  ASSERT_EQ(0, result.size());
  ASSERT_TRUE(result.capacity() >= 20);

  return TEST_SUCCESS;
}

static test_return_t bigger_resize_TEST(void*)
{
  gearman_result_st result(89);
  ASSERT_EQ(0, result.size());
  ASSERT_TRUE(result.capacity() >= 89);

  result.resize(181);
  ASSERT_EQ(0, result.size());
  ASSERT_TRUE(result.capacity() >= 181);

  return TEST_SUCCESS;
}

static test_return_t random_resize_TEST(void*)
{
  const size_t max_block= 10 * GEARMAN_VECTOR_BLOCK_SIZE;
  for (size_t x= 0; x < 20; x++)
  {
    gearman_result_st result(random() % max_block);
    ASSERT_TRUE(result.capacity() >= result.size());
    result.resize(random() % max_block);
    ASSERT_TRUE(result.capacity() >= result.size());
    result.resize(random() % max_block +GEARMAN_VECTOR_BLOCK_SIZE);
    ASSERT_TRUE(result.capacity() >= result.size());
  }

  return TEST_SUCCESS;
}

static test_return_t append_TEST(void*)
{
  const size_t max_block= 10 * GEARMAN_VECTOR_BLOCK_SIZE;
  for (size_t x= 0; x < 20; x++)
  {
    gearman_result_st result(random() % max_block);
    libtest::vchar_t random_string;
    libtest::vchar::make(random_string, (random() % max_block) +1);
    result.append(&random_string[0], random_string.size());

    if (random() % 2)
    {
      result.clear();
    }
  }

  return TEST_SUCCESS;
}

static test_return_t gearman_string_take_TEST(void*)
{
  const size_t max_block= 10 * GEARMAN_VECTOR_BLOCK_SIZE;
  for (size_t x= 0; x < 20; x++)
  {
    gearman_result_st result((random() % max_block) +1);

    // Now we insert a random string
    libtest::vchar_t random_string;
    libtest::vchar::make(random_string, (random() % max_block) +1);
    result.append(&random_string[0], random_string.size());

    gearman_string_t temp= gearman_result_take_string(&result);
    ASSERT_TRUE(gearman_c_str(temp));
    free((void*)(gearman_c_str(temp)));
  }

  return TEST_SUCCESS;
}

static test_return_t gearman_string_allocate_take_TEST(void*)
{
  const size_t max_block= 10 * GEARMAN_VECTOR_BLOCK_SIZE;
  for (size_t x= 0; x < 20; x++)
  {
    gearman_result_st* result= new gearman_result_st((random() % max_block) +1);

    { // Now we insert a random string
      libtest::vchar_t random_string;
      libtest::vchar::make(random_string, (random() % max_block) +1);
      result->append(&random_string[0], random_string.size());
    }

    gearman_string_t temp= gearman_result_take_string(result);
    ASSERT_TRUE(gearman_c_str(temp));
    free((void*)(gearman_c_str(temp)));

    if (random() % 2)
    { // Now we insert a random string
      libtest::vchar_t random_string;
      libtest::vchar::make(random_string, (random() % max_block) +1);
      result->append(&random_string[0], random_string.size());
    }

    delete result;
  }

  return TEST_SUCCESS;
}

static test_return_t gearman_result_integer_TEST(void*)
{
  gearman_result_st result;
  ASSERT_TRUE(result.store(8976));

  ASSERT_EQ(0, gearman_result_integer(NULL));
  ASSERT_EQ(8976, gearman_result_integer(&result));

  return TEST_SUCCESS;
}

static test_return_t gearman_result_boolean_TEST(void*)
{
  gearman_result_st result;
  ASSERT_TRUE(result.is_type(GEARMAN_RESULT_NULL));

  ASSERT_EQ(false, gearman_result_boolean(NULL));

  ASSERT_TRUE(result.boolean(true));
  ASSERT_EQ(true, gearman_result_boolean(&result));
  ASSERT_TRUE(result.is_type(GEARMAN_RESULT_BOOLEAN));

  ASSERT_TRUE(result.boolean(false));
  ASSERT_EQ(false, gearman_result_boolean(&result));
  ASSERT_TRUE(result.is_type(GEARMAN_RESULT_BOOLEAN));

  gearman_result_st result_false;
  ASSERT_TRUE(result_false.boolean(false));
  ASSERT_EQ(false, gearman_result_boolean(&result_false));
  ASSERT_TRUE(result.is_type(GEARMAN_RESULT_BOOLEAN));

  return TEST_SUCCESS;
}

static test_return_t gearman_result_string_TEST(void*)
{
  gearman_string_t value= { test_literal_param("This is my echo test") };
  gearman_result_st result;
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_result_store_string(&result, value));

  gearman_string_t ret_value= gearman_result_string(&result);
  ASSERT_EQ(test_literal_param_size(value), test_literal_param_size(ret_value));

  ASSERT_EQ(GEARMAN_INVALID_ARGUMENT, gearman_result_store_string(NULL, value));

  return TEST_SUCCESS;
}

static test_return_t gearman_result_store_string_TEST(void*)
{
  gearman_string_t value= { test_literal_param("This is my echo test") };
  ASSERT_EQ(GEARMAN_INVALID_ARGUMENT, gearman_result_store_string(NULL, value));

  gearman_result_st result;
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_result_store_string(&result, value));

  return TEST_SUCCESS;
}

static test_return_t gearman_result_store_integer_TEST(void*)
{
  const int64_t value= __LINE__;
  gearman_result_st result;
  gearman_result_store_integer(&result, value);

  return TEST_SUCCESS;
}

static test_return_t gearman_result_store_value_TEST(void*)
{
  ASSERT_EQ(GEARMAN_INVALID_ARGUMENT, gearman_result_store_value(NULL, NULL, 0));

  gearman_result_st result;
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_result_store_value(&result, test_literal_param(__func__)));

  return TEST_SUCCESS;
}

static test_return_t gearman_result_size_TEST(void*)
{
  gearman_result_st result;
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_result_store_value(&result, test_literal_param(__func__)));
  ASSERT_EQ(strlen(__func__), gearman_result_size(&result));

  ASSERT_EQ(0, gearman_result_size(NULL));

  return TEST_SUCCESS;
}

static test_return_t gearman_result_value_TEST(void*)
{
  gearman_result_st result;
  ASSERT_EQ(GEARMAN_SUCCESS, gearman_result_store_value(&result, test_literal_param(__func__)));

  ASSERT_EQ(GEARMAN_INVALID_ARGUMENT, gearman_result_store_value(NULL, test_literal_param(__func__)));

  return TEST_SUCCESS;
}

static test_return_t gearman_result_is_null_TEST(void*)
{
  gearman_result_st result;
  ASSERT_TRUE(gearman_result_is_null(&result));
  ASSERT_TRUE(gearman_result_is_null(NULL));

  return TEST_SUCCESS;
}

test_st allocate_TESTS[] ={
  { "declare result", 0, declare_result_TEST },
  { "new result", 0, new_result_TEST },
  { "declare result(2048)", 0, declare_result_size_TEST },
  { "new result(2023)", 0, new_result_size_TEST },
  { 0, 0, 0 }
};

test_st resize_TESTS[] ={
  { "zero", 0, zero_resize_TEST },
  { "smaller", 0, smaller_resize_TEST },
  { "bigger", 0, bigger_resize_TEST },
  { "random", 0, random_resize_TEST },
  { 0, 0, 0 }
};

test_st append_TESTS[] ={
  { "append()", 0, append_TEST },
  { 0, 0, 0 }
};

test_st take_TESTS[] ={
  { "gearman_string_take_string()", 0, gearman_string_take_TEST },
  { "new gearman_result_st() gearman_string_take_string()", 0, gearman_string_allocate_take_TEST },
  { 0, 0, 0 }
};

test_st API_TESTS[] ={
  { "gearman_result_integer()", 0, gearman_result_integer_TEST },
  { "gearman_result_boolean()", 0, gearman_result_boolean_TEST },
  { "gearman_result_string()", 0, gearman_result_string_TEST },
  { "gearman_result_store_string()", 0, gearman_result_store_string_TEST },
  { "gearman_result_store_integer()", 0, gearman_result_store_integer_TEST },
  { "gearman_result_store_value()", 0, gearman_result_store_value_TEST },
  { "gearman_result_size()", 0, gearman_result_size_TEST },
  { "gearman_result_value()", 0, gearman_result_value_TEST },
  { "gearman_result_is_null()", 0, gearman_result_is_null_TEST },
  { 0, 0, 0 }
};

collection_st collection[] ={
  {"allocate", NULL, NULL, allocate_TESTS },
  {"resize", NULL, NULL, resize_TESTS },
  {"append", NULL, NULL, append_TESTS },
  {"take", NULL, NULL, take_TESTS },
  {"API", NULL, NULL, API_TESTS },
  {0, 0, 0, 0}
};

void get_world(libtest::Framework *world)
{
  world->collections(collection);
}
