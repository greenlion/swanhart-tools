/*
  Example code to show how to send a string to a function called "reverse" and print the results.
*/

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
#include <libgearman/gearman.h>

int main(void)
{
  gearman_client_st *client= gearman_client_create(NULL);

  gearman_return_t ret= gearman_client_add_server(client, "localhost", 0);
  if (gearman_failed(ret))
  {
    return EXIT_FAILURE;
  }

  gearman_argument_t value= gearman_argument_make(0, 0, "Reverse Me", strlen("Reverse Me"));

  gearman_task_st *task= gearman_execute(client, 
                                         "reverse", strlen("reverse"),  // function
                                         NULL, 0,  // no unique value provided
                                         NULL, 
                                         &value, 0);

  if (task == NULL) // If gearman_execute() can return NULL on error
  {
    fprintf(stderr, "Error: %s\n", gearman_client_error(client));
    gearman_client_free(client);
    return EXIT_FAILURE;
  }

  // Make sure the task was run successfully
  if (gearman_success(gearman_task_return(task)))
  {
    // Make use of value
    gearman_result_st *result= gearman_task_result(task);
    printf("%.*s\n", (int)gearman_result_size(result), gearman_result_value(result));
  }

  gearman_client_free(client);

  return EXIT_SUCCESS;
}
