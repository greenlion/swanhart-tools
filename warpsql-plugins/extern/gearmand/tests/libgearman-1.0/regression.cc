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


#include "gear_config.h"
#include <libtest/test.hpp>

using namespace libtest;

#include <cassert>
#include <libgearman/gearman.h>
#include <tests/regression.h>
#include <libgearman/interface/universal.hpp>
#include <libgearman/universal.hpp>
#include <libgearman/connection.hpp>
#include <libgearman/packet.hpp>

#include <iostream>
#include <cstring>

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

test_return_t regression_bug_783141_test(void *)
{
  test_skip(true, test_is_local());

#ifdef __APPLE__
  return TEST_SKIPPED;
#endif  

  { // Try with one bad host
    gearman_client_st *client= gearman_client_create(NULL);
    ASSERT_TRUE(client);

    ASSERT_TRUE(gearman_success(gearman_client_add_server(client, "10.0.2.253", 0)));

    gearman_return_t ret;
    gearman_task_st *task= gearman_client_add_task(client, NULL, NULL,
                                                   "does not exist", NULL,
                                                   test_literal_param("dog"),
                                                   &ret);
    ASSERT_EQ(ret, GEARMAN_SUCCESS);
    ASSERT_TRUE(task);

    gearman_return_t local_ret= gearman_client_run_tasks(client);
    ASSERT_EQ(local_ret, GEARMAN_COULD_NOT_CONNECT);

    local_ret= gearman_client_run_tasks(client);
    ASSERT_EQ(local_ret, GEARMAN_COULD_NOT_CONNECT);

    gearman_client_free(client);
  }

  { // Try with three bad hosts
    gearman_client_st *client= gearman_client_create(NULL);
    ASSERT_TRUE(client);

    ASSERT_TRUE(gearman_success(gearman_client_add_server(client, "10.0.2.253", 0)));
    ASSERT_TRUE(gearman_success(gearman_client_add_server(client, "10.0.2.252", 0)));
    ASSERT_TRUE(gearman_success(gearman_client_add_server(client, "10.0.2.251", 0)));

    gearman_return_t ret;
    gearman_task_st *task= gearman_client_add_task(client, NULL, NULL,
                                                   "does not exist", NULL,
                                                   test_literal_param("dog"),
                                                   &ret);
    ASSERT_EQ(ret, GEARMAN_SUCCESS);
    ASSERT_TRUE(task);

    gearman_return_t local_ret= gearman_client_run_tasks(client);
    ASSERT_EQ(local_ret, GEARMAN_COULD_NOT_CONNECT);

    local_ret= gearman_client_run_tasks(client);
    ASSERT_EQ(local_ret, GEARMAN_COULD_NOT_CONNECT);

    gearman_client_free(client);
  }

  return TEST_SUCCESS;
}

test_return_t regression_bug_372074_test(void *)
{
  gearman_universal_st universal;
  const void *args[1];
  size_t args_size[1];

  for (uint32_t x= 0; x < 2; x++)
  {
    gearman_packet_st packet;
    gearman_connection_st *con_ptr;
    ASSERT_TRUE(con_ptr= gearman_connection_create(universal, NULL, default_port()));

    args[0]= "testUnregisterFunction";
    args_size[0]= strlen("testUnregisterFunction");
    ASSERT_TRUE(gearman_success(gearman_packet_create_args(universal, packet, GEARMAN_MAGIC_REQUEST,
                                                          GEARMAN_COMMAND_SET_CLIENT_ID,
                                                          args, args_size, 1)));

    ASSERT_TRUE(gearman_success(con_ptr->send_packet(packet, true)));

    gearman_packet_free(&packet);

    args[0]= "reverse";
    args_size[0]= strlen("reverse");
    ASSERT_TRUE(gearman_success(gearman_packet_create_args(universal, packet, GEARMAN_MAGIC_REQUEST, GEARMAN_COMMAND_CAN_DO,
                                                          args, args_size, 1)));

    ASSERT_TRUE(gearman_success(con_ptr->send_packet(packet, true)));

    gearman_packet_free(&packet);

    ASSERT_TRUE(gearman_success(gearman_packet_create_args(universal, packet, GEARMAN_MAGIC_REQUEST,
                                                          GEARMAN_COMMAND_CANT_DO,
                                                          args, args_size, 1)));

    ASSERT_TRUE(gearman_success(con_ptr->send_packet(packet, true)));

    gearman_packet_free(&packet);

    delete con_ptr;

    ASSERT_TRUE(con_ptr= gearman_connection_create(universal, NULL, default_port()));

    args[0]= "testUnregisterFunction";
    args_size[0]= strlen("testUnregisterFunction");
    ASSERT_TRUE(gearman_success(gearman_packet_create_args(universal, packet, GEARMAN_MAGIC_REQUEST,
                                                          GEARMAN_COMMAND_SET_CLIENT_ID,
                                                          args, args_size, 1)));

    ASSERT_TRUE(gearman_success(con_ptr->send_packet(packet, true)));

    gearman_packet_free(&packet);

    args[0]= "digest";
    args_size[0]= strlen("digest");
    ASSERT_TRUE(gearman_success(gearman_packet_create_args(universal, packet, GEARMAN_MAGIC_REQUEST,
                                                          GEARMAN_COMMAND_CAN_DO,
                                                          args, args_size, 1)));

    ASSERT_TRUE(gearman_success(con_ptr->send_packet(packet, true)));

    gearman_packet_free(&packet);

    args[0]= "reverse";
    args_size[0]= strlen("reverse");
    ASSERT_TRUE(gearman_success(gearman_packet_create_args(universal, packet, GEARMAN_MAGIC_REQUEST,
                                                          GEARMAN_COMMAND_CAN_DO,
                                                          args, args_size, 1)));

    ASSERT_TRUE(gearman_success(con_ptr->send_packet(packet, true)));

    gearman_packet_free(&packet);

    ASSERT_TRUE(gearman_success(gearman_packet_create_args(universal, packet, GEARMAN_MAGIC_REQUEST,
                                                          GEARMAN_COMMAND_RESET_ABILITIES,
                                                          NULL, NULL, 0)));

    ASSERT_TRUE(gearman_success(con_ptr->send_packet(packet, true)));

    gearman_packet_free(&packet);

    delete con_ptr;
  }

  gearman_universal_free(universal);

  return TEST_SUCCESS;
}

test_return_t regression_768317_test(void *object)
{
  gearman_client_st *client= (gearman_client_st *)object;

  ASSERT_TRUE(client);
  size_t result_length;
  gearman_return_t rc;
  char *job_result= (char*)gearman_client_do(client, "increment_reset_worker", 
                                             NULL, 
                                             test_literal_param("reset"),
                                             &result_length, &rc);
  ASSERT_EQ(rc, GEARMAN_SUCCESS);
  test_false(job_result);

  // Check to see that the task ran just once
  job_result= (char*)gearman_client_do(client, "increment_reset_worker", 
                                       NULL, 
                                       test_literal_param("10"),
                                       &result_length, &rc);
  ASSERT_EQ(rc, GEARMAN_SUCCESS);
  ASSERT_TRUE(job_result);
  long count= strtol(job_result, (char **)NULL, 10);
  ASSERT_EQ(10L, count);
  free(job_result);

  // Check to see that the task ran just once out of the bg queue
  {
    gearman_job_handle_t job_handle;
    rc= gearman_client_do_background(client,
                                     "increment_reset_worker",
                                     NULL,
                                     test_literal_param("14"),
                                     job_handle);
    ASSERT_EQ(rc, GEARMAN_SUCCESS);

    bool is_known;
    do {
      rc= gearman_client_job_status(client, job_handle, &is_known, NULL, NULL, NULL);
    }  while (gearman_continue(rc) or is_known);
    ASSERT_EQ(rc, GEARMAN_SUCCESS);

    job_result= (char*)gearman_client_do(client, "increment_reset_worker", 
                                         NULL, 
                                         test_literal_param("10"),
                                         &result_length, &rc);
    ASSERT_EQ(rc, GEARMAN_SUCCESS);
    ASSERT_TRUE(job_result);
    count= atol(job_result);
    ASSERT_EQ(34L, count);
    free(job_result);
  }

  return TEST_SUCCESS;
}
