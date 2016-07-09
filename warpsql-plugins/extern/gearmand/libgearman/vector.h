/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Libgearman library
 *
 *  Copyright (C) 2013 Data Differential, http://datadifferential.com/
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

#include <cstddef>
#include <cstdlib>
#include <cstdarg>

#include "libgearman-1.0/string.h"

#ifdef __cplusplus
extern "C" {
#endif

struct gearman_vector_st *gearman_string_clone(const struct gearman_vector_st*);

bool gearman_string_reserve(struct gearman_vector_st *string, size_t need);

char *gearman_string_c_copy(struct gearman_vector_st *string);

bool gearman_string_append_character(struct gearman_vector_st *string,
                                     const char character);

bool gearman_string_append(struct gearman_vector_st *string,
                           const char *value, size_t length);

void gearman_string_clear(struct gearman_vector_st *string);


void gearman_string_free(struct gearman_vector_st*& string);

size_t gearman_string_length(const struct gearman_vector_st *self);


const char *gearman_string_value(const struct gearman_vector_st *self);


char *gearman_string_value_mutable(const struct gearman_vector_st *self);


gearman_string_t gearman_string(const struct gearman_vector_st *self);


gearman_string_t gearman_string_take_string(struct gearman_vector_st *self);

#ifdef __cplusplus
}
#endif

