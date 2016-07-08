# Check to see if we're running under Win32, without using
# AC_CANONICAL_*.  If so, set output variable EXEEXT to ".exe".
# Otherwise set it to "".

dnl AM_EXEEXT()
dnl This knows we add .exe if we're building in the Cygwin32
dnl environment. But if we're not, then it compiles a test program
dnl to see if there is a suffix for executables.
AC_DEFUN(AM_EXEEXT,
[AC_REQUIRE([AM_CYGWIN32])
AC_REQUIRE([AM_MINGW32])
AC_MSG_CHECKING([for executable suffix])
AC_CACHE_VAL(am_cv_exeext,
[if test "$CYGWIN32" = yes || test "$MINGW32" = yes; then
am_cv_exeext=.exe
else
cat > am_c_test.c << 'EOF'
int main() {
/* Nothing needed here */
}
EOF
${CC-cc} -o am_c_test $CFLAGS $CPPFLAGS $LDFLAGS am_c_test.c $LIBS 1>&5
am_cv_exeext=
for file in am_c_test.*; do
   case $file in
    *.c) ;;
    *.o) ;;
    *) am_cv_exeext=`echo $file | sed -e s/am_c_test//` ;;
   esac
done
rm -f am_c_test*])
test x"${am_cv_exeext}" = x && am_cv_exeext=no
fi
EXEEXT=""
test x"${am_cv_exeext}" != xno && EXEEXT=${am_cv_exeext}
AC_MSG_RESULT(${am_cv_exeext})
AC_SUBST(EXEEXT)])
