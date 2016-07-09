// PHP_TOKENIZER
// Copyright (c) 2007 Andrew Bosworth, Facebook, inc
// All rights reserved

#ifndef PHPTOK_H
#define PHPTOK_H

#include "php_stl.h"

#include "php_tok_consts.h"

#include <ext/hash_map>
using __gnu_cxx::hash_map;

// the basic token structure
typedef struct{
  int type;
  string content;
} token;

/*
 *  TOKEN_CONT - a simple token container class, handles data and access
 */
class token_cont
{
public:
  token_cont() {};
  ~token_cont() {};

  // add a token to this object
  inline void push_back(token &t){ tokens.push_back(t); }

  // reset the token to the start
  inline void reset(){ iter = tokens.begin(); }

  // count the number of tokens we contain
  inline size_t count(){ return tokens.size(); }

  // return the next token pointed at by the iterator and advance it
  token get_next_token(){

    // we are out of tokens!
    if(iter == tokens.end()){
      token t;
      t.type = T_NOMORE;
      return t;
    }

    token t = *iter;
    iter++;
    return t;
  }

  // for debug
  void dump_tokens(FILE *f){
    for(vector<token>::iterator it = tokens.begin(); it != tokens.end(); it++){
      fprintf(f, "%4d: %s\n", it->type, (it->content).c_str());
    }
  }

private:
  vector<token> tokens;
  vector<token>::iterator iter;

  // we could add an iterator stack here to allow users to save state for
  // a subroutine, but until we have an application for it lets keep it simple
};

// for convenience
typedef hash_map<const char *, token_cont > token_map;

/*
 *  PHP_TOK - a multifile PHP tokenizer class
 */
class php_tok : public php_stl
{
public:
  php_tok(bool bOut = true, bool bWarn = true);
  ~php_tok(){};

  //
  // TOKENIZERS
  //

  // parse the given file for tokens, initialize relevant iterator
  // this is named after its analog in php
  php_ret token_get_all(const char *file);

  // tokenize the given file and all child includes
  php_ret token_get_all_recursive(const char *file);


  //
  // ACCESSORS
  // users can treat this class like a multimap iterator
  //

  // return a 2-D array of all the files currently tokenized
  // we use a vector of char* because char ** is too hard to use right
  vector<const char *> get_tokenized_files();

  // reset the iterator associated with a given file
  // returns false if that file isn't tokenized
  bool reset_iterator(const char *file);

  // get the number of tokens in the given file
  size_t count(const char *file);

  // gets the next token from the iterator for the specified file
  // returns T_NOMORE when done or if the specified file isn't tokenized
  token get_next_token(const char *file);

  // get all files included in a given file and populate the includes argument
  // return false if the file hasn't been tokenized yet
  //
  // NOTE: this will reset your iterator! Do this first if you need it!
  bool get_includes(const char *file, vector<string> &includes);


  //
  // FOR DEBUGGING
  //

  void dump_tokens(FILE *f = stdout);

private:

  // the map from files to token iterators (lists of tokens w/ iterators)
  token_map alltokens;
};

#endif
