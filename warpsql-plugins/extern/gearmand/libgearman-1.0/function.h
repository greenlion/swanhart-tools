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

#pragma once

#ifdef __cplusplus
#include <ctime>
#else
#include <time.h>
#endif

enum gearman_function_kind_t {
  GEARMAN_WORKER_FUNCTION_NULL,
  GEARMAN_WORKER_FUNCTION_V1,
  GEARMAN_WORKER_FUNCTION_V2,
  GEARMAN_WORKER_FUNCTION_PARTITION
};

struct gearman_function_v1_t {
  gearman_worker_fn *func;
};

struct gearman_function_v2_t {
  gearman_function_fn *func;
};

struct gearman_function_partition_v1_t {
  gearman_function_fn *func;
  gearman_aggregator_fn *aggregator;
};

struct gearman_function_t {
  const enum gearman_function_kind_t kind;
  union {
    char bytes[sizeof(struct gearman_function_partition_v1_t)]; // @note gearman_function_partition_v1_t is the largest structure
    struct gearman_function_v1_t function_v1;
    struct gearman_function_v2_t function_v2;
    struct gearman_function_partition_v1_t partitioner;
  } callback;
};

#ifndef __cplusplus
typedef struct gearman_function_t gearman_function_t;
#endif

#ifdef __cplusplus
extern "C" {
#endif

GEARMAN_API
  gearman_function_t gearman_function_create(gearman_function_fn func);

GEARMAN_API
  gearman_function_t gearman_function_create_partition(gearman_function_fn func,
                                                       gearman_aggregator_fn aggregator);

GEARMAN_API
  gearman_function_t gearman_function_create_v1(gearman_worker_fn func);

GEARMAN_API
  gearman_function_t gearman_function_create_v2(gearman_function_fn func);

#ifdef __cplusplus
}
#endif
