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

#include "gear_config.h"
#include <libgearman/common.h>
#include "libgearman/vector.h"

#include <cstdio>
#include <cstring>

namespace libgearman {
namespace protocol {

static inline gearman_return_t __submit(gearman_universal_st& universal,
                                        gearman_packet_st& message,
                                        const gearman_command_t command,
                                        const gearman_unique_t& unique,
                                        const gearman_string_t &function,
                                        const gearman_string_t &workload)
{
  const void *args[3];
  size_t args_size[3];

  /**
    @todo fix it so that NULL is done by default by the API not by happenstance.
  */
  char function_buffer[1024];
  if (universal._namespace)
  {
    char *ptr= function_buffer;
    memcpy(ptr, gearman_string_value(universal._namespace), gearman_string_length(universal._namespace)); 
    ptr+= gearman_string_length(universal._namespace);

    memcpy(ptr, gearman_c_str(function), gearman_size(function) +1);
    ptr+= gearman_size(function);

    args[0]= function_buffer;
    args_size[0]= ptr -function_buffer +1;
  }
  else
  {
    args[0]= gearman_c_str(function);
    args_size[0]= gearman_size(function) +1;
  }

  args[1]= gearman_c_str(unique);
  args_size[1]= gearman_size(unique) +1;

  args[2]= gearman_c_str(workload);
  args_size[2]= gearman_size(workload);

  return gearman_packet_create_args(universal, message,
                                    GEARMAN_MAGIC_REQUEST,
                                    command,
                                    args, args_size,
                                    3);
}

gearman_return_t submit(gearman_universal_st& universal,
                        gearman_packet_st& message,
                        const gearman_unique_t& unique,
                        const gearman_command_t command,
                        const gearman_string_t &function,
                        const gearman_string_t &workload)
{
  return __submit(universal, message, command, unique, function, workload);
}

gearman_return_t submit_background(gearman_universal_st& universal,
                                   gearman_packet_st& message,
                                   const gearman_unique_t& unique,
                                   const gearman_command_t command,
                                   const gearman_string_t &function,
                                   const gearman_string_t &workload)
{
  return __submit(universal, message, command, unique, function, workload);
}

gearman_return_t submit_epoch(gearman_universal_st& universal,
                              gearman_packet_st& message,
                              const gearman_unique_t& unique,
                              const gearman_string_t &function,
                              const gearman_string_t &workload,
                              time_t when)
{
  const void *args[4];
  size_t args_size[4];

  /**
    @todo fix it so that NULL is done by default by the API not by happenstance.
  */
  char function_buffer[1024];
  if (universal._namespace)
  {
    char *ptr= function_buffer;
    memcpy(ptr, gearman_string_value(universal._namespace), gearman_string_length(universal._namespace)); 
    ptr+= gearman_string_length(universal._namespace);

    memcpy(ptr, gearman_c_str(function), gearman_size(function) +1);
    ptr+= gearman_size(function);

    args[0]= function_buffer;
    args_size[0]= ptr -function_buffer +1;
  }
  else
  {
    args[0]= gearman_c_str(function);
    args_size[0]= gearman_size(function) +1;
  }

  args[1]= gearman_c_str(unique);
  args_size[1]= gearman_size(unique) +1;

  char time_string[30];
  int length= snprintf(time_string, sizeof(time_string), "%" PRIu64, static_cast<int64_t>(when));
  args[2]= time_string;
  args_size[2]= length +1;

  args[3]= gearman_c_str(workload);
  args_size[3]= gearman_size(workload);

  return gearman_packet_create_args(universal, message,
                                    GEARMAN_MAGIC_REQUEST,
                                    GEARMAN_COMMAND_SUBMIT_JOB_EPOCH,
                                    args, args_size,
                                    4);
}

} // namespace protocol
} // namespace libgearman

