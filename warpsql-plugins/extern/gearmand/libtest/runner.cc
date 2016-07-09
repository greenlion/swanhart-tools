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

#include "libtest/yatlcon.h"
#include <libtest/common.h>

namespace libtest {

Runner::Runner() :
  _servers(NULL)
{
}

test_return_t Runner::main(test_callback_fn* func, void *object)
{
  test_return_t ret;
  try {
    ret= run(func, object);
  }
  catch (const libtest::__skipped& e)
  {
    ret= TEST_SKIPPED;
  }
  catch (const libtest::__failure& e)
  {
    libtest::stream::make_cerr(e.file(), e.line(), e.func()) << e.what();
    ret= TEST_FAILURE;
  }
  catch (const libtest::__success&)
  {
    ret= TEST_SUCCESS;
  }
  catch (const libtest::fatal&)
  {
    throw;
  }
  catch (const std::exception& e)
  {
    libtest::stream::make_cerr(LIBYATL_DEFAULT_PARAM) << e.what();
    throw;
  }
  catch (...)
  {
    libtest::stream::make_cerr(LIBYATL_DEFAULT_PARAM) << "Unknown exception thrown";
    throw;
  }

  return ret;
}

test_return_t Runner::setup(test_callback_fn* func, void *object)
{
  test_return_t ret;
  try {
    ret= pre(func, object);
  }
  catch (const libtest::__skipped& e)
  {
    ret= TEST_SKIPPED;
  }
  catch (const libtest::__failure& e)
  {
    libtest::stream::make_cout(e.file(), e.line(), e.func()) << e.what();
    ret= TEST_FAILURE;
  }
  catch (const libtest::__success&)
  {
    ret= TEST_SUCCESS;
  }
  catch (const libtest::fatal& e)
  {
    throw;
  }
  catch (const std::exception& e)
  {
    libtest::stream::make_cerr(LIBYATL_DEFAULT_PARAM) << e.what();
    throw;
  }
  catch (...)
  {
    libtest::stream::make_cerr(LIBYATL_DEFAULT_PARAM) << "Unknown exception thrown";
    throw;
  }

  return ret;
}

test_return_t Runner::teardown(test_callback_fn* func, void *object)
{
  test_return_t ret;
  try {
    ret= post(func, object);
  }
  catch (const libtest::__skipped& e)
  {
    ret= TEST_SKIPPED;
  }
  catch (const libtest::__failure& e)
  {
    libtest::stream::make_cerr(LIBYATL_DEFAULT_PARAM) << e.what();
    ret= TEST_FAILURE;
  }
  catch (const libtest::__success&)
  {
    ret= TEST_SUCCESS;
  }
  catch (const libtest::fatal& e)
  {
    throw;
  }
  catch (const std::exception& e)
  {
    libtest::stream::make_cerr(LIBYATL_DEFAULT_PARAM) << e.what();
    throw;
  }
  catch (...)
  {
    libtest::stream::make_cerr(LIBYATL_DEFAULT_PARAM) << "Unknown exception thrown";
    throw;
  }

  return ret;
}

test_return_t Runner::flush(void*)
{
  return TEST_SUCCESS;
}

test_return_t Runner::run(test_callback_fn* func, void *object)
{
  if (func)
  {
    return func(object);
  }

  return TEST_SUCCESS;
}

test_return_t Runner::pre(test_callback_fn* func, void *object)
{
  if (func)
  {
    return func(object);
  }

  return TEST_SUCCESS;
}

test_return_t Runner::post(test_callback_fn* func, void *object)
{
  if (func)
  {
    return func(object);
  }

  return TEST_SUCCESS;
}

void Runner::set_servers(libtest::server_startup_st& arg)
{
  _servers= &arg;
}

bool Runner::check()
{
  return _servers ? _servers->check() : true;
}

} // namespace libtest
