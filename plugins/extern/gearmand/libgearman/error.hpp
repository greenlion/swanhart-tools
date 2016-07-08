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

#pragma once

#define STRINGIFY(x) #x
#define TOSTRING(x) STRINGIFY(x)
#define AT __FILE__ ":" TOSTRING(__LINE__) ":"
#define GEARMAN_AT __func__, AT

#define gearman_perror(__universal, __local_errno, __message) gearman_universal_set_perror((__universal), GEARMAN_ERRNO, __local_errno,  __func__, AT, (__message))
#define gearman_error(__universal, __error_t, __message) gearman_universal_set_error((__universal), (__error_t), __func__, AT, (__message))
#define gearman_gerror(__universal, __gearman_return_t) gearman_universal_set_gerror((__universal), (__gearman_return_t), __func__, AT)

gearman_return_t gearman_universal_set_error(gearman_universal_st&,
                                             gearman_return_t rc,
                                             const char *function,
                                             const char *position,
                                             const char *format, ...);

gearman_return_t gearman_universal_set_perror(gearman_universal_st&,
                                              const gearman_return_t rc,
                                              const int _system_errno,
                                              const char *function, const char *position, 
                                              const char *format, ...);

gearman_return_t gearman_universal_set_gerror(gearman_universal_st&,
                                              gearman_return_t rc,
                                              const char *func,
                                              const char *position);

void gearman_worker_reset_error(gearman_worker_st *worker);
void gearman_worker_reset_error(Worker&);
