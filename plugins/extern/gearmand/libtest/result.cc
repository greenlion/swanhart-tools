/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 *
 *  Data Differential YATL (i.e. libtest)  library
 *
 *  Copyright (C) 2012-2013 Data Differential, http://datadifferential.com/
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
#include <cstdarg>

namespace libtest {

__test_result::__test_result(const char *file_arg, int line_arg, const char *func_arg):
  libtest::exception(file_arg, line_arg, func_arg)
  {
  }

__success::__success(const char *file_arg, int line_arg, const char *func_arg):
  __test_result(file_arg, line_arg, func_arg)
{
}

__skipped::__skipped(const char *file_arg, int line_arg, const char *func_arg, ...):
  __test_result(file_arg, line_arg, func_arg)
{
  va_list args;
  va_start(args, func_arg);
  init(args);
  va_end(args);
}

__skipped::__skipped(const __skipped& other) :
  __test_result(other)
{
}

__failure::__failure(const char *file_arg, int line_arg, const char *func_arg, ...) :
  __test_result(file_arg, line_arg, func_arg)
{
  va_list args;
  va_start(args, func_arg);
  init(args);
  va_end(args);
}

__failure::__failure(const __failure& other) :
  __test_result(other)
{
}


} // namespace libtest
