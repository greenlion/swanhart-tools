/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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
 * @brief default Queue Storage Definitions
 */

#include <gear_config.h>
#include <libgearman-server/common.h>

#include <libgearman-server/plugins/queue/default/queue.h>
#include <libgearman-server/plugins/queue/base.h>

/**
 * @addtogroup gearman_queue_default_static Static default Queue Storage Definitions
 * @ingroup gearman_queue_default
 * @{
 */


namespace gearmand { namespace plugins { namespace queue { class Default; }}}

#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wold-style-cast"

/* Queue callback functions. */
static gearmand_error_t __add(gearman_server_st *server __attribute__((unused)), 
                              void *context __attribute__((unused)),
                              const char *unique __attribute__((unused)), size_t unique_size __attribute__((unused)),
                              const char *function_name __attribute__((unused)),
                              size_t function_name_size __attribute__((unused)),
                              const void *data __attribute__((unused)), size_t data_size __attribute__((unused)),
                              gearman_job_priority_t priority __attribute__((unused)),
                              int64_t when __attribute__((unused)))
{
  gearmand_debug(__func__);
  return GEARMAND_SUCCESS;
}


static gearmand_error_t __flush(gearman_server_st *server __attribute__((unused)),
                                void *context __attribute__((unused)))
{
  gearmand_debug(__func__);
  return GEARMAND_SUCCESS;
}

static gearmand_error_t __done(gearman_server_st *server __attribute__((unused)),
                               void *context __attribute__((unused)),
                               const char *unique __attribute__((unused)),
                               size_t unique_size __attribute__((unused)),
                               const char *function_name __attribute__((unused)),
                               size_t function_name_size __attribute__((unused)))
{
  gearmand_debug(__func__);
  return GEARMAND_SUCCESS;
}


static gearmand_error_t __replay(gearman_server_st *server __attribute__((unused)),
                                 void *context __attribute__((unused)),
                                 gearman_queue_add_fn *add_fn __attribute__((unused)),
                                 void *add_context __attribute__((unused)))
{
  gearmand_debug(__func__);
  return GEARMAND_SUCCESS;
}



namespace gearmand {
namespace plugins {
namespace queue {

class Default :
  public gearmand::plugins::Queue
{
public:
  Default();
  ~Default();

  gearmand_error_t initialize();

private:
};

Default::Default() :
  Queue("builtin")
{
}

Default::~Default()
{
}

gearmand_error_t Default::initialize()
{
  gearman_server_set_queue(Gearmand()->server, this, __add, __flush, __done, __replay);

  return GEARMAND_SUCCESS;
}

void initialize_default()
{
  static Default local_instance;
}

} // namespace queue
} // namespace plugins
} // namespace gearmand
#pragma GCC diagnostic pop
