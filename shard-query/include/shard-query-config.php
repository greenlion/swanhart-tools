<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 encoding=utf-8: */
/* $Id: */
/*
Copyright (c) 2010, Justin Swanhart
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the <organization> nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

#The shard query configuration and the mapper directory will be stoerd in
#this database.  You must have permissions to create and drop this database.
#when setting up shard query THE CONTENTS OF THIS DATABASE WILL BE DESTROYED!

$contents = file_get_contents('config.inc',true);
$config_database = unserialize($contents);
if(!is_array($config_database)) 
    throw new Exception('Invalid result after unserializing database config information');

#note that cacheing is only used for queries send 
#via the proxy

#should be array of host:port values
#for example:
#$config_cache = array( '127.0.0.1:11211' )
$config_cache = array();

#default is to cache all queries for 600 seconds
#but it will only take effect if you configure cache
#servers above
$cache_rules = array ( 
  '/.*/' => 600
);
