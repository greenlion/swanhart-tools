dnl ---------------------------------------------------------------------------
dnl Macro: MYSQL_CONFIG
dnl ---------------------------------------------------------------------------
AC_DEFUN([MYSQL_CONFIG_TEST], [
  AC_ARG_WITH(mysql,
  [[  --with-mysql[=mysql_config]      
                          Support for the MySQL.]],
  [
    if test -n "$withval"; then
      if test "$withval" = "yes"; then
         AC_PATH_PROG([MYSQL_CONFIG], [mysql_config], "no", [$PATH:/usr/sbin:/usr/bin:/usr/local/bin:/usr/local/mysql/bin])
         if test "x$MYSQL_CONFIG" == "xno"; then
           AC_MSG_ERROR(["could not find mysql_config"])
         fi
      else
        AC_MSG_CHECKING(for mysql_config)
        MYSQL_CONFIG="$withval"
        if test ! -f "$MYSQL_CONFIG"; then
          AC_MSG_ERROR(["could not find mysql_config 2 : $MYSQL_CONFIG"])
        fi
        AC_MSG_RESULT([$MYSQL_CONFIG])
      fi
    else
      AC_PATH_PROG([MYSQL_CONFIG], [mysql_config], "no", [$PATH:/usr/sbin:/usr/bin:/usr/local/bin:/usr/local/mysql/bin])
      if test "x$MYSQL_CONFIG" == "xno"; then
        AC_MSG_ERROR(["could not find mysql_config"])
      fi
    fi
  ],
  [
    AC_PATH_PROG([MYSQL_CONFIG], [mysql_config], "no", [$PATH])
    if test "x$MYSQL_CONFIG" == "xno"; then
      AC_MSG_ERROR(["could not find mysql_config"])
    fi
  ])
    
  AC_DEFINE([MYSQL_ENABLED], [1], [Enables MySQL])
  MYSQL_INC="`$MYSQL_CONFIG --cflags`"
  MYSQL_LIB="`$MYSQL_CONFIG --libs`"
])

dnl ---------------------------------------------------------------------------
dnl Macro: MYSQL_CONFIG
dnl ---------------------------------------------------------------------------
