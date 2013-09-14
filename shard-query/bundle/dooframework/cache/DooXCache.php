<?php
/**
 * DooXCache class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooXCache provides caching methods utilizing XCache.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooXCache.php 1000 2009-08-22 19:36:10
 * @package doo.cache
 * @since 1.1
 */

class DooXCache{

    /**
     * Adds a cache with an unique Id.
     *
     * @param string $id Cache Id
     * @param mixed $data Data to be stored
     * @param int $expire Seconds to expired
     * @return bool True if success
     */
    public function set($id, $data, $expire=0){
        return xcache_set($id, $data, $expire);
    }

    /**
     * Retrieves a value from cache with an Id.
     *
     * @param string $id A unique key identifying the cache
     * @return mixed The value stored in cache. Return false if no cache found or already expired.
     */
    public function get($id){
        return xcache_get($id);
    }

    /**
     * Deletes an data cache with an identifying Id
     *
     * @param string $id Id of the cache
     * @return bool True if success
     */
    public function flush($id){
        return xcache_unset($id);
    }

    /**
     * Deletes all data cache
     * @return bool True if success
     */
    public function flushAll(){
        return xcache_clear_cache();
    }

}

