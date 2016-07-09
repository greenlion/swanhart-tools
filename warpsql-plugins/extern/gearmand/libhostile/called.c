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

#include <libhostile/initialize.h>

#include <assert.h>
#include <stdio.h>
#include <stdlib.h>

__thread bool is_called_= false;
static __thread char** unique_ptr= NULL;

bool is_called(void)
{
  return is_called_;
}

void set_called_ptr(char* passed_pos)
{
  assert(passed_pos);
  if (passed_pos == NULL)
  {
    abort();
  }

  if (unique_ptr)
  {
    if (&passed_pos == unique_ptr)
    {
      return;
    }
  }
  set_called();
  unique_ptr= &passed_pos;
}

void reset_called_ptr(char* passed_pos)
{
  assert(unique_ptr);
  if (unique_ptr == NULL)
  {
    abort();
  }

  assert(&passed_pos == unique_ptr);
  if (&passed_pos != unique_ptr)
  {
    abort();
  }
  
  reset_called();
  unique_ptr= NULL;
}

void set_called(void)
{
  assert(is_called_ == false);
  if (is_called_ == true)
  {
    fprintf(stderr, "set_called() called when is_called_ was not set\n");
    abort();
  }
  is_called_= true;
}

void reset_called(void)
{
  assert(is_called_);
  if (is_called_ == false)
  {
    fprintf(stderr, "reset_called() called when is_called_ was not set\n");
    abort();
  }
  is_called_= false;
}

