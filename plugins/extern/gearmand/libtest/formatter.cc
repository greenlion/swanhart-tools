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
#include <fstream>
#include <iostream>

namespace {

  std::string& escape4XML(std::string const& arg, std::string& escaped_string)
  {
    escaped_string.clear();

    escaped_string+= '"';
    for (std::string::const_iterator x= arg.begin(), end= arg.end(); x != end; ++x)
    {
      unsigned char c= *x;
      if (c == '&')
      {
        escaped_string+= "&amp;";
      }
      else if (c == '>')
      {
        escaped_string+= "&gt;";
      }
      else if (c == '<')
      {
        escaped_string+= "&lt;";
      }
      else if (c == '\'')
      {
        escaped_string+= "&apos;";  break;
      }
      else if (c == '"')
      {
        escaped_string+= "&quot;";
      }
      else if (c == ' ')
      {
        escaped_string+= ' ';
      }
      else if (isalnum(c))
      {
        escaped_string+= c;
      }
      else 
      {
        char const* const hexdig= "0123456789ABCDEF";
        escaped_string+= "&#x";
        escaped_string+= hexdig[c >> 4];
        escaped_string+= hexdig[c & 0xF];
        escaped_string+= ';';
      }
    }
    escaped_string+= '"';

    return escaped_string;
  }

}
  
namespace libtest {

Formatter::Formatter(const Framework* frame_, std::ostream& output_):
  _frame(frame_),
  _output(output_)
{
}

const std::string& Formatter::name() const
{
  return _frame->name();
}

Legacy::Legacy(const Framework* frame_, std::ostream& output_):
  Formatter(frame_, output_),
  _collection(NULL)
{
}


Legacy::~Legacy()
{
  if (getenv("YATL_SUMMARY"))
  {
    Outn();
    Out << "Tests\t\t\t\t\t" << _frame->total();
    Out << "\tFailed\t\t\t\t\t" << _frame->failed();
    Out << "\tSkipped\t\t\t\t\t" << _frame->skipped();
    Out << "\tSucceeded\t\t\t\t" << _frame->success();
  }
}

void Legacy::plan(const Collection* collection)
{
  _collection= collection;
}

void Legacy::report(const libtest::TestCase* test, size_t) const
{
  switch (test->result())
  {
    case TEST_SUCCESS:
      Out << name() << "."
        << _collection->name() << "."
        << test->name()
        <<  "\t\t\t\t\t" 
        << test->timer() 
        << " [ " << test_strerror(test->result()) << " ]";
      break;

    case TEST_FAILURE:
      Out << name() << "."
        << _collection->name() << "."
        << test->name()
        <<  "\t\t\t\t\t" << "[ " << test_strerror(test->result()) << " ]";
      break;

    case TEST_SKIPPED:
      Out << name() << "."
        << _collection->name() << "."
        << test->name()
        <<  "\t\t\t\t\t" << "[ " << test_strerror(test->result()) << " ]";
      break;
  }
}

Junit::Junit(const Framework* frame_, std::ostream& output_):
  Formatter(frame_, output_)
{
  std::string escaped_string;
  _output << "<testsuites name=" << escape4XML(name(), escaped_string) << ">" << std::endl;
}

Junit::~Junit()
{
  _output << "</testsuites>" << std::endl;
}

void Junit::report(const libtest::TestCase* test, size_t) const
{
  std::string escaped_string;

  _output << "\t\t<testcase name=" 
    << escape4XML(test->name(), escaped_string)
    << " time=\"" 
    << test->timer()
    << "\">" 
    << std::endl;

  switch (test->result())
  {
    case TEST_SKIPPED:
      _output << "\t\t <skipped/>" << std::endl;
      break;

    case TEST_FAILURE:
      _output << "\t\t <failure message=\"\" type=\"\"/>"<< std::endl;
      break;

    case TEST_SUCCESS:
      break;
  }
  _output << "\t\t</testcase>" << std::endl;
}

void Junit::plan(const Collection* collection)
{
  std::string escaped_string;

  _output << "\t<testsuite name=" 
    << escape4XML(collection->name(), escaped_string)
    << ">" << std::endl;
#if 0
    << "\"  classname=\"\" package=\"\">" << std::endl;
#endif
}

void Junit::complete()
{
  _output << "\t</testsuite>" << std::endl;
}

TAP::TAP(const Framework* frame_, std::ostream& output_):
  Formatter(frame_, output_)
{
}

TAP::~TAP()
{
}

void TAP::report(const libtest::TestCase* test, size_t position) const
{
  assert(test);
  switch (test->result())
  {
    case TEST_SUCCESS:
      _output << "ok " << position << " - " << test->name() << " # ";
      _output << test->timer();
      break;

    case TEST_FAILURE:
      _output << "not ok " << position << " - " << test->name() << " # ";
      break;

    case TEST_SKIPPED:
      _output << "ok " << position << " - # SKIP ";
      break;
  }

  _output << std::endl;
}

void TAP::plan(const Collection* collection)
{
  _output << "0.." << collection->total() << std::endl;
}

#if 0
void Formatter::tap(libtest::Framework& framework_, std::ofstream& output)
{
  for (Suites::iterator framework_iter= framework_.suites().begin();
       framework_iter != framework_.suites().end();
       ++framework_iter)
  {
    output << "1.." << (*framework_iter)->formatter()->testcases().size() << " # " << (*framework_iter)->name() << std::endl;

    size_t test_count= 1;
    for (TestCases::iterator case_iter= (*framework_iter)->formatter()->testcases().begin();
         case_iter != (*framework_iter)->formatter()->testcases().end();
         ++case_iter)
    {
      switch ((*case_iter)->result())
      {
        case TEST_SKIPPED:
        output << "ok " << test_count << " - # SKIP ";
        break;

        case TEST_FAILURE:
        output << "not ok " << test_count << " - " << (*case_iter)->name() << " # ";
        break;

        case TEST_SUCCESS:
        output << "ok " << test_count << " - " << (*case_iter)->name() << " # ";
        break;
      }

      output 
        << (*case_iter)->timer().elapsed_milliseconds() 
        << std::endl;
    }
  }
}
#endif

#if 0
void Formatter::xml(libtest::Framework& framework_, std::ofstream& output)
{
  output << "<testsuites name=\"" << framework_.name() << "\">" << std::endl;
  for (Suites::iterator framework_iter= framework_.suites().begin();
       framework_iter != framework_.suites().end();
       ++framework_iter)
  {
    output << "\t<testsuite name=\"" << (*framework_iter)->name() << "\"  classname=\"\" package=\"\">" << std::endl;

    for (TestCases::iterator case_iter= (*framework_iter)->formatter()->testcases().begin();
         case_iter != (*framework_iter)->formatter()->testcases().end();
         ++case_iter)
    {
      output << "\t\t<testcase name=\"" 
        << (*case_iter)->name() 
        << "\" time=\"" 
        << (*case_iter)->timer().elapsed_milliseconds() 
        << "\">" 
        << std::endl;

      switch ((*case_iter)->result())
      {
        case TEST_SKIPPED:
        output << "\t\t <skipped/>" << std::endl;
        break;

        case TEST_FAILURE:
        output << "\t\t <failure message=\"\" type=\"\"/>"<< std::endl;
        break;

        case TEST_SUCCESS:
        break;
      }
      output << "\t\t</testcase>" << std::endl;
    }
    output << "\t</testsuite>" << std::endl;
  }
  output << "</testsuites>" << std::endl;
}
#endif

} // namespace libtest
