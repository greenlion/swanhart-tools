/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 *
 *  Data Differential YATL (i.e. libtest)  library
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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

#include <string>

namespace libtest { class Framework; }


namespace libtest {

class Collection;

class Formatter;
typedef std::vector<libtest::Formatter*> Formatters;

class Formatter {
public:
  Formatter(const Framework* frame_, std::ostream&);

  virtual ~Formatter() {}

  virtual void plan(const libtest::Collection*) {}
  virtual void report(const libtest::TestCase*, size_t) const {}
  virtual void complete() {}

protected:
  const std::string& name() const;

#if 0
  static void xml(libtest::Framework&, std::ofstream&);
  static void tap(libtest::Framework&, std::ofstream&);
#endif

protected:
  const Framework* _frame;

protected:
  std::ostream& _output;
};

class Junit : public Formatter
{
public:
  Junit(const Framework*, std::ostream&);
  ~Junit();

  void report(const libtest::TestCase*, size_t position) const;

  void plan(const libtest::Collection*);
  void complete();
};

class TAP : public Formatter
{
public:
  TAP(const Framework*, std::ostream&);
  ~TAP();

  void plan(const libtest::Collection*);
  void report(const libtest::TestCase*, size_t position) const;
};

class Legacy : public Formatter
{
public:
  Legacy(const Framework*, std::ostream&);
  ~Legacy();

  void plan(const libtest::Collection*);
  void report(const libtest::TestCase*, size_t position) const;

private:
  const libtest::Collection* _collection;
};

} // namespace libtest
