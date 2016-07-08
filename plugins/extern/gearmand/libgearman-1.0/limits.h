/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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

/* Defines. */
#define GEARMAN_ARGS_BUFFER_SIZE 128
#define GEARMAN_DEFAULT_SOCKET_RECV_SIZE 32768
#define GEARMAN_DEFAULT_SOCKET_SEND_SIZE 32768
#define GEARMAN_DEFAULT_SOCKET_TIMEOUT 10
#define GEARMAN_DEFAULT_CONNECT_TIMEOUT 4000
#define GEARMAN_DEFAULT_TCP_HOST "localhost"
#define GEARMAN_FUNCTION_MAX_SIZE 512
#define GEARMAN_JOB_HANDLE_SIZE 64
#define GEARMAN_MAXIMUM_INTEGER_DISPLAY_LENGTH 20
#define GEARMAN_MAX_COMMAND_ARGS 8
#define GEARMAN_MAX_ECHO_SIZE (UINT32_MAX -GEARMAN_ARGS_BUFFER_SIZE)
#define GEARMAN_MAX_OPTION_SIZE (UINT32_MAX -GEARMAN_ARGS_BUFFER_SIZE)
#define GEARMAN_MAX_ERROR_SIZE 2048
#define GEARMAN_MAX_IDENTIFIER 254
#define GEARMAN_MAX_UNIQUE_SIZE 64
#define GEARMAN_MAX_UUID_SIZE 36
#define GEARMAN_OPTION_SIZE 64
#define GEARMAN_PACKET_HEADER_SIZE 12
#define GEARMAN_RECV_BUFFER_SIZE 8192
#define GEARMAN_SEND_BUFFER_SIZE 8192
#define GEARMAN_UNIQUE_SIZE GEARMAN_MAX_UNIQUE_SIZE
#define GEARMAN_WORKER_WAIT_TIMEOUT (10 * 1000) /* Milliseconds */
