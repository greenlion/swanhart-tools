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

#ifdef HAVE_CONFIG_H
# include "libtest/yatlcon.h"
#endif

#include "libtest/cpu.hpp"

#include <unistd.h>

#pragma GCC diagnostic ignored "-Wundef"

#if defined(HAVE_SYS_SYSCTL_H) && HAVE_SYS_SYSCTL_H
#include <sys/sysctl.h>
#endif

namespace libtest {

uint32_t number_of_cpus()
{
  uint32_t number_of_cpu= 1;
#ifdef linux 
  number_of_cpu= sysconf(_SC_NPROCESSORS_ONLN);
#elif defined(HAVE_SYS_SYSCTL_H) && defined(CTL_HW) && defined(HW_NCPU) && defined(HW_AVAILCPU) && defined(HW_NCPU)
  int mib[4];
  size_t len= sizeof(number_of_cpu); 

  /* set the mib for hw.ncpu */
  mib[0]= CTL_HW;
  mib[1]= HW_AVAILCPU;  // alternatively, try HW_NCPU;

  /* get the number of CPUs from the system */
  if (sysctl(mib, 2, &number_of_cpu, &len, NULL, 0) == -1)
  {
    mib[1]= HW_NCPU;
    sysctl(mib, 2, &number_of_cpu, &len, NULL, 0 );

    if (number_of_cpu < 1 )
    {
      number_of_cpu = 1;
    }
  }
#else
 // Guessing number of CPU
#endif

  return number_of_cpu;
}

} // namespace libtest
