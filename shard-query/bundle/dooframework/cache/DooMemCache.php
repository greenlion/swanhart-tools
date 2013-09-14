<?php
/**
 * DooMemCache class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooMemCache provides caching methods utilizing the Memcache extension.
 *
 * If you have multiple servers for memcache, you would have to set it up in common.conf.php
 * <code>
 * // host, port, persistent, weight
 * $config['MEMCACHE'] = array(
 *                       array('192.168.1.31', '11211', true, 40),
 *                       array('192.168.1.23', '11211', true, 80)
 *                     );
 * </code>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooMemCache.php 1000 2009-08-22 19:36:10
 * @package doo.cache
 * @since 1.1
 */

class DooMemCache{
    /**
     * Memcached connection
     * @var Memcache
     */
    protected $_memcache;

    /**
     * Configurations of the connections
     * @var array
     */
    protected $_config;

    public function  __construct($conf=Null) {
        $this->_memcache = new Memcache();
        $this->_config = $conf;

        // host, port, persistent, weight
        if($conf!==Null){
            foreach ($conf as $c){
                $result = $this->_memcache->addServer($c[0], $c[1], $c[2], $c[3]);
            }
        }
        else{
            $this->_memcache->addServer('localhost', 11211);
        }
    }

    /**
     * Adds a cache with an unique Id.
     *
     * @param string $id Cache Id
     * @param mixed $data Data to be stored
     * @param int $expire Seconds to expired
     * @param int $compressed To store the data in Zlib compressed format
     * @return bool True if success
     */
    public function set($id, $data, $expire=0, $compressed=false){
        if($compressed)
            return $this->_memcache->set($id, $data, MEMCACHE_COMPRESSED, $expire);
        else
            return $this->_memcache->set($id, $data, 0, $expire);
    }

    /**
     * Retrieves a value from cache with an Id.
     *
     * @param string $id A unique key identifying the cache
     * @return mixed The value stored in cache. Return false if no cache found or already expired.
     */
    public function get($id){
        return $this->_memcache->get($id);
    }
    
    /**
     * Deletes an APC data cache with an identifying Id
     *
     * @param string $id Id of the cache
     * @return bool True if success
     */
    public function flush($id){
        return $this->_memcache->delete($id);
    }

    /**
     * Deletes all data cache
     * @return bool True if success
     */
    public function flushAll(){
        return $this->_memcache->flush();
    }

}

