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

#include "libhostile/hostile.h"

bool libhostile_is_accept(void)
{
  return false;
}

void set_poll_close(bool arg, int frequency, int not_until_arg, enum hostile_poll_t poll_type)
{
  (void)(arg);
  (void)(frequency);
  (void)(not_until_arg);
  (void)(poll_type);
}

void set_connect_close(bool arg, int frequency, int not_until_arg)
{
  (void)(arg);
  (void)(frequency);
  (void)(not_until_arg);
}

void set_accept_close(bool arg, int frequency, int not_until_arg)
{
  (void)(arg);
  (void)(frequency);
  (void)(not_until_arg);
}

void set_recv_close(bool arg, int frequency, int not_until_arg)
{
  (void)(arg);
  (void)(frequency);
  (void)(not_until_arg);
}

void set_recv_corrupt(bool arg, int frequency, int not_until_arg)
{
  (void)(arg);
  (void)(frequency);
  (void)(not_until_arg);
}

void set_send_close(bool arg, int frequency, int not_until_arg)
{
  (void)arg;
  (void)frequency;
  (void)not_until_arg;
}

void set_getaddrinfo_error(bool arg, int frequency, int not_until_arg)
{
  (void)arg;
  (void)frequency;
  (void)not_until_arg;
}
