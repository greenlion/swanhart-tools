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

#include <libtest/vchar.hpp>

namespace libtest {
namespace http {

class HTTP {
public:

  HTTP(const std::string& url_arg);

  virtual bool execute()= 0;

  virtual ~HTTP()
  { }

  const std::string& url() const
  {
    return _url;
  }

  long response()
  {
    return _response;
  }

private:
  std::string _url;

protected:
  long _response;
};

class GET: public HTTP {
public:

  GET(const std::string& url_arg) :
    HTTP(url_arg)
  {
  }

  bool execute();

private:
  libtest::vchar_t _body;
};

class POST: public HTTP {
public:

  POST(const std::string& url_arg,
       const vchar_t& post_arg) :
    HTTP(url_arg),
    _post(post_arg)
  {
  }

  bool execute();

private:
  libtest::vchar_t _post;
  libtest::vchar_t _body;
};

class TRACE: public HTTP {
public:

  TRACE(const std::string& url_arg,
        const vchar_t& body_arg) :
    HTTP(url_arg),
    _body(body_arg)
  {
  }

  bool execute();

private:
  libtest::vchar_t _body;
};

class HEAD: public HTTP {
public:

  HEAD(const std::string& url_arg) :
    HTTP(url_arg)
  {
  }

  bool execute();

private:
};

} // namespace http
} // namespace libtest
