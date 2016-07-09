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

#include <gear_config.h>

#include <libtest/test.hpp>

#include <libgearman-1.0/gearman.h>

#include "tests/workers/v2/echo_or_react_chunk.h"

gearman_return_t echo_or_react_chunk_worker_v2(gearman_job_st *job, void *split_arg)
{
  const char *workload= (const char *)gearman_job_workload(job);
  size_t workload_size= gearman_job_workload_size(job);

  bool fail= false;
  if (workload_size == test_literal_param_size("fail") and (not memcmp(workload, test_literal_param("fail"))))
  {
    fail= true;
  }
  else if (workload_size == test_literal_param_size("exception") and (not memcmp(workload, test_literal_param("exception"))))
  {
    if (gearman_failed(gearman_job_send_exception(job, test_literal_param("test exception"))))
    {
      return GEARMAN_ERROR;
    }
  }
  else if (workload_size == test_literal_param_size("warning") and (not memcmp(workload, test_literal_param("warning"))))
  {
    if (gearman_failed(gearman_job_send_warning(job, test_literal_param("test warning"))))
    {
      return GEARMAN_ERROR;
    }
  }

  size_t split_on= 1; 
  if (split_arg)
  {
    size_t *tmp= (size_t*)split_arg;
    split_on= *tmp;
  }

  if (split_on > workload_size)
  {
    split_on= workload_size;
  }

  const char* workload_ptr= &workload[0];
  size_t remaining= workload_size;
  for (size_t x= 0; x < workload_size; x+= split_on)
  {
    // Chunk
    if (gearman_failed(gearman_job_send_data(job, workload_ptr, split_on)))
    {
      return GEARMAN_ERROR;
    }
    remaining-= split_on;
    workload_ptr+= split_on;

    // report status
    {
      if (gearman_failed(gearman_job_send_status(job, (uint32_t)x, (uint32_t)workload_size)))
      {
        return GEARMAN_ERROR;
      }

      if (fail)
      {
        return GEARMAN_FAIL;
      }
    }
  }

  if (remaining)
  {
    if (gearman_failed(gearman_job_send_data(job, workload_ptr, remaining)))
    {
      return GEARMAN_ERROR;
    }
  }

  return GEARMAN_SUCCESS;
}

