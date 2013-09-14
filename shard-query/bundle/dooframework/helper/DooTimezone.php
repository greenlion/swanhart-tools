<?php
/**
 * DooTimezone class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2010 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * A helper class for PHP Timezones.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooTimezone.php 1000 2010-12-10 09:37:08
 * @package doo.helper
 * @since 1.3
 */
class DooTimezone {

	/**
	 * Returns timezone list with its offset from UTC
	 * @param string $timeformat Determines the format of the offset to be returned (in second, minute, hour or H:i (date time format))
	 * @param string $groupby Group result by offset or timezone. Defalut is null. Options: 'offset' or 'timezone'
	 * @return array
	 */
	public static function getTimezoneWithOffset( $timeformat = 'H:i', $groupby = null ){
		$list = timezone_identifiers_list();
		$size = sizeof($list);

		$tlist = array();

		if( $groupby == 'offset' ){
			for($i=0; $i<$size; $i++){
				$offset = self::getOffsetFromTimezone($list[$i], $timeformat);
				$tlist[strval($offset)][] = $list[$i];
			}
		}
		else if( $groupby == 'timezone' ){
			for($i=0; $i<$size; $i++){
				$tlist[ $list[$i] ] = self::getOffsetFromTimezone($list[$i], $timeformat);
			}
		}
		else{
			for($i=0; $i<$size; $i++){
				$tlist[$i] = array(self::getOffsetFromTimezone($list[$i], $timeformat), $list[$i]);
			}
		}

		return $tlist;
	}

	/**
	 * Get an array of common timezones with its offset from UTC as key, eg. Western European Time, Central Standard Time, Chinese Standard Time
	 * @param string $timeformat Determines the format of the offset to be returned (in second, minute, hour or H:i (date time format))
	 * @return array
	 */
	public static function getCommonTimezone( $timeformat = 'H:i' ){
		$list = array(
			'-12' => 'Baker Island Time',
			'-11' => 'Niue Time, Samoa Standard Time',
			'-10' => 'Hawaii-Aleutian Standard Time, Cook Island Time',
			'-9.5' => 'Marquesas Islands Time',
			'-9' => 'Alaska Standard Time, Gambier Island Time',
			'-8' => 'Pacific Standard Time',
			'-7' => 'Mountain Standard Time',
			'-6' => 'Central Standard Time',
			'-5' => 'Eastern Standard Time',
			'-4.5' => 'Venezuelan Standard Time',
			'-4' => 'Atlantic Standard Time',
			'-3.5' => 'Newfoundland Standard Time',
			'-3' => 'Amazon Standard Time, Central Greenland Time',
			'-2' => 'Fernando de Noronha Time, South Georgia &amp; the South Sandwich Islands Time',
			'-1' => 'Azores Standard Time, Cape Verde Time, Eastern Greenland Time',
			'0' => 'UTC, Western European Time, Greenwich Mean Time',
			'1' => 'Central European Time, West African Time',
			'2' => 'Eastern European Time, Central African Time',
			'3' => 'Moscow Standard Time, Eastern African Time',
			'3.5' => 'Iran Standard Time',
			'4' => 'Gulf Standard Time, Samara Standard Time',
			'4.5' => 'Afghanistan Time',
			'5' => 'Pakistan Standard Time, Yekaterinburg Standard Time',
			'5.5' => 'Indian Standard Time, Sri Lanka Time',
			'5.75' => 'Nepal Time',
			'6' => 'Bangladesh Time, Bhutan Time, Novosibirsk Standard Time',
			'6.5' => 'Cocos Islands Time, Myanmar Time',
			'7' => 'Indochina Time, Krasnoyarsk Standard Time',
			'8' => 'Chinese Standard Time, Australian Western Standard Time, Irkutsk Standard Time',
			'8.75' => 'Southeastern Western Australia Standard Time',
			'9' => 'Japan Standard Time, Korea Standard Time, Chita Standard Time',
			'9.5' => 'Australian Central Standard Time',
			'10' => 'Australian Eastern Standard Time, Vladivostok Standard Time',
			'10.5' => 'Lord Howe Standard Time',
			'11' => 'Solomon Island Time, Magadan Standard Time',
			'11.5' => 'Norfolk Island Time',
			'12' => 'New Zealand Time, Fiji Time, Kamchatka Standard Time',
			'12.75' => 'Chatham Islands Time',
			'13' => 'Tonga Time, Phoenix Islands Time',
			'14' => 'Line Island Time'
		);

		foreach( $list as $offset => $tz ){
			$sec = $offset * 3600;

			if( $timeformat == 'H:i' ){
				$sign = ($sec<0)?'-':'+';
				$offset = date($sign . $timeformat, strtotime('1 Jan 2010 00:00') + abs($sec) );
			}
			else if( $timeformat == 'minute' ){
				$offset = $offset*60;
			}else if( $timeformat == 'second' ){
				$offset = $sec;
			}

			$tzlist[$offset] = $tz;
		}
		return $tzlist;
	}


	/**
	 * Get select list's option HTML for common timezones with its offset from UTC as key, eg. Western European Time, Central Standard Time, Chinese Standard Time
	 * @param int|float $selectedOffset Default selected offset in hour in the options. By default none is selected. Use offset from UTC as value, eg. 0, 6.5, 8, -7
	 * @param string $prefix Prefix string for $timeformat
	 * @param string $suffix Suffix string for $timeformat
	 * @param string $timeformat Formats the time offset to be displayed in the list
	 * @param string $optionFormat Formats the time offset in option's value attribute
	 * @return string
	 */
	public static function getCommonTimezoneHTMLList( $selectedOffset = null, $prefix = '[UTC ', $suffix = '] ', $timeformat = 'H:i', $optionFormat = 'hour' ){
		$list = self::getCommonTimezone('second');
		$options = '';

		foreach( $list as $offset => $tz){
			$selected = '';

			if($offset==0){
				$prefix0 = rtrim($prefix);
				$suffix0 = ltrim($suffix);

				if( (is_int($selectedOffset) || is_float($selectedOffset)) && $selectedOffset == 0)
					$selected = ' selected="selected"';

				$tzlist[] = '<option value="'. $offset .'"'. $selected .'>'. $prefix0 . $suffix0 . $tz .'</option>';
			}
			else{

				if( (is_int($selectedOffset) || is_float($selectedOffset)) && $selectedOffset*3600 == $offset)
					$selected = ' selected="selected"';

				if( $timeformat == 'H:i' ){
					$sign = ($offset<0)?'-':'+';
					$sign = ($offset==0)?'':$sign;
					$offsetFormatted = date($sign . $timeformat, strtotime('1 Jan 2010 00:00') + abs($offset) );
				}
				else if( $timeformat == 'hour' ){
					$sign = ($offset<=0)?'':'+';
					$offsetFormatted = $sign . $offset/3600;
				}
				else if( $timeformat == 'minute' ){
					$offsetFormatted = $offset/60;
				}
				else if( $timeformat == 'second' ){
					$offsetFormatted = $offset;
				}

				if( $optionFormat == 'H:i' ){
					$sign = ($offset<0)?'-':'+';
					$sign = ($offset==0)?'':$sign;
					$offset = date($sign . $optionFormat, strtotime('1 Jan 2010 00:00') + abs($offset) );
				}
				else if( $optionFormat == 'hour' ){
					$offset = $offset/3600;
				}
				else if( $optionFormat == 'minute' ){
					$offset = $offset/60;
				}

				$tzlist[] = '<option value="'. $offset .'"'. $selected .'>'. $prefix . $offsetFormatted . $suffix . $tz .'</option>';
			}
		}

		return implode("\n", $tzlist);
	}

	/**
	 * Get select list's option HTML for all PHP timezones with its offset from UTC as key, eg. Western European Time, Central Standard Time, Chinese Standard Time
	 * @param string $selectedTimezone Default selected timezone in the options. By default none is selected. Use timezone identifier as value, eg. 'Asia/Hong_Kong'
	 * @param bool $useOffsetAsValue Use UTC offset as option value. Default is true. If false, timezone identifier will be used
	 * @param string $prefix Prefix string for $timeformat
	 * @param string $suffix Suffix string for $timeformat
	 * @param string $timeformat Formats the time offset to be displayed in the list
	 * @param string $optionFormat Formats the time offset in option's value attribute
	 * @return string
	 */
	public static function getTimezoneHTMLList( $selectedTimezone = '', $useOffsetAsValue = true, $prefix = '[UTC ', $suffix = '] ', $timeformat = 'H:i', $optionFormat = 'hour' ){
		$list = self::getTimezoneWithOffset('second', 'offset');
		ksort($list);
		$options = '';

		foreach( $list as $offset => $tz){

			if($offset==0){
				$prefix0 = rtrim($prefix);
				$suffix0 = ltrim($suffix);

				foreach( $tz as $tzname ) {
					if($selectedTimezone == $tzname)
						$selected = ' selected="selected"';

					if($useOffsetAsValue)
						$tzlist[] = '<option value="'. $offset .'"'. $selected .'>'. $prefix0 . $suffix0 . $tzname .'</option>';
					else
						$tzlist[] = '<option value="'. $tzname .'"'. $selected .'>'. $prefix0 . $suffix0 . $tzname .'</option>';
				}
			}
			else {

				if( $timeformat == 'H:i' ){
					$sign = ($offset<0)?'-':'+';
					$sign = ($offset==0)?'':$sign;
					$offsetFormatted = date($sign . $timeformat, strtotime('1 Jan 2010 00:00') + abs($offset) );
				}
				else if( $timeformat == 'hour' ){
					$sign = ($offset<=0)?'':'+';
					$offsetFormatted = $sign . $offset/3600;
				}
				else if( $timeformat == 'minute' ){
					$offsetFormatted = $offset/60;
				}
				else if( $timeformat == 'second' ){
					$offsetFormatted = $offset;
				}

				if($useOffsetAsValue){
					if( $optionFormat == 'H:i' ){
						$sign = ($offset<0)?'-':'+';
						$sign = ($offset==0)?'':$sign;
						$offset = date($sign . $optionFormat, strtotime('1 Jan 2010 00:00') + abs($offset) );
					}
					else if( $optionFormat == 'hour' ){
						$offset = $offset/3600;
					}
					else if( $optionFormat == 'minute' ){
						$offset = $offset/60;
					}
				}

				foreach( $tz as $tzname ) {
					$selected = '';

					if($selectedTimezone == $tzname)
						$selected = ' selected="selected"';

					if($useOffsetAsValue)
						$tzlist[] = '<option value="'. $offset .'"'. $selected .'>'. $prefix . $offsetFormatted . $suffix . $tzname .'</option>';
					else
						$tzlist[] = '<option value="'. $tzname .'"'. $selected .'>'. $prefix . $offsetFormatted . $suffix . $tzname .'</option>';
				}
			}
		}

		return implode("\n", $tzlist);
	}

	/**
	 * Get an array of timezone identifiers by region (Asia, Africa, America, etc.)
	 * @param string|array $regionName Optional parameter to specify a/a list of regions
	 * @return array
	 */
    public static function getTimezoneByRegion( $regionName = null ){
		$regions = array(
			'africa' => DateTimeZone::AFRICA,
			'america' => DateTimeZone::AMERICA,
			'antarctica' => DateTimeZone::ANTARCTICA,
			'artic' => DateTimeZone::ARCTIC,
			'asia' => DateTimeZone::ASIA,
			'atlantic' => DateTimeZone::ATLANTIC,
			'australica' => DateTimeZone::AUSTRALIA,
			'europe' => DateTimeZone::EUROPE,
			'indian' => DateTimeZone::INDIAN,
			'pacific' => DateTimeZone::PACIFIC,
			'utc' => DateTimeZone::UTC
		);

		if( is_string($regionName) && in_array(strtolower($regionName), array_keys($regions)) ){
			$regionName = strtolower($regionName);
			return DateTimeZone::listIdentifiers( $regions[$regionName] );
		}
		else if( is_array($regionName) ){
			foreach($regionName as $key => $value){
				$regionName[$key] = strtolower($value);
			}

			$all = array_intersect(array_keys($regions), $regionName);

			foreach ($all as $region_name) {
				$list[$region_name] = DateTimeZone::listIdentifiers( $regions[$region_name] );
			}
		}
		else {
			foreach ($regions as $name => $timezone) {
				$list[$name] = DateTimeZone::listIdentifiers($timezone);
			}
		}

		return $list;
    }

	/**
	 * Get UTC offset from timezone in second, minute, hour or Hi (date time format)
	 * @param string $timezoneIdentifier Timezone identifier, eg. Asia/Kuala_Lumpur
	 * @param string $timeformat Determines the format of the offset to be returned (in second, minute, hour or H:i (date time format))
	 * @return int|string Offset in second, minute, hour or H:i (date time format)
	 */
	public static function getOffsetFromTimezone( $timezoneIdentifier, $timeformat = 'H:i' ){
		$dateTimeZone = new DateTimeZone($timezoneIdentifier);
		$dateTime = new DateTime('now', $dateTimeZone);
		$sec = $dateTimeZone->getOffset($dateTime);

		if( $timeformat == 'second' )
			return $sec;
		else if( $timeformat == 'minute' )
			return $sec/60;
		else if( $timeformat == 'hour' ){
			return $sec/3600;
		}else{
			$sign = ($sec<0)?'-':'+';
		    return date($sign . $timeformat, strtotime('1 Jan 2010 00:00') + abs($sec) );
		}
	}

	/**
	 * Get one/a list of timezones identifiers from a UTC offset(in hour)
	 * @param int|float $offsetInHour Offset from UTC. eg. -6, 7.5, 8
	 * @param bool $onlyFirst
	 * @return string
	 */
	public static function getTimezoneFromOffset( $offsetInHour, $onlyFirst = false){
		$tz = DooTimeZone::getTimezoneWithOffset('hour', 'offset');

		if( isset($tz[$offsetInHour]) ){
			if($onlyFirst == true)
				return $tz[$offsetInHour][0];
			else
				return $tz[$offsetInHour];
		}
	}


	/**
	 * Convert time from a timezone to another
	 * @param string $time A valid date time string
	 * @param string $fromTimezone A valid timezone identifier for the original time
	 * @param string $toTimezone A valid timezone identifier to be converted to
	 * @param string $dateFormat Date format for the converted time. Default is 'Y-m-d H:i:s'
	 * @param bool $enableDST Enable/disable Daylight Saving Time in the time conversion. Default is DST enabled.
	 * @return string The formatted date time after conversion
	 */
	public static function convertTime( $time, $fromTimezone, $toTimezone, $dateFormat = 'Y-m-d H:i:s', $enableDST = true ){
		$timezone = new DateTimeZone( $fromTimezone );
		$date = new DateTime($time, $timezone);
		$date->setTimezone(new DateTimeZone( $toTimezone ));

		//If in DST,  calculate the DST offset difference for any given timezone
		if( $enableDST == false ){
			$dateDstFrom = new DateTime($time, new DateTimeZone($fromTimezone));

			$dateDst = new DateTime($time, new DateTimeZone($toTimezone));

			//check if timezone  of the date is in DST observation
			if ( $dateDst->format('I') == 1) {
				//check if needs to calculate DST offset, by generating the difference of time in original timezone and time in timezone to be converted to.
				$dtfrom = new DateTime($time, new DateTimeZone('UTC'));
				$dtto = new DateTime($date->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
				$offsetDiff = ( $dtto->getTimestamp()  - $dtfrom->getTimestamp()  );

				if( $offsetDiff != self::getOffsetFromTimezone($toTimezone, 'second') ){

					$dateDstEnd = new DateTime($dateDst->format('Y') .'-12-14 00:00', new DateTimeZone($toTimezone));
					$offset = $dateDstEnd->getOffset();

					$dateDstStart = new DateTime($dateDst->format('Y') .'-06-14 00:00', new DateTimeZone($toTimezone));
					$offset2 = $dateDstStart->getOffset();

					$offset = $offset2 - $offset;

					//get the date in dst to be converted to a non-dst
					$dstdatestr = $date->format('Y-m-d H:i:s');

					//use a UTC (without DST) to calculate the time(non-dst) with the offset
					$dtobj = new DateTime($dstdatestr, new DateTimeZone('UTC'));
					$dtobj->modify( -$offset . ' seconds');
					return $dtobj->format($dateFormat);
				}
			}
			else if ( $dateDstFrom->format('I') == 1 ) {
				$dtfrom = new DateTime($dateDstFrom->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
				$dtto = new DateTime($time, new DateTimeZone('UTC'));
				$offsetDiff = ( $dateDst->getTimestamp()  - $dateDstFrom->getTimestamp() ) / 3600;
				$noDstDiff =  self::getOffsetFromTimezone($fromTimezone, 'hour') - self::getOffsetFromTimezone($toTimezone, 'hour');

				if( $offsetDiff != $noDstDiff ){
					$offset = $noDstDiff - $offsetDiff;
					$dtobj = new DateTime($date->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
					$dtobj->modify( -$offset . ' hour');
					return $dtobj->format($dateFormat);
				}
			}
		}

		return $date->format( $dateFormat );
	}

	/**
	 * Convert time from UTC time
	 * @param string $time A valid date time string
	 * @param string $toTimezone A valid timezone identifier to be converted to
	 * @param string $dateFormat Date format for the converted time. Default is 'Y-m-d H:i:s'
	 * @param bool $enableDST Enable/disable Daylight Saving Time in the time conversion. Default is DST enabled.
	 * @return string The formatted date time after conversion
	 */
	public static function convertFromUTCTime( $time, $toTimezone, $dateFormat = 'Y-m-d H:i:s', $enableDST = true ){
		return self::convertTime($time, 'UTC', $toTimezone, $dateFormat, $enableDST);
	}

	/**
	 * Convert time to UTC time
	 * @param string $time A valid date time string
	 * @param string $fromTimezone A valid timezone identifier to be converted to
	 * @param string $dateFormat Date format for the converted time. Default is 'Y-m-d H:i:s'
	 * @param bool $enableDST Enable/disable Daylight Saving Time in the time conversion. Default is DST enabled.
	 * @return string The formatted date time after conversion
	 */
	public static function convertToUTCTime( $time, $fromTimezone, $dateFormat = 'Y-m-d H:i:s' ){
		return self::convertTime($time, $fromTimezone, 'UTC', $dateFormat, $enableDST);
	}

	/**
	 * Get location information for a timezone, including country_code, latitude, longitude and comments.
	 * @param string $timezoneIdentifier
	 * @return array
	 */
	public static function getGeolocationFromTimezone( $timezoneIdentifier ){
		$tz = new DateTimeZone($timezoneIdentifier);
		return $tz->getLocation();
	}
}
