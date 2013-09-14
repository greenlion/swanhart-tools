<?php
/**
 * DooEAcceleratorCache class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooEAcceleratorCache provides caching methods utilizing the EAccelerator extension.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooEAcceleratorCache.php 1000 2009-08-22 19:36:10
 * @package doo.cache
 * @since 1.1
 */

class DooEAcceleratorCache{

    /**
     * Adds a cache with an unique Id.
     *
     * @param string $id Cache Id
     * @param mixed $data Data to be stored
     * @param int $expire Seconds to expired
     * @return bool True if success
     */
    public function set($id, $data, $expire=0){
        return eaccelerator_put($id, $data, $expire);
    }

    /**
     * Retrieves a value from cache with an Id.
     *
     * @param string $id A unique key identifying the cache
     * @return mixed The value stored in cache. Return false if no cache found or already expired.
     */
    public function get($id){
        return eaccelerator_get($id);
    }

    /**
     * Deletes an EAccelerator data cache with an identifying Id
     *
     * @param string $id Id of the cache
     * @return bool True if success
     */
    public function flush($id){
        return eaccelerator_rm($id);
    }

    /**
     * Deletes all EAccelerator data cache
     */
    public function flushAll(){
        //delete expired content then delete all
        eaccelerator_gc();

        $idkeys = eaccelerator_list_keys();

        foreach($idkeys as $k)
            $this->flush(substr($k['name'], 1));
    }

}
