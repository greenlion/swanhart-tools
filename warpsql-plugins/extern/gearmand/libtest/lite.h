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

#ifdef __cplusplus
# include <cstdarg>
# include <cstddef>
# include <cstdio>
# include <cstdlib>
# include <cstring>
#else
# include <stdarg.h>
# include <stdbool.h>
# include <stddef.h>
# include <stdio.h>
# include <stdlib.h>
# include <string.h>
#endif

#if defined(WIN32)
# include <malloc.h>
#else
# include <alloca.h>
#endif

#ifndef __PRETTY_FUNCTION__
# define __PRETTY_FUNCTION__ __func__
#endif

#ifndef EXIT_SKIP
# define EXIT_SKIP 77
#endif

#ifndef YATL_FULL
# define YATL_FULL 0
#endif

#ifndef FAIL
# define FAIL(__message_format, ...)
#endif

#ifndef SKIP
# define SKIP(__message_format, ...)
#endif

#include <libtest/valgrind.h>
#ifndef DUMPCORE
# if defined(YATL_FULL) && YATL_FULL
#  include <libtest/core.h>
#  define DUMPCORE libtest_create_core()
# else
#  define DUMPCORE
# endif
#endif

static inline size_t yatl_strlen(const char *s)
{
  if (s)
  {
    return strlen(s);
  }

  return (size_t)(0);
}

static inline int yatl_strcmp(const char *s1, const char *s2, size_t *s1_length, size_t *s2_length)
{
  *s1_length= yatl_strlen(s1);
  *s2_length= yatl_strlen(s2);

  if (*s1_length == 0 &&  *s1_length == *s2_length)
  {
    return 0;
  }

  if (*s1_length == 0 && *s2_length)
  {
    return 1;
  }

  if (*s1_length &&  *s2_length == 0)
  {
    return 1;
  }

  return strcmp(s1, s2);
}

#define SKIP_IF(__expression) \
do \
{ \
  if ((__expression)) { \
    if (YATL_FULL) { \
      SKIP(#__expression); \
    } \
    fprintf(stdout, "\n%s:%d: %s SKIP '!(%s)'\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression); \
    exit(EXIT_SKIP); \
  } \
} while (0)

#define SKIP_IF_(__expression, ...) \
do \
{ \
  if ((__expression)) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      SKIP(#__expression, buffer); \
    } \
    fprintf(stdout, "\n%s:%d: %s SKIP '%s' [ %s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression, buffer); \
    exit(EXIT_SKIP); \
  } \
} while (0)

#define SKIP_UNLESS(__expression) \
do \
{ \
  if (! (__expression)) { \
    if (YATL_FULL) { \
      SKIP(#__expression); \
    } \
    fprintf(stdout, "\n%s:%d: %s SKIP '(%s)'\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression); \
    exit(EXIT_SKIP); \
  } \
} while (0)

#define SKIP_UNLESS_(__expression, ...) \
do \
{ \
  if (! (__expression)) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      SKIP(#__expression, buffer); \
    } \
    fprintf(stdout, "\n%s:%d: %s SKIP '%s' [ %s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression, buffer); \
    exit(EXIT_SKIP); \
  } \
} while (0)

#define ASSERT_TRUE(__expression) \
do \
{ \
  if (! (__expression)) { \
    DUMPCORE; \
    if (YATL_FULL) { \
      FAIL("Assertion '%s'", #__expression); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s'\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression);\
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_FALSE(__expression) \
do \
{ \
  if ((__expression)) { \
    if (YATL_FULL) { \
     FAIL("Assertion '!%s'", #__expression); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '!%s'\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression);\
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_NULL(__expression) \
do \
{ \
  if ((__expression) != NULL) { \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' != NULL", #__expression);\
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' != NULL\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression);\
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_NULL_(__expression, ...) \
do \
{ \
  if ((__expression) != NULL) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' != NULL [ %s ]", #__expression, buffer);\
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' != NULL [ %s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression, buffer);\
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_NOT_NULL(__expression) \
do \
{ \
  if ((__expression) == NULL) { \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' == NULL", #__expression);\
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' == NULL\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression);\
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_NOT_NULL_(__expression, ...) \
do \
{ \
  if ((__expression) == NULL) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' == NULL [ %s ]", #__expression, buffer);\
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' == NULL [ %s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression, buffer);\
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_TRUE_(__expression, ...) \
do \
{ \
  if (! (__expression)) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' [ %s ]", #__expression, buffer); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' [ %s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression, buffer); \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_EQ(__expected, __actual) \
do \
{ \
  if ((__expected) != (__actual)) { \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' != '%s'", #__expected, #__actual); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' != '%s'\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expected, #__actual); \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_EQ_(__expected, __actual, ...) \
do \
{ \
  if ((__expected) != (__actual)) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' != '%s' [ %s ]", #__expected, #__actual, buffer); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' != '%s' [ %s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expected, #__actual, buffer); \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_STREQ(__expected_str, __actual_str) \
do \
{ \
  size_t __expected_length; \
  size_t __actual_length; \
  int __ret= yatl_strcmp(__expected_str, __actual_str, &__expected_length, &__actual_length); \
  if (__ret) { \
    if (YATL_FULL) { \
      FAIL("Assertion '%.*s' != '%.*s'\n", \
           (int)(__expected_length), (__expected_str), \
           (int)__actual_length, (__actual_str)) ; \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%.*s' != '%.*s'\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, \
            (int)(__expected_length), (__expected_str), \
            (int)__actual_length, (__actual_str)) ; \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_STREQ_(__expected_str, __actual_str, ...) \
do \
{ \
  size_t __expected_length; \
  size_t __actual_length; \
  int __ret= yatl_strcmp(__expected_str, __actual_str, &__expected_length, &__actual_length); \
  if (__ret) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    ask= snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      FAIL("Assertion '%.*s' != '%.*s' [ %.*s ]", \
           (int)(__expected_length), (__expected_str), \
           (int)(__actual_length), (__actual_str), \
           (int)(ask), buffer); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%.*s' != '%.*s' [ %.*s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, \
            (int)(__expected_length), (__expected_str), \
            (int)(__actual_length), (__actual_str), \
            (int)(ask), buffer); \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_STRNE(__expected_str, __actual_str) \
do \
{ \
  size_t __expected_length; \
  size_t __actual_length; \
  int __ret= yatl_strcmp(__expected_str, __actual_str, &__expected_length, &__actual_length); \
  if (__ret == 0) { \
    if (YATL_FULL) { \
      FAIL("Assertion '%.*s' == '%.*s'", \
           (int)(__expected_length), (__expected_str), \
           (int)__actual_length, (__actual_str)) ; \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%.*s' == '%.*s'\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, \
            (int)(__expected_length), (__expected_str), \
            (int)__actual_length, (__actual_str)) ; \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_STRNE_(__expected_str, __actual_str, ...) \
do \
{ \
  size_t __expected_length; \
  size_t __actual_length; \
  int __ret= yatl_strcmp(__expected_str, __actual_str, &__expected_length, &__actual_length); \
  if (__ret == 0) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    ask= snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      FAIL("Assertion '%.*s' == '%.*s' [ %.*s ]", \
           (int)(__expected_length), (__expected_str), \
           (int)(__actual_length), (__actual_str), \
           (int)(ask), buffer); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%.*s' == '%.*s' [ %.*s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, \
            (int)(__expected_length), (__expected_str), \
            (int)(__actual_length), (__actual_str), \
            (int)(ask), buffer); \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_NEQ(__expected, __actual) \
do \
{ \
  if ((__expected) == (__actual)) { \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' == '%s'", #__expected, #__actual); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' == '%s'\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expected, #__actual); \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_NEQ_(__expected, __actual, ...) \
do \
{ \
  if ((__expected) == (__actual)) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      FAIL("Assertion '%s' == '%s' [ %s ]", #__expected, #__actual, buffer); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '%s' == '%s' [ %s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expected, #__actual, buffer); \
    exit(EXIT_FAILURE); \
  } \
} while (0)

#define ASSERT_FALSE_(__expression, ...) \
do \
{ \
  if ((__expression)) { \
    size_t ask= snprintf(0, 0, __VA_ARGS__); \
    ask++; \
    char *buffer= (char*)alloca(sizeof(char) * ask); \
    snprintf(buffer, ask, __VA_ARGS__); \
    if (YATL_FULL) { \
      FAIL("Assertion '!%s' [ %s ]", #__expression, buffer); \
    } \
    fprintf(stderr, "\n%s:%d: %s Assertion '!%s' [ %s ]\n", __FILE__, __LINE__, __PRETTY_FUNCTION__, #__expression, buffer); \
    exit(EXIT_FAILURE); \
  } \
} while (0)
