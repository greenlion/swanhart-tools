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

gearman_function_t gearman_function_create_v1(gearman_worker_fn func)
{
  gearman_function_t _function= { GEARMAN_WORKER_FUNCTION_V1, { { 0 } } };

  _function.callback.function_v1.func= func;

  return _function;
}

gearman_function_t gearman_function_create(gearman_function_fn func)
{
  gearman_function_t _function= { GEARMAN_WORKER_FUNCTION_V2, { { 0 } } };

  _function.callback.function_v2.func= func;

  return _function;
}

gearman_function_t gearman_function_create_v2(gearman_function_fn func)
{
  return gearman_function_create(func);
}

gearman_function_t gearman_function_create_null()
{
  gearman_function_t _function= { GEARMAN_WORKER_FUNCTION_NULL, { { 0 } } };

  return _function;
}

gearman_function_t gearman_function_create_partition(gearman_function_fn func,
                                                     gearman_aggregator_fn aggregator)
{
  gearman_function_t _function= { GEARMAN_WORKER_FUNCTION_PARTITION, { { 0 } } };

  _function.callback.partitioner.func= func;
  _function.callback.partitioner.aggregator= aggregator;
  return _function;
}
