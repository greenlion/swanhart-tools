/* Gearman server and library
 * Copyright (C) 2008 Brian Aker, Eric Day
 * All rights reserved.
 *
 * Use and distribution licensed under the BSD license.  See
 * the COPYING file in the parent directory for full text.
 */

#include "gear_config.h"
#include <libtest/test.hpp>

using namespace libtest;

#include <cassert>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <unistd.h>

#include <libgearman/gearman.h>

#include <tests/basic.h>
#include <tests/context.h>

#define WORKER_FUNCTION "drizzle_queue_test"

#if defined(HAVE_LIBDRIZZLE) && HAVE_LIBDRIZZLE
#include <libdrizzle-5.1/drizzle_client.h>
#endif

static in_port_t drizzled_port= 0;

#ifndef __INTEL_COMPILER
#pragma GCC diagnostic ignored "-Wold-style-cast"
#endif

static test_return_t gearmand_basic_option_test(void *)
{
  const char *args[]= { "--check-args", 
    "--libdrizzle-host=localhost",
    "--libdrizzle-port=90",
    "--libdrizzle-uds=tmp/foo.socket",
    "--libdrizzle-user=root",
    "--libdrizzle-password=test",
    "--libdrizzle-db=gearman",
    "--libdrizzle-table=gearman",
    "--libdrizzle-mysql",
    0 };

  ASSERT_EQ(EXIT_SUCCESS, exec_cmdline(drizzled_binary(), args, true));
  return TEST_SUCCESS;
}

#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wunreachable-code"
static test_return_t collection_init(void *object)
{
  Context *test= (Context *)object;
  assert(test);

#if defined(HAVE_DRIZZLED_BINARY) && HAVE_DRIZZLED_BINARY
  drizzled_port= libtest::get_free_port();
  if (server_startup(test->_servers, "drizzled", drizzled_port, NULL) == false)
  {
    return TEST_SKIPPED;
  }
#else
  drizzled_port= 0;
#endif

  if (HAVE_LIBDRIZZLE)
  {
    if (libtest::ping_drizzled(drizzled_port) == false)
    {
      return TEST_FAILURE;
    }
  }


  char drizzled_server_string[1024];
  int length= snprintf(drizzled_server_string, 
                       sizeof(drizzled_server_string),
                       "--libdrizzle-port=%d",
                       int(drizzled_port));
  ASSERT_TRUE(length > 0);
  const char *argv[]= {
    drizzled_server_string,
    "--queue-type=libdrizzle",
    0 };

  ASSERT_TRUE(test->initialize(argv));

  return TEST_SUCCESS;
}
#pragma GCC diagnostic pop

static test_return_t collection_cleanup(void *object)
{
  Context *test= (Context *)object;
  test->reset();

  return TEST_SUCCESS;
}


static void *world_create(server_startup_st& servers, test_return_t&)
{
  SKIP_IF(HAVE_UUID_UUID_H != 1);
  SKIP_IF(has_drizzled() == false);

  return new Context(servers);
}

static bool world_destroy(void *object)
{
  Context *test= (Context *)object;

  delete test;

  return TEST_SUCCESS;
}

test_st gearmand_basic_option_tests[] ={
  {"all options", 0, gearmand_basic_option_test },
  {0, 0, 0}
};

test_st tests[] ={
  {"gearman_client_echo()", 0, client_echo_test },
  {"gearman_client_echo() fail", 0, client_echo_fail_test },
  {"gearman_worker_echo()", 0, worker_echo_test },
  {"clean", 0, queue_clean },
  {"add", 0, queue_add },
  {"worker", 0, queue_worker },
  {0, 0, 0}
};

test_st regressions[] ={
  {"lp:734663", 0, lp_734663 },
  {0, 0, 0}
};

collection_st collection[] ={
  {"drizzle queue", collection_init, collection_cleanup, tests},
  {"regressions", collection_init, collection_cleanup, regressions},
  {0, 0, 0, 0}
};

void get_world(libtest::Framework *world)
{
  world->collections(collection);
  world->create(world_create);
  world->destroy(world_destroy);
}
