#include "gear_config.h"
/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2013 Data Differential, http://datadifferential.com/
 *  Copyright (C) 2008 Brian Aker, Eric Day
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
#include "server.h"
#include "gearmand/error.hpp"
#include "gearmand/log.hpp"
#include "libgearman/backtrace.hpp"
using namespace datadifferential;
using namespace gearmand;

static bool _set_fdlimit(rlim_t fds);
static bool _switch_user(const char *user);

extern "C" {
static bool _set_signals(bool core_dump= false);
}

static void _log(const char *line, gearmand_verbose_t verbose, void *context);


gearmand_st *_gearmand;

void gearmand_quit(void *p) {
  gearmand_free(_gearmand);
}

/* This shim function is the orginal main() function from gearmand.
 * The unix command line is emulated by simply populating argc and 
 * argv structures as they would be populated by the Unix command
 * line, and allow gearmand to simply parse the arguments and take
 * the appropriate actions. 
 */
int gearman_shim(std::string port) {

  gearmand::error::init("WarpSQL_gearman");
  int backlog = 256;
  bool opt_daemon = false;
  rlim_t fds = 0;
  uint32_t job_retries=0;
  uint32_t worker_wakeup=4;
  uint32_t hashtable_buckets=1733003;
  bool opt_keepalive=false;
  int opt_keepalive_idle=-1;
  int opt_keepalive_interval=-1;
  int opt_keepalive_count=-1;
  std::string log_file="stderr";
  std::string host="127.0.0.1";
  std::string pid_file=GEARMAND_PID;
  std::string protocol="";
  std::string queue_type="builtin";
  std::string job_handle_prefix="WarpSQL:";
  std::string verbose_string="NOTICE";
  std::string config_file=GEARMAND_CONFIG;
  bool opt_syslog=false;
  uint32_t threads=0;
  bool opt_exceptions=false;
  bool opt_round_robin=true;
  bool opt_check_args=false;
  bool opt_coredump=false;

  gearmand::protocol::HTTP http;
  gearmand::protocol::Gear gear(port);
  gearmand::plugins::initialize();
/*
  if (_set_signals(opt_coredump))
  {
    return EXIT_FAILURE;
  }
*/

  gearmand::gearmand_log_info_st log_info(log_file, opt_syslog);

  if (log_info.initialized() == false)
  {
    return EXIT_FAILURE;
  }

  if (threads == 0)
  {
    uint32_t number_of_threads= libtest::number_of_cpus();

    if (number_of_threads > 4)
    {
      threads= number_of_threads;
    }
  }

  gearmand_config_st *gearmand_config= gearmand_config_create();

  if (gearmand_config == NULL)
  {
    return EXIT_FAILURE;
  }

  gearmand_config_sockopt_keepalive(gearmand_config, opt_keepalive);

  _gearmand= gearmand_create(gearmand_config,
                                          host.empty() ? NULL : host.c_str(),
                                          threads, backlog,
                                          static_cast<uint8_t>(job_retries),
                                          job_handle_prefix.empty() ? NULL : job_handle_prefix.c_str(),
                                          static_cast<uint8_t>(worker_wakeup),
                                          _log, &log_info,GEARMAND_VERBOSE_NOTICE ,
                                          opt_round_robin, opt_exceptions,
                                          hashtable_buckets);
  if (_gearmand == NULL)
  {
    error::message("Could not create gearmand library instance.");
    return EXIT_FAILURE;
  }

  gearmand_config_free(gearmand_config);

  assert(queue_type.size());
  if (queue_type.empty() == false)
  {
    gearmand_error_t rc;
    if ((rc= gearmand::queue::initialize(_gearmand, queue_type.c_str())) != GEARMAND_SUCCESS)
    {
      error::message("Error while initializing the queue", queue_type.c_str());
      gearmand_free(_gearmand);

      return EXIT_FAILURE;
    }
  }

  if (gear.start(_gearmand) != GEARMAND_SUCCESS)
  {
    error::message("Error while enabling Gear protocol module");
    gearmand_free(_gearmand);

    return EXIT_FAILURE;
  }

  if (protocol.compare("http") == 0)
  {
    if (http.start(_gearmand) != GEARMAND_SUCCESS)
    {
      error::message("Error while enabling protocol module", protocol.c_str());
      gearmand_free(_gearmand);

      return EXIT_FAILURE;
    }
  }
  else if (protocol.empty() == false)
  {
    error::message("Unknown protocol module", protocol.c_str());
    gearmand_free(_gearmand);

    return EXIT_FAILURE;
  }

  if (opt_daemon)
  {
    if (util::daemon_is_ready(true) == false)
    {
      return EXIT_FAILURE;
    }
  }

//  pthread_cleanup_push(gearmand_quit,NULL);
  gearmand_error_t ret= gearmand_run(_gearmand);
//  pthread_cleanup_pop(cleanup_pop_arg); 
  _gearmand= NULL;

  return 0;
}

static bool _set_fdlimit(rlim_t fds)
{
  struct rlimit rl;

  if (getrlimit(RLIMIT_NOFILE, &rl) == -1)
  {
    error::perror("Could not get file descriptor limit");
    return true;
  }

  rl.rlim_cur= fds;
  if (rl.rlim_max < rl.rlim_cur)
  {
    rl.rlim_max= rl.rlim_cur;
  }

  if (setrlimit(RLIMIT_NOFILE, &rl) == -1)
  {
    error::perror("Failed to set limit for the number of file "
		  "descriptors.  Try running as root or giving a "
		  "smaller value to -f.");
    return true;
  }

  return false;
}

static bool _switch_user(const char *user)
{

  if (getuid() == 0 or geteuid() == 0)
  {
    struct passwd *pw= getpwnam(user);

    if (not pw)
    {
      error::message("Could not find user", user);
      return EXIT_FAILURE;
    }

    if (setgid(pw->pw_gid) == -1 || setuid(pw->pw_uid) == -1)
    {
      error::message("Could not switch to user", user);
      return EXIT_FAILURE;
    }
  }
  else
  {
    error::message("Must be root to switch users.");
    return true;
  }

  return false;
}

extern "C" void _shutdown_handler(int signal_, siginfo_t*, void*)
{
  if (signal_== SIGUSR1)
  {
    gearmand_wakeup(Gearmand(), GEARMAND_WAKEUP_SHUTDOWN_GRACEFUL);
  }
  else
  {
    gearmand_wakeup(Gearmand(), GEARMAND_WAKEUP_SHUTDOWN);
  }
}

extern "C" void _reset_log_handler(int, siginfo_t*, void*) // signal_arg
{
  gearmand_log_info_st *log_info= static_cast<gearmand_log_info_st *>(Gearmand()->log_context);
  
  log_info->write(GEARMAND_VERBOSE_NOTICE, "SIGHUP, reopening log file");

  log_info->reset();
}

extern "C" void gearman_quit(void *p) {
  gearmand_wakeup(_gearmand, GEARMAND_WAKEUP_SHUTDOWN);
}

static bool segfaulted= false;
extern "C" void _crash_handler(int signal_, siginfo_t*, void*)
{
  if (segfaulted)
  {
    error::message("\nFatal crash while backtrace from signal:", strsignal(signal_));
    _exit(EXIT_FAILURE); 
  }

  segfaulted= true;
  custom_backtrace();
  _exit(EXIT_FAILURE); 
}
/*
extern "C" {
static bool _set_signals(bool core_dump)
{
  struct sigaction sa;

  memset(&sa, 0, sizeof(struct sigaction));

  sa.sa_handler= SIG_IGN;
  if (sigemptyset(&sa.sa_mask) == -1 or
      sigaction(SIGPIPE, &sa, 0) == -1)
  {
    error::perror("Could not set SIGPIPE handler.");
    return true;
  }

  sa.sa_sigaction= _shutdown_handler;
  sa.sa_flags= SA_SIGINFO;
  if (sigaction(SIGTERM, &sa, 0) == -1)
  {
    error::perror("Could not set SIGTERM handler.");
    return true;
  }

  if (sigaction(SIGINT, &sa, 0) == -1)
  {
    error::perror("Could not set SIGINT handler.");
    return true;
  }

  if (sigaction(SIGUSR1, &sa, 0) == -1)
  {
    error::perror("Could not set SIGUSR1 handler.");
    return true;
  }

  sa.sa_sigaction= _reset_log_handler;
  if (sigaction(SIGHUP, &sa, 0) == -1)
  {
    error::perror("Could not set SIGHUP handler.");
    return true;
  }

  bool in_gdb_libtest= bool(getenv("LIBTEST_IN_GDB"));

  if ((in_gdb_libtest == false) and (core_dump == false))
  {
    sa.sa_sigaction= _crash_handler;
    if (sigaction(SIGSEGV, &sa, NULL) == -1)
    {
      error::perror("Could not set SIGSEGV handler.");
      return true;
    }

    if (sigaction(SIGABRT, &sa, NULL) == -1)
    {
      error::perror("Could not set SIGABRT handler.");
      return true;
    }

#ifdef SIGBUS
    if (sigaction(SIGBUS, &sa, NULL) == -1)
    {
      error::perror("Could not set SIGBUS handler.");
      return true;
    }
#endif
    if (sigaction(SIGILL, &sa, NULL) == -1)
    {
      error::perror("Could not set SIGILL handler.");
      return true;
    }

    if (sigaction(SIGFPE, &sa, NULL) == -1)
    {
      error::perror("Could not set SIGFPE handler.");
      return true;
    }
  }

  return false;
}
}
*/

static void _log(const char *mesg, gearmand_verbose_t verbose, void *context)
{
  gearmand_log_info_st *log_info= static_cast<gearmand_log_info_st *>(context);

  log_info->write(verbose, mesg);
}
