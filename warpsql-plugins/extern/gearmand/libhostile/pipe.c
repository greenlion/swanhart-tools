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

#include <assert.h>
#include <errno.h>
#include <pthread.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <time.h>
#include <unistd.h>

static struct function_st __function_pipe;
static struct function_st __function_pipe2;

static pthread_once_t function_lookup_once= PTHREAD_ONCE_INIT;
static void set_local(void)
{
  __function_pipe= set_function("pipe", "HOSTILE_PIPE");
#if defined(HAVE_PIPE2) && HAVE_PIPE2
  __function_pipe2= set_function("pipe2", "HOSTILE_PIPE2");
#endif
}

int pipe(int pipefd_arg[2])
{
  hostile_initialize();

  (void) pthread_once(&function_lookup_once, set_local);

  int stored_errno;
  int ret;
  {
    set_called();

    if (pipefd_arg)
    {
      int pipefd[2];
      ret= __function_pipe.function.pipe(pipefd);
      stored_errno= errno;
      pipefd_arg[0]= pipefd[0];
      pipefd_arg[1]= pipefd[1];
    }
    else
    {
      ret= __function_pipe.function.pipe(NULL);
      stored_errno= errno;
    }

    reset_called();
  }

  errno= stored_errno;
  return ret;
}

#if defined(HAVE_PIPE2) && HAVE_PIPE2
int pipe2(int pipefd_arg[2], int flags)
{
  hostile_initialize();

  (void) pthread_once(&function_lookup_once, set_local);

  int stored_errno;
  int ret;
  {
    set_called();
    if (pipefd_arg)
    {
      int pipefd[2];
      ret= __function_pipe2.function.pipe2(pipefd, flags);
      stored_errno= errno;
      pipefd_arg[0]= pipefd[0];
      pipefd_arg[1]= pipefd[1];
    }
    else
    {
      ret= __function_pipe2.function.pipe2(pipefd_arg, flags);
      stored_errno= errno;
    }
    reset_called();
  }

  errno= stored_errno;
  return ret;
}
#endif

