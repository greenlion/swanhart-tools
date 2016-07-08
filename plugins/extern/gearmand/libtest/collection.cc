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

#include <algorithm>

// @todo possibly have this code fork off so if it fails nothing goes bad
static test_return_t runner_code(libtest::Framework* frame,
                                 const struct test_st* run, 
                                 libtest::Timer& _timer)
{ // Runner Code

  assert(frame->runner());
  assert(run->test_fn);

  test_return_t return_code;
  try 
  {
    _timer.reset();
    assert(frame);
    assert(frame->runner());
    assert(run->test_fn);
    return_code= frame->runner()->main(run->test_fn, frame->creators_ptr());
  }
  // Special case where check for the testing of the exception
  // system.
  catch (const libtest::fatal& e)
  {
    alarm(0);
    if (libtest::fatal::is_disabled())
    {
      libtest::fatal::increment_disabled_counter();
      return_code= TEST_SUCCESS;
    }
    else
    {
      throw;
    }
  }
  catch (...)
  {
    alarm(0);
    throw;
  }

  _timer.sample();

  return return_code;
}

namespace libtest {

Collection::Collection(Framework* frame_,
                       collection_st* tests_) :
  _name(tests_->name),
  _ret(TEST_SKIPPED),
  _pre(tests_->pre),
  _post(tests_->post),
  _tests(tests_->tests),
  _frame(frame_),
  _success(0),
  _skipped(0),
  _failed(0),
  _formatter(frame_->formatter())
{
  fatal_assert(tests_);
  for (const test_st *run= _tests; run->name; run++)
  {
    push_testcase(run);
  }

  case_iter= _testcases.begin();
}

Collection::~Collection()
{
  std::for_each(_testcases.begin(), _testcases.end(), DeleteFromVector());
  _testcases.clear();

  std::for_each(_formatter.begin(), _formatter.end(), DeleteFromVector());
  _formatter.clear();
}

void Collection::push_testcase(const test_st* test_)
{
  TestCase* _current_testcase= new TestCase(test_);
  _testcases.push_back(_current_testcase);
}

void Collection::format()
{
  for (Formatters::iterator format_iter= _formatter.begin();
       format_iter != _formatter.end();
       ++format_iter)
  {
    (*format_iter)->report((*case_iter), std::distance(_testcases.begin(), case_iter));
  }
}

void Collection::plan()
{
  for (Formatters::iterator format_iter= _formatter.begin();
       format_iter != _formatter.end();
       ++format_iter)
  {
    (*format_iter)->plan(this);
  }
}

void Collection::complete()
{
  for (Formatters::iterator format_iter= _formatter.begin();
       format_iter != _formatter.end();
       ++format_iter)
  {
    (*format_iter)->complete();
  }
}

void Collection::succeess()
{
  _success++;
  (*case_iter)->success(_timer);
  format();
}

void Collection::skip()
{
  _skipped++;
  (*case_iter)->skipped();
  format();
}

void Collection::fail()
{
  _failed++;
  (*case_iter)->failed();
  format();
}

test_return_t Collection::exec()
{
  // Write out any headers required by formatting.
  plan();

  if (test_success(_frame->runner()->setup(_pre, _frame->creators_ptr())))
  {
    for (; case_iter != _testcases.end();
         ++case_iter)
    {
      if (_frame->match((*case_iter)->name()))
      {
        skip();
        continue;
      }

      test_return_t return_code;
      try 
      {
        if ((*case_iter)->requires_flush())
        {
          if (test_failed(_frame->runner()->flush(_frame->creators_ptr())))
          {
            Error << "frame->runner()->flush(creators_ptr)";
            skip();
            continue;
          }
        }

        set_alarm();

        try 
        {
          return_code= runner_code(_frame, (*case_iter)->test(), _timer);
        }
        catch (...)
        {
          cancel_alarm();

          throw;
        }
        libtest::cancel_alarm();
      }
      catch (const libtest::fatal& e)
      {
        stream::cerr(e.file(), e.line(), e.func()) << e.what();
        fail();
        throw;
      }

      switch (return_code)
      {
      case TEST_SUCCESS:
        succeess();
        break;

      case TEST_FAILURE:
        fail();
        break;

      case TEST_SKIPPED:
        skip();
        break;

      default:
        FATAL("invalid return code");
      }
#if 0
      @TODO add code here to allow for a collection to define a method to reset to allow tests to continue.
#endif
    }

    (void) _frame->runner()->teardown(_post, _frame->creators_ptr());
  }

  if (_failed == 0 and _skipped == 0 and _success)
  {
    _ret= TEST_SUCCESS;
  }
  else if (_failed)
  {
    _ret= TEST_FAILURE;
  }

  // Complete any headers required by formatting.
  complete();

  return _ret;
}

} // namespace libtest

