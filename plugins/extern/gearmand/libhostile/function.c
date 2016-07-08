/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 *
 *  Data Differential's libhostle
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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

#ifdef __linux

#include <libhostile/function.h>
#include <libhostile/initialize.h>

#include <dlfcn.h>
#include <pthread.h>
#include <stdio.h>
#include <stdlib.h>

static int64_t function_cache_index= 0;
struct function_st function_cache[20];

void print_function_cache_usage(void)
{
  for (struct function_st *ptr= function_cache;
       ptr->name == NULL; ptr++)
  {
    if (ptr->frequency)
    {
      fprintf(stderr, "--------------------------------------------------------\n\n");
      fprintf(stderr, "%s hostilized %d\n", ptr->name, ptr->frequency);
      fprintf(stderr, "\n--------------------------------------------------------\n");
    }
  }
}

struct function_st set_function(const char *name, const char *environ_name)
{
  struct function_st set;

  set.name= name;
  set._used= 0;

  (void)dlerror();
  set.function.ptr= dlsym(RTLD_NEXT, set.name);

  if (set.function.ptr == NULL)
  {
    fprintf(stderr, "libhostile: %s(%s)", set.name, dlerror());
    exit(EXIT_FAILURE);
  }

  set._corrupt= false;

  if (set.function.ptr == NULL)
  {
    fprintf(stderr, "libhostile: %s(%s)", set.name, dlerror());
    exit(EXIT_FAILURE);
  }

  char *ptr;
  if ((ptr= getenv(environ_name)))
  {
    set.frequency= atoi(ptr);
  }
  else
  {
    set.frequency= 0;
  }

  if (set.frequency)
  {
    fprintf(stderr, "--------------------------------------------------------\n\n");
    fprintf(stderr, "\t\tHostile Engaged -> %s\n\n", set.name);
    fprintf(stderr, "Frequency used %d\n", set.frequency);
    fprintf(stderr, "\n--------------------------------------------------------\n");
  }

  function_cache[function_cache_index]= set; 
  function_cache_index++;

  print_function_cache_usage();

  return set;
}

#endif
