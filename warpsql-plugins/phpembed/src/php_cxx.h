// PHPEmbed header
// Copyright (c) 2007 Andrew Bosworth, Facebook, inc
// Modified by Dmitry Zenovich <dzenovich@gmail.com>
// All rights reserved

#ifndef PHPCXX_H
#define PHPCXX_H

#include <php_embed.h>
#include "php_arr.h"

#define SUCCESS 0
#define FAIL 1

#ifdef ZTS
#define PUSH_CTX() tsrm_mutex_lock(lock); \
                   void *prev_ctx = tsrm_set_interpreter_context(ctx); \
                   { TSRMLS_FETCH();
#define POP_CTX() } tsrm_set_interpreter_context(prev_ctx); \
                    tsrm_mutex_unlock(lock); 
#else
#define PUSH_CTX()
#define POP_CTX()
#endif

typedef unsigned int php_ret;

// these function pointers are in a separate class because
// 1) clients of this library should have a simple const char * interface
// 2) the interface to the zend php_embed_module is fixed
// 3) class member functions/variables don't have the same interface
// 4) static class member functions can't access member variables
class php_const
{
public:
  php_const() { 
    message_function = error_function = output_function = NULL; 
#ifdef ZTS
    initialized = false;
    clients = 0;
    init_lock = tsrm_mutex_alloc();
#endif
  }

  ~php_const(){
#ifdef ZTS
    tsrm_mutex_free(init_lock);
#endif
  }

  void (*message_function)(const char *);
  void (*error_function)(const char *);
  void (*output_function)(const char *);
#ifdef ZTS
  bool initialized;
  int clients;
  MUTEX_T init_lock;
#endif
};

// This is the global constants variable we define for the reasons listed above
static php_const p;

class php
{

public:
  php(bool _type_warnings = false);
  ~php();

  // use these to set the php output, php message, and SAPI error behavior
  // default is to send output to stdout and message/error output to stderr
  // using functions defined at the bottom of this file
  //
  // EX p.set_output_function(php_output_function);
  void set_message_function(void (*_message_function)(const char *));
  void set_error_function(void (*_error_function)(const char *));
  void set_output_function(void (*_output_function)(const char *));

  // calling arbitrary php functions with specified return values
  //
  // argspec works as follows:
  // s - string (really char *, must be null terminated!)
  // S - binary-safe string (char * and unsigned int length)
  // i - int (u32/i32)
  // l - long (u64)
  // d - double
  // b - boolean
  // a - array (a POINTER to a php_array object!)
  //
  // EX:
  // long num = 5;
  // char *str = "blah";
  // double pi = 3.14;
  // unsigned int resultStrLen;
  // call_xxx("foo", "lsd", num, str, pi);
  // call_xxx("foo", "S", str, strLen);
  // call_c_string_ex("foo", &resultStrLen, "lsd", num, str, pi);
  //
  void call_void(char *fn, char *argspec =  "", ...);
  long call_long(char *fn, char *argspec =  "", ...);
  bool call_bool(char *fn, char *argspec =  "", ...);
  double call_double(char *fn, char *argspec =  "", ...);
  char * call_c_string(char *fn, char *argspec =  "", ...);
  // returns binary-safe string
  // the string's length will be returned into the resultLen parameter
  char * call_c_string_ex(char *fn, unsigned int *resultLen = NULL, char *argspec =  "", ...);
  // no int or uint versions since the user can just call_long and truncate

  // gotta use this if you're going for nested arrays of any kind
  //
  // NOTE: user is responsible for deleting the returned object
  php_array *call_php_array(char *fn, char *argspec = "", ...);

  // same as above except these return an array of the given type
  // and set the size argument to the size of the array
  //
  // NOTE: user is responsible for freeing the memory allocated by these
  //       functions, the size argument is a return value not a buffer size
  long *call_long_arr(size_t *size, char *fn, char *argspec =  "", ...);
  bool *call_bool_arr(size_t *size, char *fn, char *argspec =  "", ...);
  double *call_double_arr(size_t *size, char *fn, char *argspec =  "", ...);
  char * *call_c_string_arr(size_t *size, char *fn, char *argspec =  "", ...);
  // these really just truncate each long argument, user beware!
  // provided to keep the user from having to do an extra loop through the array
  int *call_int_arr(size_t *size, char *fn, char *argspec =  "", ...);
  unsigned int *call_uint_arr(size_t *size, char *fn, char *argspec =  "", ...);

  // public in case the user wants to load more than 1 php file
  php_ret load(const char *filename);

  // this can be pretty handy if there is a need to set up the environment
  // EX: eval_string("ini_set('memory_limit', '100M');");
  php_ret eval_string(const char *fmt, ...);

  // this is set after any function call or eval_string call
  // the client should probably check that it is set to SUCCESS after each call
  // but especially after attempting to load a file
  php_ret status;

protected:

  // we don't want the user to have to deal with zval returns
  // caller needs to free the zval returned
  zval *call(char *fn, char *argspec, va_list ap TSRMLS_DC);
  zval *call(char *fn, char *argspec = "", ...);

  // we'll use the same function as error_wrap for this
  static void internal_error(const char *str);

  bool type_warnings;

#ifdef ZTS
  // this is the context in which we evaluate this objects requests
  void *ctx;

  // a mutex to prevent re-entrance
  MUTEX_T lock;
#endif

private:

  // these calls match the signatures required by php_embed_module but really
  // just wrap the simple functions ideally provided by the user
  //
  // In the absence of a user provided function, output is sent to stdout and
  // errors are sent to stderr
  static void message_wrap(char *str);
  static void error_wrap(int error, const char *fmt, ...);
  static int output_wrap(const char *str, unsigned int strlen TSRMLS_DC);

  // initialize the global PHP instance
  int init_global_php();
  
  // simulate an ini file on startup
  int php_set_ini_entry(char *entry, char *value, int stage);

  // parse C++ arguments into a zend parameter list
  // the first two arguments are return values
  php_ret parse_args(zval **params,zend_uint *count, char *argspec, va_list ap);
};

#endif
