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
 * @brief Gearman Server Declarations
 */

#include <libgearman-server/struct/server.h>

#pragma once

#ifdef __cplusplus
extern "C" {
#endif

#include <pthread.h>

inline static void gearmand_set_round_robin(gearman_server_st *server, bool round_robin)
{
  server->flags.round_robin= round_robin;
}

/**
 * Process commands for a connection.
 * @param server_con Server connection that has a packet to process.
 * @param packet The packet that needs processing.
 * @return Standard gearman return value.
 */
GEARMAN_API
gearmand_error_t gearman_server_run_command(gearman_server_con_st *server_con,
                                            gearmand_packet_st *packet);

/**
 * Tell server that it should enter a graceful shutdown state.
 * @param server Server structure previously initialized with
 *        gearman_server_create.
 * @return Standard gearman return value. This will return GEARMAND_SHUTDOWN if
 *         the server is ready to shutdown now.
 */
GEARMAN_API
gearmand_error_t gearman_server_shutdown_graceful(gearman_server_st *server);

/**
 * Replay the persistent queue to load all unfinshed jobs into the server. This
 * should only be run at startup.
 * @param server Server structure previously initialized with
 *        gearman_server_create.
 * @return Standard gearman return value. This will return GEARMAND_SHUTDOWN if
 *         the server is ready to shutdown now.
 */
#ifdef __cplusplus
GEARMAN_API
gearmand_error_t gearman_server_queue_replay(gearman_server_st& server);
#endif

/**
 * Get persistent queue context.
 */
GEARMAN_API
void *gearman_server_queue_context(const gearman_server_st *server);

/** @} */

#ifdef __cplusplus
}
#endif
