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

#include <cerrno>
#include <csignal>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <iostream>

#include "libgearman/backtrace.hpp"

class Test {
public:
  Test()
  {
  }

  void call_backtrace()
  {
    std::cerr << __func__ << std::endl;
    custom_backtrace();
  }
};

void SIGSEGV_handler(int sig_num, siginfo_t* info, void* ucontext)
{
  std::cerr << __func__ << std::endl;
  (void)sig_num;
  (void)info;
  (void)ucontext;

  custom_backtrace();
}

int raise_SIGSEGV()
{
  std::cerr << std::endl << "Calling backtrace()" << std::endl;
  custom_backtrace();
  std::cerr << std::endl << "Calling raise()" << std::endl;
  return raise(SIGSEGV);
}

int layer4()
{
  return raise_SIGSEGV();
}

int layer3()
{
  return layer4();
}

int layer2()
{
  return layer3();
}

int layer1()
{
  return layer2();
}

int main(int, char **)
{
  Test t;

  t.call_backtrace();

  struct sigaction sigact;

  sigact.sa_sigaction= SIGSEGV_handler;
  sigact.sa_flags= SA_RESTART | SA_SIGINFO;

  if (sigaction(SIGSEGV, &sigact, (struct sigaction *)NULL) != 0)
  {
    std::cerr << "error setting signal handler for " << strsignal(SIGSEGV) << "(" <<  SIGSEGV << ")" << std::endl;

    exit(EXIT_FAILURE);
  }

  int ret= layer1();
  if (ret)
  {
    std::cerr << "raise() " << strerror(errno) << std::endl;
    exit(EXIT_FAILURE);
  }

  exit(EXIT_SUCCESS);
}
