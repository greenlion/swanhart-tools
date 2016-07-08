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
#include <libgearman/add.hpp>
#include <libgearman/universal.hpp>

#include "libgearman/assert.hpp"

#include <cerrno>

static inline gearman_command_t pick_command_by_priority(const gearman_job_priority_t &arg)
{
  if (arg == GEARMAN_JOB_PRIORITY_NORMAL)
    return GEARMAN_COMMAND_SUBMIT_JOB;
  else if (arg == GEARMAN_JOB_PRIORITY_HIGH)
    return GEARMAN_COMMAND_SUBMIT_JOB_HIGH;

  return GEARMAN_COMMAND_SUBMIT_JOB_LOW;
}

static inline gearman_command_t pick_command_by_priority_background(const gearman_job_priority_t &arg)
{
  if (arg == GEARMAN_JOB_PRIORITY_NORMAL)
  {
    return GEARMAN_COMMAND_SUBMIT_JOB_BG;
  }
  else if (arg == GEARMAN_JOB_PRIORITY_HIGH)
  {
    return GEARMAN_COMMAND_SUBMIT_JOB_HIGH_BG;
  }

  return GEARMAN_COMMAND_SUBMIT_JOB_LOW_BG;
}



gearman_task_st *gearman_execute(gearman_client_st *client_shell,
                                 const char *function_name, size_t function_length,
                                 const char *unique_str, size_t unique_length,
                                 gearman_task_attr_t *task_attr,
                                 gearman_argument_t *arguments,
                                 void *context)
{
  if (client_shell == NULL or client_shell->impl() == NULL)
  {
    return NULL;
  }

  Client* client= client_shell->impl();

  gearman_argument_t null_arg= gearman_argument_make(0, 0, 0, 0);
  if (arguments == NULL)
  {
    arguments= &null_arg;
  }

  if (function_name == NULL or function_length == 0)
  {
    gearman_error(client->universal, GEARMAN_INVALID_ARGUMENT, "function_name was NULL");
    return NULL;
  }
  gearman_string_t function= { function_name, function_length };

  gearman_task_st *task= NULL;
  gearman_unique_t unique= gearman_unique_make(unique_str, unique_length);
  if (task_attr)
  {
    switch (task_attr->kind)
    {
    case GEARMAN_TASK_ATTR_BACKGROUND:
      task= add_task(*client,
                     context,
                     pick_command_by_priority_background(task_attr->priority),
                     function,
                     unique,
                     arguments->value,
                     time_t(0),
                     gearman_actions_execute_defaults());
      break;

    case GEARMAN_TASK_ATTR_EPOCH:
      task= add_task(*client,
                     context,
                     GEARMAN_COMMAND_SUBMIT_JOB_EPOCH,
                     function,
                     unique,
                     arguments->value,
                     gearman_task_attr_has_epoch(task_attr),
                     gearman_actions_execute_defaults());
      break;

    case GEARMAN_TASK_ATTR_FOREGROUND:
      task= add_task(*client,
                     context,
                     pick_command_by_priority(task_attr->priority),
                     function,
                     unique,
                     arguments->value,
                     time_t(0),
                     gearman_actions_execute_defaults());
      break;
    }
  }
  else
  {
    task= add_task(*client,
                   NULL,
                   GEARMAN_COMMAND_SUBMIT_JOB,
                   function,
                   unique,
                   arguments->value,
                   time_t(0),
                   gearman_actions_execute_defaults());
  }

  if (task == NULL)
  {
    gearman_universal_error_code(client->universal);

    return NULL;
  }

  task->impl()->type= GEARMAN_TASK_KIND_EXECUTE;
  gearman_client_run_tasks(client->shell());

  return task;
}

gearman_task_st *gearman_execute_by_partition(gearman_client_st *client_shell,
                                              const char *partition_function, const size_t partition_function_length,
                                              const char *function_name, const size_t function_name_length,
                                              const char *unique_str, const size_t unique_length,
                                              gearman_task_attr_t *task_attr,
                                              gearman_argument_t *arguments,
                                              void *context)
{
  if (client_shell == NULL or client_shell->impl() == NULL)
  {
    errno= EINVAL;
    return NULL;
  }

  Client* client= client_shell->impl();

  if ((partition_function == NULL) or (partition_function_length == 0))
  {
    gearman_error(client->universal, GEARMAN_INVALID_ARGUMENT, "partition_function was NULL");
    return NULL;
  }

  if ((function_name == NULL) or (function_name_length == 0))
  {
    gearman_error(client->universal, GEARMAN_INVALID_ARGUMENT, "function_name was NULL");
    return NULL;
  }
  
  universal_reset_error(client->universal);

  gearman_task_st *task= NULL;
  gearman_string_t partition= { partition_function, partition_function_length };
  gearman_string_t function= { function_name, function_name_length };
  gearman_unique_t unique= gearman_unique_make(unique_str, unique_length);

  if (task_attr)
  {
    switch (task_attr->kind)
    {
    case GEARMAN_TASK_ATTR_BACKGROUND:
      task= add_reducer_task(client,
                             GEARMAN_COMMAND_SUBMIT_REDUCE_JOB_BACKGROUND,
                             task_attr->priority,
                             partition,
                             function,
                             unique,
                             arguments->value,
                             gearman_actions_execute_defaults(),
                             time_t(0),
                             context);
      break;

    case GEARMAN_TASK_ATTR_EPOCH:
      gearman_error(client->universal, GEARMAN_INVALID_ARGUMENT, "EPOCH is not currently supported for gearman_client_execute_reduce()");
      return NULL;
#if 0
      task= add_task(client,
                     GEARMAN_COMMAND_SUBMIT_REDUCE_JOB_BACKGROUND,
                     task_attr->priority,
                     partition,
                     function,
                     unique,
                     arguments->value,
                     gearman_actions_execute_defaults(),
                     gearman_work_epoch(task_attr),
                     context);
      break;
#endif

    case GEARMAN_TASK_ATTR_FOREGROUND:
      task= add_reducer_task(client,
                             GEARMAN_COMMAND_SUBMIT_REDUCE_JOB,
                             task_attr->priority,
                             partition,
                             function,
                             unique,
                             arguments->value,
                             gearman_actions_execute_defaults(),
                             time_t(0),
                             context);
      break;
    }
  }
  else
  {
    task= add_reducer_task(client,
                           GEARMAN_COMMAND_SUBMIT_REDUCE_JOB,
                           GEARMAN_JOB_PRIORITY_NORMAL,
                           partition,
                           function,
                           unique,
                           arguments->value,
                           gearman_actions_execute_defaults(),
                           time_t(0),
                           NULL);
  }

  if (task)
  {
    do {
      gearman_return_t rc;
      if (gearman_failed(rc= gearman_client_run_tasks(client->shell())))
      {
        gearman_gerror(client->universal, rc);
        gearman_task_free(task);
        return NULL;
      }
    } while (gearman_continue(gearman_task_return(task)));
  }

  return task;
}
