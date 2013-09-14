<?php

/**
 * DooBechmark class file.
 * @package doo.helper
 * @author Zohaib Sibt-e-Hassan <zohaib.hassan@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Zohaib Sibt-e-Hassan
 * @license http://www.doophp.com/license
 */

/**
* DooBenchmark class, for benchmarking memory and time of particular code segment
*
* @author Zohaib Sibt-e-Hassan <zohaib.hassan@gmail.com>
* @copyright &copy; 2009 Zohaib Sibt-e-Hassan
* @license http://www.doophp.com/license
*/

class DooBenchmark {
    /**
    * Associative array of marked label as key and value of microtime
    * 
    * @var array
    */
    protected static $timeArr = array();
    
    /**
    * Associative array of marked label as key and value of memory used
    * 
    * @var array
    */
    protected static $memoArr = array();
    
    /**
    * Associative array of saved points that can be fetched later on and displayed
    * Keys are mark names and values are objects carrying the 
    * @var array
    */
    
    protected static $savedPoints = array();
    
    /**
    * Mark current position with given name
    * 
    * @param string $name
    */
    
    public static function mark($name){
        self::$timeArr[$name] = microtime();
        self::$memoArr[$name] = self::getMemUsage();
    }
    
    /**
    * Finish the bechmarking process and return the total memory
    * and time consumed as obj->time and obj->memory
    * 
    * @param string $name
    * @param int $decimals
    * @return Object (time and memory containing elapsed values)
    */
    public static function get($name){
        list($startMic, $startSec) = explode(' ', self::$timeArr[$name]);
        list($endMic, $endSec) = explode(' ', microtime());
        
        $mem = null;
        if(isset(self::$memoArr[$name]) && self::$memoArr[$name])
            $mem = self::getMemUsage() - self::$memoArr[$name];
        
        $tm = (float)($endMic + $endSec) - (float)($startMic + $startSec);
        
        $ret=new DooBenchmark();
        $ret->memory = $mem;
        $ret->time = $tm;
        
        return $ret;
    }
    
    /**
    * Retrieve memory usage. Use memory_get_usage() if available.
    * 
    * @return false|int Bytes of memory consumed. Returns False if failed to retrieve memory usage
    */
    protected static function getMemUsage(){
        $mmus = false;
        if(function_exists('memory_get_usage')){
            $mmus =  memory_get_usage();
        }
        else{
            $output=array();
            if(strncmp(PHP_OS,'WIN',3)===0){
                exec('tasklist /FI "PID eq ' . getmypid() . '" /FO LIST',$output);
                $mmus =  isset($output[5])?preg_replace('/[\D]/','',$output[5])*1024 : 0;
            }
            else{
                $pid=getmypid();
                exec("ps -eo%mem,rss,pid | grep $pid", $output);
                $output=explode("  ",$output[0]);
                $mmus =  isset($output[1]) ? $output[1]*1024 : 0;
            }
        }
        
        return $mmus;
    }
    
    /**
    * Save all the unsaved benchmarks
    * This subroutine will look up for any benchmarks that were marked with DooBenchmark::mark
    * And for all unsaved 
    * 
    * @param int $decimals number of decimal precisions as place
    */
    
    public static function saveAllMarks(){
        list($em, $es) = explode(' ', microtime());
		
		$mmus = self::getMemUsage();
        
        foreach(self::$memoArr as $k => &$val){
            if( !isset(self::$savedPoints[$k]) ){
                list($sm, $ss) = explode(' ', self::$timeArr[$k]);
                $tm = (float)($em + $es) - (float)($sm + $ss);
                
                $mem = null;
                if(isset(self::$memoArr[$k]) && self::$memoArr[$k])
                    $mem =  $mmus - self::$memoArr[$k];
                
                $ret=new DooBenchmark();
                $ret->memory = $mem;
                $ret->time = $tm;
                
                self::$savedPoints[$k] = $ret;
                
            }
        }
    }
    
    /**
    * Save current bechmark associateed with its name
    * 
    * @param string $name name of bencmark item marked with mark function previously
    */
    
    public static function saveMark($name){
        self::$savedPoints[$name] = self::get($name, $decimal);
    }
    
    /**
    * Get all the saved points
    * 
    */
    
    public static function getAll(){
        return self::$savedPoints;
    }
}
