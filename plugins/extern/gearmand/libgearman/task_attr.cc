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

#include "libgearman/assert.hpp"

#include <cstring>
#include <memory>

gearman_task_st *gearman_next(gearman_task_st *shell)
{
  if (shell and shell->impl())
  {
    return shell->impl()->next;
  }

  return NULL;
}

gearman_task_attr_t gearman_task_attr_init(gearman_job_priority_t priority)
{
  gearman_task_attr_t local= { GEARMAN_TASK_ATTR_FOREGROUND, priority, {{0}} };

  return local;
}

gearman_task_attr_t gearman_task_attr_init_background(gearman_job_priority_t priority)
{
  gearman_task_attr_t local= { GEARMAN_TASK_ATTR_BACKGROUND, priority, {{0}} };

  return local;
}

gearman_task_attr_t gearman_task_attr_init_epoch(time_t epoch, gearman_job_priority_t priority)
{
  gearman_task_attr_t local= { GEARMAN_TASK_ATTR_EPOCH, priority, {{0}} };
  local.options.epoch.value= epoch;

  return local;
}

time_t gearman_task_attr_has_epoch(const gearman_task_attr_t *self)
{
  if (self and self->kind == GEARMAN_TASK_ATTR_EPOCH)
  {
    return self->options.epoch.value;
  }

  return 0;
}

gearman_job_priority_t gearman_task_attr_priority(const gearman_task_attr_t *self)
{
  if (self == NULL)
  {
    return GEARMAN_JOB_PRIORITY_NORMAL;
  }

  return self->priority;
}

bool gearman_task_attr_is_background(const gearman_task_attr_t *self)
{
  if (self)
  {
    return (self->kind == GEARMAN_TASK_ATTR_BACKGROUND or self->kind == GEARMAN_TASK_ATTR_EPOCH);
  }
  return false;
}
