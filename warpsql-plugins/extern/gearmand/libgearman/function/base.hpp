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

#include <cstring>
#include <memory>

#include "libgearman/vector.h"

enum gearman_function_error_t {
  GEARMAN_FUNCTION_SUCCESS= GEARMAN_SUCCESS,
  GEARMAN_FUNCTION_FATAL= GEARMAN_FATAL,
  GEARMAN_FUNCTION_SHUTDOWN= GEARMAN_SHUTDOWN,
  GEARMAN_FUNCTION_ERROR= GEARMAN_ERROR
};

struct _worker_function_st
{
  struct _options {
    bool packet_in_use;
    bool change;
    bool remove;

    _options() :
      packet_in_use(true),
      change(true),
      remove(false)
    { }

  } options;

  struct _worker_function_st *next;
  struct _worker_function_st *prev;

  private:
  char* _function_name;
  size_t _function_length;
  size_t _namespace_length;
  void* _context;
  struct gearman_function_t _function;
  int _timeout;

  public:

  _worker_function_st(const gearman_function_t& function_, void *context_) : 
    next(NULL),
    prev(NULL),
    _function_name(NULL),
    _function_length(0),
    _namespace_length(0),
    _context(context_),
    _function(function_),
    _timeout(0)
  { }

  virtual bool has_callback() const= 0;

  virtual gearman_function_error_t callback(gearman_job_st* job, void *context_arg)= 0;

  bool init(gearman_vector_st* namespace_,
            const char *name_, const size_t size,
            const int timeout_)
  {
    _timeout= timeout_;

    _namespace_length= gearman_string_length(namespace_);
    _function_length= _namespace_length +size;
    _function_name= new (std::nothrow) char[_function_length +1];
    if (_function_name == NULL)
    {
      return false;
    }

    char *ptr= _function_name;
    if (gearman_string_length(namespace_))
    {
      memcpy(ptr, gearman_string_value(namespace_), gearman_string_length(namespace_));
      ptr+= gearman_string_length(namespace_);
    }

    memcpy(ptr, name_, size);
    _function_name[_function_length]= 0;

    return true;
  }

  int timeout() const
  {
    return _timeout;
  }

  const char *name() const
  {
    return _function_name;
  }

  const char *function_name() const
  {
    return _function_name +_namespace_length;
  }

  size_t function_length() const
  {
    return length() -_namespace_length;
  }

  size_t length() const
  {
    return _function_length;
  }

  const gearman_function_t& function()
  {
    return _function;
  }

  void* context()
  {
    return _context;
  }

  virtual ~_worker_function_st()
  {
    if (options.packet_in_use)
    {
      gearman_packet_free(&_packet);
    }

    delete [] _function_name;
  }

  gearman_packet_st& packet()
  {
    return _packet;
  }

private:
  gearman_packet_st _packet;
};
