/* C++ code produced by gperf version 3.0.4 */
/* Command-line: /usr/bin/gperf --compare-strncmp --switch=1 --struct-type libgearman/error_code.gperf  */
/* Computed positions: -k'1,$' */

#if !((' ' == 32) && ('!' == 33) && ('"' == 34) && ('#' == 35) \
      && ('%' == 37) && ('&' == 38) && ('\'' == 39) && ('(' == 40) \
      && (')' == 41) && ('*' == 42) && ('+' == 43) && (',' == 44) \
      && ('-' == 45) && ('.' == 46) && ('/' == 47) && ('0' == 48) \
      && ('1' == 49) && ('2' == 50) && ('3' == 51) && ('4' == 52) \
      && ('5' == 53) && ('6' == 54) && ('7' == 55) && ('8' == 56) \
      && ('9' == 57) && (':' == 58) && (';' == 59) && ('<' == 60) \
      && ('=' == 61) && ('>' == 62) && ('?' == 63) && ('A' == 65) \
      && ('B' == 66) && ('C' == 67) && ('D' == 68) && ('E' == 69) \
      && ('F' == 70) && ('G' == 71) && ('H' == 72) && ('I' == 73) \
      && ('J' == 74) && ('K' == 75) && ('L' == 76) && ('M' == 77) \
      && ('N' == 78) && ('O' == 79) && ('P' == 80) && ('Q' == 81) \
      && ('R' == 82) && ('S' == 83) && ('T' == 84) && ('U' == 85) \
      && ('V' == 86) && ('W' == 87) && ('X' == 88) && ('Y' == 89) \
      && ('Z' == 90) && ('[' == 91) && ('\\' == 92) && (']' == 93) \
      && ('^' == 94) && ('_' == 95) && ('a' == 97) && ('b' == 98) \
      && ('c' == 99) && ('d' == 100) && ('e' == 101) && ('f' == 102) \
      && ('g' == 103) && ('h' == 104) && ('i' == 105) && ('j' == 106) \
      && ('k' == 107) && ('l' == 108) && ('m' == 109) && ('n' == 110) \
      && ('o' == 111) && ('p' == 112) && ('q' == 113) && ('r' == 114) \
      && ('s' == 115) && ('t' == 116) && ('u' == 117) && ('v' == 118) \
      && ('w' == 119) && ('x' == 120) && ('y' == 121) && ('z' == 122) \
      && ('{' == 123) && ('|' == 124) && ('}' == 125) && ('~' == 126))
/* The character set is not based on ISO-646.  */
#error "gperf generated tables don't work with this execution character set. Please report a bug to <bug-gnu-gperf@gnu.org>."
#endif

#line 10 "libgearman/error_code.gperf"
 
/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2013 Data Differential, http://datadifferential.com/
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

#include "libgearman-1.0/return.h"

#line 54 "libgearman/error_code.gperf"
struct error_code_st
{ 
  const char* name;
  const gearman_return_t code;
};
#include <string.h>
/* maximum key range = 97, duplicates = 0 */

#ifndef GPERF_DOWNCASE
#define GPERF_DOWNCASE 1
static unsigned char gperf_downcase[256] =
  {
      0,   1,   2,   3,   4,   5,   6,   7,   8,   9,  10,  11,  12,  13,  14,
     15,  16,  17,  18,  19,  20,  21,  22,  23,  24,  25,  26,  27,  28,  29,
     30,  31,  32,  33,  34,  35,  36,  37,  38,  39,  40,  41,  42,  43,  44,
     45,  46,  47,  48,  49,  50,  51,  52,  53,  54,  55,  56,  57,  58,  59,
     60,  61,  62,  63,  64,  97,  98,  99, 100, 101, 102, 103, 104, 105, 106,
    107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121,
    122,  91,  92,  93,  94,  95,  96,  97,  98,  99, 100, 101, 102, 103, 104,
    105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119,
    120, 121, 122, 123, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134,
    135, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149,
    150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161, 162, 163, 164,
    165, 166, 167, 168, 169, 170, 171, 172, 173, 174, 175, 176, 177, 178, 179,
    180, 181, 182, 183, 184, 185, 186, 187, 188, 189, 190, 191, 192, 193, 194,
    195, 196, 197, 198, 199, 200, 201, 202, 203, 204, 205, 206, 207, 208, 209,
    210, 211, 212, 213, 214, 215, 216, 217, 218, 219, 220, 221, 222, 223, 224,
    225, 226, 227, 228, 229, 230, 231, 232, 233, 234, 235, 236, 237, 238, 239,
    240, 241, 242, 243, 244, 245, 246, 247, 248, 249, 250, 251, 252, 253, 254,
    255
  };
#endif

#ifndef GPERF_CASE_MEMCMP
#define GPERF_CASE_MEMCMP 1
static int
gperf_case_memcmp (register const char *s1, register const char *s2, register unsigned int n)
{
  for (; n > 0;)
    {
      unsigned char c1 = gperf_downcase[(unsigned char)*s1++];
      unsigned char c2 = gperf_downcase[(unsigned char)*s2++];
      if (c1 == c2)
        {
          n--;
          continue;
        }
      return (int)c1 - (int)c2;
    }
  return 0;
}
#endif

class String2gearman_return_t
{
private:
  static inline unsigned int hash (const char *str, unsigned int len);
public:
  static const struct error_code_st *in_word_set (const char *str, unsigned int len);
};

inline unsigned int
String2gearman_return_t::hash (register const char *str, register unsigned int len)
{
  static const unsigned char asso_values[] =
    {
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104,   5, 104,  40,  15,   0,
       40,  10, 104,  20,  25, 104,   0,  40,   5,  35,
       55,  20,  35,   0,  25,  30, 104,   0, 104, 104,
      104, 104, 104, 104, 104, 104, 104,   5, 104,  40,
       15,   0,  40,  10, 104,  20,  25, 104,   0,  40,
        5,  35,  55,  20,  35,   0,  25,  30, 104,   0,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104, 104, 104, 104, 104,
      104, 104, 104, 104, 104, 104
    };
  return len + asso_values[(unsigned char)str[len - 1]] + asso_values[(unsigned char)str[0]];
}

const struct error_code_st *
String2gearman_return_t::in_word_set (register const char *str, register unsigned int len)
{
  enum
    {
      TOTAL_KEYWORDS = 53,
      MIN_WORD_LENGTH = 5,
      MAX_WORD_LENGTH = 25,
      MIN_HASH_VALUE = 7,
      MAX_HASH_VALUE = 103
    };

  static const struct error_code_st wordlist[] =
    {
#line 60 "libgearman/error_code.gperf"
      {"SUCCESS", GEARMAN_SUCCESS},
#line 108 "libgearman/error_code.gperf"
      {"WORK_FAIL", GEARMAN_WORK_FAIL},
#line 109 "libgearman/error_code.gperf"
      {"WORK_STATUS", GEARMAN_WORK_STATUS},
#line 86 "libgearman/error_code.gperf"
      {"NO_JOBS", GEARMAN_NO_JOBS},
#line 98 "libgearman/error_code.gperf"
      {"SHUTDOWN", GEARMAN_SHUTDOWN},
#line 105 "libgearman/error_code.gperf"
      {"WORK_DATA", GEARMAN_WORK_DATA},
#line 89 "libgearman/error_code.gperf"
      {"NO_SERVERS", GEARMAN_NO_SERVERS},
#line 96 "libgearman/error_code.gperf"
      {"SEND_IN_PROGRESS", GEARMAN_SEND_IN_PROGRESS},
#line 99 "libgearman/error_code.gperf"
      {"SHUTDOWN_GRACEFUL", GEARMAN_SHUTDOWN_GRACEFUL},
#line 85 "libgearman/error_code.gperf"
      {"NO_ACTIVE_FDS", GEARMAN_NO_ACTIVE_FDS},
#line 107 "libgearman/error_code.gperf"
      {"WORK_EXCEPTION", GEARMAN_WORK_EXCEPTION},
#line 80 "libgearman/error_code.gperf"
      {"LOST_CONNECTION", GEARMAN_LOST_CONNECTION},
#line 95 "libgearman/error_code.gperf"
      {"SEND_BUFFER_TOO_SMALL", GEARMAN_SEND_BUFFER_TOO_SMALL},
#line 110 "libgearman/error_code.gperf"
      {"WORK_WARNING", GEARMAN_WORK_WARNING},
#line 61 "libgearman/error_code.gperf"
      {"ARGUMENT_TOO_LARGE", GEARMAN_ARGUMENT_TOO_LARGE},
#line 64 "libgearman/error_code.gperf"
      {"ECHO_DATA_CORRUPTION", GEARMAN_ECHO_DATA_CORRUPTION},
#line 82 "libgearman/error_code.gperf"
      {"NEED_WORKLOAD_FN", GEARMAN_NEED_WORKLOAD_FN},
#line 84 "libgearman/error_code.gperf"
      {"NOT_FLUSHING", GEARMAN_NOT_FLUSHING},
#line 88 "libgearman/error_code.gperf"
      {"NO_REGISTERED_FUNCTIONS", GEARMAN_NO_REGISTERED_FUNCTIONS},
#line 63 "libgearman/error_code.gperf"
      {"DATA_TOO_LARGE", GEARMAN_DATA_TOO_LARGE},
#line 66 "libgearman/error_code.gperf"
      {"EVENT", GEARMAN_EVENT},
#line 76 "libgearman/error_code.gperf"
      {"IN_PROGRESS", GEARMAN_IN_PROGRESS},
#line 87 "libgearman/error_code.gperf"
      {"NO_REGISTERED_FUNCTION", GEARMAN_NO_REGISTERED_FUNCTION},
#line 83 "libgearman/error_code.gperf"
      {"NOT_CONNECTED", GEARMAN_NOT_CONNECTED},
#line 78 "libgearman/error_code.gperf"
      {"JOB_EXISTS", GEARMAN_JOB_EXISTS},
#line 101 "libgearman/error_code.gperf"
      {"TOO_MANY_ARGS", GEARMAN_TOO_MANY_ARGS},
#line 79 "libgearman/error_code.gperf"
      {"JOB_QUEUE_FULL", GEARMAN_JOB_QUEUE_FULL},
#line 65 "libgearman/error_code.gperf"
      {"ERRNO", GEARMAN_ERRNO},
#line 72 "libgearman/error_code.gperf"
      {"INVALID_FUNCTION_NAME", GEARMAN_INVALID_FUNCTION_NAME},
#line 104 "libgearman/error_code.gperf"
      {"UNKNOWN_STATE", GEARMAN_UNKNOWN_STATE},
#line 106 "libgearman/error_code.gperf"
      {"WORK_ERROR", GEARMAN_WORK_ERROR},
#line 111 "libgearman/error_code.gperf"
      {"INVALID_SERVER_OPTION", GEARMAN_INVALID_SERVER_OPTION},
#line 97 "libgearman/error_code.gperf"
      {"SERVER_ERROR", GEARMAN_SERVER_ERROR},
#line 75 "libgearman/error_code.gperf"
      {"INVALID_WORKER_FUNCTION", GEARMAN_INVALID_WORKER_FUNCTION},
#line 103 "libgearman/error_code.gperf"
      {"UNKNOWN_OPTION", GEARMAN_UNKNOWN_OPTION},
#line 71 "libgearman/error_code.gperf"
      {"INVALID_COMMAND", GEARMAN_INVALID_COMMAND},
#line 94 "libgearman/error_code.gperf"
      {"RECV_IN_PROGRESS", GEARMAN_RECV_IN_PROGRESS},
#line 77 "libgearman/error_code.gperf"
      {"IO_WAIT", GEARMAN_IO_WAIT},
#line 112 "libgearman/error_code.gperf"
      {"JOB_NOT_FOUND", GEARMAN_JOB_NOT_FOUND},
#line 67 "libgearman/error_code.gperf"
      {"FLUSH_DATA", GEARMAN_FLUSH_DATA},
#line 68 "libgearman/error_code.gperf"
      {"GETADDRINFO", GEARMAN_GETADDRINFO},
#line 100 "libgearman/error_code.gperf"
      {"TIMEOUT", GEARMAN_TIMEOUT},
#line 69 "libgearman/error_code.gperf"
      {"IGNORE_PACKET", GEARMAN_IGNORE_PACKET},
#line 74 "libgearman/error_code.gperf"
      {"INVALID_PACKET", GEARMAN_INVALID_PACKET},
#line 90 "libgearman/error_code.gperf"
      {"PAUSE", GEARMAN_PAUSE},
#line 70 "libgearman/error_code.gperf"
      {"INVALID_ARGUMENT", GEARMAN_INVALID_ARGUMENT},
#line 81 "libgearman/error_code.gperf"
      {"MEMORY_ALLOCATION_FAILURE", GEARMAN_MEMORY_ALLOCATION_FAILURE},
#line 93 "libgearman/error_code.gperf"
      {"QUEUE_ERROR", GEARMAN_QUEUE_ERROR},
#line 102 "libgearman/error_code.gperf"
      {"UNEXPECTED_PACKET", GEARMAN_UNEXPECTED_PACKET},
#line 73 "libgearman/error_code.gperf"
      {"INVALID_MAGIC", GEARMAN_INVALID_MAGIC},
#line 92 "libgearman/error_code.gperf"
      {"PTHREAD", GEARMAN_PTHREAD},
#line 62 "libgearman/error_code.gperf"
      {"COULD_NOT_CONNECT", GEARMAN_COULD_NOT_CONNECT},
#line 91 "libgearman/error_code.gperf"
      {"PIPE_EOF", GEARMAN_PIPE_EOF}
    };

  if (len <= MAX_WORD_LENGTH && len >= MIN_WORD_LENGTH)
    {
      register int key = hash (str, len);

      if (key <= MAX_HASH_VALUE && key >= MIN_HASH_VALUE)
        {
          register const struct error_code_st *resword;

          switch (key - 7)
            {
              case 0:
                if (len == 7)
                  {
                    resword = &wordlist[0];
                    goto compare;
                  }
                break;
              case 2:
                if (len == 9)
                  {
                    resword = &wordlist[1];
                    goto compare;
                  }
                break;
              case 4:
                if (len == 11)
                  {
                    resword = &wordlist[2];
                    goto compare;
                  }
                break;
              case 5:
                if (len == 7)
                  {
                    resword = &wordlist[3];
                    goto compare;
                  }
                break;
              case 6:
                if (len == 8)
                  {
                    resword = &wordlist[4];
                    goto compare;
                  }
                break;
              case 7:
                if (len == 9)
                  {
                    resword = &wordlist[5];
                    goto compare;
                  }
                break;
              case 8:
                if (len == 10)
                  {
                    resword = &wordlist[6];
                    goto compare;
                  }
                break;
              case 9:
                if (len == 16)
                  {
                    resword = &wordlist[7];
                    goto compare;
                  }
                break;
              case 10:
                if (len == 17)
                  {
                    resword = &wordlist[8];
                    goto compare;
                  }
                break;
              case 11:
                if (len == 13)
                  {
                    resword = &wordlist[9];
                    goto compare;
                  }
                break;
              case 12:
                if (len == 14)
                  {
                    resword = &wordlist[10];
                    goto compare;
                  }
                break;
              case 13:
                if (len == 15)
                  {
                    resword = &wordlist[11];
                    goto compare;
                  }
                break;
              case 14:
                if (len == 21)
                  {
                    resword = &wordlist[12];
                    goto compare;
                  }
                break;
              case 15:
                if (len == 12)
                  {
                    resword = &wordlist[13];
                    goto compare;
                  }
                break;
              case 16:
                if (len == 18)
                  {
                    resword = &wordlist[14];
                    goto compare;
                  }
                break;
              case 18:
                if (len == 20)
                  {
                    resword = &wordlist[15];
                    goto compare;
                  }
                break;
              case 19:
                if (len == 16)
                  {
                    resword = &wordlist[16];
                    goto compare;
                  }
                break;
              case 20:
                if (len == 12)
                  {
                    resword = &wordlist[17];
                    goto compare;
                  }
                break;
              case 21:
                if (len == 23)
                  {
                    resword = &wordlist[18];
                    goto compare;
                  }
                break;
              case 22:
                if (len == 14)
                  {
                    resword = &wordlist[19];
                    goto compare;
                  }
                break;
              case 23:
                if (len == 5)
                  {
                    resword = &wordlist[20];
                    goto compare;
                  }
                break;
              case 24:
                if (len == 11)
                  {
                    resword = &wordlist[21];
                    goto compare;
                  }
                break;
              case 25:
                if (len == 22)
                  {
                    resword = &wordlist[22];
                    goto compare;
                  }
                break;
              case 26:
                if (len == 13)
                  {
                    resword = &wordlist[23];
                    goto compare;
                  }
                break;
              case 28:
                if (len == 10)
                  {
                    resword = &wordlist[24];
                    goto compare;
                  }
                break;
              case 31:
                if (len == 13)
                  {
                    resword = &wordlist[25];
                    goto compare;
                  }
                break;
              case 32:
                if (len == 14)
                  {
                    resword = &wordlist[26];
                    goto compare;
                  }
                break;
              case 33:
                if (len == 5)
                  {
                    resword = &wordlist[27];
                    goto compare;
                  }
                break;
              case 34:
                if (len == 21)
                  {
                    resword = &wordlist[28];
                    goto compare;
                  }
                break;
              case 36:
                if (len == 13)
                  {
                    resword = &wordlist[29];
                    goto compare;
                  }
                break;
              case 38:
                if (len == 10)
                  {
                    resword = &wordlist[30];
                    goto compare;
                  }
                break;
              case 39:
                if (len == 21)
                  {
                    resword = &wordlist[31];
                    goto compare;
                  }
                break;
              case 40:
                if (len == 12)
                  {
                    resword = &wordlist[32];
                    goto compare;
                  }
                break;
              case 41:
                if (len == 23)
                  {
                    resword = &wordlist[33];
                    goto compare;
                  }
                break;
              case 42:
                if (len == 14)
                  {
                    resword = &wordlist[34];
                    goto compare;
                  }
                break;
              case 43:
                if (len == 15)
                  {
                    resword = &wordlist[35];
                    goto compare;
                  }
                break;
              case 44:
                if (len == 16)
                  {
                    resword = &wordlist[36];
                    goto compare;
                  }
                break;
              case 45:
                if (len == 7)
                  {
                    resword = &wordlist[37];
                    goto compare;
                  }
                break;
              case 46:
                if (len == 13)
                  {
                    resword = &wordlist[38];
                    goto compare;
                  }
                break;
              case 48:
                if (len == 10)
                  {
                    resword = &wordlist[39];
                    goto compare;
                  }
                break;
              case 49:
                if (len == 11)
                  {
                    resword = &wordlist[40];
                    goto compare;
                  }
                break;
              case 50:
                if (len == 7)
                  {
                    resword = &wordlist[41];
                    goto compare;
                  }
                break;
              case 51:
                if (len == 13)
                  {
                    resword = &wordlist[42];
                    goto compare;
                  }
                break;
              case 52:
                if (len == 14)
                  {
                    resword = &wordlist[43];
                    goto compare;
                  }
                break;
              case 53:
                if (len == 5)
                  {
                    resword = &wordlist[44];
                    goto compare;
                  }
                break;
              case 54:
                if (len == 16)
                  {
                    resword = &wordlist[45];
                    goto compare;
                  }
                break;
              case 58:
                if (len == 25)
                  {
                    resword = &wordlist[46];
                    goto compare;
                  }
                break;
              case 59:
                if (len == 11)
                  {
                    resword = &wordlist[47];
                    goto compare;
                  }
                break;
              case 65:
                if (len == 17)
                  {
                    resword = &wordlist[48];
                    goto compare;
                  }
                break;
              case 66:
                if (len == 13)
                  {
                    resword = &wordlist[49];
                    goto compare;
                  }
                break;
              case 70:
                if (len == 7)
                  {
                    resword = &wordlist[50];
                    goto compare;
                  }
                break;
              case 75:
                if (len == 17)
                  {
                    resword = &wordlist[51];
                    goto compare;
                  }
                break;
              case 96:
                if (len == 8)
                  {
                    resword = &wordlist[52];
                    goto compare;
                  }
                break;
            }
          return 0;
        compare:
          {
            register const char *s = resword->name;

            if ((((unsigned char)*str ^ (unsigned char)*s) & ~32) == 0 && !gperf_case_memcmp (str, s, len))
              return resword;
          }
        }
    }
  return 0;
}
#line 113 "libgearman/error_code.gperf"

