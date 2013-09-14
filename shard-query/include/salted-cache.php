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
/*
 * This is the SaltedCache.  
 * 
 * This is a Memcache wrapper which 
 * combines keys with salts.  The idea is to get a consistent
 * cache view for a single query, but not show expired cache
 * entries to other queries.  If salts are not used, then 
 * it behaves just like a normal Memcache class.
 * 
 * For example:
 * 
 * $SC = new SaltedCache('127.0.0.1');
 * $query = "select * from some_table";
 * 
 * $query_id = 32;
 * $data = run_query($query);
 * $SC->put($data, $query_id);
 * 
 * sleep(600); #wait 10 mins
 * 
 * $new_query_id = 33;
 * $data2 = run_query($query);
 * $SC->put($data2, $new_query_id);
 * 
 * unset($data); 
 * unset($data2);
 * 
 * $data  = $SC->get($query, $query_id);
 * $data2 = $SC->get($query, $new_query_id);
 * 
 * if($data === $data2) {
 *   echo "Data has not changed since old snapshot";
 * }
 */
class SaltedCache {
    private $mc = null;
    private static $hashfunc = 'sha1';
    
	function __construct($servers = null) {
        if(!extension_loaded('memcache') || !isset($servers) || empty($servers)) {
            return false;
        }
    
        $this->mc = new Memcache(); 
        $connected = false;
        foreach ($servers as $server) { 
            $info = explode(':', $server); 

            $host = $info[0]; 
            $port = isset($info[1]) ? $info[1] : 11211; 

            if ($this->mc->addServer($host, $port)) { 
                $connected = true; 
            } 
        } 

        if(!$connected) unset($this->mc);
        
    }
    
	/* This function uses the provided hash function to hash
	 * the input value with the salt (if one is provided).
	 */
    public static function hash($item, $salt = "") {
        return SimpleCache::$hashfunc($item . $salt);
    }

    /* Get an item from the cache, using the salt on the
     * cache key if one is provided.
     */
    function get($item, $salt = "") {
        if(!isset($this->mc)) return false;

        $hash = SaltedCache::hash($item, $salt);
        return $this->mc->get($hash);
    }

    /* Sets an item in the cache.  The key is salted
     * if the salt is provided.
     */
    function set($item, $value, $expires = -1, $salt = "") {
        if(!isset($this->mc)) return false;
        if (!is_numeric($expires)) { 
            $expires = strtotime($expires); 
        } 

        if ($expires < 1) { 
            return false; 
        } 

        $hash = SaltedCache::hash($item, $salt);
        return $this->mc->set($hash, $item, 0, time() + $expires);

    }

}
