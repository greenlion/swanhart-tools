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

/*
  Structures for generic tests.
*/

#pragma once

#ifndef YATL_FULL
# define YATL_FULL 1
#endif

#ifndef __PRETTY_FUNCTION__
# define __PRETTY_FUNCTION__ __func__
#endif

#define YATL_STRINGIFY(x) #x
#define YATL_TOSTRING(x) YATL_STRINGIFY(x)
#define YATL_AT __FILE__ ":" YATL_TOSTRING(__LINE__)
#define YATL_AT_PARAM __func__, AT
#define YATL_UNIQUE __FILE__ ":" YATL_TOSTRING(__LINE__) "_unique"
#define YATL_UNIQUE_FUNC_NAME __FILE__ ":" YATL_TOSTRING(__LINE__) "_unique_func"

#define LIBYATL_DEFAULT_PARAM __FILE__, __LINE__, __PRETTY_FUNCTION__

#include <cstdio>
#include <cstdlib>
#include <arpa/inet.h>

#include <libtest/visibility.h>
#include <libtest/version.h>

#include <libtest/vchar.hpp>
#include <libtest/error.h>
#include <libtest/exception.hpp>
#include <libtest/exception/disconnected.hpp>
#include <libtest/exception/fatal.hpp>
#include <libtest/result.hpp>

#include <libtest/has.hpp>
#include <libtest/error.h>
#include <libtest/strerror.h>
#include <libtest/timer.hpp>
#include <libtest/alarm.h>
#include <libtest/stream.h>
#include <libtest/comparison.hpp>
#include <libtest/server.h>
#include <libtest/server_container.h>
#include <libtest/wait.h>
#include <libtest/callbacks.h>
#include <libtest/test.h>
#include <libtest/dream.h>
#include <libtest/core.h>
#include <libtest/runner.h>
#include <libtest/port.h>
#include <libtest/is_local.hpp>
#include <libtest/socket.hpp>
#include <libtest/collection.h>
#include <libtest/framework.h>
#include <libtest/get.h>
#include <libtest/cmdline.h>
#include <libtest/string.hpp>
#include <libtest/binaries.h>
#include <libtest/http.hpp>
#include <libtest/cpu.hpp>
#include <libtest/tmpfile.hpp>
#include <libtest/client.hpp>
#include <libtest/thread.hpp>
#include <libtest/ssl.h>

