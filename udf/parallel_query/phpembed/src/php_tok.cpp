// PHP TOKENIZER
// Copyright (c) 2007 Andrew Bosworth, Facebook, inc
// All rights reserved

#include "php_tok.h"

php_tok::php_tok(bool bOut, bool bWarn) : php_stl() {

  // check that the tokenizer is loaded, die if it is not
  if(!call_bool("extension_loaded", "s", "tokenizer")){
    printf("Failed because tokenizer is not loaded\n");
    exit(1);
  }

  //
  // PHP CODE
  //
  string phpcode;
  phpcode += "function munge_tokenize($file){";
  phpcode += "  return token_get_all(file_get_contents($file)); }";
  phpcode += "function var_val($var){";
  phpcode += "  return eval('return ' . $var . ';'); }";

  if(SUCCESS != eval_string("PHPTOK", phpcode.c_str())){
    printf("Failed defining local php functions");
    exit(1);
  }
}

php_ret php_tok::token_get_all(const char *file){

  // don't tokenize the same file twice
  if(alltokens.find(file) != alltokens.end())
    return SUCCESS;

  zval *ztokens;

  // make the call to the php tokenizer
  ztokens = call("munge_tokenize", "s", file);

  // sanity
  if(NULL == ztokens || Z_TYPE_P(ztokens) != IS_ARRAY)
    return FAIL;

  // initialize our hashtable walking routine
  zval **data;
  HashTable *ht = Z_ARRVAL_P(ztokens);
  zend_hash_internal_pointer_reset(ht);

  // walk through the array of ztokens
  while(zend_hash_get_current_data(ht, (void **)&data) == SUCCESS){

    token t;

    // sometimes the tokenizer returns syntax tokens
    if(Z_TYPE_PP(data) != IS_ARRAY){
      t.type = T_SYNTAX;
      if(Z_TYPE_PP(data) == IS_STRING){
        t.content = string(Z_STRVAL_PP(data));
      } else {
        convert_to_string_ex(data);
        t.content = string(Z_STRVAL_PP(data));
        printf("Bad Data returned from tokenizer, trying to continue...\n");
      }
    } else {

      // initialize the inner hash walking routine
      zval **datai;
      HashTable *hti = Z_ARRVAL_PP(data);
      zend_hash_internal_pointer_reset(hti);

      // walk through the token array, should do this twice
      int i = 0;
      while(i<2 && zend_hash_get_current_data(hti, (void **)&datai) == SUCCESS){

        if(i == 0){

          // parse the token type
          if(Z_TYPE_PP(datai) != IS_LONG){
            printf("Expected token type to be a long!\n");
            convert_to_long_ex(datai);
          }

          t.type = Z_LVAL_PP(datai);

        } else {

          // parse the token string
          if(Z_TYPE_PP(datai) != IS_STRING){
            printf("Expected token content to be a string!\n");
            convert_to_string_ex(datai);
          }

          t.content = string(Z_STRVAL_PP(datai));
        } 

        zend_hash_move_forward(hti);
        i++;
      }
    }

    // add this token to the vector for this file
    alltokens[file].push_back(t);

    zend_hash_move_forward(ht);
  }

  zval_ptr_dtor(&ztokens);
  return SUCCESS;
}

php_ret php_tok::token_get_all_recursive(const char *file){
  php_ret rv = SUCCESS;

  vector<string> includes;

  if(alltokens.find(file) == alltokens.end()){
    php_ret rv = token_get_all(file);
    if(SUCCESS != rv)
      return rv;
  }

  // gather includes will handle tokenizing this file if necessary
  if(!get_includes(file, includes))
    return FAIL;

  // now go through those and recur
  while(!includes.empty()){
    string inc = includes.back();
    includes.pop_back();

    // add the new includes to the end of the existing list if not already done
    if(alltokens.find(inc.c_str()) == alltokens.end()){
      php_ret rv = token_get_all(inc.c_str());
      if(SUCCESS != rv)
        return rv;

      if(!get_includes(inc.c_str(), includes))
        return FAIL;
    }
  }

  return rv;
}

bool php_tok::get_includes(const char *file, vector<string> &includes){

  // if it hasn't been tokenized, then fail
  if(alltokens.find(file) == alltokens.end())
    return false;

  // go through the file and look out for include type tokens
  reset_iterator(file);
  token t = get_next_token(file);
  while(t.type != T_NOMORE){
    
    // if we get a token calling for the inclusion of a file
    if(t.type == T_INCLUDE_ONCE || t.type == T_INCLUDE || 
       t.type == T_REQUIRE_ONCE || t.type == T_REQUIRE){

      string inc;

      // the next token which should be white space or punctuation
      t = get_next_token(file);
      if(t.type != T_SYNTAX && t.type != T_WHITESPACE){
        fprintf(stderr, "found an invalid include! continuing...\n");
        continue;
      }

      // skip ahead
      t = get_next_token(file);

      // the path is from here until we get to the semicolon
      while(t.type != T_NOMORE && (t.type != T_SYNTAX || t.content != ";")){

        // if we find variables, evaluate them with php
        if(t.type == T_VARIABLE){
          string var = t.content;

          // variables have to be followed by whitespace, so watch for it
          t = get_next_token(file);
          while(t.type != T_NOMORE && t.type != T_WHITESPACE){
            var += t.content;
            t = get_next_token(file);
          }

          // we're in a string context here...eval the variable and add it in
          inc += call_string("var_val", "s", var.c_str());
        }

        // if we find a constant, append it to our woring filename
        if(t.type == T_CONSTANT_ENCAPSED_STRING){
          // substring out the first and last char, which are quotes
          inc += t.content.substr(1, t.content.length() - 2);
        }

        t = get_next_token(file);
      }

      includes.push_back(inc);
    }

    t = get_next_token(file);
  }

  // just a courtesey
  reset_iterator(file);

  return true;
}

// The following are basically just wrappers for the token_cont class
bool php_tok::reset_iterator(const char *file){
  if(alltokens.find(file) == alltokens.end())
    return false;

  alltokens[file].reset();
  return true;
}

size_t php_tok::count(const char *file){
  if(alltokens.find(file) == alltokens.end())
    return 0;

  return alltokens[file].count();
}

token php_tok::get_next_token(const char *file){
  if(alltokens.find(file) == alltokens.end()){
    token t;
    t.type = T_NOMORE;
    return t;
  }

  return alltokens[file].get_next_token();
}

vector<const char *> php_tok::get_tokenized_files(){
  vector<const char *> rv;
  for(token_map::iterator mi = alltokens.begin(); mi != alltokens.end(); mi++){
    rv.push_back(mi->first);
  }
  return rv;
}

void php_tok::dump_tokens(FILE *f){
  for(token_map::iterator mi = alltokens.begin(); mi != alltokens.end(); mi++){
    fprintf(f, "%s\n", mi->first);
    (mi->second).dump_tokens(f);
  }
}
