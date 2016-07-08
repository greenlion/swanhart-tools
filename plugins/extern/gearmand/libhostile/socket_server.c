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
#include <libhostile/function.h>
#include "libhostile/socket_server.h"

#include <string.h>
#include <stdlib.h>
#include <stdio.h>
#include <sys/types.h>
#include <sys/socket.h>

int make_socket(in_port_t port)
{
  (void)port;
#if 0
  struct sockaddr_in addr;
  
  addr.sin_family= AF_INET;

  /* The port to listen on */
  addr.sin_port= htons(port);
  addr.sin_addr.s_addr= INADDR_ANY;
  
  int socket_fd= socket(PF_INET, SOCK_DGRAM, 0);  /* FIXME: Use PROTO_UDP instead? */
  if (socket_fd < 0)
  {
    perror("socket() failed");
    return -1;
  }

  if (bind(socket_fd, (struct sockaddr *) &addr, sizeof(addr)) < 0)
  {
    perror("bind() failed");
    return EXIT_FAILURE;
  }

  return socket_fd;
#endif

  return -1;
}

int read_packet(int socket_fd, unsigned *length, unsigned char *packet, struct sockaddr_in *peer)
{
  (void)socket_fd;
  (void)length;
  (void)packet;
  (void)peer;
  
  return 0;
}
