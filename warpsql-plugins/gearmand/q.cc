/* Copyright (c) 2006, 2011, Oracle and/or its affiliates. All rights reserved.

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; version 2 of the License.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA */
#define MYSQL_ABI_CHECK
#include <my_global.h>
#include <stdlib.h>
#include <ctype.h>
#include <mysql_version.h>
#include <mysql/plugin.h>
#include <my_dir.h>
#include "my_thread.h"
#include "my_sys.h"                             // my_write, my_malloc
#include "m_string.h"                           // strlen
#include "sql_plugin.h"                         // st_plugin_int
#include <pthread.h>
#include <string>
#include "server.h"

PSI_memory_key key_q_context;
static long my_q_port;
static MYSQL_SYSVAR_LONG(
  port, 
  my_q_port, 
  PLUGIN_VAR_RQCMDARG,
  "q (gearmand) plugin port to use (default 43078)",
  NULL,
  NULL,
  43078,
  1,
  65535,
  0
);

static struct st_mysql_sys_var *my_plugin_vars[] = {
  MYSQL_SYSVAR(port),
  NULL
};

#ifdef HAVE_PSI_INTERFACE

static PSI_memory_info all_q_memory[]=
{
  {&key_q_context, "q_context", 0}
};

static void init_q_psi_keys()
{
  const char* category= "q";
  int count;

  count= array_elements(all_q_memory);
  mysql_memory_register(category, all_q_memory, count);
};
#endif /* HAVE_PSI_INTERFACE */

/*
  Disable __attribute__() on non-gcc compilers.
*/
#if !defined(__attribute__) && !defined(__GNUC__)
#define __attribute__(A)
#endif

struct q_context
{
  my_thread_handle q_thread;
};

static int cleanup_pop_arg = 0;

/* This is where gearmand actually hooks in via gearman_shim() */
void *q(void *p) 
{
  DBUG_ENTER("q");
  struct q_context *con __attribute__ ((unused)) = (struct q_context *)p;

  pthread_cleanup_push(gearmand_quit,NULL);
  int res __attribute__ ((unused)) = gearman_shim(std::to_string(my_q_port));
  pthread_cleanup_pop(cleanup_pop_arg); 
  void *dummy=NULL;
  pthread_exit(dummy);
  DBUG_RETURN(0);
}


/*
  Initialize the daemon example at server start or plugin installation.

  SYNOPSIS
    q_plugin_init()

  DESCRIPTION
    Starts up message queue thread

  RETURN VALUE
    0                    success
    1                    failure (cannot happen)
*/

static int q_plugin_init(void *p __attribute__ ((unused)))
{

  DBUG_ENTER("q_plugin_init");
#ifdef HAVE_PSI_INTERFACE
  init_q_psi_keys();
#endif
  struct q_context *con;
  my_thread_attr_t attr;          /* Thread attributes */

  struct st_plugin_int *plugin= (struct st_plugin_int *)p;

  con= (struct q_context *)
    my_malloc(key_q_context,sizeof(struct q_context), MYF(0)); 

  my_thread_attr_init(&attr);
  my_thread_attr_setdetachstate(&attr, MY_THREAD_CREATE_JOINABLE);


  /* now create the thread */
  if (my_thread_create(&con->q_thread, &attr, q,
                     (void *)con) != 0)
  {
    fprintf(stderr,"Could not create message queue thread!\n");
    exit(0);
  }
  plugin->data= (void *)con;

  DBUG_RETURN(0);
}


/*
  Terminate the daemon example at server shutdown or plugin deinstallation.

  SYNOPSIS
    q_plugin_deinit()
    Does nothing.

  RETURN VALUE
    0                    success
    1                    failure (cannot happen)

*/

static int q_plugin_deinit(void *p __attribute__ ((unused)))
{
  DBUG_ENTER("q_plugin_deinit");
  struct st_plugin_int *plugin= (struct st_plugin_int *)p;
  struct q_context *con=
    (struct q_context *)plugin->data;
  void *dummy_retval;
  my_thread_cancel(&con->q_thread);
  my_thread_join(&con->q_thread, &dummy_retval);

  my_free(con);
  DBUG_RETURN(0);
}


struct st_mysql_daemon q_plugin=
{ MYSQL_DAEMON_INTERFACE_VERSION  };

/*
  Plugin library descriptor
*/

mysql_declare_plugin(q)
{
  MYSQL_DAEMON_PLUGIN,
  &q_plugin,
  "q",
  "Justin Swanhart",
  "Q is a message queue plugin based on gearmand",
  PLUGIN_LICENSE_GPL,
  q_plugin_init, /* Plugin Init */
  q_plugin_deinit, /* Plugin Deinit */
  0x0100 /* 1.0 */,
  NULL,                       /* status variables                */
  my_plugin_vars,             /* system variables                */
  NULL,                      
  0
}
mysql_declare_plugin_end;
