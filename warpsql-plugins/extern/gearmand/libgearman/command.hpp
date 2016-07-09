/* C++ code produced by gperf version 3.0.4 */
/* Command-line: /bin/gperf --compare-strncmp --switch=1 -D --struct-type libgearman/command.gperf  */
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

#line 11 "libgearman/command.gperf"
 
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

#include "gear_config.h"
#include <libgearman/common.h>

#include <libgearman-1.0/visibility.h>
#include <libgearman/command.h>

#include "libgearman/assert.hpp"
#include "libgearman/command.h"

#line 60 "libgearman/command.gperf"
struct gearman_command_string_st
{
  const char *name;
  const gearman_command_t code;
};
#include <string.h>

#define TOTAL_KEYWORDS 43
#define MIN_WORD_LENGTH 4
#define MAX_WORD_LENGTH 28
#define MIN_HASH_VALUE 4
#define MAX_HASH_VALUE 73
/* maximum key range = 70, duplicates = 0 */

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

class String2gearman_command_t
{
private:
  static inline unsigned int hash (const char *str, unsigned int len);
public:
  static const struct gearman_command_string_st *in_word_set (const char *str, unsigned int len);
};

inline unsigned int
String2gearman_command_t::hash (register const char *str, register unsigned int len)
{
  static const unsigned char asso_values[] =
    {
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 35,  5, 25, 10, 25,
      74,  0, 25, 74,  5, 74, 10, 74,  0, 15,
      30, 40, 15, 10,  0,  0, 74,  0, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 35,  5, 25,
      10, 25, 74,  0, 25, 74,  5, 74, 10, 74,
       0, 15, 30, 40, 15, 10,  0,  0, 74,  0,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74, 74, 74, 74, 74,
      74, 74, 74, 74, 74, 74
    };
  return len + asso_values[(unsigned char)str[len - 1]] + asso_values[(unsigned char)str[0]];
}

static const struct gearman_command_string_st gearman_command_string_st[] =
  {
#line 66 "libgearman/command.gperf"
    {"TEXT", GEARMAN_COMMAND_TEXT},
#line 76 "libgearman/command.gperf"
    {"NO_JOB", GEARMAN_COMMAND_NO_JOB},
#line 95 "libgearman/command.gperf"
    {"WORK_WARNING", GEARMAN_COMMAND_WORK_WARNING },
#line 75 "libgearman/command.gperf"
    {"GRAB_JOB", GEARMAN_COMMAND_GRAB_JOB},
#line 91 "libgearman/command.gperf"
    {"WORK_EXCEPTION", GEARMAN_COMMAND_WORK_EXCEPTION },
#line 77 "libgearman/command.gperf"
    {"JOB_ASSIGN", GEARMAN_COMMAND_JOB_ASSIGN },
#line 71 "libgearman/command.gperf"
    {"UNUSED", GEARMAN_COMMAND_UNUSED},
#line 80 "libgearman/command.gperf"
    {"WORK_FAIL",GEARMAN_COMMAND_WORK_FAIL},
#line 81 "libgearman/command.gperf"
    {"GET_STATUS",GEARMAN_COMMAND_GET_STATUS},
#line 78 "libgearman/command.gperf"
    {"WORK_STATUS", GEARMAN_COMMAND_WORK_STATUS},
#line 105 "libgearman/command.gperf"
    {"GRAB_JOB_ALL", GEARMAN_COMMAND_GRAB_JOB_ALL },
#line 84 "libgearman/command.gperf"
    {"SUBMIT_JOB_BG", GEARMAN_COMMAND_SUBMIT_JOB_BG },
#line 99 "libgearman/command.gperf"
    {"SUBMIT_JOB_LOW", GEARMAN_COMMAND_SUBMIT_JOB_LOW },
#line 73 "libgearman/command.gperf"
    {"SUBMIT_JOB", GEARMAN_COMMAND_SUBMIT_JOB },
#line 74 "libgearman/command.gperf"
    {"JOB_CREATED", GEARMAN_COMMAND_JOB_CREATED},
#line 100 "libgearman/command.gperf"
    {"SUBMIT_JOB_LOW_BG", GEARMAN_COMMAND_SUBMIT_JOB_LOW_BG },
#line 98 "libgearman/command.gperf"
    {"SUBMIT_JOB_HIGH_BG", GEARMAN_COMMAND_SUBMIT_JOB_HIGH_BG },
#line 106 "libgearman/command.gperf"
    {"JOB_ASSIGN_ALL", GEARMAN_COMMAND_JOB_ASSIGN_ALL },
#line 86 "libgearman/command.gperf"
    {"STATUS_RES", GEARMAN_COMMAND_STATUS_RES},
#line 103 "libgearman/command.gperf"
    {"SUBMIT_REDUCE_JOB", GEARMAN_COMMAND_SUBMIT_REDUCE_JOB},
#line 88 "libgearman/command.gperf"
    {"SET_CLIENT_ID", GEARMAN_COMMAND_SET_CLIENT_ID},
#line 72 "libgearman/command.gperf"
    {"NOOP", GEARMAN_COMMAND_NOOP},
#line 93 "libgearman/command.gperf"
    {"OPTION_RES", GEARMAN_COMMAND_OPTION_RES},
#line 101 "libgearman/command.gperf"
    {"SUBMIT_JOB_SCHED", GEARMAN_COMMAND_SUBMIT_JOB_SCHED },
#line 79 "libgearman/command.gperf"
    {"WORK_COMPLETE", GEARMAN_COMMAND_WORK_COMPLETE },
#line 89 "libgearman/command.gperf"
    {"CAN_DO_TIMEOUT", GEARMAN_COMMAND_CAN_DO_TIMEOUT},
#line 69 "libgearman/command.gperf"
    {"RESET_ABILITIES", GEARMAN_COMMAND_RESET_ABILITIES},
#line 107 "libgearman/command.gperf"
    {"GET_STATUS_UNIQUE", GEARMAN_COMMAND_GET_STATUS_UNIQUE},
#line 83 "libgearman/command.gperf"
    {"ECHO_RES", GEARMAN_COMMAND_ECHO_RES },
#line 94 "libgearman/command.gperf"
    {"WORK_DATA", GEARMAN_COMMAND_WORK_DATA },
#line 85 "libgearman/command.gperf"
    {"ERROR", GEARMAN_COMMAND_ERROR},
#line 67 "libgearman/command.gperf"
    {"CAN_DO", GEARMAN_COMMAND_CAN_DO},
#line 68 "libgearman/command.gperf"
    {"CANT_DO", GEARMAN_COMMAND_CANT_DO},
#line 104 "libgearman/command.gperf"
    {"SUBMIT_REDUCE_JOB_BACKGROUND", GEARMAN_COMMAND_SUBMIT_REDUCE_JOB_BACKGROUND},
#line 87 "libgearman/command.gperf"
    {"SUBMIT_JOB_HIGH", GEARMAN_COMMAND_SUBMIT_JOB_HIGH },
#line 102 "libgearman/command.gperf"
    {"SUBMIT_JOB_EPOCH", GEARMAN_COMMAND_SUBMIT_JOB_EPOCH },
#line 108 "libgearman/command.gperf"
    {"STATUS_RES_UNIQUE", GEARMAN_COMMAND_STATUS_RES_UNIQUE},
#line 96 "libgearman/command.gperf"
    {"GRAB_JOB_UNIQ", GEARMAN_COMMAND_GRAB_JOB_UNIQ},
#line 90 "libgearman/command.gperf"
    {"ALL_YOURS", GEARMAN_COMMAND_ALL_YOURS},
#line 97 "libgearman/command.gperf"
    {"JOB_ASSIGN_UNIQ", GEARMAN_COMMAND_JOB_ASSIGN_UNIQ },
#line 92 "libgearman/command.gperf"
    {"OPTION_REQ", GEARMAN_COMMAND_OPTION_REQ},
#line 70 "libgearman/command.gperf"
    {"PRE_SLEEP", GEARMAN_COMMAND_PRE_SLEEP},
#line 82 "libgearman/command.gperf"
    {"ECHO_REQ", GEARMAN_COMMAND_ECHO_REQ }
  };

const struct gearman_command_string_st *
String2gearman_command_t::in_word_set (register const char *str, register unsigned int len)
{
  if (len <= MAX_WORD_LENGTH && len >= MIN_WORD_LENGTH)
    {
      register int key = hash (str, len);

      if (key <= MAX_HASH_VALUE && key >= MIN_HASH_VALUE)
        {
          register const struct gearman_command_string_st *resword;

          switch (key - 4)
            {
              case 0:
                if (len == 4)
                  {
                    resword = &gearman_command_string_st[0];
                    goto compare;
                  }
                break;
              case 7:
                if (len == 6)
                  {
                    resword = &gearman_command_string_st[1];
                    goto compare;
                  }
                break;
              case 8:
                if (len == 12)
                  {
                    resword = &gearman_command_string_st[2];
                    goto compare;
                  }
                break;
              case 9:
                if (len == 8)
                  {
                    resword = &gearman_command_string_st[3];
                    goto compare;
                  }
                break;
              case 10:
                if (len == 14)
                  {
                    resword = &gearman_command_string_st[4];
                    goto compare;
                  }
                break;
              case 11:
                if (len == 10)
                  {
                    resword = &gearman_command_string_st[5];
                    goto compare;
                  }
                break;
              case 12:
                if (len == 6)
                  {
                    resword = &gearman_command_string_st[6];
                    goto compare;
                  }
                break;
              case 15:
                if (len == 9)
                  {
                    resword = &gearman_command_string_st[7];
                    goto compare;
                  }
                break;
              case 16:
                if (len == 10)
                  {
                    resword = &gearman_command_string_st[8];
                    goto compare;
                  }
                break;
              case 17:
                if (len == 11)
                  {
                    resword = &gearman_command_string_st[9];
                    goto compare;
                  }
                break;
              case 18:
                if (len == 12)
                  {
                    resword = &gearman_command_string_st[10];
                    goto compare;
                  }
                break;
              case 19:
                if (len == 13)
                  {
                    resword = &gearman_command_string_st[11];
                    goto compare;
                  }
                break;
              case 20:
                if (len == 14)
                  {
                    resword = &gearman_command_string_st[12];
                    goto compare;
                  }
                break;
              case 21:
                if (len == 10)
                  {
                    resword = &gearman_command_string_st[13];
                    goto compare;
                  }
                break;
              case 22:
                if (len == 11)
                  {
                    resword = &gearman_command_string_st[14];
                    goto compare;
                  }
                break;
              case 23:
                if (len == 17)
                  {
                    resword = &gearman_command_string_st[15];
                    goto compare;
                  }
                break;
              case 24:
                if (len == 18)
                  {
                    resword = &gearman_command_string_st[16];
                    goto compare;
                  }
                break;
              case 25:
                if (len == 14)
                  {
                    resword = &gearman_command_string_st[17];
                    goto compare;
                  }
                break;
              case 26:
                if (len == 10)
                  {
                    resword = &gearman_command_string_st[18];
                    goto compare;
                  }
                break;
              case 28:
                if (len == 17)
                  {
                    resword = &gearman_command_string_st[19];
                    goto compare;
                  }
                break;
              case 29:
                if (len == 13)
                  {
                    resword = &gearman_command_string_st[20];
                    goto compare;
                  }
                break;
              case 30:
                if (len == 4)
                  {
                    resword = &gearman_command_string_st[21];
                    goto compare;
                  }
                break;
              case 31:
                if (len == 10)
                  {
                    resword = &gearman_command_string_st[22];
                    goto compare;
                  }
                break;
              case 32:
                if (len == 16)
                  {
                    resword = &gearman_command_string_st[23];
                    goto compare;
                  }
                break;
              case 34:
                if (len == 13)
                  {
                    resword = &gearman_command_string_st[24];
                    goto compare;
                  }
                break;
              case 35:
                if (len == 14)
                  {
                    resword = &gearman_command_string_st[25];
                    goto compare;
                  }
                break;
              case 36:
                if (len == 15)
                  {
                    resword = &gearman_command_string_st[26];
                    goto compare;
                  }
                break;
              case 38:
                if (len == 17)
                  {
                    resword = &gearman_command_string_st[27];
                    goto compare;
                  }
                break;
              case 39:
                if (len == 8)
                  {
                    resword = &gearman_command_string_st[28];
                    goto compare;
                  }
                break;
              case 40:
                if (len == 9)
                  {
                    resword = &gearman_command_string_st[29];
                    goto compare;
                  }
                break;
              case 41:
                if (len == 5)
                  {
                    resword = &gearman_command_string_st[30];
                    goto compare;
                  }
                break;
              case 42:
                if (len == 6)
                  {
                    resword = &gearman_command_string_st[31];
                    goto compare;
                  }
                break;
              case 43:
                if (len == 7)
                  {
                    resword = &gearman_command_string_st[32];
                    goto compare;
                  }
                break;
              case 44:
                if (len == 28)
                  {
                    resword = &gearman_command_string_st[33];
                    goto compare;
                  }
                break;
              case 46:
                if (len == 15)
                  {
                    resword = &gearman_command_string_st[34];
                    goto compare;
                  }
                break;
              case 47:
                if (len == 16)
                  {
                    resword = &gearman_command_string_st[35];
                    goto compare;
                  }
                break;
              case 48:
                if (len == 17)
                  {
                    resword = &gearman_command_string_st[36];
                    goto compare;
                  }
                break;
              case 49:
                if (len == 13)
                  {
                    resword = &gearman_command_string_st[37];
                    goto compare;
                  }
                break;
              case 50:
                if (len == 9)
                  {
                    resword = &gearman_command_string_st[38];
                    goto compare;
                  }
                break;
              case 56:
                if (len == 15)
                  {
                    resword = &gearman_command_string_st[39];
                    goto compare;
                  }
                break;
              case 61:
                if (len == 10)
                  {
                    resword = &gearman_command_string_st[40];
                    goto compare;
                  }
                break;
              case 65:
                if (len == 9)
                  {
                    resword = &gearman_command_string_st[41];
                    goto compare;
                  }
                break;
              case 69:
                if (len == 8)
                  {
                    resword = &gearman_command_string_st[42];
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
#line 109 "libgearman/command.gperf"

