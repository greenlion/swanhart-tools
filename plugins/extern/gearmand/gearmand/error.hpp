/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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

#pragma once

#include <cerrno>
#include <cstdio>

static const char* _progname= NULL;

namespace gearmand {

namespace error {

void init(const char* progname_)
{
  _progname= progname_;
}

inline void perror(const char *message)
{
  char *errmsg_ptr;
  char errmsg[BUFSIZ];
  errmsg[0]= 0;

#ifdef STRERROR_R_CHAR_P
  errmsg_ptr= strerror_r(errno, errmsg, sizeof(errmsg));
#else
  strerror_r(errno, errmsg, sizeof(errmsg));
  errmsg_ptr= errmsg;
#endif
  std::cerr << _progname << ": " << message << " (" << errmsg_ptr << ")" << std::endl;
}

inline void message(const char *arg)
{
  std::cerr << _progname << ": " << arg << std::endl;
}

inline void message(const char *arg, const char *arg2)
{
  std::cerr << _progname << ": " << arg << " : " << arg2 << std::endl;
}

#ifdef DBUILDING_LIBGEARMAN
inline void message(const std::string &arg, gearmand_error_t rc)
{
  std::cerr << _progname << ": " << arg << " : " << gearmand_strerror(rc) << std::endl;
}
#endif

} // namespace error

} // namespace gearmand
