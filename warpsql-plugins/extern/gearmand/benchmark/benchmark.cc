/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2012 Data Differential, http://datadifferential.com/
 *  Copyright (C) 2008 Brian Aker, Eric Day
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

/**
 * @file
 * @brief Common benchmark functions
 */

#include "gear_config.h"

#include <benchmark/benchmark.h>
#include <cstdio>

/*
 * Public definitions
 */

void benchmark_check_time(gearman_benchmark_st *benchmark)
{
  benchmark->jobs++;

  gettimeofday(&(benchmark->end), NULL);
  if (benchmark->end.tv_sec != benchmark->begin.tv_sec)
  {
    benchmark->total_jobs+= benchmark->jobs;

    printf("[Current: %6" PRIu64 " jobs/s, Total: %6" PRIu64 " jobs/s]\n",
           (uint64_t(benchmark->jobs) * 1000000) /
           (((uint64_t(benchmark->end.tv_sec) * 1000000) +
             uint64_t(benchmark->end.tv_usec)) -
            ((uint64_t(benchmark->begin.tv_sec) * 1000000) +
             uint64_t(benchmark->begin.tv_usec))),
           (uint64_t(benchmark->total_jobs) * 1000000) /
           (((uint64_t(benchmark->end.tv_sec) * 1000000) +
             uint64_t(benchmark->end.tv_usec)) -
            ((uint64_t(benchmark->total.tv_sec) * 1000000) +
             uint64_t(benchmark->total.tv_usec))));

    benchmark->jobs= 0;
    gettimeofday(&(benchmark->begin), NULL);
  }
}
