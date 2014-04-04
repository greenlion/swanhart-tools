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


/*USAGE:
$ci = new ChunkIt();
$offset_info = $ci->find_offsets('/tmp/ontime_fact.txt', 8);
print_r($offset_info);
*/


class ChunkIt extends StdClass {
    var $line_terminator = null;

    public function __construct($line_terminator = "\n") {
        $this->line_terminator = $line_terminator;
    }

    public function find_offsets($path, $chunks = 1) {
        $fh = fopen($path,'r');
        if(!$fh) throw new Exception('Could not open ' . $path . " for reading\n");

        $info = stream_get_meta_data($fh);
        if(empty($info['seekable']) || $info['seekable'] != 1) {
            throw new Exception('Given file is not seekable.');
        }

        $size = filesize($path);
        $base_chunk_size = floor($size / $chunks);

        $info = array();
        for($i=0; $i< $chunks; ++$i) {
            $start = $i * $base_chunk_size;
            $info[] = $this->find_chunk_boundary($fh, $start) ;
        }	

        $out = array();
        for($i=0;$i<count($info)-1;++$i) {
            $start = $info[$i];
            $length = $info[$i+1] - $start;
            $end = $start + $length-1;
            $out[] = array('start' => $start, 'length' => $length, 'end' => $end);
        }
        $out[] = array('start' => $info[count($info)-1], 'length' => $size - $info[count($info)-1], 'end' => $size);

        return $out;
    }

    public function s3_find_offsets($s3, $bucket, $file, $size, $base_chunk_size, $chunks = 1) {

        $info = array();
	$info[0] = 0;
        for($i=0; $i< $chunks; ++$i) {
            echo ".";
            $start = $i * $base_chunk_size;
            $end = $start + $base_chunk_size;
            $fname = tempnam("/tmp", mt_rand(1,999999999));
            unlink($fname);
            @$s3->getObject($bucket, $file, $fname, array($end, $end+70000));
            $fh = fopen($fname, 'rb');
            unlink($fname); // so it disappears if we crash
            $offset = $this->find_chunk_boundary($fh, null);
            $info[] = $offset += $end;
            
        }	
        echo "\n";

        $out = array();
        for($i=0;$i<count($info)-1;++$i) {
            $start = $info[$i];
            $length = $info[$i+1] - $start;
            $end = $start + $length-1;
            $out[] = array('start' => $start, 'length' => $length, 'end' => $end);
        }
        $out[] = array('start' => $info[count($info)-1], 'length' => $size - $info[count($info)-1], 'end' => $size);

        return $out;
    }


    private function find_chunk_boundary($fh, $start) {
        #fgets is only safe for terminators that end in \n, but it is an order of magnitude 
        #faster than using fread()  
    	$fgets_safe=false; 
    	
    	if($start != null && $start == 0) {
            #the first chunk always starts at the beginning
            return 0;
    	} elseif($start == null) {
            $start = 0;
        }

    	#seek to the requested starting position (which might be zero)
    	fseek($fh, $start, SEEK_SET);
    
    	#stop reading when we have reached the end of file
    	$terminator_len = strlen($this->line_terminator);
    
    	if(substr($this->line_terminator,-1,1) == "\n") {
    		$fgets_safe=1;
    	}
    
    	while(!feof($fh)) {
    		if($fgets_safe==1) {
    			$chars = fgets($fh); #fast because it can read a batch of characters
    		} else {
    			$chars = fread($fh, $terminator_len); #SLOW
    		}
    
    		$last_chars = substr($chars,-1, $terminator_len);
    
    		if($last_chars == $this->line_terminator) return ftell($fh);
    			
        }	
    }
}
