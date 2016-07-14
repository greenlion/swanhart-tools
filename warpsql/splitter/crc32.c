#include "crc32.h"
#include <string.h>
#include <stdio.h>

unsigned long crc32(const char *p) {
  long nr = strlen(p);
  unsigned long crcinit = 0;
  register unsigned long crc;

  crc = crcinit^0xFFFFFFFF;

  for (; nr--; ++p) {
    crc = ((crc >> 8) & 0x00FFFFFF) ^ crc32tab[(crc ^ (*p)) & 0xFF ];
  }
  return crc^0xFFFFFFFF;
}

unsigned long crc32mod(const char *p, int m) {
  return crc32(p) % m;
}

int main(int argc, char** argv) {
  unsigned long c = crc32mod("abcdef",10);
  printf("%lu\n", c);
  c = crc32mod("12345",10);
  printf("%lu\n", c);
}
