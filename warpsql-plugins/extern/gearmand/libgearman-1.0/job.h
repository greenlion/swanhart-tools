/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2013 Data Differential, http://datadifferential.com/
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
 * @brief Job Declarations
 */

#pragma once

#include <libgearman-1.0/interface/job.h>

#ifdef __cplusplus
extern "C" {
#endif

/**
 * @addtogroup gearman_job Job Declarations
 * @ingroup gearman_worker
 *
 * The job functions are used to manage jobs assigned to workers. It is most
 * commonly used with the worker interface.
 *
 * @{
 */

/** Free a job structure.
 *
 * @param[in] job Structure previously initialized with
 *  gearman_worker_grab_job().
 */
GEARMAN_API
void gearman_job_free(gearman_job_st *job);


/**
 * Send data for a running job.
 */
GEARMAN_API
gearman_return_t gearman_job_send_data(gearman_job_st *job,
                                       const void *data, size_t data_size);

/**
 * Send warning for a running job.
 */
GEARMAN_API
gearman_return_t gearman_job_send_warning(gearman_job_st *job,
                                          const void *warning,
                                          size_t warning_size);

/**
 * Send status information for a running job.
 */
GEARMAN_API
gearman_return_t gearman_job_send_status(gearman_job_st *job,
                                         uint32_t numerator,
                                         uint32_t denominator);

/**
 * Send result and complete status for a job.
 */
GEARMAN_API
gearman_return_t gearman_job_send_complete(gearman_job_st *job,
                                           const void *result,
                                           size_t result_size);

/** Send exception for a running job.
 */
GEARMAN_API
gearman_return_t gearman_job_send_exception(gearman_job_st *job,
                                            const void *exception,
                                            size_t exception_size);

/**
 * Send fail status for a job.
 */
GEARMAN_API
gearman_return_t gearman_job_send_fail(gearman_job_st *job);

/** Get job handle.
 */
GEARMAN_API
const char *gearman_job_handle(const gearman_job_st *job);

/**
 * Get the function name associated with a job.
 */
GEARMAN_API
  const char *gearman_job_function_name(const gearman_job_st *job);

/** Get the unique ID associated with a job.
 */
GEARMAN_API
  const char *gearman_job_unique(const gearman_job_st *job);

/** Get a pointer to the workload for a job.
 */
GEARMAN_API
const void *gearman_job_workload(const gearman_job_st *job);

/**
 * Get size of the workload for a job.
 */
GEARMAN_API
size_t gearman_job_workload_size(const gearman_job_st *job);

/**
 * Take allocated workload from job. After this, the caller is responsible
 * for free()ing the memory.
 */
GEARMAN_API
void *gearman_job_take_workload(gearman_job_st *job, size_t *data_size);

GEARMAN_API
gearman_worker_st *gearman_job_clone_worker(gearman_job_st *job);

GEARMAN_API
gearman_client_st *gearman_job_use_client(gearman_job_st *job);

GEARMAN_API
  const char *gearman_job_error(gearman_job_st *job);

/** @} */

#ifdef __cplusplus
}
#endif
