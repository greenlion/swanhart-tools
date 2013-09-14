<?php
/**
 * DooFileCache class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooFileCache provides file based caching methods.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooFileCache.php 1000 2009-08-27 19:36:10
 * @package doo.cache
 * @since 1.1
 */

class DooFileCache {

    private $_directory;

    /**
     * Option to hash the Cache ID into md5 hash string
     * @var bool
     */
    public $hashing = true;

    public function __construct($path='') {
        if ( $path=='' ) {
            if(isset(Doo::conf()->CACHE_PATH))
                $this->_directory = Doo::conf()->CACHE_PATH;
            else
                $this->_directory = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'cache/';
        }else{
            $this->_directory = $path;
        }
    }

    /**
     * Retrieves a value from cache with an Id.
     *
     * @param string $id A unique key identifying the cache
     * @return mixed The value stored in cache. Return null if no cache found or already expired.
     */
    public function get($id) {
        if($this->hashing===true)
            $cfile = $this->_directory . md5($id);
        else
            $cfile = $this->_directory . $id;

        if (file_exists($cfile)){
            $data = file_get_contents($cfile) ;
            $expire = substr($data, 0, 10);

            if(time() < $expire){
                return unserialize(substr($data, 10));
            }else{
                unlink($cfile);
            }
        }
    }


    /**
     * Retrieves a value from cache with an Id from different directories
     *
     * @param string $folder Directory name for the cache files stored
     * @param string $id A unique key identifying the cache
     * @return mixed The value stored in cache. Return null if no cache found or already expired.
     */
    public function getIn($folder, $id) {
        if($this->hashing===true)
            $cfile = $this->_directory . $folder .'/'. md5($id);
        else
            $cfile = $this->_directory . $folder .'/'. $id;

        if (file_exists($cfile)){
            $data = file_get_contents($cfile) ;
            $expire = substr($data, 0, 10);

            if(time() < $expire){
                return unserialize(substr($data, 10));
            }else{
                unlink($cfile);
            }
        }
    }

     /**
      * Adds a cache with an unique Id.
      *
      * @param string $id Unique Id of the cache
      * @param mixed $value Cache data value to be stored.
      * @param int $expire Duration to determine if the cache is expired. 0 for never expire
      * @return bool
      */
    public function set($id, $value, $expire=0) {
        if($expire===0)
            $expire = time()+31536000;
        else
            $expire = time()+$expire;

        if($this->hashing===true)
            return file_put_contents($this->_directory . md5($id) , $expire.serialize($value), LOCK_EX);
            
        return file_put_contents($this->_directory . $id , $expire.serialize($value), LOCK_EX);
    }

    /**
     * Store cache in different directories
     *
     * @param string $folder Directory name for the cache files to be created and stored
     * @param string $id Unique Id of the cache
     * @param mixed $value Cache data value to be stored.
     * @param int $expire Duration to determine if the cache is expired. 0 for never expire
     * @return bool
     */
    public function setIn($folder, $id, $value, $expire=0) {
        $cfile = $this->_directory.$folder.'/';

        if(!file_exists($cfile))
            mkdir($cfile);

        if($this->hashing===true)
            $cfile .= md5($id);
        else
            $cfile .= $id;

        if($expire===0)
            $expire = time()+31536000;
        else
            $expire = time()+$expire;
        return file_put_contents($cfile, $expire.serialize($value), LOCK_EX);
    }

    /**
     * Delete a cache file by Id
     * @param $id Id of the cache
     * @return mixed
     */
    public function flush($id) {
        if($this->hashing===true)
            $cfile = $this->_directory.md5($id);
        else
            $cfile = $this->_directory.$id;

        if (file_exists($cfile)) {
            unlink($cfile);
            return true;
        }
        return false;
    }

    /**
     * Deletes all data cache files
     * @return bool
     */
    public function flushAll() {
        $handle = opendir($this->_directory);

        while(($file = readdir($handle)) !== false) {
            if (is_file($this->_directory . $file))
                unlink($this->_directory . $file);
            else if (is_dir($this->_directory . $file) && substr($file, 0, 4) == 'mdl_')
                $this->flushAllIn($file);	
        }
        return true;
    }

    /**
     * Deletes all data cache in a folder
     * @param string $folder
     */
	public function flushAllIn($folder){
        $cfile = $this->_directory.$folder.'/';
        if(file_exists($cfile)){
            $handle = opendir($cfile);
            while(($file = readdir($handle)) !== false) {
                $file = $cfile.$file;
                if (is_file($file)){
                    unlink( $file );
                }
            }
        }
    }

    /**
     * Deletes a data cache in a folder identified by an ID
     * @param string $folder
     * @param string $id
     */
    public function flushIn($folder, $id){
        if($this->hashing===true)
            $cfile = $this->_directory.$folder.'/'.md5($id);
        else
            $cfile = $this->_directory.$folder.'/'.$id;

        if(file_exists($cfile)){
            unlink( $file );
        }
    }

}
