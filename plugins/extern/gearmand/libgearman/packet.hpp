/*  vim:expandtab:shiftwidth=2:tabstop=2:smarttab:
 * 
 *  Gearmand client and server library.
 *
 *  Copyright (C) 2011 Data Differential, http://datadifferential.com/
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


gearman_packet_st *gearman_packet_create(gearman_universal_st&, gearman_packet_st& packet);


void gearman_packet_free(gearman_packet_st*);

/**
 * Initialize a packet with all arguments. For example:
 *
 * void *args[3];
 * size_t args_suze[3];
 *
 * args[0]= function_name;
 * args_size[0]= strlen(function_name) + 1;
 * args[1]= unique_string;
 * args_size[1]= strlen(unique_string,) + 1;
 * args[2]= workload;
 * args_size[2]= workload_size;
 *
 * ret= gearman_packet_create_args(gearman, packet,
 *                              GEARMAN_MAGIC_REQUEST,
 *                              GEARMAN_COMMAND_SUBMIT_JOB,
 *                              args, args_size, 3);
 */
gearman_return_t gearman_packet_create_args(gearman_universal_st&,
                                            gearman_packet_st& packet,
                                            enum gearman_magic_t magic,
                                            gearman_command_t command,
                                            const void *args[],
                                            const size_t args_size[],
                                            size_t args_count);

/**
 * Give allocated memory to packet. After this, the library will be responsible
 * for freeing the workload memory when the packet is destroyed.
 */
void gearman_packet_give_data(gearman_packet_st& packet, 
                              const void *data, size_t data_size);

/**
 * Take allocated data from packet. After this, the caller is responsible for
 * free()ing the memory.
 */
void *gearman_packet_take_data(gearman_packet_st& packet, size_t *data_size);

/**
 * Pack packet into output buffer.
 */
size_t gearman_packet_pack(const gearman_packet_st& packet,
                           void *data, size_t data_size,
                           gearman_return_t &ret);

/**
 * Unpack packet from input data.
 */
size_t gearman_packet_unpack(gearman_packet_st& packet,
                             const void *data, size_t data_size,
                             gearman_return_t &ret);

/**
 * Add an argument to a packet.
 */
gearman_return_t gearman_packet_create_arg(gearman_packet_st& packet,
                                           const void *arg, size_t arg_size);

/**
 * Pack header.
 */
gearman_return_t gearman_packet_pack_header(gearman_packet_st *packet);

/**
 * Unpack header.
 */
gearman_return_t gearman_packet_unpack_header(gearman_packet_st *packet);
