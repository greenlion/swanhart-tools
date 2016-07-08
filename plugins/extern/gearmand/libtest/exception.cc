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

exception::exception(const char *file_arg, int line_arg, const char *func_arg):
  std::exception(),
  _line(line_arg),
  _file(file_arg),
  _func(func_arg),
  _error_message(NULL),
  _error_message_size(0)
{
}

#ifndef __INTEL_COMPILER
# pragma GCC diagnostic ignored "-Wformat-nonliteral"
#endif
void exception::init(va_list args_)
{
  const char *format= va_arg(args_, const char *);
  int error_message_length= vasprintf(&_error_message, format, args_);
  assert(error_message_length != -1);
  if (error_message_length > 0)
  {
    _error_message_size= error_message_length +1;
  }
}

exception::~exception() throw()
{
  if (_error_message)
  {
    free(_error_message);
  }
}

void exception::what(size_t length_, const char* message_)
{
  if (length_ > 0 and message_)
  {
    char *ptr= (char*) realloc(_error_message, length_ +1);
    if (ptr)
    {
      _error_message= ptr;
      memcpy(_error_message, message_, length_);
      _error_message[length_]= 0;
    }
  }
}

exception::exception(const exception& other) :
  std::exception(),
  _line(other._line),
  _file(other._file),
  _func(other._func),
  _error_message_size(0)
{
  if (other.length() > 0)
  {
    _error_message= (char*) malloc(other.length() +1);
    if (_error_message)
    {
      memcpy(_error_message, other._error_message, other.length());
      _error_message_size= other.length();
    }
  }
}

} // namespace libtest

