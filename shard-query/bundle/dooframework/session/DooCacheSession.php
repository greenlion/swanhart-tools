<?php
/**
 * DooCacheSession class file.
 *
 * @author Zohaib Sibt-e-Hassan <zohaib.hassan@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Zohaib Sibt-e-Hassan
 * @license http://www.doophp.com/license
 */

/**
* DooCacheSession, manage session inside cache system instead of
* default php session system, extremely useful when we need extreme
* performance and combined with memcache
* Create your required cache system, pass the cache object to the DooCacheSession::installOnCache, example:
* <code>
* $cache = Doo::cache('memcache');
* DooCacheSession::insallOnCache($cache, 'myPrefixForSessionID');
* $session = Doo::session("mysite");
* </code>
* After that use session variables normally and transparently ;)
* Add something to session
* <code>$session->someVariable = "something";</code>
* Get that variable
* <code>
* $var = $session=>get("someVariable"); (returns "something")
* </code>
*
* @author Zohaib Sibt-e-Hassan <zohaib.hassan@gmail.com>
* @copyright &copy; 2009 Zohaib Sibt-e-Hassan
* @license http://www.doophp.com/license
* @package doo.session
* @since 1.3
*/

class DooCacheSession
{
	/**
     * Session lifetime
     */
    protected static $lifetime = 0;
	
	/**
     * Session ID prefix
     */
	protected static $sessionPrefix = 'DooCS';
	
	/**
     * Cache object
     */
	protected static $cache = null;
	
	/**
	* Open session system
	*
	* @return boolean true
	*/
    public static function open()
    {
		return true;
    }
	
	/**
	* Read session data against given $id
	*
	* @return mixed false if id was not found in session system; string data otherwise
	*/
    public static function read($id)
    {
		$pfx = self::$sessionPrefix;
        return self::$cache->get("{$pfx}{$id}");
    }
	
	/**
	* Write given session $data again given session $id
	*
	* @return boolean true if value was session was written successfully
	*/
    public static function write($id, $data)
    {
		$pfx = self::$sessionPrefix;
        return self::$cache->set("{$pfx}{$id}", $data, self::$lifetime);
    }

	/**
	* Destroy given session $id from cache
	*
	* @return boolean true if key was deleted, otherwise false
	*/
    public static function destroy($id)
    {
		$pfx = self::$sessionPrefix;
        return self::$cache->flush("{$pfx}{$id}");
    }
	
	/**
	* Garbage collect always returns true; since cache systems automatically expire out keys
	*
	* @return boolean true
	*/
    public static function gc(){ return true; }
	
	/**
	* Close session system
	*
	* @return boolean true
	*/
    public static function close(){ return true; }
	
	/**
	* Install session managment on given $cacheSystem, with given $prefix
	*
	* @return boolean status of installtaion
	*/
	public static function installOnCache(&$cacheSystem, $prefix=null){
		ini_set('session.save_handler', 'user');
		self::$lifetime = ini_get('session.gc_maxlifetime');
				
		self::$cache = $cacheSystem;
		if(is_string($prefix))
			self::$sessionPrefix = $prefix;
			
		return session_set_save_handler(
				array(__CLASS__,"open"),
				array(__CLASS__,"close"),
				array(__CLASS__,"read"),
				array(__CLASS__,"write"),
				array(__CLASS__,"destroy"),
				array(__CLASS__,"gc")
				);
	}
}
