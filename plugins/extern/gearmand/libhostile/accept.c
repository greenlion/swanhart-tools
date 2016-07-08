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

#include <errno.h>
#include <pthread.h>
#include <stdio.h>
#include <stdlib.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <time.h>
#include <unistd.h>

#include <libhostile/initialize.h>
#include <libhostile/function.h>

static int not_until= 500;

static struct function_st __function_accept;
static struct function_st __function_accept4;

static pthread_once_t function_lookup_once = PTHREAD_ONCE_INIT;
static void set_local(void)
{
  __function_accept= set_function("accept", "HOSTILE_ACCEPT");
#if defined(HAVE_ACCEPT4) && HAVE_ACCEPT4
  __function_accept4= set_function("accept4", "HOSTILE_ACCEPT4");
#endif
}

bool libhostile_is_accept(void)
{
  hostile_initialize();

  (void) pthread_once(&function_lookup_once, set_local);

  if (__function_accept.frequency)
  {
    return true;
  }

  return false;
}

void set_accept_close(bool arg, int frequency, int not_until_arg)
{
  if (arg)
  {
    __function_accept.frequency= frequency;
    not_until= not_until_arg;
  }
  else
  {
    __function_accept.frequency= 0;
    not_until= 0;
  }
}

int accept(int sockfd, struct sockaddr *addr, socklen_t *addrlen)
{

  hostile_initialize();

  (void) pthread_once(&function_lookup_once, set_local);

  if (is_called() == false)
  {
    if (__function_accept.frequency)
    {
      if (--not_until < 0 && rand() % __function_accept.frequency)
      {
        if (rand() % 1)
        {
          shutdown(sockfd, SHUT_RDWR);
          close(sockfd);
          errno= ECONNABORTED;
          return -1;
        }
        else
        {
          shutdown(sockfd, SHUT_RDWR);
          close(sockfd);
          errno= EMFILE;
          return -1;
        }
      }
    }
  }

  set_called();
  int ret= __function_accept.function.accept(sockfd, addr, addrlen);
  reset_called();

  return ret;
}

#if defined(HAVE_ACCEPT4) && HAVE_ACCEPT4
int accept4(int sockfd, struct sockaddr *addr, socklen_t *addrlen, int flags)
{

  hostile_initialize();

  (void) pthread_once(&function_lookup_once, set_local);

  if (is_called() == false)
  {
    if (__function_accept4.frequency)
    {
      if (--not_until < 0 && rand() % __function_accept4.frequency)
      {
        if (rand() % 1)
        {
          shutdown(sockfd, SHUT_RDWR);
          close(sockfd);
          errno= ECONNABORTED;
          return -1;
        }
        else
        {
          shutdown(sockfd, SHUT_RDWR);
          close(sockfd);
          errno= EMFILE;
          return -1;
        }
      }
    }
  }

  set_called();
  int ret= __function_accept4.function.accept4(sockfd, addr, addrlen, flags);
  reset_called();

  return ret;
}
#endif
