
/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2013 Data Differential, http://datadifferential.com/
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

#include "libgearman/assert.hpp"
#include "libgearman/vector.hpp"
#include "libgearman-1.0/return.h"

#include <cstddef>
#include <cstdlib>
#include <cstring>

enum gearman_result_t {
  GEARMAN_RESULT_BINARY,
  GEARMAN_RESULT_BOOLEAN,
  GEARMAN_RESULT_INTEGER,
  GEARMAN_RESULT_NULL
};

struct gearman_result_st
{
  enum gearman_result_t _type;

  struct Value {
    bool _boolean;
    int64_t _integer;
    gearman_vector_st string;

    Value() :
      _boolean(false),
      _integer(0),
      string()
    { }

    Value(size_t initial_size) :
      _boolean(false),
      _integer(0),
      string(initial_size)
    { }

  } value;

  gearman_result_st();

  explicit gearman_result_st(size_t);

  bool is_null() const
  {
    return _type == GEARMAN_RESULT_NULL;
  }

  void clear()
  {
    value.string.clear();
    value._integer= 0;
    value._boolean= false;
    _type= GEARMAN_RESULT_NULL;
  }

  bool store(const gearman_string_t&);
  bool store(const char*, const size_t);
  bool store(int64_t);
  bool append(const char*, const size_t);

  size_t size() const;

  size_t capacity() const
  {
    return value.string.capacity();
  }

  bool resize(size_t arg_)
  {
    return value.string.resize(arg_);
  }

  bool reserve(size_t arg_)
  {
    return value.string.reserve(arg_);
  }

  gearman_vector_st *mutable_string()
  {
    value._integer= 0;
    value._boolean= false;
    _type= GEARMAN_RESULT_BINARY;

    return &value.string;
  }

  const gearman_vector_st *string() const
  {
    if (_type == GEARMAN_RESULT_BINARY)
    {
      return &value.string;
    }

    return NULL;
  }

  bool boolean(bool arg_)
  {
    if (_type != GEARMAN_RESULT_BOOLEAN)
    {
      clear();
      _type= GEARMAN_RESULT_BOOLEAN;
    }

    value._boolean= arg_;

    return true;
  }

  bool boolean() const;

  int64_t integer() const;
  bool integer(int64_t);

  gearman_string_t take();

  bool is_type(gearman_result_t arg)
  {
    return _type == arg;
  }

  ~gearman_result_st()
  {
  }

private:
  gearman_result_st( const gearman_result_st& );
  const gearman_result_st& operator=( const gearman_result_st& );
};


gearman_string_t gearman_result_take_string(gearman_result_st *self);
