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

#pragma once

namespace gearmand {
namespace protocol {
namespace httpd {

enum response_t {
  HTTP_CONTINUE=                     100,
  HTTP_SWITCHING_PROTOCOLS=          101,
  HTTP_PROCESSING=                   102,
  HTTP_OK=                           200,
  HTTP_CREATED=                      201,
  HTTP_ACCEPTED=                     202,
  HTTP_NON_AUTHORITATIVE=            203,
  HTTP_NO_CONTENT=                   204,
  HTTP_RESET_CONTENT=                205,
  HTTP_PARTIAL_CONTENT=              206,
  HTTP_MULTI_STATUS=                 207,
  HTTP_MULTIPLE_CHOICES=             300,
  HTTP_MOVED_PERMANENTLY=            301,
  HTTP_MOVED_TEMPORARILY=            302,
  HTTP_SEE_OTHER=                    303,
  HTTP_NOT_MODIFIED=                 304,
  HTTP_USE_PROXY=                    305,
  HTTP_TEMPORARY_REDIRECT=           307,
  HTTP_BAD_REQUEST=                  400,
  HTTP_UNAUTHORIZED=                 401,
  HTTP_PAYMENT_REQUIRED=             402,
  HTTP_FORBIDDEN=                    403,
  HTTP_NOT_FOUND=                    404,
  HTTP_METHOD_NOT_ALLOWED=           405,
  HTTP_NOT_ACCEPTABLE=               406,
  HTTP_PROXY_AUTHENTICATION_REQUIRED=407,
  HTTP_REQUEST_TIME_OUT=             408,
  HTTP_CONFLICT=                     409,
  HTTP_GONE=                         410,
  HTTP_LENGTH_REQUIRED=              411,
  HTTP_PRECONDITION_FAILED=          412,
  HTTP_REQUEST_ENTITY_TOO_LARGE=     413,
  HTTP_REQUEST_URI_TOO_LARGE=        414,
  HTTP_UNSUPPORTED_MEDIA_TYPE=       415,
  HTTP_RANGE_NOT_SATISFIABLE=        416,
  HTTP_EXPECTATION_FAILED=           417,
  HTTP_UNPROCESSABLE_ENTITY=         422,
  HTTP_LOCKED=                       423,
  HTTP_FAILED_DEPENDENCY=            424,
  HTTP_UPGRADE_REQUIRED=             426,
  HTTP_INTERNAL_SERVER_ERROR=        500,
  HTTP_NOT_IMPLEMENTED=              501,
  HTTP_BAD_GATEWAY=                  502,
  HTTP_SERVICE_UNAVAILABLE=          503,
  HTTP_GATEWAY_TIME_OUT=             504,
  HTTP_VERSION_NOT_SUPPORTED=        505,
  HTTP_VARIANT_ALSO_VARIES=          506,
  HTTP_INSUFFICIENT_STORAGE=         507,
  HTTP_NOT_EXTENDED=                 510
};

const char *response(response_t);

} // namespace http
} // namespace protocol
} // namespace gearmand
