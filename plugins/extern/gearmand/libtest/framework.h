/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 *
 *  Data Differential YATL (i.e. libtest)  library
 *
 *  Copyright (C) 2012-2013 Data Differential, http://datadifferential.com/
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are
 *  met:
 *
 *      * Redistributions of source code must retain the above copyright
 *  notice, this list of conditions and the following disclaimer.
 *
 *      * Redistributions in binary form must reproduce the above
 *  copyright notice, this list of conditions and the following disclaimer
 *  in the documentation and/or other materials provided with the
 *  distribution.
 *
 *      * The names of its contributors may not be used to endorse or
 *  promote products derived from this software without specific prior
 *  written permission.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 *  "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 *  LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 *  A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 *  OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 *  LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 *  DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 *  THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 *  (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 *  OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

#pragma once

#include <libtest/signal.h>

/**
  Framework is the structure which is passed to the test implementation to be filled.
  This must be implemented in order for the test framework to load the tests. We call
  get_world() in order to fill this structure.
*/

#include <vector>
#include <fstream>

namespace { class Collection; }
typedef std::vector<libtest::Collection*> Suites;

namespace libtest {

class Framework {
public:

public:
  test_return_t create();

  const std::string& name() const
  {
    return _name;
  }

  void create(test_callback_create_fn* arg)
  {
    _create= arg;
  }

  void destroy(test_callback_destroy_fn* arg)
  {
    _destroy= arg;
  }

  void collections(collection_st arg[]);

  void set_on_error(test_callback_error_fn *arg)
  {
    _on_error= arg;
  }

  test_return_t on_error(const enum test_return_t, void *);

  void set_socket()
  {
    _servers.set_socket();
  }

  void set_sasl(const std::string& username_arg, const std::string& password_arg)
  {
    _servers.set_sasl(username_arg, password_arg);
  }

  libtest::server_startup_st& servers()
  {
    return _servers;
  }

  void set_runner(libtest::Runner *arg)
  {
    _runner= arg;
  }

  libtest::Runner *runner();

  void exec();

  libtest::Collection& collection();

  virtual ~Framework();

  Framework(libtest::SignalThread&,
            const std::string&,
            const std::string&,
            const std::string&);

  bool match(const char* arg);

  void *creators_ptr()
  {
    return _creators_ptr;
  }

  libtest::SignalThread& signal()
  {
    return _signal;
  }

  size_t size() 
  {
    return _collection.size();
  }

  Suites& suites()
  {
    return _collection;
  }

  libtest::Formatters& formatter()
  {
    return _formatter;
  }

  size_t total() const
  {
    return _total;
  }

  size_t success() const
  {
    return _success;
  }

  size_t skipped() const
  {
    return _skipped;
  }

  size_t failed() const
  {
    return _failed;
  }

private:
  // Sums
  size_t _total;
  size_t _success;
  size_t _skipped;
  size_t _failed;

  /* These methods are called outside of any collection call. */
  test_callback_create_fn *_create;
  test_callback_destroy_fn *_destroy;

  /**
    If an error occurs during the test, this is called.
  */
  test_callback_error_fn *_on_error;

  /**
    Runner represents the callers for the tests. If not implemented we will use
    a set of default implementations.
  */
  libtest::Runner *_runner;

  libtest::server_startup_st _servers;
  bool _socket;
  void *_creators_ptr;
  unsigned long int _servers_to_run;
  Suites _collection;
  libtest::SignalThread& _signal;
  std::string _only_run;
  std::string _wildcard;
  std::string _name;

private:
  Framework( const Framework& );
  const Framework& operator=( const Framework& );
  libtest::Formatters _formatter;
  std::ofstream xml_file;
  std::ofstream tap_file;

};

} // namespace libtest
