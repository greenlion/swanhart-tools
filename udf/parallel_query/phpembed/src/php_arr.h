// PHPEmbed array headers
// Copyright (c) 2007 Andrew Bosworth, Facebook, inc
// Modified by Dmitry Zenovich <dzenovich@gmail.com>
// All rights reserved

#ifndef PHPARR_H
#define PHPARR_H

#include <php_embed.h>

// let's make this a little more attractive
typedef zend_uchar php_type;

// just forward declare these so we can talk about them
class php;
class php_iterator;

class php_array
{

  // make these friend classes so they can get at our protected members
  // this is only slightly better than making them public...=)
  friend class php;
  friend class php_iterator;

public:
  php_array();
  ~php_array();

  // a copy constructor for proper deep copies
  php_array(const php_array &pa);
  // an assignment operator uses existing memory (no copies)
  const php_array& operator=(const php_array &pa);

  // Adding data to a php_array can be done with any of the add functions
  //
  // You can mix and match indexed/associative values just like in php
  //
  // the add function is the same as inserting a value into a php_array
  // it will get the next available enumeration as it would in php
  //
  // Both add_assoc and add_index require data to be in pairs
  // add_assoc requires that the even arguments (0 indexed) be strings
  // add_index requires that the even arguments (0 indexed) be longs
  //
  // removing values based on associative string or index works as unset in php
  //
  // argspec works as follows:
  // s - string (really char *, must be null terminated!)
  // i - int (u32/i32)
  // l - long (u64)
  // d - double
  // b - boolean
  // a - array (a POINTER to a php_array object!)
  // S - binary-safe string (char * followed by an unsigned int length)
  //
  // EX
  // long l1 = 5, l2 = 6;
  // char *s1 = "foo", *s2 = "bar";
  // double pi = 3.14, e = 2.71;
  // bool raining = false;
  // char *also = "one more thing";
  // char *binary_safe_key = "ke\0y";
  // char *binary_safe_data = "da\0ta";
  //
  // php_array a();
  // a.add_assoc("sdsb", s1, pi, s2, raining);
  // a.add("s", also);
  // a.add_assoc("SS", binary_safe_key, 4 /*key length*/,
  //                   binary_safe_data, 5 /*data length*/);
  //
  // php_array b();
  // b.add_index("ldlb", l1, e, l2, raining);
  // b.add("s", also);
  //
  // php_array c();
  // c.add("asa", &a, also, &b);

  // add dictionary entries
  void add_assoc(char *argspec, ...);

  // remove data from a php_array by an associative string
  // provide non-zero value as the len parameter to remove data
  // by a binary-safe associative string index
  void remove(char *key, unsigned int len = 0);

  // add indexed entries
  void add_index(char *argspec, ...);
  void remove(long index);

  // this will just use the standard enumeration, like in PHP
  void add(char *argspec, ...);

protected:

  // These functions NOT intended for end users
  // used to pass arrays efficiently and mutate arrays with the iterator

  // THIS makes a deep copy of the array
  php_array(zval *val);

  // THIS uses existing memory (no copies)
  php_array(zval **val);

  // NOT intended for end users, required for nesting arrays
  zval *data() { return array; }

private:

  zval *array;

  // the real functions
  void add_assoc(char *argspec, va_list ap);
  void add_index(char *argspec, va_list ap);
  void add_enum(char *argspec, va_list ap);
};


// an iterator class for getting data back out of nested php arrays
class php_iterator
{
public:

  // by default we go to the start of the array on init
  php_iterator(php_array &a, bool _type_warnings = false);
  ~php_iterator() {}

  // this just tells us if we have valid data, regardless of which end
  // there is no way to tell if we're off the front or back through the API
  bool done();

  // reset the iterator to one end of the array or the other
  void go_to_start();
  void go_to_end();

  // get the total size of this array
  int size();

  // get the data type at the current position. 
  //
  // Use the provided get functions for these types:
  // IS_LONG
  // IS_DOUBLE
  // IS_STRING
  // IS_BOOL
  // IS_ARRAY
  //
  // There is currently no support for these types:
  // IS_NULL
  // IS_OBJECT
  // IS_RESOURCE
  // IS_CONSTANT
  php_type get_key_type();
  php_type get_data_type();

  // get the data or key at the current position (based on types above)
  // if the len is non-zero the string's length will be returned into it
  // NOTE: these functions will do conversions wherever necessary and possible
  //       EG string "123" would be returned as the long 123 in get_data_long()
  char *get_key_c_string(unsigned int* len = NULL);
  long get_key_long();

  char *get_data_c_string(unsigned int* len = NULL);
  double get_data_double();
  long get_data_long();
  bool get_data_bool();

  // NOTE: this returns a reference to the actual subarray
  // modifying the array returned will modify the larger array you are in
  // if you want a copy, make a new php_array with what this function returns
  php_array get_data_array();

  // standard postfix operators for moving the iterator
  void operator++(int ignore);
  void operator--(int ignore);

private:

  // true iff we should warn the user (via stderr) on type conversions
  bool type_warnings;

  // we must keep track of the current location in the array
  // NOTE: deleting array elements during traversal may invalidate this!
  HashTable *ht;

  // this doesn't make sense without an array to operate on
  php_iterator();
};

#endif
