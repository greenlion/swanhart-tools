/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Libgearman library
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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

/* shallow structure we use externally */

struct gearman_string_t {
  const char *c_str;
  const size_t size;
};

#define gearman_size(X) (X).size
#define gearman_c_str(X) (X).c_str
#define gearman_string_param_printf(X) int((X).size), (X).c_str
#define gearman_string_param(X) (X).c_str, (X).size
#define gearman_string_param_null NULL, 0
#define gearman_string_param_cstr(X) (X), ((X) ? strlen(X) : 0)

#ifndef gearman_literal_param
# ifdef __cplusplus
#  define gearman_literal_param(X) (X), (static_cast<size_t>((sizeof(X) - 1)))
#  define gearman_literal_param_size(X) static_cast<size_t>(sizeof(X) - 1)
# endif
#endif

#ifdef BUILDING_LIBGEARMAN

#ifdef __cplusplus // correct define
# define gearman_string_make(X) (X), size_t((sizeof(X) - 1))
#else
# define gearman_string_make(X) (X), (((size_t)((sizeof(X) - 1)))
#endif // correct define

#endif // BUILDING_LIBGEARMAN
