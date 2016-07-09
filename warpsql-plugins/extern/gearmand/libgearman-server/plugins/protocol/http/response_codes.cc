/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 *
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2012 Data Differential, http://datadifferential.com/
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

#include <libgearman-server/plugins/protocol/http/response_codes.h>

namespace gearmand {
namespace protocol {
namespace httpd {

const char *response(response_t arg)
{
  switch (arg)
  {
  case HTTP_CONTINUE: return "CONTINUE";
  case HTTP_SWITCHING_PROTOCOLS: return "SWITCHING_PROTOCOLS";
  case HTTP_PROCESSING: return "PROCESSING";
  case HTTP_OK: return "OK";
  case HTTP_CREATED: return "CREATED";
  case HTTP_ACCEPTED: return "ACCEPTED";
  case HTTP_NON_AUTHORITATIVE: return "NON_AUTHORITATIVE";
  case HTTP_NO_CONTENT: return "NO_CONTENT";
  case HTTP_RESET_CONTENT: return "RESET_CONTENT";
  case HTTP_PARTIAL_CONTENT: return "PARTIAL_CONTENT";
  case HTTP_MULTI_STATUS: return "MULTI_STATUS";
  case HTTP_MULTIPLE_CHOICES: return "MULTIPLE_CHOICES";
  case HTTP_MOVED_PERMANENTLY: return "MOVED_PERMANENTLY";
  case HTTP_MOVED_TEMPORARILY: return "MOVED_TEMPORARILY";
  case HTTP_SEE_OTHER: return "SEE_OTHER";
  case HTTP_NOT_MODIFIED: return "NOT_MODIFIED";
  case HTTP_USE_PROXY: return "USE_PROXY";
  case HTTP_TEMPORARY_REDIRECT: return "TEMPORARY_REDIRECT";
  case HTTP_BAD_REQUEST: return "BAD_REQUEST";
  case HTTP_UNAUTHORIZED: return "UNAUTHORIZED";
  case HTTP_PAYMENT_REQUIRED: return "PAYMENT_REQUIRED";
  case HTTP_FORBIDDEN: return "FORBIDDEN";
  case HTTP_NOT_FOUND: return "NOT_FOUND";
  case HTTP_METHOD_NOT_ALLOWED: return "METHOD_NOT_ALLOWED";
  case HTTP_NOT_ACCEPTABLE: return "NOT_ACCEPTABLE";
  case HTTP_PROXY_AUTHENTICATION_REQUIRED: return "PROXY_AUTHENTICATION_REQUIRED";
  case HTTP_REQUEST_TIME_OUT: return "REQUEST_TIME_OUT";
  case HTTP_CONFLICT: return "CONFLICT";
  case HTTP_GONE: return "GONE";
  case HTTP_LENGTH_REQUIRED: return "LENGTH_REQUIRED";
  case HTTP_PRECONDITION_FAILED: return "PRECONDITION_FAILED";
  case HTTP_REQUEST_ENTITY_TOO_LARGE: return "REQUEST_ENTITY_TOO_LARGE";
  case HTTP_REQUEST_URI_TOO_LARGE: return "REQUEST_URI_TOO_LARGE";
  case HTTP_UNSUPPORTED_MEDIA_TYPE: return "UNSUPPORTED_MEDIA_TYPE";
  case HTTP_RANGE_NOT_SATISFIABLE: return "RANGE_NOT_SATISFIABLE";
  case HTTP_EXPECTATION_FAILED: return "EXPECTATION_FAILED";
  case HTTP_UNPROCESSABLE_ENTITY: return "UNPROCESSABLE_ENTITY";
  case HTTP_LOCKED: return "LOCKED";
  case HTTP_FAILED_DEPENDENCY: return "FAILED_DEPENDENCY";
  case HTTP_UPGRADE_REQUIRED: return "UPGRADE_REQUIRED";
  case HTTP_NOT_IMPLEMENTED: return "NOT_IMPLEMENTED";
  case HTTP_BAD_GATEWAY: return "BAD_GATEWAY";
  case HTTP_SERVICE_UNAVAILABLE: return "SERVICE_UNAVAILABLE";
  case HTTP_GATEWAY_TIME_OUT: return "GATEWAY_TIME_OUT";
  case HTTP_VERSION_NOT_SUPPORTED: return "VERSION_NOT_SUPPORTED";
  case HTTP_VARIANT_ALSO_VARIES: return "VARIANT_ALSO_VARIES";
  case HTTP_INSUFFICIENT_STORAGE: return "INSUFFICIENT_STORAGE";
  case HTTP_NOT_EXTENDED: return "NOT_EXTENDED";

  case HTTP_INTERNAL_SERVER_ERROR:
  default:
                          break;
  }

  return "INTERNAL_SERVER_ERROR";
}

} // namespace http
} // namespace protocol
} // namespace gearmand

