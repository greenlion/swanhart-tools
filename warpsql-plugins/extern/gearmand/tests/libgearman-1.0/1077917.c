/*
  Look for any basic issue that valgrind might flag.

  https://bugs.launchpad.net/gearmand/+bug/1077917
*/

#include <libgearman-1.0/gearman.h>

int main (int argc, char **argv)
{
  (void)argc;
  (void)argv;
  gearman_client_st client;

  gearman_client_create(&client);
  gearman_client_free(&client);

  exit( 0 );
}

