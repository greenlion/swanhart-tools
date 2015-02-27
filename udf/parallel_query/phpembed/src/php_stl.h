// PHPEmbed STL header
// Copyright (c) 2007 Andrew Bosworth, Facebook, inc
// All rights reserved

#ifndef PHPSTL_H
#define PHPSTL_H

#include "php_cxx.h"
#include <string>
#include <set>
#include <vector>
#include <map>
#include <ext/hash_set>
#include <ext/hash_map>

using std::string;
using std::set;
using std::vector;
using std::map;
using __gnu_cxx::hash_set;
using __gnu_cxx::hash_map;

// define the hash method for STL strings
namespace __gnu_cxx
{
  template<> struct hash< std::string >
  {
    size_t operator()( const std::string& x ) const
    {
      return hash< const char* >()( x.c_str() );
    }
  };
}

class php_stl : public php
{
public:
  php_stl(bool type_warnings = false) : php(type_warnings) {};
  ~php_stl(){};

  // STL strings are more convenient than C strings
  string call_string(char *fn, char *argspec =  "", ...);

  // NOTE ON INT AND UINT TYPES
  // these just truncate php longs into integers before insertion

  vector<string> call_string_vector(char *fn, char *argspec =  "", ...);
  vector<double> call_double_vector(char *fn, char *argspec =  "", ...);
  vector<long> call_long_vector(char *fn, char *argspec =  "", ...);
  vector<bool> call_bool_vector(char *fn, char *argspec =  "", ...);
  vector<int> call_int_vector(char *fn, char *argspec =  "", ...);
  vector<unsigned int> call_uint_vector(char *fn, char *argspec =  "", ...);

  // a set of bools doesn't make much sense
  set<string> call_string_set(char *fn, char *argspec =  "", ...);
  set<double> call_double_set(char *fn, char *argspec =  "", ...);
  set<long> call_long_set(char *fn, char *argspec =  "", ...);
  set<int> call_int_set(char *fn, char *argspec =  "", ...);
  set<unsigned int> call_uint_set(char *fn, char *argspec =  "", ...);

  // a hash set of bools also makes no sense
  hash_set<string> call_string_hash_set(char *fn, char *argspec =  "", ...);
  hash_set<long> call_long_hash_set(char *fn, char *argspec =  "", ...);
  hash_set<int> call_int_hash_set(char *fn, char *argspec =  "", ...);
  hash_set<unsigned int> call_uint_hash_set(char *fn, char *argspec =  "", ...);

  // NOTE ON NESTED ARRAYS: These maps only support flat associative arrays
  // for nested arrays you'll need to use the php_arr class!

  // We only support string and long indices since that is what is in PHP
  map<string, string> call_string_string_map(char *fn, char *argspec = "", ...);
  map<string, double> call_string_double_map(char *fn, char *argspec = "", ...);
  map<string, long> call_string_long_map(char *fn, char *argspec = "", ...);
  map<string, bool> call_string_bool_map(char *fn, char *argspec = "", ...);
  map<string, int> call_string_int_map(char *fn, char *argspec =  "", ...);
  map<string, unsigned int> call_string_uint_map(char *fn, char *argspec =  "", ...);
  map<long, string> call_long_string_map(char *fn, char *argspec = "", ...);
  map<long, double> call_long_double_map(char *fn, char *argspec = "", ...);
  map<long, long> call_long_long_map(char *fn, char *argspec = "", ...);
  map<long, bool> call_long_bool_map(char *fn, char *argspec = "", ...);
  map<long, int> call_long_int_map(char *fn, char *argspec =  "", ...);
  map<long, unsigned int> call_long_uint_map(char *fn, char *argspec =  "", ...);

  hash_map<string, string> call_string_string_hash_map(char *fn, char *argspec = "", ...);
  hash_map<string, double> call_string_double_hash_map(char *fn, char *argspec = "", ...);
  hash_map<string, long> call_string_long_hash_map(char *fn, char *argspec = "", ...);
  hash_map<string, bool> call_string_bool_hash_map(char *fn, char *argspec = "", ...);
  hash_map<string, int> call_string_int_hash_map(char *fn, char *argspec = "", ...);
  hash_map<string, unsigned int> call_string_uint_hash_map(char *fn, char *argspec = "", ...);
  hash_map<long, string> call_long_string_hash_map(char *fn, char *argspec = "", ...);
  hash_map<long, double> call_long_double_hash_map(char *fn, char *argspec = "", ...);
  hash_map<long, long> call_long_long_hash_map(char *fn, char *argspec = "", ...);
  hash_map<long, bool> call_long_bool_hash_map(char *fn, char *argspec = "", ...);
  hash_map<long, int> call_long_int_hash_map(char *fn, char *argspec = "", ...);
  hash_map<long, unsigned int> call_long_uint_hash_map(char *fn, char *argspec = "", ...);
};

#endif
