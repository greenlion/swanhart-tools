/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011-2012 Data Differential, http://datadifferential.com/
 *  Copyright (C) 2008 Brian Aker, Eric Day
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

/**
 * @file
 * @brief Thread Declarations
 */

#pragma once

#include <pthread.h>

#include <libgearman-server/struct/gearmand_thread.h>

/**
 * @addtogroup gearmand_thread Thread Declarations
 * @ingroup gearmand
 *
 * Thread handling for gearmand.
 *
 * @{
 */

/**
 * Create a new gearmand thread.
 * @param gearmand Server instance structure previously initialized with
 *        gearmand_create.
 * @return Standard gearman return value.
 */
gearmand_error_t gearmand_thread_create(struct gearmand_st& gearmand);

/**
 * Free resources used by a thread.
 * @param thread Thread previously initialized with gearmand_thread_create.
 */
void gearmand_thread_free(gearmand_thread_st *thread);

/**
 * Interrupt a running gearmand thread.
 * @param thread Thread structure previously initialized with
 * gearmand_thread_create.
 * @param wakeup Wakeup event to send to running thread.
 */
void gearmand_thread_wakeup(gearmand_thread_st *thread,
                            gearmand_wakeup_t wakeup);

/**
 * Run the thread when there are events ready.
 * @param thread Thread structure previously initialized with
 * gearmand_thread_create.
 */
void gearmand_thread_run(gearmand_thread_st *thread);
