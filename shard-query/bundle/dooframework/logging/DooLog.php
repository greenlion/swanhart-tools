<?php
/**
 * DooLog class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * Records log messages in memory.
 *
 * <p>Log various messages type with filters including log levels and log categories.
 * Use DooLogWriter to write logs into files. You can output the log message to the debugger tool for development purpose.</p>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooLog.php 1000 2009-07-7 18:27:22
 * @package doo.logging
 * @since 1.0
 */
class DooLog{
	const EMERG = 0;	//Emergency - system is unusable 
	const ALERT = 1;	//Alert - action must be taken immediately 
	const CRIT = 2;		//Critical - critical conditions 
	const ERR = 3;		//Error - error conditions 
	const WARN = 4;		//Warning - warning conditions 
	const NOTICE = 5;	//Notice - normal but significant condition 
	const INFO = 6;		//Informational - informational messages 
	const TRACE = 7;	//Debug trace - debug messages 
	const PROFILE = 8;	//Profile

    /**
     * Log/Profile rotate file size in KB
     * @var int
     */
    protected $rotate_size = 0;

	private $_levels;
	private $_logs = array();
	private $_profiles = array();
	private $_debug;
	
	function __construct($debug=true){
		$this->_debug = $debug;
		$this->_levels = array('EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'TRACE', 'PROFILE');
	}

    /**
     * Set the Log/Profile rotate file size in KB
     * @param int $size File size in KB
     */
    public function rotateFile($size){
        $this->rotate_size = $size*1024;
    }

    /**
     * Turn on/off debug mode for DooLog
     * Traces will only be logged in debug mode.
     * @param bool $enable
     */
    public function debug($enable){
        $this->_debug = $enable;
    }

    /**
	 * Logs a message.
	 * Messages logged by this method may be retrieved back via {@link getLogs}.
     * @param string $msg
     * @param int $level default DooLog::TRACE
     * @param string $category
     */
	public function log($msg, $level=6, $category='application'){
		$this->_logs[] = array($msg, $level, $category, microtime(true));
	}

	/**
	 * Get log messages.
	 *
	 * <p>Log messages can be filtered by levels and/or categories.
	 * A level filter is specified by a list of levels or a single level
     * A category filter is specified in the same way.</p>
	 *
	 * <p>If you do not specify level filter, it will bring back logs at all levels.
	 * The same applies to category filter.</p>
     * 
	 * @param int level filter
	 * @param string category filter
	 * @return array list of messages. Each array element represents one message
	 * with the structure:
	 * <code>
     * array(
	 *   [0] => message (string)
	 *   [1] => level (int)
	 *   [2] => category (string)
	 *   [3] => timestamp (float, microtime(true)
     * );
     * </code>
	 */
    public function getLogs($levels=NULL,$categories=NULL){
        if(empty($levels) && empty($categories)){
            return $this->_logs;
        }
    }

    //---------------------- logging shorthands ----------------------
    /**
     * Shorthand for logging messages with level Emergency
     * @param string $msg
     * @param string $category
     */
	public function emerg($msg, $category='application'){
		$this->log($msg, 0, $category);
	}

    /**
     * Shorthand for logging messages with level Alert
     * @param string $msg
     * @param string $category 
     */
    public function alert($msg, $category='application'){
		$this->log($msg, 1, $category);
	}

    /**
     * Shorthand for logging messages with level Crtical
     * @param string $msg
     * @param string $category 
     */
    public function crit($msg, $category='application'){
		$this->log($msg, 2, $category);
	}

    /**
     * Shorthand for logging messages with level Error
     * @param string $msg
     * @param string $category
     */
	public function err($msg, $category='application'){
		$this->log($msg, 3, $category);
	}

    /**
     * Shorthand for logging messages with level Warning
     * @param string $msg
     * @param string $category
     */
	public function warn($msg, $category='application'){
		$this->log($msg, 4, $category);
	}

    /**
     * Shorthand for logging messages with level Notice
     * @param string $msg
     * @param string $category
     */
	public function notice($msg, $category='application'){
		$this->log($msg, 5, $category);
	}

    /**
     * Shorthand for logging messages with level Info
     * @param string $msg
     * @param string $category
     */
	public function info($msg, $category='application'){
		$this->log($msg, 6, $category);
	}

    /**
     * Shorthand for logging messages with level Trace
     * Trace messages will only be logged if debug mode is On
     * @param string $msg
     * @param string $category
     */
	public function trace($msg, $category='application'){
		if($this->_debug)
			$this->log($msg, 7, $category);
	}

    //---------------------- General Profiling ----------------------
    /**
     * Marks the start of a profile identified by a unique token and category.
     * @param string $token This must be unique
     * @param string $category Category filter
     */
	public function beginProfile($token, $category='application'){
		$log = array("Begin Profiling $token", 8, $category, microtime(true));
		$this->_logs[] = $log;
		$this->_profiles[$token] = $log;
		$this->_profiles[$token]['startmem'] = $this->memory_used();
	}

    /**
     * Ending of a profile and adds the profile result to memory
     * @param string $token This must be unique
     */
	public function endProfile($token){
		$log = $this->_profiles[$token];
        $log[0] = "End Profiling $token";
		$this->_logs[] = $log;
		$this->_profiles[$token]['result'] = microtime(true) - $this->_profiles[$token][3];
		$this->_profiles[$token]['memory'] = $this->memory_used() - $this->_profiles[$token]['startmem'];
	}

    /**
     * Returns a profile result to get the time and memory used
     * @param string $token This must be unique
     * @return array Profile result in an array
     */
	public function getProfileResult($token){
		return $this->_profiles[$token];
	}

    //---------------------- Database Profiling ----------------------
    /**
     * Marks the start of a database profile identified by a unique token and category.
     * @param string $token This must be unique
     * @param string $category Category filter
     */
	public function beginDbProfile($token, $category='database'){
		$log = array("Begin SQL Profiling $token", 8, $category, microtime(true));
		$this->_logs[] = $log;
		$this->_profiles[$token] = $log;
        $query_index = Doo::db()->getQueryCount();
        $this->_profiles[$token]['sqlstart'] = ($query_index<0)?0:$query_index;
		$this->_profiles[$token]['startmem'] = $this->memory_used();
	}

    /**
     * Ending of a database profile and adds the profile result to memory
     * @param string $token This must be unique
     */
	public function endDbProfile($token){
		$log = $this->_profiles[$token];
        $log[0] = "End SQL Profiling $token";
		$this->_logs[] = $log;
        if($sqls = Doo::db()->showSQL())
            $this->_profiles[$token]['sql'] = implode("\n\r", array_slice($sqls, $this->_profiles[$token]['sqlstart']));
        else
            $this->_profiles[$token]['sql'] = '';
		$this->_profiles[$token]['result'] = microtime(true) - $this->_profiles[$token][3];
		$this->_profiles[$token]['memory'] = $this->memory_used() - $this->_profiles[$token]['startmem'];
	}

    /**
     * Returns a profile result to get the time and memory used
     * @param string $token This must be unique
     * @return array Database profile result in an array
     */
	public function getDbProfileResult($token){
		return $this->_profiles[$token];
	}

    //---------------------- log & profile displaying HTML ----------------------
    /**
     * Return a neatly formatted XML log view, filtered by level or category.
     * @param int $level
     * @param string $category 
     * @return string formatted XML log view
     */
    public function showLogs($xml=true, $level=NULL, $category=NULL){
            $msg = "\n<!-- Generate on ".date('Y-m-d H:i:s', time() )." -->\n";
            $keep = $msg;
            if($level==NULL && $category==NULL){
                foreach($this->_logs as $k=>$p){
                    if($p[0]!=''){
                        if(!$xml){
                            $msg .= $this->formatLog($p[0], $p[1], $p[2], $p[3]);
                        }else{
                            $msg .= $this->formatLogXML($p[0], $p[1], $p[2], $p[3]);
                        }
                    }
                }
            }
            else if($category==NULL){
                foreach($this->_logs as $k=>$p){
                    if($p[0]!='' && $p[1]==$level){
                        if(!$xml){
                            $msg .= $this->formatLog($p[0], $p[1], $p[2], $p[3]);
                        }else{
                            $msg .= $this->formatLogXML($p[0], $p[1], $p[2], $p[3]);
                        }
                    }
                }
            }
            else if($level==NULL){
                foreach($this->_logs as $k=>$p){
                    if($p[0]!='' && $p[2]==$category){
                       if(!$xml){
                            $msg .= $this->formatLog($p[0], $p[1], $p[2], $p[3]);
                        }else{
                            $msg .= $this->formatLogXML($p[0], $p[1], $p[2], $p[3]);
                        }
                    }
                 }
            }else{
                foreach($this->_logs as $k=>$p){
                    if($p[0]!='' && $p[1]==$level && $p[2]==$category){
                       if(!$xml){
                            $msg .= $this->formatLog($p[0], $p[1], $p[2], $p[3]);
                        }else{
                            $msg .= $this->formatLogXML($p[0], $p[1], $p[2], $p[3]);
                        }
                    }
                 }
            }

        if($keep!=$msg)
            return $msg;
        return;
    }

    /**
     * Return a neatly formatted XML debug traces, filtered by level or category.
     * @param string $category
     * @return string formatted XML debug traces
     */
    public function showTraces($xml=true, $category=NULL){
        if($this->_debug)
            return $this->showLogs($xml, self::TRACE , $category);
    }

    /**
     * Return a neatly formatted XML profile view, filtered by level or category.
     * @param string $category
     * @return string formatted XML Profile result
     */
    public function showProfiles($xml=true, $category=NULL){
        $msg = "\n<!-- Generate on ".date('Y-m-d H:i:s', time() )." -->\n";
        $keep = $msg;
        if($category==NULL){
            foreach($this->_profiles as $k=>$p){
                if(!$xml){
                    $msg .= $this->formatProfile($k,$p[2], $p[3], $p['result'], $p['memory']);
                }else{
                    $msg .= $this->formatProfileXML($k,$p[2],$p[3], $p['result'], $p['memory']);
                }
            }
        }else{
            foreach($this->_profiles as $k=>$p){
                if($p[2]!=$category)continue;
                if(!$xml){
                    $msg .= $this->formatProfile($k,$p[2], $p[3], $p['result'], $p['memory']);
                }else{
                    $msg .= $this->formatProfileXML($k,$p[2], $p[3], $p['result'], $p['memory']);
                }
            }
        }
        if($keep!=$msg)
            return $msg;
        return;
    }

    /**
     * Return a neatly formatted String/XML profile view, filtered category 'database'.
     * @param bool $xml Return as XML string
     * @return string formatted String/XML Database profile result
     */
    public function showDbProfiles($xml=true){
        $msg = "\n<!-- Generate on ".date('Y-m-d H:i:s', time() )." -->\n";
        $keep = $msg;
        foreach($this->_profiles as $k=>$p){
            if(isset($p['sql']) && $p['sql']!=''){
                if(!$xml){
                    $msg .= $this->formatDbLog($k, $p['sql'], $p[3], $p['result'], $p['memory']);
                }else{
                    $msg .= $this->formatDbLogXML($k, $p['sql'], $p[3], $p['result'], $p['memory']);                        
                }
            }
        }
        if($keep!=$msg)
            return $msg;
        return;
    }

    /**
     * Writes the Database profiles into a file.
     * @param string $filename File name for the profiles data log.
     * @param bool $xml Whether to write as plain text or XML file.
     */
    public function writeDbProfiles($filename='db.profile.xml', $xml=true){
        $this->writeToFile($this->showDbProfiles($xml), $filename);
    }

    /**
     * Writes the profiles into a file.
     * @param string $filename File name for the profiles data log.
     * @param string $category Filter by a category name
     * @param bool $xml Whether to write as plain text or XML file.
     */
    public function writeProfiles($filename='profile.xml', $xml=true, $category=NULL){
        $this->writeToFile($this->showProfiles($xml,$category), $filename);
    }

    /**
     * Writes the log messages into a file.
     * @param string $filename File name for log.
     * @param int $level Filter by a log level, eg. DooLog::INFO
     * @param string $category Filter by a category name
     * @param bool $xml Whether to write as plain text or XML file.
     */
    public function writeLogs($filename='log.xml', $xml=true, $level=NULL, $category=NULL){
        $this->writeToFile($this->showLogs($xml, $level, $category), $filename);
    }

    /**
     * Writes the Trace messages into a file.
     * @param string $filename File name for trace log.
     * @param bool $xml Whether to write as plain text or XML file.
     */
    public function writeTraces($filename='trace.log.xml', $xml=true){
        $this->writeToFile($this->showTraces($xml), $filename);
    }

    /**
     * Write to file.
     * If rotate file size is set, logs and profiles are automatically rotate when the file size is reached.
     * @param string $data Data string to be logged
     * @param string $filename File name for the log/profile
     */
    protected function writeToFile($data, $filename){
        //only write to file if there's a record
        if($data!=NULL){
            $mode = 'a+';
            if(isset(Doo::conf()->LOG_PATH))
                $filename = Doo::conf()->LOG_PATH . $filename;

            if($this->rotate_size!=0){
                if(file_exists($filename) && filesize($filename) > $this->rotate_size ){
                    $mode = 'w+';
                }
            }
			Doo::loadHelper('DooFile');
			$file = new DooFile(0777);
			$file->create($filename, $data, $mode);
        }
    }

	/**
	 * Returns the memory usage of the current application.
	 * This method relies on the PHP function memory_get_usage().
	 * If it is not available, the method will attempt to use OS programs
	 * to determine the memory usage. A value 0 will be returned if the
	 * memory usage can still not be determined.
	 * @return integer memory usage of the application (in bytes).
	 */
	public function memory_used(){
        //might be disabled
		if(function_exists('memory_get_usage')){
			return memory_get_usage();
        }
        else{
			$output=array();
			if(strncmp(PHP_OS,'WIN',3)===0){
				exec('tasklist /FI "PID eq ' . getmypid() . '" /FO LIST',$output);
				return isset($output[5])?preg_replace('/[\D]/','',$output[5])*1024 : 0;
			}
			else{
				$pid=getmypid();
				exec("ps -eo%mem,rss,pid | grep $pid", $output);
				$output=explode("  ",$output[0]);
				return isset($output[1]) ? $output[1]*1024 : 0;
			}
		}
	}

    //---------------------- log & profile formatting ----------------------

    /**
     * Format a single log message
     * Example formatted message:
     * <code>2009-6-22 15:21:30 [INFO (6)] [application] User johnny has logined from 60.30.142.85</code>
     * @param string $msg Log message
     * @param int $level Log level
     * @param string $category
     * @param float $time Time used in second
     * @return string A formatted log message
     */
	protected function formatLog($msg,$level,$category,$time){
		return date('Y-m-d H:i:s',$time)." Access from {$_SERVER['REQUEST_URI']} [{$this->_levels[$level]} ($level)] [$category] $msg\n";
	}

    /**
     * Format a single log message
     * Example formatted message:
     * <code>2009/6/22 15:21:30 [INFO (6)] [application] User johnny has logined from 60.30.142.85</code>
     * @param string $msg Log message
     * @param int $level Log level
     * @param string $category
     * @param float $time Time used in second
     * @return string A formatted log message
     */
	protected function formatLogXML($msg,$level,$category,$time){
		return "<log><date>".date('Y-m-d H:i:s',$time)."</date><access_uri><![CDATA[{$_SERVER['REQUEST_URI']}]]></access_uri><level id=\"$level\">{$this->_levels[$level]}</level><category>$category</category><msg><![CDATA[$msg]]></msg></log>\n";
	}


    /**
     * Format a single database log message
     * Example formatted message:
     * <code>[application] # 2009-6-22 15:21:30 Access from /admin/product/edit/18 [time used: 0.002354] [memory used: 1902]
     * SELECT * FROM products</code>
     * @param string $msg_id Profile id
     * @param string $sql SQL of the executed query profiled
     * @param int $time Timestamp where the log was recorded
     * @param float $timeuse Time used in second
     * @param int $memory Memory used in bytes
     * @return string A formatted log message
     */
	protected function formatDbLog($msg_id, $sql,$time, $timeuse, $memory){
		return "[$msg_id] # " . date('Y-m-d H:i:s',$time)." Access from {$_SERVER['REQUEST_URI']}  [time used: {$timeuse}] [memory used: {$memory}]\n$sql\n";
	}


    /**
     * Format a single database log message in XML
     * @param string $sql SQL of the executed query profiled
     * @param int $time Timestamp where the log was recorded
     * @param float $timeuse Time used in second
     * @param int $memory Memory used in bytes
     * @return string A formatted log message
     */
	protected function formatDbLogXML($msg_id, $sql,$time, $timeuse, $memory){
		return "<dbprofile><id>$msg_id</id><date>" . date('Y-m-d H:i:s',$time) .'</date>' ."<access_uri><![CDATA[{$_SERVER['REQUEST_URI']}]]></access_uri><duration>{$timeuse}</duration><memory>{$memory}</memory><sql><![CDATA[$sql]]></sql></dbprofile>\n";
	}

    /**
     * Format a single profile message in XML
     * @param string $msg_id Profile id
     * @param string $category Category
     * @param int $time Timestamp where the log was recorded
     * @param float $timeuse Time used in second
     * @param int $memory Memory used in bytes
     * @return string A formatted log message
     */
	protected function formatProfileXML($msg_id, $category, $time, $timeuse, $memory){
		return "<profile><id>$msg_id</id><category>$category</category><date>" . date('Y-m-d H:i:s',$time) .'</date>' ."<access_uri><![CDATA[{$_SERVER['REQUEST_URI']}]]></access_uri><duration>{$timeuse}</duration><memory>{$memory}</memory></profile>\n";
	}

    /**
     * Format a single profile message
     * Example formatted message:
     * <code>[application] # 2009-6-22 15:21:30 Access from /admin/product/edit/18 [time used: 0.002354] [memory used: 1902]</code>
     * @param string $msg_id Profile id
     * @param string $category Category
     * @param int $time Timestamp where the log was recorded
     * @param float $timeuse Time used in second
     * @param int $memory Memory used in bytes
     * @return string A formatted log message
     */
	protected function formatProfile($msg_id, $category, $time, $timeuse, $memory){
		return "[$category][$msg_id] # " . date('Y-m-d H:i:s',$time)." Access from {$_SERVER['REQUEST_URI']}  [time used: {$timeuse}] [memory used: {$memory}]\n$sql\n";
	}

}
