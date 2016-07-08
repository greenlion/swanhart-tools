/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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

#include "gear_config.h"
#include <libgearman/common.h>

#include <libgearman/packet.hpp>
#include <libgearman/function/base.hpp>

#include <libgearman/function/make.hpp>
#include <libgearman/function/function_v1.hpp>
#include <libgearman/function/function_v2.hpp>
#include <libgearman/function/partition.hpp>
#include <libgearman/function/null.hpp>

_worker_function_st *make(gearman_vector_st* namespace_arg,
                          const char *name, size_t name_length, 
                          const gearman_function_t &function_, 
                          void *context_arg, const int timeout_)
{
  _worker_function_st *function= NULL;

  switch (function_.kind)
  {
    case GEARMAN_WORKER_FUNCTION_V1:
      function= new (std::nothrow) FunctionV1(function_, function_.callback.function_v1.func, context_arg);
      break;

    case GEARMAN_WORKER_FUNCTION_V2:
      function= new (std::nothrow) FunctionV2(function_, function_.callback.function_v2.func, context_arg);
      break;

    case GEARMAN_WORKER_FUNCTION_PARTITION:
      function= new (std::nothrow) Partition(function_,
                                             function_.callback.partitioner.func, 
                                             function_.callback.partitioner.aggregator,
                                             context_arg);
      break;

    case GEARMAN_WORKER_FUNCTION_NULL:
      function= new (std::nothrow) Null(function_, context_arg);
      break;
  }

  if (function and function->init(namespace_arg, name, name_length, timeout_) == false)
  {
    delete function;
    return NULL;
  }

  return function;
}
