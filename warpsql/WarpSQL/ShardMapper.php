<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 encoding=utf-8 */
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

error_reporting(E_ALL);
require_once('common.php');

interface WarpSQL\ShardMapper {
  function __construct($config, $no_change_db);
  function &map($column, $key, $schema_name=null, $operator = "=", $create_on_miss = true);
}


class HashShardKeyMapper implements ShardKeyMapper{
  var $conn = null;  
  var $cache = array();

  #returns a shard object
  public function &map(&$HANDLE, $table_name, $column_list, &$key_list, $operator = "=") {
    static $null = null;
    if($operator == "BETWEEN") {
       preg_match("/([0-9]+) AND ([0-9]+)/", $key, $matches);
       if(!$matches) return $null;
       $operator = "in";
       $out = "";
       for($i=$matches[1];$i<=$matches[2];++$i) {
           if($out) $out .= ",";
           $out .= $i;
       }
       $key = "($out)";
    }

    if($operator != '=' && strtolower($operator) != 'in') return $null;

    if(strtolower($operator) == "in") {
      $vals = explode(',', trim($key,'()'));
      $prev_item = "";
      $list = array();
      foreach($vals as $item) {
        if($prev_item != "") $item = $prev_item . $item;
        #did explode split a string containing an embedded comma?
        if(!is_numeric($item) && !in_array(substr(trim($item), -1), array("'",'"'))) {
          $prev_item = $item;
        } else {
          $prev_item = "";
        }

        $list[] = $item;
      }

    } else {
      $list = array($key);
    }

    $map = array();
    $shard_names = array_keys($HANDLE->shards);
    foreach($list as $item) {

      if(is_numeric($item)) {
        $map[] = array($shard_names[ $item % count($shard_names) ] => $item);
      } else {
        $map[] = array($shard_names[ (abs(crc32($item)) % count($shard_names)) ] => $item);
      }

    }

    return $map;

  }

}

class NoneShardKeyMapper implements ShardKeyMapper {

  public function &map($column, $key, $schema_name=null, $operator = "=", $create_on_miss = true) {
    static $null = null;
    return $null;
  }

}
