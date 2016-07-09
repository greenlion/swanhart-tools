/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Libgearman library
 *
 *  Copyright (C) 2011-2013 Data Differential, http://datadifferential.com/
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

#include <cstddef>
#include <cstdlib>
#include <cstdarg>

#include "libgearman-1.0/string.h"

#define GEARMAN_VECTOR_BLOCK_SIZE 1024*4

/**
  vectors are always under our control so we make some assumptions about them.

  1) is_initialized is always valid.
  2) A string once intialized will always be, until free where we
     unset this flag.
*/
struct gearman_vector_st {
  char *end;
  char *string;
  size_t current_size;

  struct Options {
    bool is_allocated;
    bool is_initialized;
    Options() :
      is_allocated(false),
      is_initialized(true)
    { }
  } options;

  gearman_vector_st() :
    end(NULL),
    string(NULL),
    current_size(0)
  {
  }

  gearman_vector_st(const gearman_vector_st& copy) :
    end(NULL),
    string(NULL),
    current_size(0)
  {
    store(copy);
  }

  gearman_vector_st(const size_t reserve);

  ~gearman_vector_st();

  bool resize(const size_t);
  bool reserve(const size_t);

  int	vec_printf(const char *format__, ...); // __printflike(1, 2);
  int	vec_append_printf(const char *format__, ...); // __printflike(1, 2);

  void clear()
  {
    end= string;
    if (current_size)
    {
      string[0]= 0;
    }
  }

  const char* value() const
  {
    return string;
  }

  const char* c_str() const
  {
    return string;
  }

  const void* void_ptr() const
  {
    return (const void*)string;
  }

  bool empty() const
  {
    return string == end;
  }

  size_t capacity() const
  {
    // We tell a white lie about size since we always keep things null
    // terminated
    if (current_size == 1)
    {
      return 0;
    }

    return current_size;
  }

  char* ptr(size_t expect)
  {
    if (resize(expect +1))
    {
      end= string +expect;
      string[expect]= 0;
      return string;
    }

    return NULL;
  }

  bool store(const gearman_vector_st&);
  bool store(const char*, const size_t);
  bool append(const char* arg_, const size_t arg_length_);
  bool append_character(const char character);

  size_t size() const;
  gearman_string_t take();

private:
  void init();
  int	vec_size_printf(const char *format__, va_list args__);
  int	vec_ptr_printf(const int required_size, const char *format__, va_list args__);
};

gearman_vector_st *gearman_string_create(gearman_vector_st *string,
                                         size_t initial_size);


gearman_vector_st *gearman_string_create(gearman_vector_st *self, 
                                         const char *str, size_t initial_size);
