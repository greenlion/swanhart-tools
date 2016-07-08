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

#include "libgearman/common.h"

#include "libgearman/uuid.hpp"

#if defined(HAVE_UUID_UUID_H) && HAVE_UUID_UUID_H
#  include <uuid/uuid.h>

int safe_uuid_generate(char* buffer, size_t& length)
{
  uuid_t uuid;
  int ret;
#if defined(HAVE_UUID_GENERATE_TIME_SAFE) && HAVE_UUID_GENERATE_TIME_SAFE
  ret= uuid_generate_time_safe(uuid);
#else
  uuid_generate(uuid);
  ret= -1;
#endif

  uuid_unparse(uuid, buffer);
  length= GEARMAN_MAX_UUID_SIZE;

  buffer[length]= 0;

  return ret;
}

#else

int safe_uuid_generate(char* buffer, size_t& length)
{
  // Buffer has to be null terminated even if we are not storing anything
  buffer[0]= 0;
  length= 0;

  return -1;
}

#endif

gearman_vector_st *gearman_string_create_guid()
{
  gearman_vector_st* _guid= gearman_string_create(NULL, GEARMAN_MAX_UUID_SIZE);
  assert(_guid);
  if (_guid)
  {
    char* ptr= _guid->ptr(GEARMAN_MAX_UUID_SIZE);
    size_t created_length= GEARMAN_MAX_UUID_SIZE;
    safe_uuid_generate(ptr, created_length);
  }

  return _guid;
}
