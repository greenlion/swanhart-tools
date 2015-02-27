// PHPEmbed implementation
// Copyright (c) 2007 Andrew Bosworth, Facebook, inc
// Modified by Dmitry Zenovich <dzenovich@gmail.com>
// All rights reserved

#include "php_cxx.h"
#include <stdio.h>
#include <signal.h>

void php::set_message_function(void (*_message_function)(const char *)){
  p.message_function = _message_function;
}

void php::set_error_function(void (*_error_function)(const char *)){
  p.error_function = _error_function;
}

void php::set_output_function(void (*_output_function)(const char *)){
  p.output_function = _output_function;
}

void php::message_wrap(char * str){
  if(p.message_function != NULL)
    p.message_function(str);
  else 
    fprintf(stderr, "PHP MESSAGE: %s\n", str);
}

void php::internal_error(const char *str){
  if(p.error_function != NULL){
    p.error_function(str);
  } else {
    fprintf(stderr, "PHP ERROR: %s", str);
  }
}

void php::error_wrap(int error, const char * fmt, ...){

  // NOTE: for reasons passing understanding, compiling with ZTS enabled
  // causes vasprintf to seg fault on allocation, so we've implemented this
  // vsnprintf workaround

  va_list ap;
  va_start(ap, fmt);

  char *dummy;
  int size = vsnprintf(dummy, 0, fmt, ap);

  va_end(ap);
  va_start(ap, fmt);

  char buffer[size + 1];
  int ret= vsnprintf(buffer, size + 1, fmt, ap);

  va_end(ap);

  // give up if we failed to allocate a proper buffer
  if(ret < size)
    return;

  internal_error(buffer);
}

int php::output_wrap(const char *str, unsigned int strlen TSRMLS_DC){
  if(strlen <= 0)
    return SUCCESS;

  if(p.output_function != NULL)
    p.output_function(str);
  else
    printf("PHP OUTPUT: %s", str);

  return SUCCESS;
}

// void is the easiest, we just call the function and free the return
void php::call_void(char *fn, char *argspec, ...)
{
  PUSH_CTX();
  zval *rv;

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
    zval_ptr_dtor(&rv);

  POP_CTX();
  return;
}

long php::call_long(char *fn, char *argspec, ...)
{
  long rrv = 0;

  PUSH_CTX();
  zval *rv;
  
  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_LONG)
    {
      if(type_warnings)
        internal_error("TYPE MISMATCH: expected long return type from PHP!\n");

      // copy rv into new memory for the in place conversion
      zval *cp;
      MAKE_STD_ZVAL(cp);
      *cp = *rv;
      zval_copy_ctor(cp);
      INIT_PZVAL(cp);

      // destroy the original now that we have a copy
      zval_ptr_dtor(&rv);

      // rename rv to our copy
      rv = cp;
      convert_to_long_ex(&rv);

    } 

    rrv = Z_LVAL_P(rv);

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

bool php::call_bool(char *fn, char *argspec, ...)
{
  bool rrv = false;

  PUSH_CTX();
  zval *rv;

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_BOOL)
    {
      if(type_warnings)
        internal_error("TYPE MISMATCH: expected bool return type from PHP!\n");

      // copy rv into new memory for the in place conversion
      zval *cp;
      MAKE_STD_ZVAL(cp);
      *cp = *rv;
      zval_copy_ctor(cp);
      INIT_PZVAL(cp);

      // destroy the original now that we have a copy
      zval_ptr_dtor(&rv);

      // rename rv to our copy
      rv = cp;
      convert_to_boolean_ex(&rv);
    }

    rrv = (bool)Z_LVAL_P(rv);
    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

double php::call_double(char *fn, char *argspec, ...)
{
  double rrv = 0;

  PUSH_CTX();
  zval *rv;

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_DOUBLE)
    {
      if(type_warnings)
        internal_error("TYPE MISMATCH: expected double return type from PHP!\n");

      // copy rv into new memory for the in place conversion
      zval *cp;
      MAKE_STD_ZVAL(cp);
      *cp = *rv;
      zval_copy_ctor(cp);
      INIT_PZVAL(cp);

      // destroy the original now that we have a copy
      zval_ptr_dtor(&rv);

      // rename rv to our copy
      rv = cp;
      convert_to_double_ex(&rv);
    }

    rrv = Z_DVAL_P(rv);
    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

char *php::call_c_string(char *fn, char *argspec, ...)
{
  char *rrv;
  va_list ap;

  va_start(ap, argspec);
  rrv = call_c_string_ex(fn, NULL, argspec, ap);
  va_end(ap);

  return rrv;
}

char *php::call_c_string_ex(char *fn, unsigned int* resultStrLen, char *argspec, ...)
{
  char *rrv = NULL;

  if (resultStrLen) {
    *resultStrLen = 0;
  }

  PUSH_CTX();
  zval *rv;

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_STRING)
    {
      if(type_warnings)
        internal_error("TYPE MISMATCH: expected string return type from PHP!\n");

      // copy rv into new memory for the in place conversion
      zval *cp;
      MAKE_STD_ZVAL(cp);
      *cp = *rv;
      zval_copy_ctor(cp);
      INIT_PZVAL(cp);

      // destroy the original now that we have a copy
      zval_ptr_dtor(&rv);

      // rename rv to our copy
      rv = cp;
      convert_to_string_ex(&rv);
    }

    rrv = (char*) malloc(Z_STRLEN_P(rv)+1);
    if(rrv == NULL){
        internal_error("Not enough memory!\n");
        return NULL;
    }
    memcpy(rrv, Z_STRVAL_P(rv), Z_STRLEN_P(rv));
    rrv[Z_STRLEN_P(rv)] = 0;

    if (resultStrLen) {
        *resultStrLen = Z_STRLEN_P(rv);
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

php_array *php::call_php_array(char *fn, char *argspec, ...)
{
  php_array *rrv = NULL;

  PUSH_CTX();
  zval *rv;

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_ARRAY)
    {
      if(type_warnings)
        internal_error("TYPE MISMATCH: expected array return type from PHP!\n");

      // copy rv into new memory for the in place conversion
      zval *cp;
      MAKE_STD_ZVAL(cp);
      *cp = *rv;
      zval_copy_ctor(cp);
      INIT_PZVAL(cp);

      // destroy the original now that we have a copy
      zval_ptr_dtor(&rv);

      // rename rv to our copy
      rv = cp;
      convert_to_array_ex(&rv);
    }

    // create the new php array object
    rrv = new php_array(rv);
    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

long *php::call_long_arr(size_t *size, char *fn, char *argspec, ...)
{
  long *rrv = NULL;

  PUSH_CTX();
  zval *rv;

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_ARRAY)
      internal_error("TYPE MISMATCH: expected array return type from PHP!\n");
    else
    {
      HashTable *ht = Z_ARRVAL_P(rv);
      zval **data;
      rrv = (long *) malloc(zend_hash_num_elements(ht) * sizeof(long));

      // in case we have to copy data and conver tit
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      int i = 0;
      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy data into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv[i] = Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[i] = Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
        i++;
      }

      *size = i;
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

bool *php::call_bool_arr(size_t *size, char *fn, char *argspec, ...)
{
  bool *rrv = NULL;

  PUSH_CTX();
  zval *rv;

  if(NULL == size){
    internal_error("size must point to a valid size_t object\n");
    return NULL;
  }

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_ARRAY)
      internal_error("TYPE MISMATCH: expected array return type from PHP!\n");
    else
    {
      HashTable *ht = Z_ARRVAL_P(rv);
      zval **data;
      rrv = (bool *) malloc(zend_hash_num_elements(ht) * sizeof(bool));

      // in case we have to copy data and conver tit
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      int i = 0;
      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_BOOL)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected bool array entry!\n");

          // copy data into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_boolean_ex(&cp);
        }

        if(copy){
          rrv[i] = (bool)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[i] = (bool)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
        i++;
      }

      *size = i;
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

double *php::call_double_arr(size_t *size, char *fn, char *argspec, ...)
{
  double *rrv = NULL;

  PUSH_CTX();
  zval *rv;

  if(NULL == size){
    internal_error("size must point to a valid size_t object\n");
    return NULL;
  }

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_ARRAY)
      internal_error("TYPE MISMATCH: expected array return type from PHP!\n");
    else
    {
      HashTable *ht = Z_ARRVAL_P(rv);
      zval **data;
      rrv = (double *) malloc(zend_hash_num_elements(ht) * sizeof(double));

      // in case we have to copy data and conver tit
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      int i = 0;
      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_DOUBLE)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected double array entry!\n");

          // copy data into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_double_ex(&cp);
        }

        if(copy){
          rrv[i] = Z_DVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[i] = Z_DVAL_PP(data);
        }

        zend_hash_move_forward(ht);
        i++;
      }

      *size = i;
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

char **php::call_c_string_arr(size_t *size, char *fn, char *argspec, ...)
{
  char **rrv = NULL;

  PUSH_CTX();
  zval *rv;

  if(NULL == size){
    internal_error("size must point to a valid size_t object\n");
    return NULL;
  }

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_ARRAY)
      internal_error("TYPE MISMATCH: expected array return type from PHP!\n");
    else
    {
      HashTable *ht = Z_ARRVAL_P(rv);
      zval **data;
      rrv = (char **) malloc(zend_hash_num_elements(ht) * sizeof(char *));

      // in case we have to copy data and conver tit
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      int i = 0;
      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_STRING)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected string array entry!\n");

          // copy data into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_string_ex(&cp);
        }

        if(copy){
          rrv[i] = strndup(Z_STRVAL_P(cp), Z_STRLEN_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[i] = strndup(Z_STRVAL_PP(data), Z_STRLEN_PP(data));
        }

        zend_hash_move_forward(ht);
        i++;
      }

      *size = i;
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to an int!
int *php::call_int_arr(size_t *size, char *fn, char *argspec, ...)
{
  int *rrv = NULL;

  PUSH_CTX();
  zval *rv;

  if(NULL == size){
    internal_error("size must point to a valid size_t object\n");
    return NULL;
  }

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_ARRAY)
      internal_error("TYPE MISMATCH: expected array return type from PHP!\n");
    else
    {
      HashTable *ht = Z_ARRVAL_P(rv);
      zval **data;
      rrv = (int *) malloc(zend_hash_num_elements(ht) * sizeof(int));

      // in case we have to copy data and conver tit
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      int i = 0;
      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy data into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // typecast each element
        if(copy){
          rrv[i] = (int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[i] = (int)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
        i++;
      }

      *size = i;
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to an int!
unsigned int *php::call_uint_arr(size_t *size, char *fn, char *argspec, ...)
{
  unsigned int *rrv = NULL;

  PUSH_CTX();
  zval *rv;

  if(NULL == size){
    internal_error("size must point to a valid size_t object\n");
    return NULL;
  }

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  if(rv)
  {
    if(Z_TYPE_P(rv) != IS_ARRAY)
      internal_error("TYPE MISMATCH: expected array return type from PHP!\n");
    else
    {
      HashTable *ht = Z_ARRVAL_P(rv);
      zval **data;
      rrv = (unsigned int *) malloc(zend_hash_num_elements(ht)
                                    * sizeof(unsigned int));

      // in case we have to copy data and conver tit
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      int i = 0;
      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy data into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // typecast each element
        if(copy){
          rrv[i] = (unsigned int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[i] = (unsigned int)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
        i++;
      }

      *size = i;
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// We could go to great lengths to use zend_file_handles...or just do this...
php_ret php::load(const char *filename)
{
  return eval_string("include_once('%s');", filename);
}

// evaulate a string of php in our environment, changes are permanent!
php_ret php::eval_string(const char *fmt, ...)
{
  char *data = NULL;
  va_list ap;

  va_start(ap, fmt);

  PUSH_CTX();

  zend_first_try {
    vspprintf(&data, 0, fmt, ap);
    status = zend_eval_string(data, NULL, "" TSRMLS_CC);
  } zend_catch {
    status = FAIL;
  } zend_end_try();

  if (data)
    efree(data);

  POP_CTX();
  va_end(ap);
  return status;
}

// call an arbitrary php function with the given arguments
zval *php::call(char *fn, char *argspec, ...){
  zval *rv;

  PUSH_CTX();

  va_list ap;
  va_start(ap, argspec);
  rv = call(fn, argspec, ap TSRMLS_CC);
  va_end(ap);

  POP_CTX();

  return rv;
}

// call an arbitrary php function with the given arguments
zval *php::call(char *fn, char *argspec, va_list ap TSRMLS_DC)
{
  zval *rrv = NULL;

  zend_try {
    // convert the function name to a zval
    zval *function_name;
    MAKE_STD_ZVAL(function_name);
    ZVAL_STRING(function_name, fn, 0);

    // parse the parameter list
    zval *params[strlen(argspec)];
    zend_uint count;
    if (parse_args(params, &count, argspec, ap) != SUCCESS)
    {
      error_wrap(0, "parsing args for function %s\n", fn);
      for(unsigned int i = 0; i < count; i++)
        if(params[i]) zval_ptr_dtor(&params[i]);
      efree(function_name);
      status = FAIL;
    }

    if(status != FAIL){
      zval *rv;
      MAKE_STD_ZVAL(rv);
      if(call_user_function(EG(function_table), NULL, function_name, rv,
                            count, params TSRMLS_CC) != SUCCESS)
      {
        error_wrap(0, "calling function %s\n", fn);
        for(unsigned int i = 0; i < count; i++)
          if(params[i]) zval_ptr_dtor(&params[i]);
        efree(function_name);
        status = FAIL;
      }

      if(status != FAIL){
        for(unsigned int i = 0; i < count; i++)
          if(params[i]) zval_ptr_dtor(&params[i]);
        efree(function_name);
        rrv = rv;
      }
    }
  } zend_catch {
    error_wrap(0, "preparing function %s\n", fn);
    status = FAIL;
  } zend_end_try() {
  }

  return rrv;
}


// parse arbitrary C++ argument list into a zend argument list based on
// argspec string.
//
//  params and count are return values.
//  params should be freed by the caller
php_ret php::parse_args(zval **params, zend_uint *count, char *argspec, va_list ap)
{
  int i = 0;
  for(char *trav = argspec; *trav; trav++)
  {
    MAKE_STD_ZVAL(params[i]);
    switch(*trav)
    {
    case 'b':
      {
        // va promotes bools to ints
        int arg = va_arg(ap, int);
        ZVAL_BOOL(params[i], arg);
      }
      break;

    case 'i':
      {
        int arg = va_arg(ap, int);
        ZVAL_LONG(params[i], arg);
      }
      break;

    case 'l':
      {
        long arg = va_arg(ap, long);
        ZVAL_LONG(params[i], arg);
      }
      break;

    case 'd':
      {
        double arg = va_arg(ap, double);
        ZVAL_DOUBLE(params[i], arg);
      }
      break;

    case 's':
      {
        char *arg = va_arg(ap, char *);
        ZVAL_STRING(params[i], arg, 1);
      }
      break;

    case 'S':
      {
        char *arg = va_arg(ap, char *);
        unsigned int binStrLen = va_arg(ap, unsigned int);
        ZVAL_STRINGL(params[i], arg, binStrLen, 1);
      }
      break;

    case 'a':
      {
        php_array *arg = va_arg(ap, php_array *);
        *params[i] = *arg->data();

        // forge on even if this isn't what we expected
        if(type_warnings && Z_TYPE_P(params[i]) != IS_ARRAY)
          internal_error("TYPE WARNING: Expecting an array argument!\n");

        // copy the contents to new memory for this array
        zval_copy_ctor(params[i]);

        // reset refcount
        INIT_PZVAL(params[i]);
      }
      break;

    default:
      error_wrap(0, "encountered bad argument specifier: %s\n", trav);
      status = FAIL;
      return status;
    }

    i++;
  }

  *count = i;

  return SUCCESS;
}

// set an ini entry (you could also do this with set_ini in a php file)
int php::php_set_ini_entry(char *entry, char *value, int stage)
{
  return zend_alter_ini_entry(entry, strlen(entry)+1, value, strlen(value)+1,
                              PHP_INI_USER, stage);
}

php::~php()
{
  PUSH_CTX();
#ifdef ZTS
  php_request_shutdown(TSRMLS_C);
#else
  php_request_shutdown((void *)NULL);
#endif
  POP_CTX();
#ifdef ZTS
  tsrm_mutex_free(lock);
  tsrm_free_interpreter_context(ctx);
  p.clients--;
  if(p.clients == 0 && p.initialized == true){
    p.initialized = false;
#endif 
    p.message_function = p.error_function = p.output_function = NULL;
    TSRMLS_FETCH();
    php_module_shutdown(TSRMLS_C);
    sapi_shutdown();
#ifdef ZTS
    tsrm_shutdown();
  } 
#endif
}

php::php(bool _type_warnings)
{
  type_warnings = _type_warnings;

  // this will happen only once, but it keeps track of that
  if(SUCCESS != init_global_php()){
    internal_error("PHP ERROR: failed to initialize global PHP object\n");
    status = FAIL;
  }

  // now we need to create a specific context for this particular object
#ifdef ZTS
  ctx = tsrm_new_interpreter_context();
  lock = tsrm_mutex_alloc();
# endif

  PUSH_CTX();

  // Set some Embedded PHP defaults 
  zend_alter_ini_entry("html_errors", 12, "0", 1, PHP_INI_SYSTEM, PHP_INI_STAGE_ACTIVATE);
  zend_alter_ini_entry("implicit_flush", 15, "1", 1, PHP_INI_SYSTEM, PHP_INI_STAGE_ACTIVATE);
  zend_alter_ini_entry("max_execution_time", 19, "0", 1, PHP_INI_SYSTEM, PHP_INI_STAGE_ACTIVATE);
  zend_alter_ini_entry("variables_order", 16, "S", 1, PHP_INI_SYSTEM, PHP_INI_STAGE_ACTIVATE);

  if (php_request_startup(TSRMLS_C)==FAILURE) {
    internal_error("PHP ERROR: failed initializing php_request\n");
    status = FAIL;
  }

  SG(options) |= SAPI_OPTION_NO_CHDIR;
  SG(headers_sent) = 1;
  SG(request_info).no_headers = 1;

  PG(during_request_startup) = 0;

  // as an embedded module, we don't want any PHP timeout!
  php_set_ini_entry("max_execution_time", "0", PHP_INI_STAGE_ACTIVATE);
  // Terminate any currently running timeout interval-timer
  zend_unset_timeout(TSRMLS_C);
  // And remove the signal handler just to make sure
  signal(SIGPROF, SIG_IGN);
  
  // we don't have get or post or cookie data in an embedded context
  php_set_ini_entry("variables_order", "S", PHP_INI_STAGE_ACTIVATE);

  POP_CTX();
}

int php::init_global_php(){

  // PHPE: lots of objects will use this one initialized php instance
#ifdef ZTS
  tsrm_mutex_lock(p.init_lock);
  p.clients++;
  if(p.initialized == true){
    tsrm_mutex_unlock(p.init_lock);
    return SUCCESS;
  }
#endif

  // set up the callbacks
  php_embed_module.sapi_error = error_wrap;
  php_embed_module.log_message = message_wrap;
  php_embed_module.ub_write = output_wrap;

  // ADAPTED FROM php_embed_init, look for PHPE comments for our local edits

  zend_llist global_vars;
#ifdef ZTS
  zend_compiler_globals *compiler_globals;
  zend_executor_globals *executor_globals;
  php_core_globals *core_globals;
  sapi_globals_struct *sapi_globals;
  void ***tsrm_ls;
#endif

#ifdef HAVE_SIGNAL_H
#if defined(SIGPIPE) && defined(SIG_IGN)
    signal(SIGPIPE, SIG_IGN); /* ignore SIGPIPE in standalone mode so
                                 that sockets created via fsockopen()
                                 don't kill PHP if the remote site
                                 closes it.  in apache|apxs mode apache
                                 does that for us!  thies@thieso.net
                                 20000419 */
#endif
#endif

#ifdef PHP_WIN32
  _fmode = _O_BINARY;                //sets default for file streams to binary 
  setmode(_fileno(stdin), O_BINARY);          // make the stdio mode be binary 
  setmode(_fileno(stdout), O_BINARY);         // make the stdio mode be binary 
  setmode(_fileno(stderr), O_BINARY);         // make the stdio mode be binary 
#endif

#ifdef ZTS
  // PHPE: if they want threads, let's give them more than 1!
  tsrm_startup(128, 32, 0, NULL);
  compiler_globals = (zend_compiler_globals *)ts_resource(compiler_globals_id);
  executor_globals = (zend_executor_globals *)ts_resource(executor_globals_id);
  core_globals = (php_core_globals *)ts_resource(core_globals_id);
  sapi_globals = (sapi_globals_struct *)ts_resource(sapi_globals_id);
  tsrm_ls = (void ***)ts_resource(0);
#endif

  sapi_startup(&php_embed_module);

  if (php_embed_module.startup(&php_embed_module)==FAILURE) {
    return FAILURE;
  }

  zend_llist_init(&global_vars, sizeof(char *), NULL, 0);

#ifdef ZTS
  p.initialized = true;
  tsrm_mutex_unlock(p.init_lock);
#endif

  return SUCCESS;
  // END ADAPTED php_embed_init
}

