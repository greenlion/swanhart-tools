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

#pragma once

#include "libgearman/magic.h"

struct gearman_universal_st;

/**
 * @ingroup gearman_packet
 */
struct gearman_packet_st
{
  struct Options {
    const bool is_allocated;
    bool complete;
    bool free_data;

    Options(bool is_allocted_) :
      is_allocated(is_allocted_),
      complete(false),
      free_data(false)
    { }
  } options;
  enum gearman_magic_t magic;
  gearman_command_t command;
  uint8_t argc;
  size_t args_size;
  size_t data_size;
  gearman_universal_st *universal;
  gearman_packet_st *next;
  gearman_packet_st *prev;
  char *args;
  const void *data;
  char *arg[GEARMAN_MAX_COMMAND_ARGS];
  size_t arg_size[GEARMAN_MAX_COMMAND_ARGS];
  char args_buffer[GEARMAN_ARGS_BUFFER_SIZE];
#ifdef GEARMAN_PACKET_TRACE
  uint32_t _id;
#endif

  gearman_packet_st(bool is_allocted_= false) :
    options(is_allocted_),
    magic(GEARMAN_MAGIC_TEXT),
    command(GEARMAN_COMMAND_TEXT),
    argc(0),
    args_size(0),
    data_size(0),
    universal(0),
    next(0),
    prev(0),
    args(0),
    data(0)
  {
  }

  ~gearman_packet_st()
  {
    reset();
  }

  void free__data();

  bool failed() const
  {
    if (command == GEARMAN_COMMAND_WORK_FAIL)
    {
      return true;
    }

    return false;
  }

  size_t size() const
  {
    return data_size;
  }

  const char* value(size_t& length_) const
  {
    length_= data_size;

    if (length_)
    {
      return (const char*)data;
    }

    return NULL;
  }

  const char* value() const
  {
    if (data_size)
    {
      return (const char*)data;
    }

    return NULL;
  }

  void reset();
};
