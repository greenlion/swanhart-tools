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

#include <iostream>
#include <ostream>

static inline std::ostream& operator<<(std::ostream& output, const gearman_packet_st &arg)
{
  const char* command_str= gearman_enum_strcommand(arg.command);

  output << std::boolalpha 
    << "command: " << command_str
    << " argc: " << int(arg.argc);

  for (uint8_t x= 0; x < arg.argc; x++)
  {
    output << "arg[" << int(x) << "]: ";
    output.write(arg.arg[x], arg.arg_size[x]);
    output << " ";
  }

  return output;
}

static inline std::ostream& operator<<(std::ostream& output, const gearman_task_st &arg)
{
  output << std::boolalpha 
    << "job: " << gearman_task_job_handle(&arg)
    << " unique: " << gearman_task_unique(&arg)
    << " has_result:" << bool(arg.result_ptr)
    << " complete: " << gearman_task_numerator(&arg) << "/" << gearman_task_denominator(&arg)
    << " state: " << gearman_task_strstate(&arg)
    << " is_known: " << gearman_task_is_known(&arg)
    << " is_running: " << gearman_task_is_running(&arg)
    << " ret: " << gearman_task_return(&arg);

  if (arg.recv)
  {
    output << " " << *arg.recv;
  }

  return output;
}
