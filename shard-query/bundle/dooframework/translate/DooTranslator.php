<?php
/**
 * DooTranslator class file
 * @package doo.translate
 * @author Milos Kovacki <kovacki@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
* DooTranslator
*
* @author Milos Kovacki <kovacki@gmail.com>
* @copyright &copy; 2009 Milos Kovacki
* @license http://www.doophp.com/license
*/

class DooTranslator {

	/**
	* Instance of DooCache
	*/

	public $_cache = null;

	/**
	* Cache name
	*/

	private $_cachedName = null;

	/**
	* Adapter
	*/

	private $_adapter = null;

	/**
	* Data file
	*/

	private $_data = null;

	/**
	* Supported adapters
	*/

	private $_supportedAdapters = array("csv", "gettext", "array", "ini");

	/**
	* Supported cache types
	*/

	private $_supportedCacheTypes = array('apc', 'php', 'xcache', 'eaccelerator', 'file');

	/**
	* Options
	*/

	private $_options = array();

	/**
	* File for translation
	*/

	private $_file = null;

	private $_cacheIt = false;

	private $_bigEndian = false;

	/**
	* Class constructor
	*
	* Creating class instance for Gettext:
	* <code>$translator = Doo::translator('Gettext', $this->_basePath . 'languages/en/LC_MESSAGES/en.mo', array());</code>
	*
	* Creating class instance for Csv
	* <code>$translator = Doo::translator('Csv', $this->_basePath . 'languages/en/LC_MESSAGES/main.csv', array('delimiter' => ';',
	*																									   'enclosure' => '"'));</code>
	* For Csv, default delimiter is ";", enclosure '"' and length is 0
	*
	* @param string $adapter Adapter you are using, for example cvs, mo, etc...
	* @param string $data Data needed for translation, or file
	* @param array $options Options for adapter.
	*/

	public function __construct($adapter, $data, $options=array()) {
        $adapter = strtolower($adapter);
		if (!in_array($adapter, $this->_supportedAdapters)) {
			throw new DooTranslatorException($adapter . " is not supported by DooTranslator, supported types are: " . implode(', ', $this->_supportedAdapters));
		}
		$this->_setAdapter($adapter);
		if ($data == null) {
			throw new DooTranslatorException("Data not set!");
		}
		$this->_options = $options;
		// set cache
		if (isset($this->_options['cache'])) {
			if (!in_array($this->_options['cache'], $this->_supportedCacheTypes)) {
				throw new DooTranslatorException($this->_options['cache'] . " is unsuported cache type, supported cache types are: " . implode(', ', $this->_supportedCacheTypes));
			} else {
				switch ($this->_options['cache']) {
					case "apc":
						$this->_cache = Doo::cache('apc');
						break;
					case "php":
						$this->_cache = Doo::cache('php');
						break;
					case "xcache":
						$this->_cache = Doo::cache('xcache');
						break;
					case "eaccelerator":
						$this->_cache = Doo::cache('eaccelerator');
						break;
					case "file":
						if (isset($this->_options['path'])) {
							$this->_cache = new DooFileCache($this->_options['path']);
						} else {
							throw new DooTranslatorException("Cache directory is not set, please add path in options.");
						}
						break;
				}
				$this->_cachedName = md5($data);
			}
		}
		// load data
		$this->_loadData($data);
	}

	/**
	* Set adapter
	*
	* @param string $adapter Adapter name
	*/

	private function _setAdapter($adapter) {
		$this->_adapter = $adapter;
	}

	/**
	* Translate function, translates string
	*
	* @param string $message
	* @param array $params Placeholders you want to add in string.
	*
	* @example $translator->translate("Hello [_1], wellcome to my webpage", array('John'));
	*
	* Translating message
	* <code>
	* $translator->translate("This is message to be translated");
	* </code>
	*
	* Translating with placeholders
	* <code>
	* $translator->translate("Hello [_1], wellcome to my webpage", array('John'));
	* </code>
	*/

	public function translate($messageId, $params=array()) {
		if ($this->_adapter == null) throw new DooTranslatorException("Adapter not set!");
		$translatedMessage = "";
		switch ($this->_adapter) {
			default:
				$translatedMessage = isset($this->_data[$messageId])?$this->_data[$messageId]:$messageId;
				break;
		}

		foreach ($params as $key => $val) {
            $placeholder = $key + 1;
            $translatedMessage = str_replace("[_$placeholder]", $val, $translatedMessage);
		}
		return $translatedMessage;
	}

	private function _loadData($data) {
		if ($this->_adapter == null) throw new DooTranslatorException("Adapter not set!");

		if ($this->hasCache() && (($this->_data = unserialize($this->_cache->get("translation_".$this->_cachedName))) == true)) {
			//var_dump($this->_data);
			return;
		} else {
			$this->_cacheIt = true;
		}

		switch ($this->_adapter) {
			// CSV
			case 'csv':
				$filename = $data;
				$this->_file = fopen($filename, 'rb');
				if (!$this->_file) {
					throw new DooTranslatorException('Error opening translation file "' . $filename . '".');
				}
				// if user has specified options use these if not use the defaults
				$options = array();
				$options['delimiter'] = isset($this->_options['delimiter']) ? $this->_options['delimiter'] : ";";
				$options['length']    = isset($this->_options['length']) ? $this->_options['length'] : 0;
				$options['enclosure'] = isset($this->_options['length']) ? $this->_options['length'] : '"';

				while(($data = fgetcsv($this->_file, $options['length'], $options['delimiter'], $options['enclosure'])) !== false) {
					if (substr($data[0], 0, 1) === '#') {
						continue;
					}

					if (!isset($data[1])) {
						continue;
					}

					if (count($data) == 2) {
						if (isset($data[0])) {
							$translation = (isset($data[1]))?$data[1]:$data[0];
							$this->_data[$data[0]] = $translation;
						}
					}
				}
				break;
			// GETTEXT
			case 'gettext':
				$this->_file = fopen($data, 'rb');;
				if (!$this->_file) {
					throw new DooTranslatorException('Error opening translation file "' . $filename . '".');
				}
				$input = $this->_readMOData(1);
				$this->_bigEndian = false;
				//
				if (strtolower(substr(dechex($input[1]), -8)) == "950412de") {
					$this->_bigEndian = false;
				} else if (strtolower(substr(dechex($input[1]), -8)) == "de120495") {
					$this->_bigEndian = true;
				} else {
					throw new DooTranslatorException('"' . $filename . '" is not a valid gettext file!');
				}
				// read revision - not supported for now
				$input = $this->_readMOData(1);

				// number of bytes
				$input = $this->_readMOData(1);
				$total = $input[1];

				// number of original strings
				$input = $this->_readMOData(1);
				$OOffset = $input[1];

				// number of translation strings
				$input = $this->_readMOData(1);
				$TOffset = $input[1];

				// fill the original table
				fseek($this->_file, $OOffset);
				$origtemp = $this->_readMOData(2 * $total);
				fseek($this->_file, $TOffset);
				$transtemp = $this->_readMOData(2 * $total);

				for($count = 0; $count < $total; ++$count) {
					if ($origtemp[$count * 2 + 1] != 0) {
						fseek($this->_file, $origtemp[$count * 2 + 2]);
						$original = fread($this->_file, $origtemp[$count * 2 + 1]);
						$original = explode(chr(00), $original);
					} else {
						$original[0] = '';
					}

					if ($transtemp[$count * 2 + 1] != 0) {
						fseek($this->_file, $transtemp[$count * 2 + 2]);
						$translate = fread($this->_file, $transtemp[$count * 2 + 1]);
						$translate = explode(chr(00), $translate);

						if ((count($original) > 1) && (count($translate) > 1)) {
							$this->_data[$original[0]] = $translate;
							array_shift($original);
							foreach ($original as $orig) {
								$this->_data[$orig] = '';
							}
						} else {
							$this->_data[$original[0]] = $translate[0];
						}
					}
				}
				$this->_data[''] = trim($this->_data['']);
				break;
			// Array
			case 'array':
				$this->_data = $data;
				break;
			// Ini
			case 'ini':
				$this->_file = $data;
				if (!file_exists($this->_file)) {
					throw new DooTranslatorException('Error opening translation file "' . $filename . '".');
				}
				$inidata = parse_ini_file($data, false);
                $this->_data = $inidata;
				break;
		}
		// if caching on cach it
		if ($this->hasCache() && ($this->_cacheIt == true)) {
			$this->_cache->set("translation_".$this->_cachedName, serialize($this->_data));
		}
	}

	/**
	* hasCache function returns true if there is cache set
	*
	* @return bool True if there is cache mechanism set and false if not.
	*/

	public function hasCache() {
		if ($this->_cache == null) {
			return false;
		} else {
			return true;
		}
    }

	/**
	* Read data from mo files
	*
	* @param string $bytes
	*/
    private function _readMOData($bytes)
    {
        if ($this->_bigEndian === false) {
            return unpack('V' . $bytes, fread($this->_file, 4 * $bytes));
        } else {
            return unpack('N' . $bytes, fread($this->_file, 4 * $bytes));
        }
    }

}

class DooTranslatorException extends Exception {

}