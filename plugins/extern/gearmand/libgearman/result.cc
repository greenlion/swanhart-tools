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

#include "gear_config.h"

#include "libgearman/result.hpp"
#include "libgearman/assert.hpp"

#include "libgearman-1.0/visibility.h"
#include "libgearman-1.0/result.h"
#include "libgearman/vector.h"

#include <cstdlib>
#include <limits>
#include <memory>

#include <libgearman/result.hpp>

gearman_result_st::gearman_result_st() :
  _type(GEARMAN_RESULT_NULL)
{
  value._boolean= false;
}

gearman_result_st::gearman_result_st(size_t reserve_size_) :
  _type(GEARMAN_RESULT_NULL),
  value(reserve_size_)
{
}

bool gearman_result_is_null(const gearman_result_st *self)
{
  if (self)
  {
    return self->is_null();
  }

  return true;
}

size_t gearman_result_st::size() const
{
  switch (_type)
  {
  case GEARMAN_RESULT_BINARY:
    return value.string.size();

  case GEARMAN_RESULT_BOOLEAN:
    return 1;

  case GEARMAN_RESULT_INTEGER:
    return sizeof(int64_t);

  case GEARMAN_RESULT_NULL:
    return 0;
  }

  assert_msg(false, "Invalid result");
  return size_t(0) -1;
}

int64_t gearman_result_st::integer() const
{
  switch (_type)
  {
  case GEARMAN_RESULT_BINARY:
    return atoll(value.string.value());

  case GEARMAN_RESULT_BOOLEAN:
    return value._boolean;

  case GEARMAN_RESULT_INTEGER:
    return value._integer;

  case GEARMAN_RESULT_NULL:
    return 0;
  }

  assert_msg(false, "Invalid result");
  return int64_t(0) -1;
}

int64_t gearman_result_integer(const gearman_result_st *self)
{
  if (self)
  {
    return self->integer();
  }

  return 0;
}

bool gearman_result_st::boolean() const
{
  switch (_type)
  {
  case GEARMAN_RESULT_BINARY:
    return value.string.size();

  case GEARMAN_RESULT_BOOLEAN:
    return value._boolean;

  case GEARMAN_RESULT_INTEGER:
    return value._integer ? true : false;

  case GEARMAN_RESULT_NULL:
    return false;
  }

  assert_msg(false, "Invalid result");
  return false;
}

bool gearman_result_boolean(const gearman_result_st *self)
{
  if (self)
  {
    return self->boolean();
  }

  return false;
}

size_t gearman_result_size(const gearman_result_st *self)
{
  if (self and self->_type == GEARMAN_RESULT_BINARY)
  {
    return gearman_string_length(&self->value.string);
  }

  return 0;
}

const char *gearman_result_value(const gearman_result_st *self)
{
  if (self and self->_type == GEARMAN_RESULT_BINARY)
  {
    gearman_string_t ret= gearman_string(&self->value.string);
    return gearman_c_str(ret);
  }

  return NULL;
}

gearman_string_t gearman_result_string(const gearman_result_st *self)
{
  if (not self or self->_type != GEARMAN_RESULT_BINARY)
  {
    gearman_string_t ret= {0, 0};
    return ret;
  }

  return gearman_string(&self->value.string);
}

gearman_string_t gearman_result_st::take()
{
  if (_type == GEARMAN_RESULT_BINARY and size())
  {
    gearman_string_t ret_string= value.string.take();
    clear();

    return ret_string;
  }

  static gearman_string_t ret= {0, 0};
  return ret;
}

gearman_string_t gearman_result_take_string(gearman_result_st *self)
{
  if (self)
  {
    return self->take();
  }

  static gearman_string_t ret= {0, 0};
  return ret;
}

gearman_return_t gearman_result_store_string(gearman_result_st *self, gearman_string_t arg)
{
  if (self)
  {
    if (self->store(gearman_string_param(arg)) == false)
    {
      return GEARMAN_MEMORY_ALLOCATION_FAILURE;
    }

    return GEARMAN_SUCCESS;
  }

  return GEARMAN_INVALID_ARGUMENT;
}

bool gearman_result_st::append(const char* arg, const size_t arg_length)
{
  if (_type != GEARMAN_RESULT_BINARY)
  {
    clear();
    _type= GEARMAN_RESULT_BINARY;
  }

  if (value.string.append(arg, arg_length) == false)
  {
    _type= GEARMAN_RESULT_NULL;
    return false;
  }

  return true;
}

#if 0
bool gearman_result_st::store(bool arg)
{
  if (_type != GEARMAN_RESULT_BOOLEAN)
  {
    value.string.clear();
  } 
  value._boolean= arg; 
  _type= GEARMAN_RESULT_BOOLEAN; 

  return true;
}
#endif

bool gearman_result_st::store(int64_t arg)
{
  if (_type != GEARMAN_RESULT_INTEGER)
  {
    value.string.clear();
  } 
  value._integer= arg; 
  _type= GEARMAN_RESULT_INTEGER; 

  return true;
}

bool gearman_result_st::store(const gearman_string_t& arg)
{
  return store(gearman_c_str(arg), gearman_size(arg));
}

bool gearman_result_st::store(const char* arg, const size_t arg_length)
{
  value.string.clear();
  if (value.string.store(arg, arg_length) == false)
  {
    _type= GEARMAN_RESULT_NULL;
    return false;
  }

  _type= GEARMAN_RESULT_BINARY;

  return true;
}

gearman_return_t gearman_result_store_value(gearman_result_st *self, const void *value, size_t size)
{
  if (self)
  {
    return self->store((const char*)value, size) == true ? GEARMAN_SUCCESS : GEARMAN_MEMORY_ALLOCATION_FAILURE;
  }

  return GEARMAN_INVALID_ARGUMENT;
}

bool gearman_result_st::integer(int64_t arg_)
{
  if (_type != GEARMAN_RESULT_INTEGER)
  {
    clear();
    _type= GEARMAN_RESULT_INTEGER;
  }

  value._integer= arg_;

  return true;
}

void gearman_result_store_integer(gearman_result_st *self, int64_t arg_)
{
  if (self)
  {
    self->integer(arg_);
  }
}

void gearman_result_store_boolean(gearman_result_st *self, const bool arg_)
{
  if (self)
  {
    self->boolean(arg_);
  }
}
