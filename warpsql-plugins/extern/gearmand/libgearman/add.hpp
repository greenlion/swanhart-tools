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

/**
 * @file
 * @brief Client Declarations
 */

#pragma once

gearman_task_st *add_task(Client& client,
                          void *context,
                          gearman_command_t command,
                          const gearman_string_t &function,
                          gearman_unique_t &unique,
                          const gearman_string_t &work,
                          time_t when,
                          const gearman_actions_t &actions);

gearman_task_st *add_task_ptr(Client& client,
                              gearman_task_st *task,
                              void *context,
                              gearman_command_t command,
                              const char *function_name,
                              const char *unique,
                              const void *workload_str, size_t workload_size,
                              time_t when,
                              gearman_return_t& ret_ptr,
                              const gearman_actions_t &actions);

gearman_task_st *add_task(Client& client,
                          gearman_task_st *task,
                          void *context,
                          gearman_command_t command,
                          const gearman_string_t &function,
                          gearman_unique_t &unique,
                          const gearman_string_t &workload,
                          time_t when,
                          const gearman_actions_t &actions);

gearman_task_st *add_reducer_task(Client *client,
                                  gearman_command_t command,
                                  const gearman_job_priority_t priority,
                                  const gearman_string_t &function,
                                  const gearman_string_t &reducer,
                                  const gearman_unique_t &unique,
                                  const gearman_string_t &workload,
                                  const gearman_actions_t &actions,
                                  const time_t epoch,
                                  void *context);
