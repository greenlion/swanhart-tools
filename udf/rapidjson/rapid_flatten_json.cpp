// phpembed uses const char* = "" which C++11 does not like - disable the warning
#include "rapid_flatten_json.h"
#include <iostream>
#include <string>
#include "rapidjson/reader.h"
using namespace rapidjson;

my_bool rapid_flatten_json_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
	if(args->arg_count != 1 || args->arg_type[0] != STRING_RESULT) {
		strcpy(message,"Takes one string arg: json_doc");
		return 1;
	}

	return 0;
}


char* rapid_flatten_json(UDF_INIT *initid, UDF_ARGS *args,char *result, long long *length, char *is_null, char *error) {
  if(args->args[0] == NULL) {
    *is_null = 1;
    return 0;
  }

  std::string json(args->args[0], args->lengths[0]);
  struct Handler {
    std::ostringstream oss;
    std::string key;
    bool Null()             { this->oss << this->key << "=" << "NULL" << "\n";  return true; }
    bool Bool(bool b)       { this->oss << this->key << "=" << std::boolalpha << b << "\n" ; return true; }
    bool Int(int i)         { this->oss << this->key << "=" << i << "\n"; return true; }
    bool Uint(unsigned u)   { this->oss << this->key << "=" << u << "\n"; return true; }
    bool Int64(int64_t i)   { this->oss << this->key << "=" << i << "\n"; return true; }
    bool Uint64(uint64_t u) { this->oss << this->key << "=" << u << "\n"; return true; }
    bool Double(double d)   { this->oss << this->key << "=" << d << "\n"; return true; }
    bool String(const char* str, SizeType length, bool copy) { 
        this->oss << this->key << "=" << str << "\n";
        std::string token = "";
        int cnt=0;
        for(int i=0;i<length;++i) {
          if(str[i] != ' ') {
            token += str[i];
          } else {
            ++cnt;
            if(token=="") continue;
            this->oss << this->key << "=" << token << "\n";
            token = "";
          }
        }
        if(token != "" && cnt>0) 
            this->oss << this->key << "=" << token << "\n";
        return true;
    }
    bool Key(const char* str, SizeType length, bool copy) { 
        this->key.assign(str, length);
        return true;
    }
    /* these do nothing */
    bool StartObject() { return true; }
    bool EndObject(SizeType memberCount) { return true; }
    bool StartArray() { return true; }
    bool EndArray(SizeType elementCount) { return true; }
  };

  Handler handler;
  Reader reader;
  StringStream ss(json.c_str());
  reader.Parse<0>(ss, handler);

  initid->ptr = (char*)malloc(handler.oss.str().length()+1);
  initid->ptr = strcpy(initid->ptr, handler.oss.str().c_str());
  *length = handler.oss.str().length();
  return initid->ptr;
}

void rapid_flatten_json_deinit(UDF_INIT *initid) {
  delete[] initid->ptr;
}


my_bool rapid_extract_all_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
	if(args->arg_count != 2 || (args->arg_type[0] != STRING_RESULT && args->arg_type[1] != STRING_RESULT)) {
		strcpy(message,"Takes two string args: json_doc, key_to_extract");
		return 1;
	}

	return 0;
}

char* rapid_extract_all(UDF_INIT *initid, UDF_ARGS *args,char *result, long long *length, char *is_null, char *error) {
  if(args->args[0] == NULL) {
    *is_null = 1;
    return 0;
  }

  std::string json(args->args[0], args->lengths[0]);
  struct Handler {
    std::ostringstream oss;
    std::string findkey;
    bool found = false;
    bool Null()             { if(this->found) this->oss << "NULL" << "\n";  return true; }
    bool Bool(bool b)       { if(this->found) this->oss << std::boolalpha << b << "\n" ; return true; }
    bool Int(int i)         { if(this->found) this->oss << i << "\n"; return true; }
    bool Uint(unsigned u)   { if(this->found) this->oss << u << "\n"; return true; }
    bool Int64(int64_t i)   { if(this->found) this->oss << i << "\n"; return true; }
    bool Uint64(uint64_t u) { if(this->found) this->oss << u << "\n"; return true; }
    bool Double(double d)   { if(this->found) this->oss << d << "\n"; return true; }
    bool String(const char* str, SizeType length, bool copy) { 
        if(this->found) this->oss << str << "\n";
        return true;
    }
    bool Key(const char* str, SizeType length, bool copy) { 
        this->found = false;
        if(std::string(str, length) == this->findkey) found = true;
        return true;
    }
    /* these do nothing */
    bool StartObject() { return true; }
    bool EndObject(SizeType memberCount) { return true; }
    bool StartArray() { return true; }
    bool EndArray(SizeType elementCount) { return true; }
  };

  Handler handler;
  handler.findkey.assign(args->args[1], args->lengths[1]);
  Reader reader;
  StringStream ss(json.c_str());
  reader.Parse<0>(ss, handler);

  initid->ptr = (char*)malloc(handler.oss.str().length()+1);
  initid->ptr = strcpy(initid->ptr, handler.oss.str().c_str());
  *length = handler.oss.str().length();
  return initid->ptr;
}



void rapid_extract_all_deinit(UDF_INIT *initid) {
  delete[] initid->ptr;
}
