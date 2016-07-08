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

#include <libhostile/function.h>
#include <libhostile/initialize.h>

#include <errno.h>
#include <pthread.h>
#include <stdio.h>
#include <stdlib.h>
#include <time.h>
#include <unistd.h>

static int not_until= 500;
static struct function_st __function;

static pthread_once_t function_lookup_once = PTHREAD_ONCE_INIT;
static void set_local(void)
{
  __function= set_function("write", "HOSTILE_WRITE");
}

#define __WRITE_DEFAULT_ERROR ECONNRESET
static __thread int __default_error= __WRITE_DEFAULT_ERROR;

ssize_t write(int fd, const void *buf, size_t count)
{
  hostile_initialize();
  (void) pthread_once(&function_lookup_once, set_local);

  if (is_called() == false)
  {
    if (__function.frequency)
    {
      if (--not_until < 0 && random() % __function.frequency)
      {
        int ret= -1;
        struct stat statbuf;
        fstat(fd, &statbuf);

        if (S_ISSOCK(statbuf.st_mode))
        {
          switch (__default_error)
          {
            case EIO:
              close(fd);
              errno= EIO;
              break;

            case ENETDOWN:
              close(fd);
              errno= ENETDOWN;
              break;

            case ECONNRESET:
            default:
              close(fd);
              errno= ECONNRESET;
              break;
          }
        }
        else
        {
          switch (__default_error)
          {
            case EIO:
              errno= EIO;
              ret= 0;
              break;

            case ENOSPC:
              errno= ENOSPC;
              ret= 0;
              break;

            case EFBIG:
            default:
              errno= EFBIG;
              ret= 0;
              break;
          }
        }

        return ret;
      }
    }
  }

  set_called();
  ssize_t ret= __function.function.write(fd, buf, count);
  reset_called();

  return ret;
}
