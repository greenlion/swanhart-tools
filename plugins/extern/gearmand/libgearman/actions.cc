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

#include <libgearman/unique.hpp>
#include <libgearman/result.hpp>

#include "libgearman/assert.hpp"
#include "libgearman/vector.h"

#include <memory>

struct gearman_result_st;

static gearman_return_t _client_pause_data(gearman_task_st* shell)
{
  Task* task= shell->impl();
  if (task->options.is_paused)
  {
    task->options.is_paused= false;
    return GEARMAN_SUCCESS;
  }

  if (gearman_task_data_size(shell))
  {
    if (gearman_task_result(shell))
    {
      gearman_task_result(shell)->clear();
      gearman_task_result(shell)->reserve(gearman_task_data_size(shell));
    }
    else
    {
      if (task->create_result(gearman_task_data_size(shell)) == false)
      {
        return gearman_error(task->client->universal, GEARMAN_MEMORY_ALLOCATION_FAILURE, "Failed to create result_st");
      }
    }
    assert_msg(task->result(), "programmer error, result_ptr has not been allocated for task");

    gearman_string_append(gearman_task_mutable_result(shell)->mutable_string(), static_cast<const char*>(gearman_task_data(shell)), gearman_task_data_size(shell));
  }

  if (task->recv->command == GEARMAN_COMMAND_WORK_DATA)
  { }
  else if (task->recv->command == GEARMAN_COMMAND_WORK_WARNING)
  { }
  else // GEARMAN_COMMAND_WORK_COMPLETE or GEARMAN_COMMAND_WORK_EXCEPTION
  {
    return GEARMAN_SUCCESS;
  }

  task->options.is_paused= true;

  return GEARMAN_PAUSE;
}

static gearman_return_t _client_pause_complete(gearman_task_st *task)
{
  return _client_pause_data(task);
}


static gearman_return_t _client_pause_status(gearman_task_st* shell)
{
  Task* task= shell->impl();
  assert_msg(task->recv->command == GEARMAN_COMMAND_WORK_STATUS or
             task->recv->command == GEARMAN_COMMAND_STATUS_RES_UNIQUE or
             task->recv->command == GEARMAN_COMMAND_STATUS_RES, "status has been called out of order for task, or was registered to run on non-status callback, see gearman_actions_t(3)");
  if (task->options.is_paused)
  {
    task->options.is_paused= false;
    return GEARMAN_SUCCESS;
  }
  task->options.is_paused= true;

  return GEARMAN_PAUSE;
}

static gearman_return_t _client_pause_fail(gearman_task_st* shell)
{
  Task* task= shell->impl();
  assert_msg(task->recv->command == GEARMAN_COMMAND_WORK_FAIL, 
             "fail callback has been called out of order for task, or was registered to run on non-fail callback, see gearman_actions_t(3)");
  if (task->options.is_paused)
  {
    task->options.is_paused= false;
    return GEARMAN_SUCCESS;
  }
  task->options.is_paused= true;

  return GEARMAN_PAUSE;
}

static gearman_return_t _client_do_data(gearman_task_st* shell)
{
  Task* task= shell->impl();

  if (gearman_task_data_size(shell))
  {
    if (gearman_task_result(shell) == NULL)
    {
      if (task->create_result(gearman_task_data_size(shell)) == false)
      {
        return gearman_error(task->client->universal, GEARMAN_MEMORY_ALLOCATION_FAILURE, "Failed to create result_st");
      }
    }

    assert(gearman_task_mutable_result(shell));
    gearman_task_mutable_result(shell)->mutable_string()->append(static_cast<const char*>(gearman_task_data(shell)), gearman_task_data_size(shell));
  }

  return GEARMAN_SUCCESS;
}

static gearman_return_t _client_do_complete(gearman_task_st *shell)
{
  Task* task= shell->impl();

  if (gearman_task_data_size(shell))
  {
    if (gearman_task_result(shell) == NULL)
    {
      if (task->create_result(gearman_task_data_size(shell)) == false)
      {
        return gearman_error(task->client->universal, GEARMAN_MEMORY_ALLOCATION_FAILURE, "Failed to create result_st");
      }
    }

    gearman_string_append(gearman_task_mutable_result(shell)->mutable_string(), static_cast<const char*>(gearman_task_data(shell)), gearman_task_data_size(shell));
  }

  task->error_code(GEARMAN_SUCCESS);

  return GEARMAN_SUCCESS;
}

const gearman_actions_t &gearman_actions_default(void)
{
  static gearman_actions_t default_actions= { 0, 0, 0, 0, 0, 0, 0, 0 };

  return default_actions;
}


const gearman_actions_t &gearman_actions_do_default(void)
{
  static gearman_actions_t default_actions= { 0, 0, _client_do_data, 0, 0, _client_do_complete, 0, 0 };

  return default_actions;
}

const gearman_actions_t &gearman_actions_execute_defaults(void)
{
  static gearman_actions_t default_actions= { 0, 0, _client_do_data, 0, 0, _client_do_complete, 0, 0 };

  return default_actions;
}

const gearman_actions_t &gearman_actions_pause(void)
{
  static gearman_actions_t default_actions= { 0, 0, 
    _client_pause_data,  // gearman_data_fn
    _client_pause_data,  // gearman_warning_fn
    _client_pause_status,  // gearman_universal_status_fn
    _client_pause_complete, // gearman_complete_fn 
    _client_pause_data, // gearman_exception_fn
    _client_pause_fail }; // gearman_fail_fn

  return default_actions;
}
