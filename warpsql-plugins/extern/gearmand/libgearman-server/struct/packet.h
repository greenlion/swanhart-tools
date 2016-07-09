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

#include <libgearman-1.0/protocol.h>
#include "libgearman/magic.h"

/**
 * @ingroup gearman_packet
 */
struct gearmand_packet_st
{
  struct Options {
    bool complete;
    bool free_data;

    Options() :
      complete(false),
      free_data(false)
    { }
  } options;
  enum gearman_magic_t magic;
  enum gearman_command_t command;
  uint8_t argc;
  size_t args_size;
  size_t data_size;
  struct gearmand_packet_st *next;
  struct gearmand_packet_st *prev;
  char *args;
  const char *data;
  char *arg[GEARMAND_MAX_COMMAND_ARGS];
  size_t arg_size[GEARMAND_MAX_COMMAND_ARGS];
  char args_buffer[GEARMAND_ARGS_BUFFER_SIZE];

  gearmand_packet_st():
    magic(GEARMAN_MAGIC_TEXT),
    command(GEARMAN_COMMAND_TEXT),
    argc(0),
    args_size(0),
    data_size(0),
    next(NULL),
    prev(NULL),
    args(0),
    data(0)
  {
  }
  void reset(enum gearman_magic_t, gearman_command_t);
};

struct gearman_server_packet_st
{
  gearmand_packet_st packet;
  gearman_server_packet_st *next;

  gearman_server_packet_st():
    next(NULL)
  {
  }
};
