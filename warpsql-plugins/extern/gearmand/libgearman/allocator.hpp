/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearman library
 *
 *  Copyright (C) 2011-2013 Data Differential, http://datadifferential.com/
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

void *gearman_real_malloc(gearman_allocator_t& allocator, size_t size, const char *func, const char *file, int line);

#define gearman_malloc(__gearman_universal_st, __size) gearman_real_malloc(((__gearman_universal_st).allocator), (__size), __func__, __FILE__, __LINE__)

void *gearman_real_calloc(gearman_allocator_t& allocator, size_t nelem, size_t size, const char *func, const char *file, int line);

#define gearman_calloc(__gearman_universal_st, __nelem,  __size) gearman_real_calloc(((__gearman_universal_st).allocator), (__nelem), (__size), __func__, __FILE__, __LINE__)

void *gearman_real_realloc(gearman_allocator_t&, void *ptr, size_t size, const char *func, const char *file, int line);

#define gearman_realloc(__gearman_universal_st, __ptr, __size) gearman_real_realloc(((__gearman_universal_st).allocator), (__ptr), (__size), __func__, __FILE__, __LINE__)

void gearman_real_free(gearman_allocator_t& allocator, void *&ptr, const char *func, const char *file, int line);

#define gearman_free(__gearman_universal_st, __ptr) gearman_real_free(((__gearman_universal_st).allocator), (__ptr), __func__, __FILE__, __LINE__)

#define gearman_has_allocator(__gearman_universal_st) bool(__gearman_universal_st.allocator.malloc)

gearman_return_t gearman_set_memory_allocator(gearman_allocator_t& allocator,
                                              gearman_malloc_fn *malloc_fn,
                                              gearman_free_fn *free_fn,
                                              gearman_realloc_fn *realloc_fn,
                                              gearman_calloc_fn *calloc_fn,
                                              void *context);

gearman_allocator_t gearman_default_allocator();

