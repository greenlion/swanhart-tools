/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
 *  All rights reserved.
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

#include <libgearman-server/plugins/queue/base.h>

#include <sqlite3.h>

#include <string>

namespace gearmand {
namespace queue {

class Instance : public gearmand::queue::Context 
{
public:
  Instance(const std::string& schema_, const std::string& table_);

  ~Instance();

  gearmand_error_t init();

  gearmand_error_t add(gearman_server_st *server,
                       const char *unique, size_t unique_size,
                       const char *function_name, size_t function_name_size,
                       const void *data, size_t data_size,
                       gearman_job_priority_t priority,
                       int64_t when);

  gearmand_error_t flush(gearman_server_st *server);

  gearmand_error_t done(gearman_server_st *server,
                        const char *unique, size_t unique_size,
                        const char *function_name, size_t function_name_size);

  gearmand_error_t replay(gearman_server_st *server);

  bool has_error()
  {
    return _error_string.size();
  }

private:
  gearmand_error_t replay_loop(gearman_server_st *server);

  void reset_error()
  {
    _error_string.clear();
  }

  bool _sqlite_count(const std::string& arg, int& count);
  bool _sqlite_dispatch(const std::string& arg);
  bool _sqlite_dispatch(const char* arg);
  bool _sqlite_count(const char* arg, int& count);
  bool _sqlite_prepare(const std::string& query_size, sqlite3_stmt ** sth);
  bool _sqlite_commit();
  bool _sqlite_rollback();
  bool _sqlite_lock();
  void _sqlite3_finalize(sqlite3_stmt*);

private:
  bool _epoch_support;
  bool _check_replay;
  int _in_trans;
  sqlite3 *_db;
  sqlite3_stmt* delete_sth;
  sqlite3_stmt* insert_sth;
  sqlite3_stmt* replay_sth;
  std::string _error_string;
  std::string _schema;
  std::string _table;
  std::string _insert_query;
  std::string _delete_query;
};

} // namespace queue
} // namespace gearmand
