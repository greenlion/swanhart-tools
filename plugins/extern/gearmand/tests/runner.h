/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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

#pragma once

#include "tests/libgearman-1.0/client_test.h"

class GearmandRunner : public Runner {
private:

  typedef test_return_t (*libgearman_test_prepost_callback_fn)(client_test_st *);
  typedef test_return_t (*libgearman_test_callback_fn)(gearman_client_st *);

public:
  test_return_t run(test_callback_fn* func, void *object)
  {
    return _run(libgearman_test_callback_fn(func), (client_test_st*)object);
  }

  test_return_t pre(test_callback_fn* func, void *object)
  {
    return _setup(libgearman_test_prepost_callback_fn(func), (client_test_st*)object);
  }

  test_return_t post(test_callback_fn* func, void *object)
  {
    return _teardown(libgearman_test_prepost_callback_fn(func), (client_test_st*)object);
  }

private:
  test_return_t _setup(libgearman_test_prepost_callback_fn func, client_test_st *container)
  {
    if (func)
    {
      return func(container);
    }

    return TEST_SUCCESS;
  }

  test_return_t _teardown(libgearman_test_prepost_callback_fn func, client_test_st *container)
  {
    if (func)
    {
      return func(container);
    }
    container->clear();

    return TEST_SUCCESS;
  }

  test_return_t _run(libgearman_test_callback_fn func, client_test_st *container)
  {
    if (func)
    {
      test_return_t rc;

      {
        org::gearmand::libgearman::Client client(container->client());
        gearman_client_set_context(&client, (void *)container->worker_name());
        if (container->session_namespace())
        {
          gearman_client_set_namespace(&client, container->session_namespace(),strlen(container->session_namespace()));
        }
        rc= func(&client);
        if (rc == TEST_SUCCESS)
        {
#if defined(DEBUG) && DEBUG
          test_warn(gearman_client_has_tasks(&client) == false, "client has uncompleted tasks");
#endif
        }
      }

      return rc;
    }

    return TEST_SUCCESS;
  }
};
