<?php
/*  FlexCDC is part of Flexviews for MySQL
    Copyright 2008-2010 Justin Swanhart

    FlexViews is free software: you can redistribute it and/or modify
    it under the terms of the Lesser GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    FlexViews is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FlexViews in the file COPYING, and the Lesser extension to
    the GPL (the LGPL) in COPYING.LESSER.
    If not, see <http://www.gnu.org/licenses/>.

    Portions of this code are strongly influenced by:
    mysql_binlog (https://github.com/jeremycole/mysql_binlog)
*/

class binlog_event_consumer {
	public $table_map = array();
	public $rows = array();
	private $read_pos = 0;
	private $sent_fde = false;
	private $mvlogList = array();
	
	private $event_types = array(
		'START_EVENT_V3'           => 1,
		'FORMAT_DESCRIPTION_EVENT' => 15,
		'TABLE_MAP_EVENT'          => 19,
		'WRITE_ROWS_EVENT'         => 23,
		'UPDATE_ROWS_EVENT'        => 24,
		'DELETE_ROWS_EVENT'        => 25
	);

	private $data_types = array( 
	    'decimal'         => 0,
	    'tiny'            => 1,
	    'short'           => 2,
	    'long'            => 3,
	    'float'           => 4,
	    'double'          => 5,
	    'null'            => 6,
	    'timestamp'       => 7,
	    'longlong'        => 8,
	    'int24'           => 9,
	    'date'            => 10,
	    'time'            => 11,
	    'datetime'        => 12,
	    'year'            => 13,
	    'newdate'         => 14,
	    'varchar'         => 15,
	    'bit'             => 16,
	    'newdecimal'      => 246,
	    'enum'            => 247,
	    'set'             => 248,
	    'tiny_blob'       => 249,
	    'medium_blob'     => 250,
	    'long_blob'       => 251,
	    'blob'            => 252,
	    'var_string'      => 253,
	    'string'          => 254,
	    'geometry'        => 255
	);
	private $data_type_map;
	private $event_type_map;

	public $gsn;
	public $dest;
	public $mvlogs;
	public $mvlogDB;

	public function set($key, $val) {
		$this->$key = $val;
	}

	public function get($key) {
		return $this->$key;
	}

	function __CONSTRUCT(&$data="", $gsn=0) {
		$this->gsn = $gsn;
		$this->data_type_map = array_flip($this->data_types);
		$this->event_type_map = array_flip($this->event_types);
		$this->consume($data);
	}
	
	function consume(&$data) {
		if(!$data) return;
		if(empty($this->mvlogList)) $this->refresh_mvlog_cache();
		$events = explode("\n", $data);
		unset($data);
		
		$save_data = "";
		for($i = 0, $cnt = count($events); $i < $cnt; ++$i) {
			$event = $events[$i];
			unset($events[$i]);
			if(strlen($event) === 76 && substr($event, -1) !== '=') {
				#echo "SAVING DATA: $event\n";
				$save_data .= $event;
				continue;
			}
			if($save_data) {
				$event = $save_data . $event;
				$save_data = "";
			}
			if(!$event) continue;

			$this->raw_data = $event;
			#echo "RAW EVENT: $event\n";
			#echo "RAW LEN:" . strlen($event) . "\n";
			unset($event);
			while($this->next_event());
		}
	}
	
	protected function next_event() {
		global $data_size;
		$data_size=0;
		#$m = memory_get_usage(true);
		#echo "IN NEXT_EVENT: ALLOCATED MEMORY BEFORE BODY {bytes:$m mega:" . ($m / 1024 / 1024) . " giga:" . ($m / 1024 / 1024 / 1024) . "}\n";
		if($this->raw_data === false || $this->raw_data === "") return false;
		$header = substr($this->raw_data,0,28); # 28 = (19 * 4 / 3) + 3
		#echo "HEADER: $header LEN: " . strlen($header) . "\n";
		if(!$this->data .= base64_decode($header,true)) die1('base64 decode failed for: ' . $header . "\n");
		#echo "DECODED LEN: " . strlen($this->data) . "\n";

		$this->read_pos = 0;
		$this->parse_event_header();

		$body_size = $this->header->event_length - 19;
		$bytes_to_decode = floor($body_size * 4 / 3) + ( ($body_size * 4 % 3) != 0 ? 4 - ($body_size * 4 % 3) : 0 );
		$body = substr($this->raw_data, 28, $bytes_to_decode);

		#echo "EXPECT BODY SIZE(after decoding): $body_size, BODY SIZE(encoded): $bytes_to_decode, BYTES IN STREAM: " . strlen($body) . "\n";
		if(!$this->data .= base64_decode($body,true)) die1('base64 decode failed for: ' . $body . "\n");
		$this->raw_data = substr($this->raw_data, $bytes_to_decode + 28);

		#echo "EXPECTED BODY SIZE: $body_size, GOT BODY SIZE: " . strlen($this->data) . "\n";
		$this->parse_event_body();
		$this->process_rows();

		$rows = 0;

		#echo "AT ACTUAL_READ_POS: {$this->read_pos} EXPECTED_READ_POS: {$this->header->event_length}\n";
		#$m = memory_get_usage(true);
		#echo "IN NEXT_EVENT: ALLOCATED MEMORY AFTER BODY {bytes:$m mega:" . ($m / 1024 / 1024) . " giga:" . ($m / 1024 / 1024 / 1024) . "}\n";

		if($this->read_pos < $this->header->event_length) {
			$error = "AFTER EVENT PARSE REMAINING_DATA_LENGTH: " . strlen($this->data) . "\n" .
			"EVENT UNDERREAD AT ACTUAL_READ_POS: {$this->read_pos} EXPECTED_READ_POS: {$this->header->event_length}\n";
			foreach(str_split($this->data) as $key => $char) {
				$error .= "$key => " . ord($char) . "\n";
			}
			die1($error);
			
			
		}
		if($this->read_pos > $this->header->event_length) {
			$error = "AFTER EVENT PARSE REMAINING_DATA_LENGTH: " . strlen($this->data) . "\n";
			$error .= "EVENT OVERREAD AT ACTUAL_READ_POS: {$this->read_pos} EXPECTED_READ_POS: {$this->header->event_length}\n";
			die1($error);
		}

		return true;
	}

	protected function refresh_mvlog_cache() {
		$this->mvlogList = array();
			
		$sql = "SELECT table_schema, table_name, mvlog_name from `{$this->mvlogDB}`.`$this->mvlogs` where active_flag=1";
		$stmt = my_mysql_query($sql, $this->dest);
		while($row = mysql_fetch_array($stmt)) {
			$this->mvlogList[$row[0] . $row[1]] = $row[2];
		}
	}
	
	protected function process_rows() {
		#echo "IN PROCESS ROWS\n";
		#print_r($this);
	
		foreach($this->table_map as $table_id => $info) {
			#FIXME: smartly (and transactionally) invalidate the cache
			if($info->db === $this->mvlogDB && $info->table === $this->mvlogs) $this->refresh_mvlog_cache();
			if(empty($this->mvlogList[$info->db . $info->table])) {
				continue;
			}
	
			if(!$this->sent_fde) {
				$sql = "BINLOG '\n" . chunk_split(base64_encode($this->encode_fde()), 76, "\n") . "'/*!*/;\n";
				my_mysql_query($sql, $this->dest) or die1("Could not execute statement:\n" . $sql);
				unset($sql);
			}
	
			$events = $this->encode_remap_table($table_id);
			$events = chunk_split(base64_encode($events), 76, "\n");
	
			if(!empty($this->rows[$table_id]) && $this->rows[$table_id]['old']['images'] != "") {
				$events .= chunk_split(base64_encode($this->encode_row_events($table_id,'old')), 76, "\n");
			}
	
			if(!empty($this->rows[$table_id]) && $this->rows[$table_id]['new']['images'] != "") {
				$events .= chunk_split(base64_encode($this->encode_row_events($table_id,'new')), 76, "\n");
			}
	
			$sql = "BINLOG '\n$events'/*!*/;\n";
			unset($events);

			my_mysql_query($sql, $this->dest) or die1("Could not execute statement:\n" . $sql);
			unset($sql);

		}

	}
	
	protected function encode_fde() {
		$output = $this->encode_int(time(), 32);
		$output .= chr(15); #FDE
		$output .= $this->encode_int($this->header->server_id,32);
		$output .= $this->encode_int(19 + strlen($this->fde->encoded),32); #event size
		$output .= $this->encode_int(19 + strlen($this->fde->encoded),32); #next offset
		$output .= $this->encode_int(0,16);
		return $output . $this->fde->encoded;
	}
	
	protected function encode_row_events(&$table_id, $mode) {
		$map = $this->table_map[$table_id];
		$output = $table_id;
		$output .= pack('S', 0); #flags
		$output .= $this->encode_varint(count($map->columns) + 4); #column count (we add four columns)
		$columns_used=array(1,1,1,1); # front-load the columns used list with the four new columns
		foreach($this->rows[$table_id][$mode]['columns_used'] as $bit) {
			$columns_used[] = $bit;
		}
	
		$output .= $this->encode_bitmap($columns_used, count($map->columns) + 4); #columns used bitmap
		$output .= $this->rows[$table_id][$mode]['images'];
		unset($this->rows[$table_id][$mode]['images']);
	
		#encode the header, knowing the body size
		$h = $this->encode_int(time(), 32); #timestamp
		$h .= chr(23); #FDE event type
		$h .= $this->encode_int($this->header->server_id,32); #server id
		$h .= $this->encode_int(19 + strlen($output),32); #event size
		$h .= $this->encode_int(19 + strlen($output)*2,32); #next offset
		$h .= $this->encode_int(0,16); #flags
	
		return $h . $output;
	}
	
	protected function encode_lpstring($string, $with_null = true) {
		$size_type = (strlen($string) < 256 ? 'C' : 'S');
		$size_bytes = pack($size_type, strlen($string));
		if($with_null) $string .= chr(0);
		return $size_bytes . $string;
	}
	
	protected function encode_varint($int) {
		if($int === null) return chr(251);
		if(($int >= -128 && $int <= 250)) return chr($int);
		if(($int >= -32768 && $int <= 65535)) return(chr(252) . pack("S", $int));
		if(($int >= -8388608 && $int <= 8388607)) return(chr(253) . $this->encode_int($int,24));
		return(chr(254) . $this->encode_int($int,64));
	}
	
	function encode_int($in, $pad_to_bits=64, $little_endian=true) {
		$in = decbin($in);
		$in = str_pad($in, $pad_to_bits, '0', STR_PAD_LEFT);
		$out = '';
		for ($i = 0, $len = strlen($in); $i < $len; $i += 8) {
			$out .= chr(bindec(substr($in,$i,8)));
		}
		if($little_endian) $out = strrev($out);
		return $out;
	}
	
	function encode_bitmap($bits, $max_bits=false) {
		if(!is_string($bits)) $bits = join('',$bits);
		if($max_bits !== false) {
			$bits = substr($bits, 0, $max_bits);
		}
	
		$bits = str_pad($bits, floor(strlen($bits) / 8) + ((strlen($bits) % 8) > 0 ? 1 : 0), '0', STR_PAD_LEFT);
		$out = '';
	
		for ($i = 0, $len = strlen($bits); $i < $len; $i += 8) {
			$out .= chr(bindec(strrev(substr($bits, $i, 8))));
		}
	
		return $out;
	}
	
	function encode_remap_table($table_id) {
		$map = $this->table_map[$table_id];

		#encode the body header
		$output = $table_id; # 6 bytes for table id
		$output .= $map->raw_flags;
	
		#remap the table to the new table
		$output .= $this->encode_lpstring($this->mvlogDB); #remap the DB into the new db
		$output .= $this->encode_lpstring($this->mvlogList[$map->db . $map->table]); #remap to the new table
	
		#store the number of columns
		$output .= $this->encode_varint(count($map->columns) + 4); #column count + 4 new columns (dml_type, uow_id, fv$server_id, fv$gsn)
	
		#output the byte array of data types
		$output .= chr(3) . chr(8) . chr(3) . chr(8);  #data types for new columns (int=3, bigint=8)
		foreach($map->columns as $col) {
			$output .= chr($col->type);
		}
	
		#none of the types we added have metadata, so tack on any metadata for the existing columns if it exists
		$output .= $this->encode_varint(strlen($map->raw_metadata)); #size of metadata section in bytes
		$output .= $map->raw_metadata;
		
		#encode the nullability data
		$nullable = array(0,0,0,0); #none of the columns we added are nullable
		foreach($map->columns as $col) {
			$nullable[] = $col->nullable;
		}
		$output .= $this->encode_bitmap($nullable, count($map->columns) + 4);
		
		$h = $this->encode_int(time(), 32);
		$h .= chr(19); #table map event
		$h .= $this->encode_int($this->header->server_id,32);
		$h .= $this->encode_int(19 + strlen($output),32);
		$h .= $this->encode_int(0,32);
		$h .= $this->encode_int(0,16);

		unset($map);
		
		return $h . $output;
	}
	
	
	function reset() {
		unset($this->rows);
		unset($this->table_map);
		unset($this->data);
		unset($this->raw_data);

		$this->table_map = array();
		$this->rows = array();
		$this->data = "";
		$this->raw_data = "";
		$this->read_pos = 0;
	}

	protected function parse_event_body() {
		switch($this->header->event_type) {
			case $this->event_types['START_EVENT_V3']:
				#echo "START_EVENT_V3\n";
				break;
			case $this->event_types['TABLE_MAP_EVENT']:
				#echo "TABLE_MAP_EVENT\n";
				$this->parse_table_map_event();
				break;
			case $this->event_types['WRITE_ROWS_EVENT']:
				#echo "WRITE_ROWS_EVENT\n";
				$this->parse_row_event('insert');
				break;
			case $this->event_types['UPDATE_ROWS_EVENT']:
				#echo "UPDATE_ROWS_EVENT\n";
				$this->parse_row_event('update');
				break;
			case $this->event_types['DELETE_ROWS_EVENT']:
				#echo "DELETE_ROWS_EVENT\n";
				$this->parse_row_event('delete');
				break;
			case $this->event_types['FORMAT_DESCRIPTION_EVENT']:
				#echo "FORMAT_DESCRIPTION_EVENT\n";
				$this->parse_format_description_event();
				break;
			default:
				die1("UNKNOWN EVENT TYPE!\n" . print_r($this->header,true));
		}
	}

	protected function parse_event_header() {
		$data = $this->read(19);
		$this->header_raw = $data;
		$this->header=(object)unpack("Vtimestamp/Cevent_type/Vserver_id/Vevent_length/Vnext_position/vflags", $data);
	}

	protected function parse_table_map_event() {
		$table_id = $this->read(6);
		$this->table_map[$table_id] = new StdClass;
		#echo "TABLE_ID(decoded): " . $this->cast($table_id) . "\n";
		$this->table_map[$table_id]->raw_flags = $this->read(2);
		$db = $this->read_lpstringz();
		$table = $this->read_lpstringz();
		#echo "DB: $db TABLE: $table [note: null trimmed on storage]\n";
		$this->table_map[$table_id]->db    = trim($db,chr(0));
		$this->table_map[$table_id]->table = trim($table,chr(0));
		
		$column_count = $this->cast($this->read_varint(false));
		#echo "COLUMN COUNT: $column_count\n";
		$data = $this->read($column_count);
		$this->table_map[$table_id]->raw_column_data = $data;
		$data = str_split($data);

		$columns = array();
		$length = $this->cast($this->read_varint(false)); 
		#echo "EXTRA LENGTH: $length\n";

		$this->table_map[$table_id]->raw_metadata = "";
		foreach($data as $col => $data_type) {
			#echo "GETTING METADATA FOR @$col OF DATA TYPE: ". $this->data_type_map[$this->cast($data_type)] . "\n";
			$data_type = $this->cast($data_type);
			$columns[$col]['type'] = $data_type;
			// $columns[$col]['type_text'] = $this->data_types[$data_type];
			$m = $this->data_types;
			$columns[$col]['metadata'] = new StdClass(); //attach empty metadata to each column
			switch($data_type) {
				case $m['float']:
				case $m['double']:
					$data = $this->read(1);
					$this->table_map[$table_id]->raw_metadata .= $data;
					$columns[$col]['metadata'] = (object)array('size'=>$this->cast($data));
				break;
			
				case $m['varchar']:
					$data = $this->read(2);
					$this->table_map[$table_id]->raw_metadata .= $data;
					$columns[$col]['metadata'] = (object)array('max_length' => $this->cast($data));
				break;
		

				case $m['bit']:
					$bits = $this->cast($this->read(1));
					$bytes = $this->cast($this->read(1));
					$this->table_map[$table_id]->raw_metadata .= $bits . $bytes;
					$columns[$col]['metadata'] = (object)array('bits'=>(bytes * 8) + bits);
				break;
	
				case $m['newdecimal']:
					$precision = $this->read(1);	
					$scale = $this->read(1);	
					$this->table_map[$table_id]->raw_metadata .= $precision. $scale;
					$precision = $this->cast($precision);
					$scale = $this->cast($scale);	
					$columns[$col]['metadata'] = (object)array('precision'=>$precision, 'scale'=>$scale);
				break;

				case $m['blob']:
				case $m['geometry']:
					$length_size = $this->cast($this->read(1));
					$this->table_map[$table_id]->raw_metadata .= $length_size;
					$columns[$col]['metadata'] = (object)array('length_size'=>$length_size);
				break;

				case $m['string']:
				case $m['var_string']:
					$data = $this->read(1);
					$real_type = $this->data_type_map[$this->cast($data)];
					$real_type_int = $this->cast($data);
					$this->table_map[$table_id]->raw_metadata .= $data;
					switch($real_type) {
						case 'enum':
						case 'set':
							$data = $this->read(1);
							$size = $this->cast($data);
							$this->table_map[$table_id]->raw_metadata .= $data;
							$columns[$col]['metadata'] = (object)array('size' => $size);
							$columns[$col]['type'] = $real_type_int;
						break;

						default:
							
							$data = $this->read(1);
							$this->table_map[$table_id]->raw_metadata .= $data;
							$size = $this->cast($data);
							$columns[$col]['metadata'] = (object)array('max_length' => $size);
					}
				break;
			}	
		}
		#echo "SAVED METADATA LEN: " . strlen($this->table_map[$table_id]->raw_metadata) . "\n";
		
		$this->table_map[$table_id]->raw_nullable = $this->read_bit_array($column_count,true);
		$nullable = $this->unpack_bit_array($this->table_map[$table_id]->raw_nullable);

		for($i=0;$i<count($columns);++$i) {
			$columns[$i] = (object)$columns[$i];
			$columns[$i]->nullable = $nullable[$i];
		}

		$this->table_map[$table_id]->columns = $columns;
		
	}

	protected function parse_format_description_event() {
		$this->fde = new StdClass;
		$this->fde->encoded = $this->data;
		$this->fde->binlog_version = $this->cast($this->read(2));
		$this->fde->server_version = $this->read(50);
		$this->fde->create_timestamp = $this->cast($this->read(4));
		$this->fde->header_length = $this->cast($this->read(1));
		$this->fde->footer = $this->read_to_end();
	}
	
	protected function parse_row_event($mode='insert') {
		#echo "PARSING ROW EVENT\n";
		$table_id = $this->read(6);
		if(empty($this->table_map[$table_id])) die1('ROW IMAGE WITHOUT MAPPING TABLE MAP');
		$this->rows[$table_id]['new'] = array('images'=>"", 'columns_used' => array());
		$this->rows[$table_id]['old'] = array('images'=>"", 'columns_used' => array());
		$flags = $this->read(2);
		$column_count = $this->cast($this->read_varint(false));
		$columns_used=array();
		if(empty($this->rows[$table_id])) $this->rows[$table_id] = array();

		switch($mode) {

			case 'insert':
					#echo "READING COLUMNS USED\n";
					$this->rows[$table_id]['new']['columns_used'] = $this->read_bit_array($column_count, false);
					while($this->data){
						#encode the data for our four extra columns
						#echo "READING AN IMAGE\n";
						$this->rows[$table_id]['new']['images'] .= $this->read_row_image($table_id, 1);
					}
			break;
			
			case 'update':
					$this->rows[$table_id]['old']['columns_used'] = $this->read_bit_array($column_count,false);
					$this->rows[$table_id]['new']['columns_used'] = $this->read_bit_array($column_count,false);
					while($this->data) {	
						$this->rows[$table_id]['old']['images'] .= $this->read_row_image($table_id, -1);
						$this->rows[$table_id]['new']['images'] .= $this->read_row_image($table_id, 1);
					}
			break;
			
			case 'delete':
					#echo "READING COLUMNS USED\n";
					$this->rows[$table_id]['old']['columns_used'] = $this->read_bit_array($column_count,false);
					while($this->data) {
						$this->rows[$table_id]['old']['images'] .= $this->read_row_image($table_id, -1);
					}
			break;	

		}

		#global $data_size;
		#if(!isset($data_size)) $data_size = 0;
		#$data_size += strlen(serialize($this->rows[$table_id]));
		#echo "FINISHED READING ROWS EVENT\n";
		#echo "TOTAL IMAGE SIZE: " . $data_size . "\n";
		#echo "TOTAL MEMORY SIZE: " . memory_get_usage() . "\n";
		#echo "GARBAGE COLLECTION YIELDED: " . gc_collect_cycles();
		#echo "NEW TOTAL MEMORY SIZE: " . memory_get_usage() . "\n\n";
		
	}
	
	protected function read_row_image(&$table_id,$dml_mode) {
		
		static $encoded = false; #cached version of encoded representation of header data
		static $last_uowid = false; #cache must be updated when the unit of work changes
		$columns_used = $this->rows[$table_id][$dml_mode == -1 ? 'old' : 'new']['columns_used'];
		if($last_uowid !== $this->uow_id) {
			$last_uowid = $this->uow_id;
			$encoded = $this->encode_int($this->uow_id, 64);
			$encoded .= pack('L', $this->header->server_id);
		}

		$row_data = pack('l', $dml_mode);
		$row_data .= $encoded;
		$row_data .= $this->encode_int(++$this->gsn, 64); #INCREMENT THE GSN

		#echo "READING NULL COLUMN MAP\n";
		$columns_null = $this->read_bit_array(count($this->table_map[$table_id]->columns), false);
		
		#echo "READING ROW IMAGE\n";
		for($col=0, $cnt = count($this->table_map[$table_id]->columns); $col < $cnt; ++$col) { 
			if($this->rows[$table_id][$dml_mode == -1 ? 'old' : 'new']['columns_used'][$col] == 0) {
				continue;
			} elseif ($columns_null[$col] == 1) {
				continue;
			}
			$row_data .= $this->read_mysql_type($this->table_map[$table_id]->columns[$col]);
		}

		#append the information about our new four columns
		$nulls = array(0,0,0,0);
		foreach($columns_null as $bit) {
			$nulls[] = $bit;
		}
		$columns_null = $this->encode_bitmap($nulls, count($this->table_map[$table_id]->columns) + 4);

		return $columns_null . $row_data;
	}
	
	protected function cast(&$data, $bits=false) {
		if ($bits === false) $bits = strlen($data) * 8;
		if($bits <= 0 ) return false;
		switch($bits) {
			case 8:
				$return = unpack('C',$data);
				$return = $return[1];
			break;

			case 16:
				$return = unpack('v',$data);
				$return = $return[1];
			break;

			case 24:
				$return = unpack('ca/ab/cc', $data);
				$return = $return['a'] + ($return['b'] << 8) + ($return['c'] << 16);
			break;

			case 32:
				$return = unpack('V', $data);
				$return = $return[1];
			break;

			case 48:
				$return = unpack('va/vb/vc', $data);
				$return = $return['a'] + ($return['b'] << 16) + ($return['c'] << 32);
			break;

			case 64:
				$return = unpack('Va/Vb', $data);
				$return = $return['a'] + ($return['b'] << 32);
			break;

		}
		return $return;
	}

	protected function read($bytes,$message="") {
		$return = substr($this->data,0,$bytes);
		#if ($message) #echo "$message LEFT: " . strlen($this->data) . " REQD: $bytes, GOT: " . strlen($return) . "\n";
		$this->read_pos += $bytes;
		$this->data = substr($this->data, $bytes);
		return $return;
	}

	protected function read_varint($keep_packed=true) {
		$data = $this->read(1);
      		$first_byte = $this->cast($data,8);
		if($first_byte <= 250) return $data;
		if($first_byte == 251) return null;
		if($first_byte == 252) return ($keep_packed ? $data : '') . $this->read(2);
		if($first_byte == 253) return ($keep_packed ? $data : '') . $this->read(3);
		if($first_byte == 254) return ($keep_packed ? $data : '') . $this->read(8);
		if($first_byte == 255) die1('invalid varint length found!\n');
	}

	protected function read_lpstring($size=1, $with_null = false) {
		$data = $this->read($size);
		$s = str_split($data);
		$length = $this->cast($data);
			
		#echo "LEN READ FOR VARCHAR: $length\n";	
		$data = $this->read($length + $with_null);
		#echo "READ DATA: $data\n";
		return $data;
	}

	protected function read_to_end() {
		$return = $this->data;
		$this->read_pos += strlen($return);
		#echo "READ_TO_END: " . strlen($return) . "\n";
		$this->data="";
		return $return;
	}

	protected function read_lpstringz($size=1) {
		return $this->read_lpstring($size, true);
	}

	protected function read_nullstring($keep_null = true) {
		$return = "";
		while(1) {
			$char = $this->read(1);
			if($char === chr(0)) break;
			$return .= $char;
			if($this->data === "") die1("did not find end of string!\n");
		}
		if($keep_null) $return .= $char;
		return $return;
	}

	protected function read_varstring() {
		$data = $this->read_varint;
		$length = $this->cast($data, strlen($data) * 8);
		return $data . $this->read($length);
	}

	protected function read_bit_array($size=1, $keep_packed=true) {
		$bytes = floor(($size+7)/8);
		$data = $this->read($bytes,'reading bit array');
		if($keep_packed) return $data;
		return $this->unpack_bit_array($data);
	}

	public function unpack_bit_array(&$data) {
		$output = "";	
		$data = str_split($data);
		foreach($data as $i => $char) {
			$int = ord($char);
			$output .= strrev(str_pad(decbin($int), 8, '0', STR_PAD_LEFT));
		}
		unset($i);
		unset($char);
		return str_split($output);
	}

	protected function read_newdecimal($precision, $scale) {
		#echo "READING NEWDEC P:$precision S:$scale\n";
		$digits_per_integer = 9;
      		$compressed_bytes = array(0, 1, 1, 2, 2, 3, 3, 4, 4, 4);
      		$integral = ($precision - $scale);
      		$uncomp_integral = floor($integral / $digits_per_integer);
      		$uncomp_fractional = floor($scale / $digits_per_integer);
      		$comp_integral = $integral - ($uncomp_integral * $digits_per_integer);
      		$comp_fractional = $scale - ($uncomp_fractional * $digits_per_integer);
      		$size = $compressed_bytes[$comp_integral];
		$data = "";

		if($size > 0) {
			$data = $this->read($size);
		}

		for($i=0;$i<$uncomp_integral;++$i) {
			$data .= $this->read(4);
		}

		for($i=0;$i<$uncomp_fractional;++$i) {
			$data .= $this->read(4);
		}

		$size = $compressed_bytes[$comp_fractional];
		if($size > 0) {
			$data .= $this->read($size);
		}

		return $data;

		
	}

	protected function read_mysql_type(&$col_info) {
		$data_type = $col_info->type;
		$metadata = $col_info->metadata;
		$m = &$this->data_types;
		#echo "READ_MYSQL_TYPE: " . $this->data_type_map[$data_type] . "\n";

		switch($data_type) {
			case $m['tiny']:
			return $this->read(1);
			case $m['short']:
			return $this->read(2);
			case $m['int24']:
			return $this->read(3);
			case $m['long']:
			$d = $this->read(4);
			#ECHO "LONG: " . $this->cast($d) . "\n";
			return $d;

			case $m['longlong']:
			return $this->read(8);
			case $m['float']:
			return $this->read(4);
			case $m['double'];
			return $this->read(8);
			case $m['string']: 
			case $m['var_string']:
			return $this->read_varstring();
			case $m['varchar']:
			return $this->read_lpstring(($metadata->max_length > 255 ? 2 : 1));
			case $m['blob']: 
			case $m['geometry']:
			return $this->read_lpstring($metadata->length_size);
			case $m['timestamp']: 
			return $this->read(4);	
			case $m['year']:
			return $this->read(1);
			case $m['date']:
			case $m['time']:
			return $this->read(3);
			case $m['datetime'];
			return $this->read(8);
			case $m['enum']: 
			case $m['set']:
			return $this->read($metadata->size);
			case $m['bit']:
			return $this->read_bit_array($metadata->bits);
			case $m['newdecimal']:
			return $this->read_newdecimal($metadata->precision, $metadata->scale);
			default: die1("DO NOT KNOW HOW TO READ TYPE: $data_type\n");
		}

		return false;
	}
			
}
