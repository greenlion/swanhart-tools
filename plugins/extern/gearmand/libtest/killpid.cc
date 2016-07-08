/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 *
 *  Data Differential YATL (i.e. libtest)  library
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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

#include "libtest/yatlcon.h"
#include <libtest/common.h>

#include <cstdlib>
#include <cstring>
#include <iostream>
#include <sstream>
#include <signal.h>
#include <sys/types.h>
#include <sys/types.h>
#include <sys/wait.h>


#include <libtest/killpid.h>
#include <libtest/stream.h>

using namespace libtest;

bool kill_pid(pid_t pid_arg)
{
  assert(pid_arg > 0);
  if (pid_arg < 1)
  {
    Error << "Invalid pid:" << pid_arg;
    return false;
  }

  if ((::kill(pid_arg, SIGTERM) == -1))
  {
    switch (errno)
    {
    case EPERM:
      Error << "Does someone else have a process running locally for " << int(pid_arg) << "?";
      return false;

    case ESRCH:
      Error << "Process " << int(pid_arg) << " not found.";
      return false;

    default:
    case EINVAL:
      Error << "kill() " << strerror(errno);
      return false;
    }
  }

  {
    uint32_t this_wait= 0;
    uint32_t timeout= 20; // This number should be high enough for valgrind startup (which is slow)
    uint32_t waited;
    uint32_t retry;

    for (waited= 0, retry= 1; ; retry++, waited+= this_wait)
    {
      int status= 0;
      if (waitpid(pid_arg, &status, WNOHANG) == 0)
      {
        break;
      }
      else if (errno == ECHILD)
      {
        // Server has already gone away
        break;
      }
      else if (waited >= timeout)
      {
        // Timeout failed
        kill(pid_arg, SIGKILL);
        break;
      }

      this_wait= retry * retry / 3 + 1;
      libtest::dream(this_wait, 0);
    }
  }

  return true;
}

bool check_pid(const std::string &filename)
{
  if (filename.empty())
  {
    return false;
  }

  FILE *fp;
  if ((fp= fopen(filename.c_str(), "r")))
  {
    libtest::vchar_t pid_buffer;
    pid_buffer.resize(1024);

    char *ptr= fgets(&pid_buffer[0], int(pid_buffer.size()), fp);
    fclose(fp);

    if (ptr)
    {
      pid_t pid= (pid_t)atoi(&pid_buffer[0]);
      if (pid > 0)
      {
        return (::kill(pid, 0) == 0);
      }
    }
  }
  
  return false;
}


bool kill_file(const std::string &filename)
{
  if (filename.empty())
  {
    return true;
  }

  FILE *fp;
  if ((fp= fopen(filename.c_str(), "r")))
  {
    libtest::vchar_t pid_buffer;
    pid_buffer.resize(1024);

    char *ptr= fgets(&pid_buffer[0], int(pid_buffer.size()), fp);
    fclose(fp);

    if (ptr)
    {
      pid_t pid= (pid_t)atoi(&pid_buffer[0]);
      if (pid != 0)
      {
        bool ret= kill_pid(pid);
        unlink(filename.c_str()); // If this happens we may be dealing with a dead server that left its pid file.

        return ret;
      }
    }
  }
  
  return false;
}

#define STRINGIFY(x) #x
#define TOSTRING(x) STRINGIFY(x)
#define LIBTEST_AT __FILE__ ":" TOSTRING(__LINE__)

pid_t get_pid_from_file(const std::string &filename, std::stringstream& error_message)
{
  pid_t ret= -1;

  if (filename.empty())
  {
    error_message << LIBTEST_AT << " empty pid file";
    return ret;
  }

  FILE *fp;
  if ((fp= fopen(filename.c_str(), "r")))
  {
    libtest::vchar_t pid_buffer;
    pid_buffer.resize(1024);

    char *ptr= fgets(&pid_buffer[0], int(pid_buffer.size()), fp);
    if (ptr)
    {
      ret= (pid_t)atoi(&pid_buffer[0]);
      if (ret < 1)
      {
        error_message << LIBTEST_AT << " Invalid pid was read from file " << filename;
      }
    }
    else
    {
      error_message << LIBTEST_AT << " File " << filename << " was empty ";
    }

    fclose(fp);

    return ret;
  }
  else
  {
    libtest::vchar_t buffer;
    buffer.resize(1024);
    char *current_directory= getcwd(&buffer[0], buffer.size());
    error_message << "Error while opening " << current_directory << "/" << filename << " " << strerror(errno);
  }
  
  return ret;
}
