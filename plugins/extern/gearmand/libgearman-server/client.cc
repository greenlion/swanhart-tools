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
 * @brief Server client definitions
 */

#include "gear_config.h"
#include "libgearman-server/common.h"

#include <cassert>
#include <memory>

/*
 * Public definitions
 */

gearman_server_client_st *
gearman_server_client_add(gearman_server_con_st *con)
{
  gearman_server_client_st *client;

  if (Server->free_client_count > 0)
  {
    client= Server->free_client_list;
    GEARMAND_LIST_DEL(Server->free_client, client, con_);
  }
  else
  {
    client= new (std::nothrow) gearman_server_client_st;
    if (client == NULL)
    {
      gearmand_merror("new", gearman_server_client_st,  0);
      return NULL;
    }
  }
  assert(client);

  if (client == NULL)
  {
    gearmand_error("In gearman_server_client_add() we failed to either allocorate of find a free one");
    return NULL;
  }

  client->init(con);

  GEARMAND_LIST_ADD(con->client, client, con_);

  return client;
}

void gearman_server_client_free(gearman_server_client_st *client)
{
  if (client)
  {
    GEARMAND_LIST_DEL(client->con->client, client, con_);

    if (client->job)
    {
      GEARMAND_LIST_DEL(client->job->client, client, job_);

      /* If this was a foreground job and is now abandoned, mark to not run. */
      if (client->job->client_list == NULL)
      {
        client->job->ignore_job= true;
        client->job->job_queued= false;
      }
    }

    if (Server->free_client_count < GEARMAND_MAX_FREE_SERVER_CLIENT)
    {
      GEARMAND_LIST_ADD(Server->free_client, client, con_)
    }
    else
    {
      gearmand_debug("delete gearman_server_client_st");
      delete client;
    }
  }
}
