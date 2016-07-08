/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 *
 *  Data Differential YATL (i.e. libtest)  library
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

#include <libtest/lite.h>

/**
  A structure describing the test case.
*/
struct test_st {
  const char *name;
  bool requires_flush;
  test_callback_fn *test_fn;
};

namespace libtest {

class TestCase;
typedef std::vector<libtest::TestCase*> TestCases;

class TestCase {
public:
  TestCase(const struct test_st* test_):
    _result(TEST_FAILURE)
  {
    _test.name= test_->name;
    _test.requires_flush= test_->requires_flush;
    _test.test_fn= test_->test_fn;
  }

  const char* name() const
  {
    return _test.name;
  }

  test_return_t result() const
  {
    return _result;
  }

  void result(test_return_t result_)
  {
    _result= result_;
  }

  void result(test_return_t result_, const libtest::Timer& timer_)
  {
    _result= result_;
    _timer= timer_;
  }

  const libtest::Timer& timer() const
  {
    return _timer;
  }

  void timer(libtest::Timer& timer_)
  {
    _timer= timer_;
  }

  void skipped()
  {
    _result= TEST_SKIPPED;
  }

  void failed()
  {
    _result= TEST_FAILURE;
  }

  void success(const libtest::Timer& timer_)
  {
    _result= TEST_SUCCESS;
    _timer= timer_;
  }


  const struct test_st* test() const
  {
    return &_test;
  }

  bool requires_flush() const
  {
    return _test.requires_flush;
  }

private:
  struct test_st _test;
  test_return_t _result;
  libtest::Timer _timer;
};
} // namespace libtest

#define test_assert_errno(A) \
do \
{ \
  if ((A)) { \
    fprintf(stderr, "\n%s:%d: Assertion failed for %s: ", __FILE__, __LINE__, __func__);\
    perror(#A); \
    fprintf(stderr, "\n"); \
    libtest::create_core(); \
    assert((A)); \
  } \
} while (0)

#define test_true_got(A, B) ASSERT_TRUE(A);
#define test_true_hint(A, B) ASSERT_TRUE(A);

#define test_compare_hint(A, B, C) test_compare(A, B);
#define test_compare_got(A, B, C) test_compare(A, B);

#define test_skip(__expected, __actual) \
do \
{ \
  if (libtest::_compare(__FILE__, __LINE__, __func__, ((__expected)), ((__actual)), false) == false) \
  { \
    return TEST_SKIPPED; \
  } \
} while (0)

#define test_skip_valgrind() \
do \
{ \
  if (libtest::_in_valgrind(__FILE__, __LINE__, __func__)) \
  { \
    return TEST_SKIPPED; \
  } \
} while (0)

#define test_fail(A) \
do \
{ \
  if (1) { \
    fprintf(stderr, "\n%s:%d: Failed with %s, in %s\n", __FILE__, __LINE__, #A, __func__);\
    libtest::create_core(); \
    return TEST_FAILURE; \
  } \
} while (0)


#define test_false(A) \
do \
{ \
  if ((A)) { \
    fprintf(stderr, "\n%s:%d: Assertion failed %s, in %s\n", __FILE__, __LINE__, #A, __func__);\
    libtest::create_core(); \
    return TEST_FAILURE; \
  } \
} while (0)

#define test_false_with(A,B) \
do \
{ \
  if ((A)) { \
    fprintf(stderr, "\n%s:%d: Assertion failed %s with %s\n", __FILE__, __LINE__, #A, (B));\
    libtest::create_core(); \
    return TEST_FAILURE; \
  } \
} while (0)

#define test_ne_compare(__expected, __actual) \
do \
{ \
  if (libtest::_ne_compare(__FILE__, __LINE__, __func__, ((__expected)), ((__actual)), true) == false) \
  { \
    libtest::create_core(); \
    return TEST_FAILURE; \
  } \
} while (0)

#define test_compare(__expected, __actual) \
do \
{ \
  if (libtest::_compare(__FILE__, __LINE__, __func__, ((__expected)), ((__actual)), true) == false) \
  { \
    libtest::create_core(); \
    return TEST_FAILURE; \
  } \
} while (0)

#define test_zero(__actual) \
do \
{ \
  if (libtest::_compare_zero(__FILE__, __LINE__, __func__, ((__actual))) == false) \
  { \
    libtest::create_core(); \
    return TEST_FAILURE; \
  } \
} while (0)

#define test_null test_zero

#define test_compare_warn(__expected, __actual) \
do \
{ \
  void(libtest::_compare(__FILE__, __LINE__, __func__, (__expected), (__actual)), true); \
} while (0)

#define test_warn(__truth, __explain) \
do \
{ \
  void(libtest::_assert_truth(__FILE__, __LINE__, __func__, bool((__truth)), #__truth, __explain)); \
} while (0)

#define test_strcmp(__expected, __actual) \
do \
{ \
  void(libtest::_compare_strcmp(__FILE__, __LINE__, __func__, (__expected), (__actual))); \
} while (0)

#define test_memcmp(A,B,C) \
do \
{ \
  if ((A) == NULL or (B) == NULL or memcmp((A), (B), (C))) \
  { \
    fprintf(stderr, "\n%s:%d: %.*s -> %.*s\n", __FILE__, __LINE__, (int)(C), (char *)(A), (int)(C), (char *)(B)); \
    libtest::create_core(); \
    return TEST_FAILURE; \
  } \
} while (0)

#define test_return_if(__test_return_t) \
do \
{ \
  if ((__test_return_t) != TEST_SUCCESS) \
  { \
    return __test_return_t; \
  } \
} while (0)

