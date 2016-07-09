#ifndef DAEMON_SERVER
#define DAEMON_SERVER 1
#include <string>
#include "common.h"
#include <cerrno>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <fcntl.h>
#include <fstream>
#include <pwd.h>
#include <signal.h>
#include <sys/resource.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <syslog.h>
#include <unistd.h>
#include <iostream>

#include "configmake.h"
#include "libgearman-server/gearmand.h"
#include "libgearman-server/plugins.h"
#include "libgearman-server/queue.hpp"
#include "libgearman-server/verbose.h"
#include "util/daemon.hpp"
#include "util/pidfile.hpp"
#include "libtest/cpu.hpp"

#ifdef TIME_WITH_SYS_TIME
# include <sys/time.h>
# include <time.h>
#else
# ifdef HAVE_SYS_TIME_H
#  include <sys/time.h>
# else
#  include <time.h>
# endif
#endif

/* BEGIN DAEMON SERVER */
#define GEARMAND_LOG_REOPEN_TIME 60

extern int gearman_shim(std::string port);
extern void gearmand_quit(void *p);
extern gearmand_st *_gearmand;
#endif
