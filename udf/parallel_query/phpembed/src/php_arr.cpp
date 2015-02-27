// PHPEmbed array implementation
// Copyright (c) 2007 Andrew Bosworth, Facebook, inc
// Modified by Dmitry Zenovich <dzenovich@gmail.com>
// All rights reserved

#include "php_arr.h"


// create an empty array
php_array::php_array(){
  MAKE_STD_ZVAL(array);
  array_init(array);
}

// destroy the array
php_array::~php_array(){
  zval_ptr_dtor(&array);
}

// copy an existing array
php_array::php_array(zval *val){
  MAKE_STD_ZVAL(array);
  *array = *val;
  zval_copy_ctor(array);
  INIT_PZVAL(array);
}

// just use an existing array (no copy)
php_array::php_array(zval **val){
  zval_add_ref(val);
  array = *val;
}

// copy constructor for deep copying of zval
php_array::php_array(const php_array &pa){
  MAKE_STD_ZVAL(array);
  *array = *(pa.array);
  zval_copy_ctor(array);
  INIT_PZVAL(array);
};

const php_array& php_array::operator=(const php_array &pa){
  zval_ptr_dtor(&array);
  array = pa.array;
  zval_add_ref(&array);
  return *this;
}

// a wrapper for the other version of add assoc
void php_array::add_assoc(char *argspec, ...){

  if(!argspec){
    fprintf(stderr, "got NULL argument specifier\n");
    return;
  }

  va_list ap;
  va_start(ap, argspec);
  add_assoc(argspec, ap);
  va_end(ap);
}

// parse a variable argument list into the associative array
void php_array::add_assoc(char *argspec, va_list ap){

  if(!argspec){
    fprintf(stderr, "got NULL argument specifier\n");
    return;
  }

  if(!argspec){
    fprintf(stderr, "got NULL argument specifier\n");
    return;
  }

  // there needs to be an even number of arguments
  if(strlen(argspec) % 2 != 0){
    fprintf(stderr, "associative arguments must be even in number!\n");
    return;
  }

  bool even = true;
  char *key, *realKey;
  unsigned int key_len = 0;
  bool freeKey = false;
  for(char *trav = argspec; *trav; trav++)
  {

    if(even && *trav != 's' && *trav != 'S') {
      fprintf(stderr, "every other associative argument must be a string\n");
      return;
    }

    if(!even) {
      if (key_len == 0) {
        key_len = strlen(key) + 1;
        realKey = key;
      } else {
        realKey = (char*) malloc(key_len + 1);
        if (realKey == NULL) {
          fprintf(stderr, "not enough memory\n");
          return;
        }
        freeKey = true;
        memcpy(realKey, key, key_len);
        realKey[key_len] = 0;
        key_len++;
      }
    }

    switch(*trav)
    {
      case 'b':
        {
          // va promotes bools to ints
          int arg = va_arg(ap, int);
          add_assoc_bool_ex(array, realKey, key_len, arg);
        }
        break;

      case 'i':
        {
          int arg = va_arg(ap, int);
          add_assoc_long_ex(array, realKey, key_len, arg);
        }
        break;

      case 'l':
        {
          long arg = va_arg(ap, long);
          add_assoc_long_ex(array, realKey, key_len, arg);
        }
        break;

      case 'd':
        {
          double arg = va_arg(ap, double);
          add_assoc_double_ex(array, realKey, key_len, arg);
        }
        break;

      case 's':
        {
          char *arg = va_arg(ap, char *);

          if(even) {
            key = arg;
            key_len = 0;
          } else {
            add_assoc_string_ex(array, realKey, key_len, arg, true);
          }
        }
        break;
      case 'S':
        {
          char *arg = va_arg(ap, char *);
          if(even) {
            key = arg;
            key_len = va_arg(ap, unsigned int);
          } else {
            unsigned int str_len = va_arg(ap, unsigned int);
            add_assoc_stringl_ex(array, realKey, key_len, arg, str_len, true);
          }
        }
        break;

      case 'a':
        {
          php_array *arg = va_arg(ap, php_array *);
          if(!arg){
            fprintf(stderr, "got null php_array argument\n");
            return;
          }

          // copy the contents to new memory for this array before adding
          zval *data;
          MAKE_STD_ZVAL(data);
          *data = *(arg->data());
          zval_copy_ctor(data);
          INIT_PZVAL(data);

          add_assoc_zval_ex(array, realKey, key_len, data);
          key_len = 0;
        }
        break;

      default:
        fprintf(stderr, "encountered bad argument specifier: %s\n", trav);
        return;
    }

    if (freeKey) {
      free(realKey);
      freeKey = false;
    }

    if (!even) {
      key_len = 0;
    }

    even = !even;
  }
}

// remove value associated with key from the array based on string
// provide non-zero value as the len parameter to remove data
// by a binary-safe associative string index
void php_array::remove(char *key, unsigned int len){
  char *nkey = key;
  if(!len){
    len = strlen(key);
  } else {
    nkey = estrndup(key, len);
  }
  zend_hash_del(Z_ARRVAL_P(array), nkey, len+1);
  if(nkey!=key){
    efree(nkey);
  }
}


// a wrapper for the other version of add assoc
void php_array::add_index(char *argspec, ...){

  if(!argspec){
    fprintf(stderr, "got NULL argument specifier\n");
    return;
  }

  va_list ap;
  va_start(ap, argspec);
  add_index(argspec, ap);
  va_end(ap);
}

// parse a variable argument list into the indexed array at precise indices
void php_array::add_index(char *argspec, va_list ap){

  // there needs to be an even number of arguments
  if(strlen(argspec) % 2 != 0){
    fprintf(stderr, "enumerated index arguments must be even in number!\n");
    return;
  }

  bool even = true;
  for(char *trav = argspec; *trav; trav++)
  {
    long index;

    if(even && *trav != 'l'){
      fprintf(stderr, "every other associative argument must be a long\n");
      return;
    }

    switch(*trav)
    {
      case 'b':
        {
          // va promotes bools to ints
          int arg = va_arg(ap, int);
          add_index_bool(array, index, arg);
        }
        break;

      case 'i':
        {
          int arg = va_arg(ap, int);
          add_index_long(array, index, arg);
        }
        break;

      case 'l':
        {
          long arg = va_arg(ap, long);

          if(even)
            index = arg;
          else
            add_index_long(array, index, arg);
        }
        break;

      case 'd':
        {
          double arg = va_arg(ap, double);
          add_index_double(array, index, arg);
        }
        break;

      case 's':
        {
          char *arg = va_arg(ap, char *);
          add_index_string(array, index, arg, true);
        }
        break;

      case 'S':
        {
          char *arg = va_arg(ap, char *);
          uint str_len = va_arg(ap, uint);
          add_index_stringl(array, index, arg, str_len, true);
        }
        break;
      case 'a':
        {
          php_array *arg = va_arg(ap, php_array *);
          if(!arg){
            fprintf(stderr, "got null php_array argument\n");
            return;
          }

          // copy the contents to new memory for this array before adding
          zval *data;
          MAKE_STD_ZVAL(data);
          *data = *(arg->data());
          zval_copy_ctor(data);
          INIT_PZVAL(data);

          add_index_zval(array, index, data);
        }
        break;

      default:
        fprintf(stderr, "encountered bad argument specifier: %s\n", trav);
        return;
    }

    even = !even;
  }
}

// remove key from the array based on the index
void php_array::remove(long index){
  zend_hash_index_del(Z_ARRVAL_P(array), index);
}


// a wrapper for the other version of add assoc
void php_array::add(char *argspec, ...){

  if(!argspec){
    fprintf(stderr, "got NULL argument specifier\n");
    return;
  }

  va_list ap;
  va_start(ap, argspec);
  add_enum(argspec, ap);
  va_end(ap);
}


// parse a variable argument list into the array with sequential indices
void php_array::add_enum(char *argspec, va_list ap){

  if(!argspec){
    fprintf(stderr, "got NULL argument specifier\n");
    return;
  }

  for(char *trav = argspec; *trav; trav++)
  {
    switch(*trav)
    {
      case 'b':
        {
          // va promotes bools to ints
          int arg = va_arg(ap, int);
          add_next_index_bool(array, arg);
        }
        break;

      case 'i':
        {
          int arg = va_arg(ap, int);
          add_next_index_long(array, arg);
        }
        break;

      case 'l':
        {
          long arg = va_arg(ap, long);
          add_next_index_long(array, arg);
        }
        break;

      case 'd':
        {
          double arg = va_arg(ap, double);
          add_next_index_double(array, arg);
        }
        break;

      case 's':
        {
          char *arg = va_arg(ap, char *);
          add_next_index_string(array, arg, true);
        }
        break;

      case 'S':
        {
          char *arg = va_arg(ap, char *);
          uint str_len = va_arg(ap, uint);
          add_next_index_stringl(array, arg, str_len, true);
        }
        break;

      case 'a':
        {
          php_array *arg = va_arg(ap, php_array *);
          if(!arg){
            fprintf(stderr, "got null php_array argument\n");
            return;
          }

          // copy the contents to new memory for this array before adding
          zval *data;
          MAKE_STD_ZVAL(data);
          *data = *(arg->data());
          zval_copy_ctor(data);
          INIT_PZVAL(data);

          add_next_index_zval(array, data);
        }
        break;

      default:
        fprintf(stderr, "encountered bad argument specifier: %s\n", trav);
        return;
    }
  }
}

php_iterator::php_iterator(php_array &a, bool _type_warnings){

  type_warnings = _type_warnings;

  if(Z_TYPE_P(a.array) != IS_ARRAY){
    fprintf(stderr, "ERROR: expected an array!\n");
    ht = NULL;
    return;
  }

  ht = Z_ARRVAL_P(a.array);

  go_to_start();
}

bool php_iterator::done(){
  zval **data;
  return(zend_hash_get_current_data(ht, (void **)&data) != SUCCESS);
}

void php_iterator::go_to_start(){
  zend_hash_internal_pointer_reset(ht);
}

void php_iterator::go_to_end(){
  zend_hash_internal_pointer_end(ht);
}

int php_iterator::size(){
   return zend_hash_num_elements(ht);
}

php_type php_iterator::get_key_type(){
  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return IS_NULL;
  }

  switch(zend_hash_get_current_key_type(ht)){
    case HASH_KEY_IS_STRING: 
      return IS_STRING;
      break;
    case HASH_KEY_IS_LONG:
      return IS_LONG;
      break;
    default:
      return 0;
      break;
  }
}

// if the len is non-zero the string's length will be returned into it
char *php_iterator::get_key_c_string(uint *len){
  char *str_index;
  ulong num_index;
  uint key_len;
  char *rrv;

  if(len != NULL){
    *len = 0;
  }

  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return NULL;
  }

  zend_hash_get_current_key_ex(ht, &str_index, &key_len, &num_index, 0, NULL);
  rrv = (char*) malloc(key_len);
  if(rrv == NULL){
    fprintf(stderr, "ERROR: not enough memory!\n");
    return NULL;
  }

  memcpy(rrv, str_index, key_len);
  
  if (len != NULL) {
    *len = key_len - 1;
  }

  return rrv;
}

long php_iterator::get_key_long(){
  char *str_index;
  ulong num_index;

  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return 0;
  }

  zend_hash_get_current_key(ht, &str_index, &num_index, 0);

  return num_index;
}

php_type php_iterator::get_data_type(){
  zval **data;

  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return IS_NULL;
  }

  if(zend_hash_get_current_data(ht, (void **)&data) != SUCCESS){
    fprintf(stderr, "ERROR: can't get data at current location\n");
    return 0;
  }

  return Z_TYPE_PP(data);
}

//if the len is non-zero the string's length will be returned into it
char *php_iterator::get_data_c_string(uint* len){
  zval **data;
  char *result;
  uint internalLen = 0;

  if(len != NULL){
    *len = 0;
  }

  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return NULL;
  }

  if(zend_hash_get_current_data(ht, (void **)&data) != SUCCESS){
    fprintf(stderr, "ERROR: can't get data at current location\n");
    return NULL;
  }

  if(Z_TYPE_PP(data) != IS_STRING){
    if(type_warnings)
      fprintf(stderr, "TYPE MISMATCH: expected string array entry!\n");
    convert_to_string_ex(data);
  }

  internalLen = Z_STRLEN_PP(data);
  result = (char*) malloc(internalLen + 1);
  if(result == NULL){
    fprintf(stderr, "ERROR: not enough memory!\n");
    return NULL;
  }

  if (len != NULL) {
    *len = internalLen;
  }

  return (char*)memcpy((void*)result, (void*)Z_STRVAL_PP(data), internalLen+1);
}

double php_iterator::get_data_double(){
  zval **data;

  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return 0;
  }

  if(zend_hash_get_current_data(ht, (void **)&data) != SUCCESS){
    fprintf(stderr, "ERROR: can't get data at current location");
    return 0;
  }

  if(Z_TYPE_PP(data) != IS_DOUBLE){
    if(type_warnings)
      fprintf(stderr, "TYPE MISMATCH: expected double array entry!\n");
    convert_to_double_ex(data);
  }

  return Z_DVAL_PP(data);
}

long php_iterator::get_data_long(){
  zval **data;

  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return 0;
  }

  if(zend_hash_get_current_data(ht, (void **)&data) != SUCCESS){
    fprintf(stderr, "ERROR: can't get data at current location");
    return 0;
  }

  if(Z_TYPE_PP(data) != IS_LONG){
    if(type_warnings)
      fprintf(stderr, "TYPE MISMATCH: expected long array entry!\n");
    convert_to_long_ex(data);
  }

  return Z_LVAL_PP(data);
}

bool php_iterator::get_data_bool(){
  zval **data;

  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return false;
  }

  if(zend_hash_get_current_data(ht, (void **)&data) != SUCCESS){
    fprintf(stderr, "ERROR: can't get data at current location\n");
    return false;
  }

  if(Z_TYPE_PP(data) != IS_BOOL){
    if(type_warnings)
      fprintf(stderr, "TYPE MISMATCH: expected bool array entry!\n");
    convert_to_boolean_ex(data);
  }

  return (bool)Z_LVAL_PP(data);
}

php_array php_iterator::get_data_array(){
  zval **data;

  if(done()){
    fprintf(stderr, "ERROR: iterator doesn't point to a valid entry!\n");
    return php_array();
  }

  if(zend_hash_get_current_data(ht, (void **)&data) != SUCCESS){
    fprintf(stderr, "ERROR: can't get data at current location\n");
    return php_array();
  }

  if(Z_TYPE_PP(data) != IS_ARRAY){
    if(type_warnings)
      fprintf(stderr, "TYPE MISMATCH: expected array array entry!\n");
    convert_to_array_ex(data);
  }

  return php_array(data);
}

void php_iterator::operator++(int ignore){
  if(!done())
    zend_hash_move_forward(ht);
}

void php_iterator::operator--(int ignore){
  if(!done())
    zend_hash_move_backwards(ht);
}
