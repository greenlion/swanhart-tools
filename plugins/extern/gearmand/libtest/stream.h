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

#include <iostream>
#include <cassert>
#include <sstream>
#include <ctime>
#include <ostream>

#include <sys/types.h>
#include <unistd.h>

namespace libtest {
namespace stream {

namespace detail {

template<class Ch, class Tr, class A>
  class channel {
  private:

  public:
    typedef std::basic_ostringstream<Ch, Tr, A> stream_buffer;

  public:
    void operator()(const stream_buffer& s, std::ostream& _out,
                    const char* filename, int line_number, const char* func)
    {
      if (filename)
      {
        _out
          << filename 
          << ":" 
          << line_number 
          << ": in " 
          << func << "() pid("
          << getpid() << ") "
          << s.str()
          << std::endl;
      }
      else
      {
        _out
          << s.str()
          << std::endl;
      }
    }
  };

template<class Ch, class Tr, class A>
  class channelln {
  private:

  public:
    typedef std::basic_ostringstream<Ch, Tr, A> stream_buffer;

  public:
    void operator()(const stream_buffer& s, std::ostream& _out,
                    const char* filename, int line_number, const char* func)
    {
      if (filename)
      {
        _out
          << std::endl
          << filename 
          << ":" 
          << line_number 
          << ": in " 
          << func << "() pid("
          << getpid() << ") "
          << s.str()
          << std::endl;
      }
      else
      {
        _out
          << std::endl
          << s.str()
          << std::endl;
      }
    }
  };

template<template <class Ch, class Tr, class A> class OutputPolicy, class Ch = char, class Tr = std::char_traits<Ch>, class A = std::allocator<Ch> >
  class log {
  private:
    typedef OutputPolicy<Ch, Tr, A> output_policy;

  private:
    std::ostream& _out;
    const char *_filename;
    int _line_number;
    const char *_func;

  public:
    log(std::ostream& out_arg, const char* filename, int line_number, const char* func) :
      _out(out_arg),
      _filename(filename),
      _line_number(line_number),
      _func(func)
    { }

    virtual ~log()
    {
      output_policy()(arg, _out, _filename, _line_number, _func);
    }

  public:
    template<class T>
      log &operator<<(const T &x)
      {
        arg << x;
        return *this;
      }

  private:
    typename output_policy::stream_buffer arg;

  private:
    log( const log& );
    const log& operator=( const log& );
  };
} // namespace detail

class make_cerr : public detail::log<detail::channelln> {
public:
  make_cerr(const char* filename, int line_number, const char* func) :
    detail::log<detail::channelln>(std::cerr, filename, line_number, func)
  { }

private:
  make_cerr( const make_cerr& );
  const make_cerr& operator=( const make_cerr& );
};

class cerr : public detail::log<detail::channel> {
public:
  cerr(const char* filename, int line_number, const char* func) :
    detail::log<detail::channel>(std::cout, filename, line_number, func)
  { }

private:
  cerr( const cerr& );
  const cerr& operator=( const cerr& );
};

class clog : public detail::log<detail::channel> {
public:
  clog(const char* filename, int line_number, const char* func) :
    detail::log<detail::channel>(std::clog, filename, line_number, func)
  { }

private:
  clog( const clog& );
  const clog& operator=( const clog& );
};

class make_cout : public detail::log<detail::channelln> {
public:
  make_cout(const char* filename, int line_number, const char* func) :
    detail::log<detail::channelln>(std::cout, filename, line_number, func)
  { }

private:
  make_cout( const make_cout& );
  const make_cout& operator=( const make_cout& );
};

class cout : public detail::log<detail::channel> {
public:
  cout(const char* filename, int line_number, const char* func) :
    detail::log<detail::channel>(std::cout, filename, line_number, func)
  { }

private:
  cout( const cout& );
  const cout& operator=( const cout& );
};


} // namespace stream

#define Error stream::cerr(__FILE__, __LINE__, __func__)

#define Out stream::cout(NULL, __LINE__, __func__)

#define Outn() stream::cout(NULL, __LINE__, __func__) << " "

#define Log stream::clog(NULL, __LINE__, __func__)

#define Logn() stream::clog(NULL, __LINE__, __func__) << " "

} // namespace libtest
