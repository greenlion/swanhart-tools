/* Gearman User Defined Functions for MySQL
 * Copyright (C) 2008 Eric Day
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

#include "libgearman_mysql_udf/common.h"

/*
 * Structs and types for clients.
 */
typedef struct gearman_udf_server gearman_udf_server_st;
typedef struct gearman_udf_client gearman_udf_client_st;

struct gearman_udf_server
{
  gearman_client_st client;
  char function_name[GEARMAN_UDF_FUNCTION_SIZE];
  gearman_udf_server_st *next;
  gearman_udf_server_st *prev;
};

typedef enum
{
  GEARMAN_UDF_CLIENT_CLONED= (1 << 0)
} gearman_udf_client_flags_t;

struct gearman_udf_client
{
  gearman_udf_client_flags_t flags;
  gearman_client_st client;
  char function_name[GEARMAN_UDF_FUNCTION_SIZE];
  void *result;
  long long sum;
  bool pause;
};

pthread_mutex_t _server_lock= PTHREAD_MUTEX_INITIALIZER;
gearman_udf_server_st *_server_list= NULL;

/*
 * Shared client functions.
 */

my_bool _do_init(UDF_INIT *initid, UDF_ARGS *args, char *message);
void _do_deinit(UDF_INIT *initid);
bool _find_server(gearman_udf_client_st *client, UDF_ARGS *args);
static void *_do_malloc(size_t size, void *arg);
static void _do_free(void *ptr, void *arg);

/*
 * Functions for gman_servers_set.
 */

my_bool gman_servers_set_init(UDF_INIT *initid, UDF_ARGS *args, char *message);
void gman_servers_set_deinit(UDF_INIT *initid);
char *gman_servers_set(UDF_INIT *initid, UDF_ARGS *args, char *result,
                       unsigned long *length, char *is_null, char *error);

my_bool gman_servers_set_init(UDF_INIT *initid, UDF_ARGS *args, char *message)
{
  if (args->arg_count < 1 || args->arg_count > 2)
  { 
    strncpy(message, "Must give one or two arguments.", MYSQL_ERRMSG_SIZE);
    message[MYSQL_ERRMSG_SIZE - 1]= 0;
    return 1;
  }

  if (args->arg_count == 2 && (args->maybe_null[1] == 1 ||
                               args->arg_type[1] != STRING_RESULT))
  {
    strncpy(message, "Second argument must be a string.", MYSQL_ERRMSG_SIZE);
    message[MYSQL_ERRMSG_SIZE - 1]= 0;
    return 1;
  }

  if (args->arg_count == 2 && args->lengths[1] >= GEARMAN_UDF_FUNCTION_SIZE)
  {
    strncpy(message, "Function name too long.", MYSQL_ERRMSG_SIZE);
    message[MYSQL_ERRMSG_SIZE - 1]= 0;
    return 1;
  }

  /* Tell MySQL to convert first argument to a string before passing it in. */
  args->arg_type[0]= STRING_RESULT;

  initid->maybe_null= 1;

  return 0;
}

void gman_servers_set_deinit(UDF_INIT *initid)
{
  (void) initid;
}

char *gman_servers_set(UDF_INIT *initid, UDF_ARGS *args, char *result,
                       unsigned long *length, char *is_null, char *error)
{
  gearman_udf_server_st *server;
  char function_name[GEARMAN_UDF_FUNCTION_SIZE];
  gearman_return_t ret;
  (void) initid;
  (void) is_null;

  if (args->arg_count == 2)
  {
    if (args->lengths[1] >= GEARMAN_UDF_FUNCTION_SIZE)
    {
      *error= 1;
      return NULL;
    }

    memcpy(function_name, args->args[1], args->lengths[1]);
    function_name[args->lengths[1]]= 0;
  }

  pthread_mutex_lock(&_server_lock);

  for (server= _server_list; server != NULL; server= server->next)
  {
    if (args->arg_count == 1 && server->function_name[0] == 0)
      break;
    if (args->arg_count == 2 && !strcmp(function_name, server->function_name))
      break;
  }

  if (server == NULL)
  {
    server= malloc(sizeof(gearman_udf_server_st));
    if (server == NULL)
    {
      snprintf(result, 255, "malloc() failed: %d", errno);
      *length= strlen(result);
      pthread_mutex_unlock(&_server_lock);
      return result;
    }

    if (gearman_client_create(&(server->client)) == NULL)
    {
      strncpy(result, "gearman_client_create() failed.", 255);
      result[254]= 0;
      *length= strlen(result);
      pthread_mutex_unlock(&_server_lock);
      return result;
    }

    if (args->arg_count == 2)
      strcpy(server->function_name, function_name);
    else
      server->function_name[0]= 0;

    if (_server_list != NULL)
      _server_list->prev= server;
    server->next= _server_list;
    _server_list= server;
  }

  gearman_client_remove_servers(&(server->client));
  ret= gearman_client_add_servers(&(server->client), args->args[0]);
  pthread_mutex_unlock(&_server_lock);
  if (ret != GEARMAN_SUCCESS)
  {
    snprintf(result, 255, "gearman_client_add_server() failed, %s",
             gearman_client_error(&(server->client)));
    *length= strlen(result);
    return result;
  }

  snprintf(result, 255, "%s", args->args[0]);
  *length= strlen(result);
  return result;
}

/*
 * Functions for gman_do.
 */

my_bool gman_do_init(UDF_INIT *initid, UDF_ARGS *args, char *message);
void gman_do_deinit(UDF_INIT *initid);
char *gman_do(UDF_INIT *initid, UDF_ARGS *args, char *result,
              unsigned long *length, char *is_null, char *error);

my_bool gman_do_init(UDF_INIT *initid, UDF_ARGS *args, char *message)
{
  return _do_init(initid, args, message);
}

void gman_do_deinit(UDF_INIT *initid)
{
  _do_deinit(initid);
}

char *gman_do(UDF_INIT *initid, UDF_ARGS *args, char *result,
              unsigned long *length, char *is_null, char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  gearman_return_t ret;
  size_t result_size;
  char unique[GEARMAN_UNIQUE_SIZE];

  if (_find_server(client, args) == false)
  {
    *error= 1;
    return NULL;
  }

  gearman_client_set_workload_malloc_fn(&(client->client), _do_malloc, result);
  gearman_client_set_workload_free_fn(&(client->client), _do_free, result);

  if (args->arg_count == 3)
  {
    snprintf(unique, GEARMAN_UNIQUE_SIZE, "%.*s", (int)args->lengths[2],
             args->args[2]);
  }

  client->result= gearman_client_do(&(client->client), client->function_name,
                                    args->arg_count == 3 ? unique : NULL,
                                    args->args[1], (size_t)(args->lengths[1]),
                                    &result_size, &ret);
  if (ret != GEARMAN_SUCCESS)
  {
    *error= 1;
    return NULL;
  }

  if (client->result == NULL)
  {
    result= NULL;
    *is_null= 1;
  }
  else if (result_size <= 255)
    client->result= NULL;
  else
    result= client->result;

  *length= result_size;
  return result;
}

/*
 * Functions for gman_do_high.
 */

my_bool gman_do_high_init(UDF_INIT *initid, UDF_ARGS *args, char *message);
void gman_do_high_deinit(UDF_INIT *initid);
char *gman_do_high(UDF_INIT *initid, UDF_ARGS *args, char *result,
                   unsigned long *length, char *is_null, char *error);

my_bool gman_do_high_init(UDF_INIT *initid, UDF_ARGS *args, char *message)
{
  return _do_init(initid, args, message);
}

void gman_do_high_deinit(UDF_INIT *initid)
{
  _do_deinit(initid);
}

char *gman_do_high(UDF_INIT *initid, UDF_ARGS *args, char *result,
                   unsigned long *length, char *is_null, char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  gearman_return_t ret;
  size_t result_size;
  char unique[GEARMAN_UNIQUE_SIZE];

  if (_find_server(client, args) == false)
  {
    *error= 1;
    return NULL;
  }

  gearman_client_set_workload_malloc_fn(&(client->client), _do_malloc, result);
  gearman_client_set_workload_free_fn(&(client->client), _do_free, result);

  if (args->arg_count == 3)
  {
    snprintf(unique, GEARMAN_UNIQUE_SIZE, "%.*s", (int)args->lengths[2],
             args->args[2]);
  }

  client->result= gearman_client_do_high(&(client->client),
                                         client->function_name,
                                         args->arg_count == 3 ? unique : NULL,
                                         args->args[1],
                                         (size_t)(args->lengths[1]),
                                         &result_size, &ret);
  if (ret != GEARMAN_SUCCESS)
  {
    *error= 1;
    return NULL;
  }

  if (client->result == NULL)
  {
    result= NULL;
    *is_null= 1;
  }
  else if (result_size <= 255)
    client->result= NULL;
  else
    result= client->result;

  *length= result_size;
  return result;
}

/*
 * Functions for gman_do_low.
 */

my_bool gman_do_low_init(UDF_INIT *initid, UDF_ARGS *args, char *message);
void gman_do_low_deinit(UDF_INIT *initid);
char *gman_do_low(UDF_INIT *initid, UDF_ARGS *args, char *result,
                   unsigned long *length, char *is_null, char *error);

my_bool gman_do_low_init(UDF_INIT *initid, UDF_ARGS *args, char *message)
{
  return _do_init(initid, args, message);
}

void gman_do_low_deinit(UDF_INIT *initid)
{
  _do_deinit(initid);
}

char *gman_do_low(UDF_INIT *initid, UDF_ARGS *args, char *result,
                   unsigned long *length, char *is_null, char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  gearman_return_t ret;
  size_t result_size;
  char unique[GEARMAN_UNIQUE_SIZE];

  if (_find_server(client, args) == false)
  {
    *error= 1;
    return NULL;
  }

  gearman_client_set_workload_malloc_fn(&(client->client), _do_malloc, result);
  gearman_client_set_workload_free_fn(&(client->client), _do_free, result);

  if (args->arg_count == 3)
  {
    snprintf(unique, GEARMAN_UNIQUE_SIZE, "%.*s", (int)args->lengths[2],
             args->args[2]);
  }

  client->result= gearman_client_do_low(&(client->client),
                                         client->function_name,
                                         args->arg_count == 3 ? unique : NULL,
                                         args->args[1],
                                         (size_t)(args->lengths[1]),
                                         &result_size, &ret);
  if (ret != GEARMAN_SUCCESS)
  {
    *error= 1;
    return NULL;
  }

  if (client->result == NULL)
  {
    result= NULL;
    *is_null= 1;
  }
  else if (result_size <= 255)
    client->result= NULL;
  else
    result= client->result;

  *length= result_size;
  return result;
}

/*
 * Functions for gman_do_background.
 */

my_bool gman_do_background_init(UDF_INIT *initid, UDF_ARGS *args,
                                char *message);
void gman_do_background_deinit(UDF_INIT *initid);
char *gman_do_background(UDF_INIT *initid, UDF_ARGS *args, char *result,
                         unsigned long *length, char *is_null, char *error);

my_bool gman_do_background_init(UDF_INIT *initid, UDF_ARGS *args,
                                char *message)
{
  return _do_init(initid, args, message);
}

void gman_do_background_deinit(UDF_INIT *initid)
{
  _do_deinit(initid);
}

char *gman_do_background(UDF_INIT *initid, UDF_ARGS *args, char *result,
                         unsigned long *length, char *is_null, char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  gearman_return_t ret;
  char job_handle[GEARMAN_JOB_HANDLE_SIZE];
  char unique[GEARMAN_UNIQUE_SIZE];
  (void) is_null;

  if (_find_server(client, args) == false)
  {
    *error= 1;
    return NULL;
  }

  if (args->arg_count == 3)
  {
    snprintf(unique, GEARMAN_UNIQUE_SIZE, "%.*s", (int)args->lengths[2],
             args->args[2]);
  }

  ret= gearman_client_do_background(&(client->client), client->function_name,
                                    args->arg_count == 3 ? unique : NULL,
                                    args->args[1], (size_t)(args->lengths[1]),
                                    job_handle);
  if (ret != GEARMAN_SUCCESS)
  {
    *error= 1;
    return NULL;
  }

  strncpy(result, job_handle, 255);
  result[254]= 0;
  *length= strlen(result);

  return result;
}

/*
 * Functions for gman_do_high_background.
 */

my_bool gman_do_high_background_init(UDF_INIT *initid, UDF_ARGS *args,
                                char *message);
void gman_do_high_background_deinit(UDF_INIT *initid);
char *gman_do_high_background(UDF_INIT *initid, UDF_ARGS *args, char *result,
                         unsigned long *length, char *is_null, char *error);

my_bool gman_do_high_background_init(UDF_INIT *initid, UDF_ARGS *args,
                                char *message)
{
  return _do_init(initid, args, message);
}

void gman_do_high_background_deinit(UDF_INIT *initid)
{
  _do_deinit(initid);
}

char *gman_do_high_background(UDF_INIT *initid, UDF_ARGS *args, char *result,
                         unsigned long *length, char *is_null, char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  gearman_return_t ret;
  char job_handle[GEARMAN_JOB_HANDLE_SIZE];
  char unique[GEARMAN_UNIQUE_SIZE];
  (void) is_null;

  if (_find_server(client, args) == false)
  {
    *error= 1;
    return NULL;
  }

  if (args->arg_count == 3)
  {
    snprintf(unique, GEARMAN_UNIQUE_SIZE, "%.*s", (int)args->lengths[2],
             args->args[2]);
  }

  ret= gearman_client_do_high_background(&(client->client),
                                         client->function_name,
                                         args->arg_count == 3 ? unique : NULL,
                                         args->args[1],
                                         (size_t)(args->lengths[1]),
                                         job_handle);
  if (ret != GEARMAN_SUCCESS)
  {
    *error= 1;
    return NULL;
  }

  strncpy(result, job_handle, 255);
  result[254]= 0;
  *length= strlen(result);

  return result;
}

/*
 * Functions for gman_do_low_background.
 */

my_bool gman_do_low_background_init(UDF_INIT *initid, UDF_ARGS *args,
                                char *message);
void gman_do_low_background_deinit(UDF_INIT *initid);
char *gman_do_low_background(UDF_INIT *initid, UDF_ARGS *args, char *result,
                         unsigned long *length, char *is_null, char *error);

my_bool gman_do_low_background_init(UDF_INIT *initid, UDF_ARGS *args,
                                char *message)
{
  return _do_init(initid, args, message);
}

void gman_do_low_background_deinit(UDF_INIT *initid)
{
  _do_deinit(initid);
}

char *gman_do_low_background(UDF_INIT *initid, UDF_ARGS *args, char *result,
                         unsigned long *length, char *is_null, char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  gearman_return_t ret;
  char job_handle[GEARMAN_JOB_HANDLE_SIZE];
  char unique[GEARMAN_UNIQUE_SIZE];
  (void) is_null;

  if (_find_server(client, args) == false)
  {
    *error= 1;
    return NULL;
  }

  if (args->arg_count == 3)
  {
    snprintf(unique, GEARMAN_UNIQUE_SIZE, "%.*s", (int)args->lengths[2],
             args->args[2]);
  }

  ret= gearman_client_do_low_background(&(client->client),
                                        client->function_name,
                                        args->arg_count == 3 ? unique : NULL,
                                        args->args[1],
                                        (size_t)(args->lengths[1]),
                                        job_handle);
  if (ret != GEARMAN_SUCCESS)
  {
    *error= 1;
    return NULL;
  }

  strncpy(result, job_handle, 255);
  result[254]= 0;
  *length= strlen(result);

  return result;
}

/*
 * Functions for gman_sum.
 */

my_bool gman_sum_init(UDF_INIT *initid, UDF_ARGS *args, char *message);
void gman_sum_deinit(UDF_INIT *initid);
long long gman_sum(UDF_INIT *initid, UDF_ARGS *args, char *is_null,
                   char *error);
void gman_sum_reset(UDF_INIT *initid, UDF_ARGS *args, char *is_null,
                    char *error);
void gman_sum_clear(UDF_INIT *initid, char *is_null, char *error);
void gman_sum_add(UDF_INIT *initid, UDF_ARGS *args, char *is_null, char *error);

static gearman_return_t _sum_created(gearman_task_st *task);
static gearman_return_t _sum_complete(gearman_task_st *task);
static gearman_return_t _sum_fail(gearman_task_st *task);

my_bool gman_sum_init(UDF_INIT *initid, UDF_ARGS *args, char *message)
{
  if (_do_init(initid, args, message))
    return 1;

  return 0;
}

void gman_sum_deinit(UDF_INIT *initid)
{
  _do_deinit(initid);
}

long long gman_sum(UDF_INIT *initid, UDF_ARGS *args, char *is_null,
                   char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  gearman_return_t ret;
  (void) args;
  (void) is_null;

  ret= gearman_client_run_tasks(&(client->client));
  if (ret != GEARMAN_SUCCESS)
    *error= 1;

  return client->sum;
}

void gman_sum_reset(UDF_INIT *initid, UDF_ARGS *args, char *is_null,
                    char *error)
{
  (void) is_null;

  gman_sum_clear(initid, is_null, error);
  gman_sum_add(initid, args, is_null, error);
}

void gman_sum_clear(UDF_INIT *initid, char *is_null, char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  (void) is_null;
  (void) error;

  client->sum= 0;
  client->pause= false;
}

void gman_sum_add(UDF_INIT *initid, UDF_ARGS *args, char *is_null, char *error)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);
  gearman_return_t ret;
  (void) is_null;

  if (_find_server(client, args) == false)
  {
    *error= 1;
    return;
  }

  gearman_client_set_created_fn(&(client->client), _sum_created);
  gearman_client_set_complete_fn(&(client->client), _sum_complete);
  gearman_client_set_fail_fn(&(client->client), _sum_fail);

  (void)gearman_client_add_task(&(client->client), NULL, client,
                                client->function_name, NULL, args->args[1],
                                (size_t)(args->lengths[1]), &ret);
  if (ret != GEARMAN_SUCCESS)
  {
    *error= 1;
    return;
  }

  ret= gearman_client_run_tasks(&(client->client));
  if (ret != GEARMAN_SUCCESS && ret != GEARMAN_PAUSE)
    *error= 1;
}

static gearman_return_t _sum_created(gearman_task_st *task)
{
  gearman_udf_client_st *client;

  client= (gearman_udf_client_st *)gearman_task_context(task);

  if (client->pause)
  {
    client->pause= false;
    return GEARMAN_SUCCESS;
  }

  client->pause= true;
  return GEARMAN_PAUSE;
}

static gearman_return_t _sum_complete(gearman_task_st *task)
{
  gearman_udf_client_st *client;

  client= (gearman_udf_client_st *)gearman_task_context(task);

  client->sum+= atoi((char *)gearman_task_data(task));

  return GEARMAN_SUCCESS;
}

static gearman_return_t _sum_fail(gearman_task_st *task)
{
  (void)task;
  return GEARMAN_WORK_FAIL;
}

/*
 * Shared client functions.
 */

my_bool _do_init(UDF_INIT *initid, UDF_ARGS *args, char *message)
{
  if (args->arg_count != 2 && args->arg_count != 3)
  { 
    strncpy(message, "Must give two or three arguments.", MYSQL_ERRMSG_SIZE);
    message[MYSQL_ERRMSG_SIZE - 1]= 0;
    return 1;
  }

  if (args->maybe_null[0] == 1 || args->arg_type[0] != STRING_RESULT)
  {
    strncpy(message, "First argument must be a string.", MYSQL_ERRMSG_SIZE);
    message[MYSQL_ERRMSG_SIZE - 1]= 0;
    return 1;
  }

  if (args->lengths[0] >= GEARMAN_UDF_FUNCTION_SIZE)
  {
    strncpy(message, "Function name too long.", MYSQL_ERRMSG_SIZE);
    message[MYSQL_ERRMSG_SIZE - 1]= 0;
    return 1;
  }

  /* Tell MySQL to convert second and third arguments to a string before
     passing it in. */
  args->arg_type[1]= STRING_RESULT;
  if (args->arg_count == 3)
    args->arg_type[2]= STRING_RESULT;

  initid->ptr= calloc(1, sizeof(gearman_udf_client_st));
  if (initid->ptr == NULL)
  { 
    snprintf(message, MYSQL_ERRMSG_SIZE, "calloc() failed: %d", errno);
    return 1;
  }

  initid->maybe_null= 1;
  initid->max_length= GEARMAN_UDF_RESULT_SIZE;
  initid->const_item= 0;

  return 0;
}

void _do_deinit(UDF_INIT *initid)
{
  gearman_udf_client_st *client= (gearman_udf_client_st *)(initid->ptr);

  if (client == NULL)
    return;

  if (client->result != NULL)
    free(client->result);

  if (client->flags & GEARMAN_UDF_CLIENT_CLONED)
    gearman_client_free(&(client->client));

  free(client);
}

bool _find_server(gearman_udf_client_st *client, UDF_ARGS *args)
{
  gearman_udf_server_st *server;
  gearman_udf_server_st *default_server= NULL;

  if (client->result != NULL)
  {
    free(client->result);
    client->result= NULL;
  }

  if (args->lengths[0] >= GEARMAN_UDF_FUNCTION_SIZE)
    return false;

  memcpy(client->function_name, args->args[0], args->lengths[0]);
  client->function_name[args->lengths[0]]= 0;

  if (!(client->flags & GEARMAN_UDF_CLIENT_CLONED))
  {
    pthread_mutex_lock(&_server_lock);

    for (server= _server_list; server != NULL; server= server->next)
    {
      if (server->function_name[0] == 0)
        default_server= server;
      else if (!strcmp(client->function_name, server->function_name))
        break;
    }

    if (server == NULL)
    {
      if (default_server == NULL)
      {
        pthread_mutex_unlock(&_server_lock);
        return false;
      }

      server= default_server;
    }

    if (gearman_client_clone(&(client->client), &(server->client)) == NULL)
    {
      pthread_mutex_unlock(&_server_lock);
      return false;
    }

    client->flags|= GEARMAN_UDF_CLIENT_CLONED;
    pthread_mutex_unlock(&_server_lock);
  }

  return true;
}

static void *_do_malloc(size_t size, void *arg)
{
  if (size <= 255)
    return arg;

  return malloc(size);
}

static void _do_free(void *ptr, void *arg)
{
  if (ptr != arg)
    free(ptr);
}
