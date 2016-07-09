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
#include <stdio.h>
#include <libgearman-1.0/gearman.h>

int main(void)
{
  gearman_client_st *client= gearman_client_create(NULL);

  gearman_return_t ret= gearman_client_add_server(client, "localhost", 0);
  if (gearman_failed(ret))
  {
    return EXIT_FAILURE;
  }

  gearman_job_handle_t job_handle;
  gearman_return_t rc= gearman_client_do_background(client,
                                                    "reverse_function",
                                                    "unique_value",
                                                    "my string to reverse", strlen("my string to reverse"),
                                                    job_handle);

  if (gearman_success(rc))
  {
    // Make use of value
    printf("%s\n", job_handle);
  }

  gearman_client_free(client);

  return 0;
}
