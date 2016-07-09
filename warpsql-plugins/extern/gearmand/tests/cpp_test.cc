/* Gearman server and library
 * Copyright (C) 2008 Brian Aker, Eric Day
 * All rights reserved.
 *
 * Use and distribution licensed under the BSD license.  See
 * the COPYING file in the parent directory for full text.
 */

/*
 * @brief C++ dummy test, aka testing C++ linking, etc
 */

#include <cstdlib> // For EXIT_SUCCESS

#include <libgearman/gearman.h>

int main(void)
{
  gearman_client_st client;

  gearman_client_create(&client);
  gearman_client_free(&client);

  return EXIT_SUCCESS;
}
