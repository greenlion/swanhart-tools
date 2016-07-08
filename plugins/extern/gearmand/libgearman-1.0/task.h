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
 * @brief Task Declarations
 */

#pragma once

#include <libgearman-1.0/actions.h>

#include <libgearman-1.0/interface/task.h>

#ifdef __cplusplus
extern "C" {
#endif


/**
 * Free a task structure.
 *
 * @param[in] task Structure previously initialized with one of the
 *  gearman_client_add_task() functions.
 */
GEARMAN_API
void gearman_task_free(gearman_task_st *task);


/**
 * Get context for a task.
 */
GEARMAN_API
void *gearman_task_context(const gearman_task_st *task);

/**
 * Set context for a task.
 */
GEARMAN_API
void gearman_task_set_context(gearman_task_st *task, void *context);

/**
 * Get function name associated with a task.
 */
GEARMAN_API
const char *gearman_task_function_name(const gearman_task_st *task);

/**
 * Get unique identifier for a task.
 */
GEARMAN_API
const char *gearman_task_unique(const gearman_task_st *task);

/**
 * Get job handle for a task.
 */
GEARMAN_API
const char *gearman_task_job_handle(const gearman_task_st *task);

/**
 * Get status on whether a task is known or not.
 */
GEARMAN_API
bool gearman_task_is_known(const gearman_task_st *task);

/**
 * Get status on whether a task is running or not.
 */
GEARMAN_API
bool gearman_task_is_running(const gearman_task_st *task);

/**
 * Get the numerator of percentage complete for a task.
 */
GEARMAN_API
uint32_t gearman_task_numerator(const gearman_task_st *task);

/**
 * Get the denominator of percentage complete for a task.
 */
GEARMAN_API
uint32_t gearman_task_denominator(const gearman_task_st *task);

/**
 * Give allocated memory to task. After this, the library will be responsible
 * for freeing the workload memory when the task is destroyed.
 */
GEARMAN_API
void gearman_task_give_workload(gearman_task_st *task, const void *workload,
                                size_t workload_size);

/**
 * Send packet workload for a task.
 */
GEARMAN_API
size_t gearman_task_send_workload(gearman_task_st *task, const void *workload,
                                  size_t workload_size,
                                  gearman_return_t *ret_ptr);

/**
 * Get result data being returned for a task.
 */
GEARMAN_API
const void *gearman_task_data(const gearman_task_st *task);

/**
 * Get result data size being returned for a task.
 */
GEARMAN_API
size_t gearman_task_data_size(const gearman_task_st *task);

/**
 * Take allocated result data from task. After this, the caller is responsible
 * for free()ing the memory.
 */
GEARMAN_API
void *gearman_task_take_data(gearman_task_st *task, size_t *data_size);

/**
 * Read result data into a buffer for a task.
 */
GEARMAN_API
size_t gearman_task_recv_data(gearman_task_st *task, void *data,
                              size_t data_size, gearman_return_t *ret_ptr);

GEARMAN_API
const char *gearman_task_error(const gearman_task_st *task);

GEARMAN_API
gearman_result_st *gearman_task_result(gearman_task_st *task);

GEARMAN_API
gearman_return_t gearman_task_return(const gearman_task_st *task);

GEARMAN_API
const char *gearman_task_strstate(const gearman_task_st *);

GEARMAN_API
bool gearman_task_has_exception(const gearman_task_st* task_shell);

GEARMAN_API
gearman_string_t gearman_task_exception(const gearman_task_st *);

/**
 * Get status on whether a task is running or not.
 */
GEARMAN_API
bool gearman_task_is_finished(const gearman_task_st *task);

/** @} */

#ifdef __cplusplus
}
#endif
