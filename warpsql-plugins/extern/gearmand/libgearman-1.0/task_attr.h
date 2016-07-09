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

enum gearman_task_attr_kind_t {
  GEARMAN_TASK_ATTR_FOREGROUND,
  GEARMAN_TASK_ATTR_BACKGROUND,
  GEARMAN_TASK_ATTR_EPOCH
};

struct gearman_task_attr_epoch_t {
  time_t value;
};

struct gearman_task_attr_t {
  enum gearman_task_attr_kind_t kind;
  gearman_job_priority_t priority;
  union {
    char bytes[sizeof(struct gearman_task_attr_epoch_t)];
    struct gearman_task_attr_epoch_t epoch;
  } options;
};

#ifdef __cplusplus
extern "C" {
#endif

GEARMAN_API
  gearman_task_st *gearman_next(gearman_task_st *task);

GEARMAN_API
  gearman_task_attr_t gearman_task_attr_init(gearman_job_priority_t priority);

GEARMAN_API
  gearman_task_attr_t gearman_task_attr_init_epoch(time_t epoch, gearman_job_priority_t priority);

GEARMAN_API
  gearman_task_attr_t gearman_task_attr_init_background(gearman_job_priority_t priority);

#ifdef __cplusplus
}
#endif
