/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearman library
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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

#include <cstdlib>
#include <cstring>

#include <libgearman/universal.hpp>
#include <libgearman/allocator.hpp>

#include "util/memory.h"
using namespace org::tangent;

void *gearman_real_malloc(gearman_allocator_t& allocator, size_t size, const char *func, const char *file, int line)
{
  void *ptr;
  if (allocator.malloc)
  {
    ptr= allocator.malloc(size, allocator.context);
  }
  else
  {
    ptr= malloc(size);
  }

#if 0
  fprintf(stderr, "gearman_real_malloc(%s, %lu) : %p -> %s:%d\n", func, static_cast<unsigned long>(size), ptr,  file, line);
#else
  (void)func; (void)file; (void)line;
#endif


  return ptr;
}

void *gearman_real_calloc(gearman_allocator_t& allocator, size_t nelem, size_t size, const char *func, const char *file, int line)
{
  void *ptr;
  if (allocator.calloc)
  {
    ptr= allocator.calloc(nelem, size, allocator.context);
  }
  else if (allocator.malloc)
  {
    ptr= gearman_real_malloc(allocator, nelem * size, func, file, line);
    if (ptr)
    {
      memset(ptr, 0, nelem * size);
    }
  }
  else
  {
    ptr= calloc(nelem, size);
  }

#if 0
  fprintf(stderr, "gearman_real_calloc(%s, %lu) : %p -> %s:%d\n", func, static_cast<unsigned long>(size), ptr,  file, line);
#else
  (void)func; (void)file; (void)line;
#endif


  return ptr;
}

void *gearman_real_realloc(gearman_allocator_t& allocator, void *ptr, size_t size, const char *func, const char *file, int line)
{
  void *new_ptr;

  if (allocator.realloc)
  {
    new_ptr= allocator.realloc(ptr, size, allocator.context);
  }
  else if (allocator.malloc)
  {
    new_ptr= NULL;
  }
  else
  {
    new_ptr= realloc(ptr, size);
  }

#if 0
  fprintf(stderr, "gearman_real_realloc(%s, %lu) : %p -> %s:%d\n", func, static_cast<unsigned long>(size), ptr,  file, line);
#else
  (void)func; (void)file; (void)line;
#endif

  return new_ptr;
}

void gearman_real_free(gearman_allocator_t& allocator, void *& ptr, const char *func, const char *file, int line)
{
#if 0
  fprintf(stderr, "gearman_real_free(%s) : %p -> %s:%d\n", func, ptr, file, line);
#else
  (void)func; (void)file; (void)line;
#endif

  if (ptr == NULL)
  {
    return;
  }

  if (allocator.free)
  {
    allocator.free(ptr, allocator.context);
  }
  else
  {
    util::free__(ptr);
  }

  ptr= NULL;
}

gearman_allocator_t gearman_default_allocator()
{
  static gearman_allocator_t _defaults= { 0, 0, 0, 0, 0 };
  return _defaults;
}

gearman_return_t gearman_set_memory_allocator(gearman_allocator_t& allocator,
                                              gearman_malloc_fn *malloc_fn,
                                              gearman_free_fn *free_fn,
                                              gearman_realloc_fn *realloc_fn,
                                              gearman_calloc_fn *calloc_fn,
                                              void *context)
{
  /* All should be set, or none should be set */
  if (malloc_fn == NULL and free_fn == NULL and realloc_fn == NULL and calloc_fn == NULL) 
  {
    allocator= gearman_default_allocator();
  }
  else if (malloc_fn == NULL or free_fn == NULL or realloc_fn == NULL or calloc_fn == NULL)
  {
    return GEARMAN_FATAL;
  }
  else
  {
    allocator.malloc= malloc_fn;
    allocator.free= free_fn;
    allocator.realloc= realloc_fn;
    allocator.calloc= calloc_fn;
    allocator.context= context;
  }

  return GEARMAN_SUCCESS;
}
