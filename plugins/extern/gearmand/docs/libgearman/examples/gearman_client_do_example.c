/*
# Gearman server and library
# Copyright (C) 2012 Data Differential, http://datadifferential.com/
# All rights reserved.
#
# Use and distribution licensed under the BSD license.  See
# the COPYING file in this directory for full text.
*/

#include <string.h>
#include <stdlib.h>
#include <libgearman/gearman.h>

int main(void)
{
  gearman_client_st *client= gearman_client_create(NULL);

  gearman_return_t ret= gearman_client_add_server(client, "localhost", 0);
  if (gearman_failed(ret))
  {
    return EXIT_FAILURE;
  }

  size_t result_size;
  gearman_return_t rc;
  void *value= gearman_client_do(client, "reverse_function", "unique_value", 
                                 "my string to reverse", strlen("my string to reverse"), 
                                 &result_size, &rc);

  if (gearman_success(rc))
  {
    // Make use of value
  }
  free(value);

  gearman_client_free(client);

  return 0;
}
