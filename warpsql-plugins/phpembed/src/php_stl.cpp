// PHPEmbed STL implementation
// Copyright (c) 2007 Andrew Bosworth, Facebook, inc
// Modified by Dmitry Zenovich <dzenovich@gmail.com>
// All rights reserved

#include "php_stl.h"

string php_stl::call_string(char *fn, char *argspec, ...)
{
  string rrv;

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

    rrv = string(Z_STRVAL_P(rv), Z_STRLEN_P(rv));
    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

vector<string> php_stl::call_string_vector(char *fn, char *argspec, ...)
{
  vector<string> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_STRING)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected string array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_string_ex(&cp);
        }

        if(copy){
          rrv.push_back(string(Z_STRVAL_P(cp), Z_STRLEN_P(cp)));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.push_back(string(Z_STRVAL_PP(data), Z_STRLEN_PP(data)));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

vector<double> php_stl::call_double_vector(char *fn, char *argspec, ...)
{
  vector<double> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_DOUBLE)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected double array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_double_ex(&cp);
        }

        if(copy){
          rrv.push_back(Z_DVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.push_back(Z_DVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

vector<long> php_stl::call_long_vector(char *fn, char *argspec, ...)
{
  vector<long> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.push_back(Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.push_back(Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

vector<bool> php_stl::call_bool_vector(char *fn, char *argspec, ...)
{
  vector<bool> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_BOOL)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected bool array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_boolean_ex(&cp);
        }

        if(copy){
          rrv.push_back((bool)Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.push_back((bool)Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to an int!
vector<int> php_stl::call_int_vector(char *fn, char *argspec, ...)
{
  vector<int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.push_back((int)Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.push_back((int)Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to an unsigned int!
vector<unsigned int> php_stl::call_uint_vector(char *fn, char *argspec, ...)
{
  vector<unsigned int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.push_back((unsigned int)Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.push_back((unsigned int)Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

set<string> php_stl::call_string_set(char *fn, char *argspec, ...)
{
  set<string> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_STRING)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected string array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_string_ex(&cp);
        }

        if(copy){
          rrv.insert(string(Z_STRVAL_P(cp), Z_STRLEN_P(cp)));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert(string(Z_STRVAL_PP(data), Z_STRLEN_PP(data)));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

set<double> php_stl::call_double_set(char *fn, char *argspec, ...)
{
  set<double> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_DOUBLE)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected double array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_double_ex(&cp);
        }

        if(copy){
          rrv.insert(Z_DVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert(Z_DVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

set<long> php_stl::call_long_set(char *fn, char *argspec, ...)
{
  set<long> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.insert(Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert(Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to an int!
set<int> php_stl::call_int_set(char *fn, char *argspec, ...)
{
  set<int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.insert((int)Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert((int)Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to an unsigned int!
set<unsigned int> php_stl::call_uint_set(char *fn, char *argspec, ...)
{
  set<unsigned int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.insert((unsigned int)Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert((unsigned int)Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_set<string> php_stl::call_string_hash_set(char *fn, char *argspec, ...)
{
  hash_set<string> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_STRING)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected string array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_string_ex(&cp);
        }

        if(copy){
          rrv.insert(string(Z_STRVAL_P(cp), Z_STRLEN_P(cp)));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert(string(Z_STRVAL_PP(data), Z_STRLEN_PP(data)));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_set<long> php_stl::call_long_hash_set(char *fn, char *argspec, ...)
{
  hash_set<long> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.insert(Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert(Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_set<int> php_stl::call_int_hash_set(char *fn, char *argspec, ...)
{
  hash_set<int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.insert((int)Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert((int)Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_set<unsigned int> php_stl::call_uint_hash_set(char *fn, char *argspec, ...)
{
  hash_set<unsigned int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        if(copy){
          rrv.insert((unsigned int)Z_LVAL_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv.insert((unsigned int)Z_LVAL_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

map<string, string> php_stl::call_string_string_map(char *fn, char *argspec, ...)
{
  map<string, string> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_STRING)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected string array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_string_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = string(Z_STRVAL_P(cp),
                                                     Z_STRLEN_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = string(Z_STRVAL_PP(data),
                                                     Z_STRLEN_PP(data));
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

map<string, double> php_stl::call_string_double_map(char *fn, char *argspec, ...)
{
  map<string, double> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_DOUBLE)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected double array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_double_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len, &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = Z_DVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = Z_DVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

map<string, long> php_stl::call_string_long_map(char *fn, char *argspec, ...)
{
  map<string, long> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = Z_LVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

map<string, bool> php_stl::call_string_bool_map(char *fn, char *argspec, ...)
{
  map<string, bool> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_BOOL)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected bool array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_boolean_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = (bool)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = (bool)Z_LVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to and int!
map<string, int> php_stl::call_string_int_map(char *fn, char *argspec, ...)
{
  map<string, int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = (int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = (int)Z_LVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to an unsigned int!
map<string, unsigned int> php_stl::call_string_uint_map(char *fn, char *argspec, ...)
{
  map<string, unsigned int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = (unsigned int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = (unsigned int)Z_LVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

map<long, string> php_stl::call_long_string_map(char *fn, char *argspec, ...)
{
  map<long, string> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_STRING)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected string array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_string_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 0);

        if(copy){
          rrv[num_index] = string(Z_STRVAL_P(cp), Z_STRLEN_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = string(Z_STRVAL_PP(data), Z_STRLEN_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

map<long, double> php_stl::call_long_double_map(char *fn, char *argspec, ...)
{
  map<long, double> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_DOUBLE)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected double array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_double_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 0);

        if(copy){
          rrv[num_index] = Z_DVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = Z_DVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

map<long, long> php_stl::call_long_long_map(char *fn, char *argspec, ...)
{
  map<long, long> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 0);

        if(copy){
          rrv[num_index] = Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

map<long, bool> php_stl::call_long_bool_map(char *fn, char *argspec, ...)
{
  map<long, bool> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_BOOL)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected bool array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_boolean_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 0);

        if(copy){
          rrv[num_index] = (bool)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = (bool)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to and int!
map<long, int> php_stl::call_long_int_map(char *fn, char *argspec, ...)
{
  map<long, int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 0);

        if(copy){
          rrv[num_index] = (int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = (int)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to and unsigned int!
map<long, unsigned int> php_stl::call_long_uint_map(char *fn, char *argspec, ...)
{
  map<long, unsigned int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 0);

        if(copy){
          rrv[num_index] = (unsigned int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = (unsigned int)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_map<string, string> php_stl::call_string_string_hash_map(char *fn, char *argspec, ...)
{
  hash_map<string, string> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_STRING)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected string array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_string_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len, &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = string(Z_STRVAL_P(cp),
                                                     Z_STRLEN_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = string(Z_STRVAL_PP(data),
                                                     Z_STRLEN_PP(data));
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_map<string, double> php_stl::call_string_double_hash_map(char *fn, char *argspec, ...)
{
  hash_map<string, double> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_DOUBLE)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected double array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_double_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = Z_DVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = Z_DVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_map<string, long> php_stl::call_string_long_hash_map(char *fn, char *argspec, ...)
{
  hash_map<string, long> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = Z_LVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_map<string, bool> php_stl::call_string_bool_hash_map(char *fn, char *argspec, ...)
{
  hash_map<string, bool> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_BOOL)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected bool array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_boolean_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = (bool)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = (bool)Z_LVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to and int!
hash_map<string, int> php_stl::call_string_int_hash_map(char *fn, char *argspec, ...)
{
  hash_map<string, int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = (int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = (int)Z_LVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to and unsigned int!
hash_map<string, unsigned int> php_stl::call_string_uint_hash_map(char *fn, char *argspec, ...)
{
  hash_map<string, unsigned int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        uint  str_len;
        zend_hash_get_current_key_ex(ht, &str_index, &str_len,
                                     &num_index, 1, NULL);

        if(copy){
          rrv[string(str_index, str_len-1)] = (unsigned int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[string(str_index, str_len-1)] = (unsigned int)Z_LVAL_PP(data);
        }
        efree(str_index);

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_map<long, string> php_stl::call_long_string_hash_map(char *fn, char *argspec, ...)
{
  hash_map<long, string> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_STRING)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected string array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_string_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 1);

        if(copy){
          rrv[num_index] = string(Z_STRVAL_P(cp), Z_STRLEN_P(cp));
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = string(Z_STRVAL_PP(data), Z_STRLEN_PP(data));
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_map<long, double> php_stl::call_long_double_hash_map(char *fn, char *argspec, ...)
{
  hash_map<long, double> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_DOUBLE)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected double array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_double_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 1);

        if(copy){
          rrv[num_index] = Z_DVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = Z_DVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_map<long, long> php_stl::call_long_long_hash_map(char *fn, char *argspec, ...)
{
  hash_map<long, long> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 1);

        if(copy){
          rrv[num_index] = Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

hash_map<long, bool> php_stl::call_long_bool_hash_map(char *fn, char *argspec, ...)
{
  hash_map<long, bool> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_BOOL)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected bool array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_boolean_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 1);

        if(copy){
          rrv[num_index] = (bool)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = (bool)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to and int!
hash_map<long, int> php_stl::call_long_int_hash_map(char *fn, char *argspec, ...)
{
  hash_map<long, int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 1);

        if(copy){
          rrv[num_index] = (int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = (int)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

// NOTE: this just truncates the php long to and unsigned int!
hash_map<long, unsigned int> php_stl::call_long_uint_hash_map(char *fn, char *argspec, ...)
{
  hash_map<long, unsigned int> rrv;

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

      // in case we have to copy the data and convert it
      zval *cp;
      bool copy = false;

      zend_hash_internal_pointer_reset(ht);

      while (zend_hash_get_current_data(ht, (void **)&data) == SUCCESS)
      {
        if(Z_TYPE_PP(data) != IS_LONG)
        {
          if(type_warnings)
            internal_error("TYPE MISMATCH: expected long array entry!\n");

          // copy rv into new memory for the in place conversion
          copy = true;
          MAKE_STD_ZVAL(cp);
          *cp = **data;
          zval_copy_ctor(cp);
          INIT_PZVAL(cp);
          convert_to_long_ex(&cp);
        }

        // extract the corresponding key too (just str for now)
        char *str_index;
        ulong num_index;
        zend_hash_get_current_key(ht, &str_index, &num_index, 1);

        if(copy){
          rrv[num_index] = (unsigned int)Z_LVAL_P(cp);
          zval_ptr_dtor(&cp);
          copy = false;
        } else {
          rrv[num_index] = (unsigned int)Z_LVAL_PP(data);
        }

        zend_hash_move_forward(ht);
      }
    }

    zval_ptr_dtor(&rv);
  }

  POP_CTX();
  return rrv;
}

