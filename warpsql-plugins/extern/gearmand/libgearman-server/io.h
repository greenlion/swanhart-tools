/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2012 Data Differential, http://datadifferential.com/
 *  Copyright (C) 2008 Brian Aker, Eric Day
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

/**
 * @file
 * @brief Connection Declarations
 */

#pragma once

#include <sys/types.h>
#include <sys/socket.h>
#include <netdb.h>

#include <libgearman-server/struct/connection_list.h>

#ifdef __cplusplus
extern "C" {
#endif


/**
 * @addtogroup gearman_con Connection Declarations
 * @ingroup gearman_universal
 *
 * This is a low level interface for gearman connections. This is used
 * internally by both client and worker interfaces, so you probably want to
 * look there first. This is usually used to write lower level clients, workers,
 * proxies, or your own server.
 *
 * @{
 */

/**
 * @ingroup gearman_connection
 */

/** Initialize a connection structure. Always check the return value even if
 * passing in a pre-allocated structure. Some other initialization may have
 * failed.
 *
 * @param[in] gearman Structure previously initialized with gearman_create() or
 *  gearman_clone().
 * @param[in] connection Caller allocated structure, or NULL to allocate one.
 * @return On success, a pointer to the (possibly allocated) structure. On
 *  failure this will be NULL.
 */
void gearmand_connection_init(gearmand_connection_list_st *gearman,
                              gearmand_io_st *connection,
                              struct gearmand_con_st *dcon,
                              gearmand_connection_options_t *options);

/**
 * Free a connection structure.
 *
 * @param[in] connection Structure previously initialized with gearmand_connection_init(),
 *  gearmand_connection_init_args(), or gearman_connection_clone().
 */
void gearmand_io_free(gearmand_io_st *connection);


gearmand_error_t gearman_io_set_option(gearmand_io_st *connection,
                                               gearmand_connection_options_t options,
                                               bool value);


/**
 * Set connection to an already open file descriptor.
 */
gearmand_error_t gearman_io_set_fd(gearmand_io_st *connection, int fd);

/**
 * Get application context pointer.
 */
gearmand_con_st *gearman_io_context(const gearmand_io_st *connection);

/**
 * Used by thread to send packets.
 */
gearmand_error_t gearman_io_send(gearman_server_con_st *connection,
                                 const struct gearmand_packet_st *packet, bool flush);

/**
 * Used by thread to recv packets.
 */
gearmand_error_t gearman_io_recv(gearman_server_con_st *con, bool recv_data);

/**
 * Set events to be watched for a connection.
 */
gearmand_error_t gearmand_io_set_events(gearman_server_con_st *connection, short events);

/**
 * Set events that are ready for a connection. This is used with the external
 * event callbacks.
 */
gearmand_error_t gearmand_io_set_revents(gearman_server_con_st *connection, short revents);

void gearmand_sockfd_close(int&);

void gearmand_pipe_close(int&);

gearmand_error_t gearmand_sockfd_nonblock(const int&);

/** @} */

#ifdef __cplusplus
}
#endif
