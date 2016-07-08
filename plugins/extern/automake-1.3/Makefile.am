## Process this file with automake to create Makefile.in

AUTOMAKE_OPTIONS = gnits
MAINT_CHARSET = latin1

SUBDIRS = tests m4

bin_SCRIPTS = automake aclocal
info_TEXINFOS = automake.texi

pkgdata_DATA = clean-kr.am clean.am clean-hdr.am comp-vars.am \
compile.am data.am dejagnu.am depend.am depend2.am dist-vars.am	\
footer.am header.am header-vars.am kr-extra.am libs.am library.am \
libtool.am ltlib.am ltlibrary.am mans-vars.am program.am progs.am \
remake-hdr.am remake.am scripts.am subdirs.am tags.am tags-clean.am \
texi-vers.am texinfos.am libs-clean.am ltlib-clean.am progs-clean.am \
data-clean.am COPYING INSTALL texinfo.tex ansi2knr.c ansi2knr.1	\
lisp.am lisp-clean.am mans.am java.am java-clean.am

## These must all be executable when installed.
pkgdata_SCRIPTS = config.guess config.sub install-sh mdate-sh missing \
mkinstalldirs elisp-comp ylwrap acinstall

EXTRA_DIST = acinstall $(pkgdata_DATA)

# The following requires a fixed version of the Emacs 19.30 etags.
ETAGS_ARGS = automake.in aclocal.in --lang=none \
 --regex='/^@node[ \t]+\([^,]+\)/\1/' automake.texi
TAGS_DEPENDENCIES = automake.in aclocal.in automake.texi

## `test -x' is not portable.  So we use Perl instead.  If Perl
## doesn't exist, then this test is meaningless anyway.
installcheck-local:
	for file in $(pkgdata_SCRIPTS); do \
	  $(PERL) -e "exit ! -x '$(pkgdatadir)/$$file';" || exit 1; \
	done

################################################################
##
## Everything past here is useful to the maintainer, but probably not
## to anybody else
##

# Run the test suite using Perl 4.
perl4-check: automake aclocal
	@if $(SHELL) -c 'perl4.036 -v' > /dev/null 2>&1; then \
	  $(MAKE) PERL=perl4.036 check; \
	else :; fi

# Some simple checks, and then ordinary check.  These are only really
# guaranteed to work on my machine.
maintainer-check: automake aclocal
## This check avoids accidental configure substitutions in the source.
## There are exactly 8 lines that should be modified.  This works out
## to 24 lines of diffs.
	@if test `diff automake.in automake | wc -l` -ne 24; then \
	  echo "found too many diffs between automake.in and automake"; 1>&2; \
	  diff -c automake.in automake; \
	  exit 1; \
	fi
## Syntax check with default Perl (on my machine, Perl 5).
	$(PERL) -c -w automake
	$(PERL) -c -w aclocal
## expect no instances of '${...}'.  However, $${...} is ok, since that
## is a shell construct, not a Makefile construct.
	@if test `fgrep '$${' $(srcdir)/[a-z]*.am | fgrep -v '$$$$' | wc -l` -ne 0; then \
	  echo "found too many uses of '\$${'" 1>&2; \
	  exit 1;				\
	else :; fi
## Make sure all invocations of mkinstalldirs are correct.
	@if test `fgrep 'mkinstalldirs' $(srcdir)/[a-z]*.am | fgrep -v '$$(mkinstalldirs)' | wc -l` -ne 0; then \
	  echo "found incorrect use of mkinstalldirs" 1>&2; \
	  exit 1; \
	else :; fi
## Another syntax check, this time with Perl 4, if it exists.
	@if $(SHELL) -c 'perl4.036 -v' >/dev/null 2>&1; then \
	  perl4.036 -c -w automake; \
	  perl4.036 -c -w aclocal; \
	else :; fi
## We never want to use "undef", only "delete".
	@if grep undef $(srcdir)/automake.in > /dev/null 2>&1; then \
	  echo "Found undef in automake.in; use delete instead" 1>&2; \
	  exit 1; \
	fi
## We never want split (/ /,...), only split (' ', ...).
	@if fgrep 'split (/ /' $(srcdir)/automake.in > /dev/null 2>&1; then \
	  echo "Found bad split in automake.in" 1>&2; \
	  exit 1; \
	fi

# Tag before making distribution.  Also, don't make a distribution if
# checks fail.  Also, make sure the NEWS file is up-to-date.
cvs-dist: maintainer-check perl4-check distcheck
	@if sed 1q $(srcdir)/NEWS | grep -e "$(VERSION)" > /dev/null; then :; else \
	  echo "NEWS not updated; not releasing" 1>&2; \
	  exit 1;				\
	fi
	cvs -q tag `echo "Release-$(VERSION)" | sed 's/\./-/g'`
	$(MAKE) dist

cvs-diff:
	thisver=`echo "Release-$(VERSION)" | sed 's/\./-/g'`; \
	if test -z "$$OLDVERSION"; then \
	  prevno=`echo "$(VERSION)" - 0.01 | bc | sed 's/^\./0./'`; \
	else prevno="$$OLDVERSION"; fi; \
	prevver=Release-`echo $$prevno | sed 's/\./-/g'`; \
	cvs -f rdiff -c -r $$prevver -r $$thisver $(PACKAGE) \
	    > $(PACKAGE)-$$prevno-$(VERSION).diff

## Check our path lengths.
path-check: distdir
	(cd $(distdir) && \
## FIXME there's got to be a better way!  pathchk should take the list
## of files on stdin, at least.
	  find . -print | xargs pathchk -p); \
	  status=$$?; \
	  rm -rf $(distdir); \
	  exit $$status
