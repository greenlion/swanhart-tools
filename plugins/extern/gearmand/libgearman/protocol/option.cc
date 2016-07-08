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
#include "libgearman/vector.h"

#include <cstdio>
#include <cstring>

namespace libgearman {
namespace protocol {

  gearman_return_t option(gearman_universal_st& universal,
                          gearman_packet_st& message,
                          const gearman_string_t& option_)
  {
    if (gearman_c_str(option_) == NULL)
    {
      return gearman_error(universal, GEARMAN_INVALID_ARGUMENT, "option was NULL");
    }

    if (gearman_size(option_) == 0)
    {
      return gearman_error(universal, GEARMAN_INVALID_ARGUMENT, "option size was 0");
    }

    if (gearman_size(option_) > GEARMAN_MAX_OPTION_SIZE)
    {
      return gearman_error(universal, GEARMAN_ARGUMENT_TOO_LARGE, "option was greater then GEARMAN_MAX_OPTION_SIZE");
    }

    const void *args[1];
    size_t args_size[1];

    args[0]= gearman_c_str(option_);
    args_size[0]= gearman_size(option_);

    return gearman_packet_create_args(universal, message, GEARMAN_MAGIC_REQUEST,
                                      GEARMAN_COMMAND_OPTION_REQ,
                                      args, args_size, 1);
  }

} // namespace protocol
} // namespace libgearman

