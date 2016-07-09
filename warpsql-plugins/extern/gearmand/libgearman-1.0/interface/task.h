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

enum gearman_task_state_t {
  GEARMAN_TASK_STATE_NEW,
  GEARMAN_TASK_STATE_SUBMIT,
  GEARMAN_TASK_STATE_WORKLOAD,
  GEARMAN_TASK_STATE_WORK,
  GEARMAN_TASK_STATE_CREATED,
  GEARMAN_TASK_STATE_DATA,
  GEARMAN_TASK_STATE_WARNING,
  GEARMAN_TASK_STATE_STATUS,
  GEARMAN_TASK_STATE_COMPLETE,
  GEARMAN_TASK_STATE_EXCEPTION,
  GEARMAN_TASK_STATE_FAIL,
  GEARMAN_TASK_STATE_FINISHED
};

enum gearman_task_kind_t {
  GEARMAN_TASK_KIND_ADD_TASK,
  GEARMAN_TASK_KIND_EXECUTE,
  GEARMAN_TASK_KIND_DO
};

#ifdef __cplusplus
struct Task;
#endif

struct gearman_task_st
{
  struct {
    bool is_allocated;
  } options;
  void *_impl;
#ifdef __cplusplus
  struct Task* impl() const
  {
    return (Task*)(_impl);
  }

  void impl(Task* impl_)
  {
    _impl= impl_;
  }
#endif
};
