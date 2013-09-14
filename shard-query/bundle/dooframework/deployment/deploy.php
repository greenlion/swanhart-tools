<?php
/**
 * Doo class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 * @version $Id: DooWebApp.php 1000 2009-06-22 18:27:22
 * @package doo
 * @since 1.0
 */

/**
 * Doo is a singleton class serving common framework functionalities.
 *
 * You can access Doo in every class to retrieve configuration settings,
 * DB connections, application properties, logging, loader utilities and etc.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: Doo.php 1000 2009-07-7 18:27:22
 * @package doo
 * @since 1.0
 */
class Doo{
    protected static $_app;
    protected static $_conf;
    protected static $_logger;
    protected static $_db;
    protected static $_useDbReplicate;
    protected static $_cache;
    protected static $_acl;
	protected static $_session;
	protected static $_translator;
    protected static $_globalApps;

    /**
     * @return DooConfig configuration settings defined in <i>common.conf.php</i>, auto create if the singleton has not been created yet.
     */
    public static function conf(){
        if(self::$_conf===NULL){
            self::$_conf = new DooConfig;
        }
        return self::$_conf;
    }
    
    /**
     * Set the list of Doo applications. 
     * <code>
     * //by default, Doo::loadModelFromApp() will load from this application path
     * $apps['default'] = '/var/path/to/shared/app/'
     * $apps['app2'] = '/var/path/to/shared/app2/'
     * $apps['app3'] = '/var/path/to/shared/app3/' 
     * </code>
     * @param array $apps 
     */
    public static function setGlobalApps($apps){
        self::$_globalApps = $apps;
    }
    
    /**
     * Imports the definition of Model class(es) from a Doo application
     * @param string|array $modelName Name(s) of the Model class to be imported
     * @param string $appName Name of the application to be loaded from
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadModelFromApp($modelName, $appName='default', $createObj=false){
        return self::load($modelName, self::$_globalApps[$appName] . 'model/', $createObj);
    }
    
    /**
     * Imports the definition of User defined class(es) from a Doo application
     * @param string|array $className Name(s) of the Model class to be imported
     * @param string $appName Name of the application to be loaded from
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadClassFromApp($className, $appName='default', $createObj=false){
        return self::load($className, self::$_globalApps[$appName] . 'class/', $createObj);
    }
    
    /**
     * Imports the definition of Controller class from a Doo application
     * @param string $class_name Name of the class to be imported
     */
    public static function loadControllerFromApp($controllerName, $appName='default'){
        return self::load($controllerName, self::$_globalApps[$appName] . 'controller/');
    }

    /**
	 * @param string $appType The type of application you want. Options are: 'DooWebApp' and 'DooCliApp'
     * @return DooWebApp|DooCliApp the application singleton, auto create if the singleton has not been created yet.
     */
    public static function app($appType='DooWebApp'){
        if(self::$_app===NULL){
            self::loadCore('app/' . $appType);
            self::$_app = new $appType();
        }
        return self::$_app;
    }

    /**
	 * @param string $class the class to use for ACL. Can be DooAcl or DooRbAcl
     * @return DooAcl|DooRbAcl the application ACL singleton, auto create if the singleton has not been created yet.
     */
    public static function acl($class = 'DooAcl'){
        if(self::$_acl===NULL){
            self::loadCore('auth/' . $class);
            self::$_acl = new $class;
        }
        return self::$_acl;
    }

    /**
     * Call this method to use database replication instead of a single db server.
     */
    public static function useDbReplicate(){
        self::$_useDbReplicate = true;
    }

    /**
     * @return DooSqlMagic the database singleton, auto create if the singleton has not been created yet.
     */
    public static function db(){
        if(self::$_db===NULL){
            if(self::$_useDbReplicate===NULL){
                self::loadCore('db/DooSqlMagic');
                self::$_db = new DooSqlMagic;
            }else{
                self::loadCore('db/DooMasterSlave');
                self::$_db = new DooMasterSlave;
            }
        }

        if(!self::$_db->connected)
            self::$_db->connect();

        return self::$_db;
    }

    /**
     * @return DooSession
     */
    public static function session($namespace = null){
        if(self::$_session===NULL){
            self::loadCore('session/DooSession');
            self::$_session = new DooSession($namespace);
        }
        return self::$_session;
    }
	
	/**
     * @return true/false according to cache system being installed
     */
    public static function cacheSession($prefix = 'dooSession/', $type='file'){
		$cache = self::cache($type);
		self::loadCore('session/DooCacheSession');
		return DooCacheSession::installOnCache($cache, $prefix);
    }

	 /**
	  * @return DooTranslator
	  */
    public static function translator($adapter, $data, $options=array()) {
        if(self::$_translator===NULL){
            self::loadCore('translate/DooTranslator');
            self::$_translator = new DooTranslator($adapter, $data, $options);
        }
        return self::$_translator;
    }

	/**
	 * Simple accessor to Doo Translator class. You must be sure you have initialised it before calling. See translator(...)
	 * @return DooTranslator
	 */
	public static function getTranslator() {
		return self::$_translator;
	}

    /**
     * @return DooLog logging tool for logging, tracing and profiling, singleton, auto create if the singleton has not been created yet.
     */
    public static function logger(){
        if(self::$_logger===NULL){
            self::loadCore('logging/DooLog');
            self::$_logger = new DooLog(self::conf()->DEBUG_ENABLED);
        }
        return self::$_logger;
    }

    /**
     * @param string $cacheType Cache type: file, php, front, apc, memcache, xcache, eaccelerator. Default is file based cache.
     * @return DooFileCache|DooPhpCache|DooFrontCache|DooApcCache|DooMemCache|DooXCache|DooEAcceleratorCache file/php/apc/memcache/xcache/eaccelerator & frontend caching tool, singleton, auto create if the singleton has not been created yet.
     */
    public static function cache($cacheType='file') {
        if($cacheType=='file'){
            if(isset(self::$_cache['file']))
                return self::$_cache['file'];

            self::loadCore('cache/DooFileCache');
            self::$_cache['file'] = new DooFileCache;
            return self::$_cache['file'];
        }
        else if($cacheType=='php'){
            if(isset(self::$_cache['php']))
                return self::$_cache['php'];

            self::loadCore('cache/DooPhpCache');
            self::$_cache['php'] = new DooPhpCache;
            return self::$_cache['php'];
        }
        else if($cacheType=='front'){
            if(isset(self::$_cache['front']))
                return self::$_cache['front'];

            self::loadCore('cache/DooFrontCache');
            self::$_cache['front'] = new DooFrontCache;
            return self::$_cache['front'];
        }
        else if($cacheType=='apc'){
            if(isset(self::$_cache['apc']))
                return self::$_cache['apc'];

            self::loadCore('cache/DooApcCache');
            self::$_cache['apc'] = new DooApcCache;
            return self::$_cache['apc'];
        }
        else if($cacheType=='xcache'){
            if(isset(self::$_cache['xcache']))
                return self::$_cache['xcache'];

            self::loadCore('cache/DooXCache');
            self::$_cache['xcache'] = new DooXCache;
            return self::$_cache['xcache'];
        }
        else if($cacheType=='eaccelerator'){
            if(isset(self::$_cache['eaccelerator']))
                return self::$_cache['eaccelerator'];

            self::loadCore('cache/DooEAcceleratorCache');
            self::$_cache['eaccelerator'] = new DooEAcceleratorCache;
            return self::$_cache['eaccelerator'];
        }
        else if($cacheType=='memcache'){
            if(isset(self::$_cache['memcache']))
                return self::$_cache['memcache'];

            self::loadCore('cache/DooMemCache');
            self::$_cache['memcache'] = new DooMemCache(Doo::conf()->MEMCACHE);
            return self::$_cache['memcache'];
        }
    }

    /**
     * Imports the definition of class(es) and tries to create an object/a list of objects of the class.
     * @param string|array $class_name Name(s) of the class to be imported
     * @param string $path Path to the class file
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object of the class name passed in.
     */
    protected static function load($class_name, $path, $createObj=FALSE){
        if(is_string($class_name)===True){
			$pure_class_name = basename($class_name);
            class_exists($pure_class_name, false)===True || require_once($path . "$class_name.php");
            if($createObj)
                return new $pure_class_name;
        }else if(is_array($class_name)===True){
            //if not string, then a list of Class name, require them all.
            //make sure the class_name has array with is_array
            if($createObj)
                $obj=array();

            foreach ($class_name as $one) {
				$pure_class_name = basename($one);
                class_exists($pure_class_name, false)===True || require_once($path . "$one.php");
                if($createObj)
                    $obj[] = new $pure_class_name;
            }

            if($createObj)
                return $obj;
        }
    }

    /**
     * Imports the definition of User defined class(es). Class file is located at <b>SITE_PATH/protected/class/</b>
     * @param string|array $class_name Name(s) of the class to be imported
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadClass($class_name, $createObj=FALSE){
        return self::load($class_name, self::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "class/", $createObj);
    }

    /**
     * Imports the definition of Controller class. Class file is located at <b>SITE_PATH/protected/controller/</b>
     * @param string $class_name Name of the class to be imported
     */
    public static function loadController($class_name){
		return self::load($class_name, self::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'controller/', false);
    }

    /**
     * Imports the definition of Model class(es). Class file is located at <b>SITE_PATH/protected/model/</b>
     * @param string|array $class_name Name(s) of the Model class to be imported
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadModel($class_name, $createObj=FALSE){
        return self::load($class_name, self::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'model/', $createObj);
    }

    /**
     * Imports the definition of Helper class(es). Class file is located at <b>BASE_PATH/protected/helper/</b>
     * @param string|array $class_name Name(s) of the Helper class to be imported
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadHelper($class_name, $createObj=FALSE){
        return self::load($class_name, self::conf()->BASE_PATH ."helper/", $createObj);
    }

    /**
     * Imports the definition of Doo framework core class. Class file is located at <b>BASE_PATH</b>.
     * @example If the file is in a package, called <code>loadCore('auth/DooLog')</code>
     * @param string $class_name Name of the class to be imported
     */
    public static function loadCore($class_name){
        require_once self::conf()->BASE_PATH ."$class_name.php";
    }

    /**
     * Imports the definition of Model class(es) in a certain module or from the main app.
     *
     * @param string|array $class_name Name(s) of the Model class to be imported
     * @param string $path module folder name. Default is the main app folder.
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadModelAt($class_name, $moduleFolder=Null, $createObj=FALSE){
        if($moduleFolder===null){
            $moduleFolder = Doo::getAppPath();
        }else{
            $moduleFolder = Doo::getAppPath() . 'module/' . $moduleFolder;            
        }
        return self::load($class_name, $moduleFolder . "/model/", $createObj);
    }

    /**
     * Imports the definition of Controller class(es) in a certain module or from the main app.
     *
     * @param string|array $class_name Name(s) of the Controller class to be imported
     * @param string $path module folder name. Default is the main app folder.
     */
    public static function loadControllerAt($class_name, $moduleFolder=Null){
        if($moduleFolder===null){
            $moduleFolder = Doo::getAppPath();
        }else{
            $moduleFolder = Doo::getAppPath() . 'module/' . $moduleFolder;            
        }        
		require_once $moduleFolder . '/controller/'.$class_name.'.php';
    }

    /**
     * Imports the definition of User defined class(es) in a certain module or from the main app.
     *
     * @param string|array $class_name Name(s) of the class to be imported
     * @param string $path module folder name. Default is the main app folder.
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadClassAt($class_name, $moduleFolder=Null, $createObj=FALSE){
        if($moduleFolder===null){
            $moduleFolder = Doo::getAppPath();
        }else{
            $moduleFolder = Doo::getAppPath() . 'module/' . $moduleFolder;            
        }
        return self::load($class_name, $moduleFolder. "/class/", $createObj);
    }

    /**
     * Loads template tag class from plugin directory for both main app and modules
     * 
     * @param string $class_name Template tag class name
     * @param string $moduleFolder Folder name of the module. If Null, the class will be loaded from main app.
     */
    public static function loadPlugin($class_name, $moduleFolder=Null){
        if($moduleFolder===null){
            require_once Doo::getAppPath() . 'plugin/'. $class_name .'.php';
        }else{
            require_once Doo::getAppPath() .'module/'. $moduleFolder .'/plugin/'. $class_name .'.php';
        }
    }
	
    /**
     * Provides auto loading feature. To be used with the Magic method __autoload
     * @param string $classname Class name to be loaded.
     */
    public static function autoload($classname){
//        if( class_exists($classname, false) === true )
//			return;
        
        //app
		$class['DooConfig']      = 'app/DooConfig';
		$class['DooSiteMagic']   = 'app/DooSiteMagic';
		$class['DooWebApp']      = 'app/DooWebApp';
        
        //auth
		$class['DooAcl']         = 'auth/DooAcl';
		$class['DooAuth']        = 'auth/DooAuth';
		$class['DooDigestAuth']  = 'auth/DooDigestAuth';
		$class['DooRbAcl']       = 'auth/DooRbAcl';    
        
        //cache
		$class['DooApcCache']            = 'cache/DooApcCache';
		$class['DooEAcceleratorCache']   = 'cache/DooEAcceleratorCache';
		$class['DooFileCache']           = 'cache/DooFileCache';
		$class['DooFrontCache']          = 'cache/DooFrontCache';
		$class['DooMemCache']            = 'cache/DooMemCache';
		$class['DooPhpCache']            = 'cache/DooPhpCache';
		$class['DooXCache']              = 'cache/DooXCache';
            
        //controller
		$class['DooController'] = 'controller/DooController';
        
        //db
		$class['DooDbExpression']    = 'db/DooDbExpression';
		$class['DooMasterSlave']     = 'db/DooMasterSlave';
		$class['DooModel']           = 'db/DooModel';
		$class['DooModelGen']        = 'db/DooModelGen';
		$class['DooSmartModel']      = 'db/DooSmartModel';
		$class['DooSqlMagic']        = 'db/DooSqlMagic';
        
        //db/manage
		$class['DooDbUpdater']       = 'db/manage/DooDbUpdater';
		$class['DooManageDb']        = 'db/manage/DooManageDb';
		$class['DooManageMySqlDb']   = 'db/manage/adapters/DooManageMySqlDb';
		$class['DooManagePgSqlDb']   = 'db/manage/adapters/DooManagePgSqlDb';
		$class['DooManageSqliteDb']  = 'db/manage/adapters/DooManageSqliteDb';
        
        //helper
		$class['DooBenchmark']       = 'helper/DooBenchmark';
		$class['DooFile']            = 'helper/DooFile';
		$class['DooFlashMessenger']  = 'helper/DooFlashMessenger';
		$class['DooForm']            = 'helper/DooForm';
		$class['DooGdImage']         = 'helper/DooGdImage';
		$class['DooMailer']          = 'helper/DooMailer';
		$class['DooPager']           = 'helper/DooPager';
		$class['DooRestClient']      = 'helper/DooRestClient';
		$class['DooTextHelper']      = 'helper/DooTextHelper';
		$class['DooTimezone']        = 'helper/DooTimezone';
		$class['DooUrlBuilder']      = 'helper/DooUrlBuilder';
		$class['DooValidator']       = 'helper/DooValidator';
        
        //logging
		$class['DooLog'] = 'logging/DooLog';
        
        //session
		$class['DooCacheSession'] = 'session/DooCacheSession';
		$class['DooSession']      = 'session/DooSession';      
        
        //translate
		$class['DooTranslator'] = 'translate/DooTranslator';
        
        //uri
		$class['DooLoader'] = 'uri/DooLoader';
		$class['DooUriRouter'] = 'uri/DooUriRouter';
        
        //view
		$class['DooView'] = 'uri/DooView';
		$class['DooViewBasic'] = 'uri/DooViewBasic';
        
        if(isset($class[$classname]))
            self::loadCore($class[$classname]);
        else{ 
            if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true){
                $path = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER_ORI;
            }else{
                $path = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER;                            
            }
            
            if(empty(Doo::conf()->AUTOLOAD)===false){
                if(Doo::conf()->APP_MODE=='dev'){
                    $includeSub = Doo::conf()->AUTOLOAD;
                    $rs = array();
                    foreach($includeSub as $sub){
                        if(file_exists($sub)===false){     
                            if(file_exists($path. $sub)===true){
                                $rs = array_merge($rs, DooFile::getFilePathList($path. $sub . '/') );                
                            }
                        }else{
                            $rs = array_merge($rs, DooFile::getFilePathList( $sub . '/') );                
                        }
                    }

                    $autoloadConfigFolder = $path . 'config/autoload/';

                    $rsExisting = null;

                    if(file_exists($autoloadConfigFolder.'autoload.php')===true){
                        $rsExisting = include($autoloadConfigFolder.'autoload.php');
                    }

                    if($rs != $rsExisting){
                        if(!file_exists($autoloadConfigFolder)){
                            mkdir($autoloadConfigFolder);
                        }
                        file_put_contents($autoloadConfigFolder.'autoload.php', '<?php return '.var_export($rs, true) . ';');                    
                    }                                
                }
                else{
                    $rs = include_once($path . 'config/autoload/autoload.php');
                }

                if( isset($rs[$classname . '.php'])===true ){
                    require_once $rs[$classname . '.php'];
                    return;
                }
            }            
            
            //autoloading namespaced class                
            if(isset(Doo::conf()->APP_NAMESPACE_ID)===true && strpos($classname, '\\')!==false){
                $pos = strpos($classname, Doo::conf()->APP_NAMESPACE_ID);
                if($pos===0){
                    $classname = str_replace('\\','/',substr($classname, strlen(Doo::conf()->APP_NAMESPACE_ID)+1));
                    require_once $path . $classname . '.php';
                }
            }
        }
    }
    
    /**
     * Get the path where the Application source is located.
     * @return string
     */
    public static function getAppPath(){
        if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true){
            return Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER_ORI;
        }else{
            return Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER;                            
        }        
    }

    /**
     * Simple benchmarking. To used this, set <code>$config['START_TIME'] = microtime(true);</code> in <i>common.conf.php</i> .
     * @param bool $html To return the duration as string in HTML comment.
     * @return mixed Duration(sec) of the benchmarked process. If $html is True, returns string <!-- Generated in 0.002456 seconds -->
     */
    public static function benchmark($html=false){
        if(!isset(self::conf()->START_TIME)){
            return 0;
        }
        $duration = microtime(true) - self::conf()->START_TIME;
        if($html)
            return '<!-- Generated in ' . $duration . ' seconds -->';
        return $duration;
    }

    public static function powerby(){
        return 'Powered by <a href="http://www.doophp.com/">DooPHP Framework</a>.';
    }

    public static function version(){
        return '1.4.1';
    }
}

/**
 * DooConfig class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * DooConfig contains all the configuration for the system. This class should not be called directly.
 *
 * <p>Framework settings are all in capital letters. Settings can be accessed via <code>Doo::conf()->SOME_SETTINGS</code></p>
 *
 * <p>You can defined your own setting properties and access them throughout the application.
 * It is recommended to defined your properties in <b>lower case</b> for future compatibility.
 * eg. <code>$config['my_setting_here'] = 'example';</code>
 * </p>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooConfig.php 1000 2009-07-7 18:27:22
 * @package doo.app
 * @since 1.0
 */
class DooConfig{
    
    /**
     * Directories consist of the classes needed in your application.
     * <code>
     * $config['AUTOLOAD'] = array(
     *                      //internal directories, live in the app
     *                      'class', 'model', 'module/example/controller', 
     *                      //external directories, live outside the app
     *                      '/var/php/library/classes'
     *                  );
     * </code>
     * @var array 
     */
    public $AUTOLOAD;
    
    /**
     * Path to the location of your project directory.eg. /var/www/myproject/
     * @var string
     */
    public $SITE_PATH;

    /**
     * Name of the protected folder where all the application scripts located. Default 'protected/'
     * @var string
     */
    public $PROTECTED_FOLDER = 'protected/';
	
	
    /**
     * Path to the location of Doo framework directory. eg. /var/lib/dooframework/
     * @var string 
     */
    public $BASE_PATH;

    /**
     * Path to store the log files.
     * Recommended to put outside the web root directory where others cannot access through the web .eg. /var/mylogs/
     * @var string
     */
    public $LOG_PATH;

    /**
     * URL of your app. eg. http://localhost/doophp/
     * @var string 
     */
    public $APP_URL;

    /**
     * Please define SUBFOLDER if your app is not in the root directory of the domain. eg. http://localhost/doophp , you should set '/doophp/'
     * @var string 
     */
    public $SUBFOLDER;

    /**
     * Application mode(<b>dev</b>, <b>prod</b>). In dev mode, view templates are always checked and compiled
     * @var string 
     */
    public $APP_MODE;

    /**
     * Enable/disable Auto routing.
     * 
     * <p>Every controller can deny being accessed by auto routes
     * just by setting <code>public $autoroute = false;</code> in the Controller class.</p>
     * @var bool 
     */
    public $AUTOROUTE;

    /**
     * Enable/disable debug mode. If debug mode is on, debug trace will be logged.
     * Debug tool can be viewed if <code>Doo::logger()->showDebugger()</code> is called
     * @var bool 
     */
    public $DEBUG_ENABLED;

    /**
     * If defined, the document specified will be included when a 404 header is sent (route not found).
     * @var string 
     */
    public $ERROR_404_DOCUMENT;

    /**
     * If defined, the route specified will be executed when a 404 header is sent (route not found).
     * If ERROR_404_DOCUMENT is defined, then the document would be loaded instead.
     * @var string
     */
    public $ERROR_404_ROUTE;

    /**
     * Path where the cache files are stored. If not defined, caches are stored in SITE_PATH/protected/cache/
     * @var string
     */
    public $CACHE_PATH;

    /**
     * Auto view render path based on URI(defined route) or resource and action string parts in URI(autoroute)
     * @var string|array
     */
    public $AUTO_VIEW_RENDER_PATH;

    /**
     * Settings for Memcache servers, defined in arrays: array(host, port, persistent, weight)
     * <code>
     * // host, port, persistent, weight
     * $config['MEMCACHE'] = array(
     *                       array('192.168.1.31', '11211', true, 40),
     *                       array('192.168.1.23', '11211', true, 80)
     *                     );
     * </code>
     * @var array
     */
    public $MEMCACHE;

	/**
	 * The template engine to use by default
	 * Options are: DooView and DooBasicView
     * @var string
	 */
	public $TEMPLATE_ENGINE;

    /**
     * Output/processed comments block in the template files.
     * @var bool
     */
    public $TEMPLATE_SHOW_COMMENT;

    /**
     * Allow, deny or parse native PHP usage in templates.
     * @var bool
     */
    public $TEMPLATE_ALLOW_PHP;

    /**
     * Always compile template files if true
     * @var bool
     */
    public $TEMPLATE_COMPILE_ALWAYS;

    /**
     * List of template tags to be used globally in DooView template engine ( global function names and PHP functions )
     * @var array
     */
    public $TEMPLATE_GLOBAL_TAGS;

    /**
     * Defines modules that are allowed to be accessed from an auto route URI.
     * Example:
     * <code>
     * //We have a module in SITE_PATH/PROTECTED_FOLDER/module/example
     * //It can be accessed via http://localhost/example/controller/method/parameters
     * $config['MODULES'] = array('example');
     * </code>
     * @var array
     */
    public $MODULES;
    
    /**
     * Unique string ID of the application to be used with PHP 5.3 namespace and auto loading of namespaced classes
     * If you wish to use namespace with the framework, your classes must have a namespace starting with this ID.
     * Example below is located at /var/www/app/protected/controller/test and can be access via autoroute http://localhost/test/my/method
     * <code>
     * <?php
     * namespace myapp\controller\test;
     * class MyController extends \DooController {
     *     .....
     * } ?>
     *
	 * //You would need to enable autoload to use Namespace classes in index.php 
     * spl_autoload_register('Doo::autoload');
	 * 
	 * //in common.conf.php
     * $config['APP_NAMESPACE_ID'] = 'myapp';
     * </code>
	 *
     * @var string
     */
    public $APP_NAMESPACE_ID;
	
    /**
     * Set the configurations. SITE_PATH, BASE_PATH and APP_URL is required
     * @param array $confArr associative array of the configs.
     */
    public function set($confArr){
        foreach($confArr as $k=>$v){
            $this->{$k} = $v;
        }
        
        if($this->SUBFOLDER===null)
           $this->SUBFOLDER='/';

        if($this->AUTOROUTE===null)
           $this->AUTOROUTE=false;

        if($this->DEBUG_ENABLED===null)
           $this->DEBUG_ENABLED=false;

		if ($this->TEMPLATE_ENGINE===null)
			$this->TEMPLATE_ENGINE='DooView';
        
    }

    /**
     * Add data to be retrieved later on. Served as a dummy storage.
     *
     * Example usage:
     * <code>
     * //Store
     * Doo::conf()->add('latestItem', $item);
     *
     * //Retrieve
     * echo Doo::conf()->latestItem;
     * </code>
     *
     * @param mixed $key Key o
     * @param  $value
     */
    public function add($key, $value){
        $this->{$key} = $value;
    }
}

/**
 * DooWebApp class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooWebApp is the global context that processed user's requests.
 *
 * <p>It manages the controllers in MVC pattern, handling URI requests 404 not found, redirection, etc.</p>
 *
 * <p>This class is tightly coupled with DooUriRouter.</p>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooWebApp.php 1000 2009-07-7 18:27:22
 * @package doo.app
 * @since 1.0
 */
class DooWebApp{
    /**
     * @var array routes defined in <i>routes.conf.php</i>
     */
    public $route;

    /**
     * Main function to run the web application
     */
    public function run(){
        $this->throwHeader( $this->routeTo() );
    }
    
     /**
     * Handles the routing process.
     * Auto routing, sub folder, subdomain, sub folder on subdomain are supported.
     * It can be used with or without the <i>index.php</i> in the URI
     * @return mixed HTTP status code such as 404 or URL for redirection
     */
    public function routeTo(){
        Doo::loadCore('uri/DooUriRouter');
        $router = new DooUriRouter;
        $routeRs = $router->execute($this->route,Doo::conf()->SUBFOLDER);

        if($routeRs[0]!==null && $routeRs[1]!==null){
            //dispatch, call Controller class            
            if($routeRs[0][0]!=='['){
                if(strpos($routeRs[0], '\\')!==false){
                    $nsClassFile = str_replace('\\','/',$routeRs[0]);
                    $nsClassFile = explode(Doo::conf()->APP_NAMESPACE_ID.'/', $nsClassFile, 2);
                    $nsClassFile = $nsClassFile[1];
                    require_once Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . $nsClassFile .'.php';                    
                }else{
                    require_once Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "controller/{$routeRs[0]}.php";
                }
            }else{
                $moduleParts = explode(']', $routeRs[0]);
                $moduleName = substr($moduleParts[0],1);
                
                if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true){
                    require_once Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER_ORI . 'module/'. $moduleName .'/controller/'.$moduleParts[1].'.php';                    
                }else{
                    require_once Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'module/'. $moduleName .'/controller/'.$moduleParts[1].'.php';                    
                    Doo::conf()->PROTECTED_FOLDER_ORI = Doo::conf()->PROTECTED_FOLDER;
                }
                
                //set class name
                $routeRs[0] = $moduleParts[1];
                Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI . 'module/'.$moduleName.'/';
            }

            if(strpos($routeRs[0], '/')!==false){
                $clsname = explode('/', $routeRs[0]);
                $routeRs[0] = $clsname[ sizeof($clsname)-1 ];
            }

            //if defined class name, use the class name to create the Controller object
            $clsnameDefined = (sizeof($routeRs)===4);
            if($clsnameDefined)
                $controller = new $routeRs[3];
            else
                $controller = new $routeRs[0];

            $controller->params = $routeRs[2];

            if(isset($controller->params['__extension'])===true){
                $controller->extension = $controller->params['__extension'];
                unset($controller->params['__extension']);
            }
			if(isset($controller->params['__routematch'])===true){
                $controller->routematch = $controller->params['__routematch'];
                unset($controller->params['__routematch']);
            }

            if($_SERVER['REQUEST_METHOD']==='PUT')
                $controller->init_put_vars();

            //before run, normally used for ACL auth
            if($clsnameDefined){
                if($rs = $controller->beforeRun($routeRs[3], $routeRs[1])){
                    return $rs;
                }
            }else{
                if($rs = $controller->beforeRun($routeRs[0], $routeRs[1])){
                    return $rs;
                }
            }

			$routeRs = $controller->$routeRs[1]();
            $controller->afterRun($routeRs);
            return $routeRs;
        }
        //if auto route is on, then auto search Controller->method if route not defined by user
        else if(Doo::conf()->AUTOROUTE){

            list($controller_name, $method_name, $method_name_ori, $params, $moduleName )= $router->auto_connect(Doo::conf()->SUBFOLDER, (isset($this->route['autoroute_alias'])===true)?$this->route['autoroute_alias']:null );

            if(empty($this->route['autoroute_force_dash'])===false){
                if($method_name!=='index' && $method_name===$method_name_ori && ctype_lower($method_name_ori)===false){
                    $this->throwHeader(404);
                    return;
                }
            }

            if(isset($moduleName)===true){
                Doo::conf()->PROTECTED_FOLDER_ORI = Doo::conf()->PROTECTED_FOLDER;
                Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI . 'module/'.$moduleName.'/';
            }

            $controller_file = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "controller/{$controller_name}.php";

            if(file_exists($controller_file)){
                require_once $controller_file;

				$methodsArray = get_class_methods($controller_name);

                //if the method not in controller class, check for a namespaced class with the same file name.
                if($methodsArray===null && isset(Doo::conf()->APP_NAMESPACE_ID)===true){
                    if(isset($moduleName)===true){
                        $controller_name = Doo::conf()->APP_NAMESPACE_ID . '\\module\\'. $moduleName .'\\controller\\' . $controller_name;                        
                    }else{
                        $controller_name = Doo::conf()->APP_NAMESPACE_ID . '\\controller\\' . $controller_name;
                    }
    				$methodsArray = get_class_methods($controller_name);   
                }
                
                //if method not found in both both controller and namespaced controller, 404 error
                if($methodsArray===null){
                    if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true)
                        Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI;
					$this->throwHeader(404);
					return;                    
                }
            }
            else if(isset($moduleName)===true && isset(Doo::conf()->APP_NAMESPACE_ID)===true){
                if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true)
                    Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI;                
                
                $controller_file = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . '/controller/'.$moduleName.'/'.$controller_name .'.php';                 
                
                if(file_exists($controller_file)===false){
					$this->throwHeader(404);
					return;                    
                }                
                $controller_name = Doo::conf()->APP_NAMESPACE_ID .'\\controller\\'.$moduleName.'\\'.$controller_name;                
                #echo 'module = '.$moduleName.'<br>';
                #echo $controller_file.'<br>';                
                #echo $controller_name.'<br>';                   
				$methodsArray = get_class_methods($controller_name);                
            }            
            else{
                if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true)
                    Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI;                
                $this->throwHeader(404);
                return;
            }
            
            //check for REST request as well, utilized method_GET(), method_PUT(), method_POST, method_DELETE()
            $restMethod = $method_name .'_'. strtolower($_SERVER['REQUEST_METHOD']);
            $inRestMethod = in_array($restMethod, $methodsArray);
            
            //check if method() and method_GET() etc. doesn't exist in the controller, 404 error
            if( in_array($method_name, $methodsArray)===false && $inRestMethod===false ){
                if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true)
                    Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI;
                $this->throwHeader(404);
                return;
            }

            //use method_GET() etc. if available
            if( $inRestMethod===true ){
                $method_name = $restMethod;
            }

            $controller = new $controller_name;

            //if autoroute in this controller is disabled, 404 error
            if($controller->autoroute===false){
                if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true)
                    Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI;
                $this->throwHeader(404);
            }

            if($params!=null)
                $controller->params = $params;

            if($_SERVER['REQUEST_METHOD']==='PUT')
                $controller->initPutVars();

            //before run, normally used for ACL auth
            if($rs = $controller->beforeRun($controller_name, $method_name)){
                return $rs;
            }

            $routeRs = $controller->$method_name();
            $controller->afterRun($routeRs);
            return $routeRs;            
        }
        else{
            $this->throwHeader(404);
        }
    }

    /**
     * Reroute the URI to an internal route
     * @param string $routeuri route uri to redirect to
     * @param bool $is404 send a 404 status in header
     */
    public function reroute($routeuri, $is404=false){

        if(Doo::conf()->SUBFOLDER!='/')
            $_SERVER['REQUEST_URI'] = substr(Doo::conf()->SUBFOLDER, 0, strlen(Doo::conf()->SUBFOLDER)-1) . $routeuri;
        else
            $_SERVER['REQUEST_URI'] = $routeuri;

        if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true){
            Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI;
            unset( Doo::conf()->PROTECTED_FOLDER_ORI );
        }

        if($is404===true)
            header('HTTP/1.1 404 Not Found');
        $this->routeTo();
    }

    /**
     * Process a module from the main application.
     *
     * <p>This is similar to rerouting to a Controller. The framework offer 3 ways to process and render a module.</p>
     *
     * <p>Based on a predefined route:</p>
     * <code>
     * # The route is predefined in routes.conf.php
     * # $route['*']['/top/:nav'] = array('MyController', 'renderTop');
     * $data['top'] = Doo::app()->module('/top/banner');
     * </code>
     *
     * <p>Based on Controller name and Action method:</p>
     * <code>
     * Doo::app()->module('MyController', 'renderTop');
     *
     * # If controller is in sub folder
     * Doo::app()->module('folder/MyController', 'renderTop');
     *
     * # Passed in parameter if controller is using $this->param['var']
     * Doo::app()->module('MyController', 'renderTop', array('nav'=>'banner'));
     * </code>
     *
     * <p>If class name is different from controller filename:</p>
     * <code>
     * # filename is index.php, class name is Admin
     * Doo::app()->module(array('index', 'Admin'), 'renderTop');
     *
     * # in a sub folder
     * Doo::app()->module(array('admin/index', 'Admin'), 'renderTop');
     *
     * # with parameters
     * Doo::app()->module(array('admin/index', 'Admin'), 'renderTop', array('nav'=>'banner'));
     * </code>
     *
     * @param string|array $moduleUri URI or Controller name of the module
     * @param string $action Action to be called
     * @param array $params Parameters to be passed in to the Module
     * @return string Output of the module
     */
    public function module($moduleUri, $action=null, $params=null){
        if($moduleUri[0]=='/'){
            if(Doo::conf()->SUBFOLDER!='/')
                $_SERVER['REQUEST_URI'] = substr(Doo::conf()->SUBFOLDER, 0, strlen(Doo::conf()->SUBFOLDER)-1) . $moduleUri;
            else
                $_SERVER['REQUEST_URI'] = $moduleUri;

            $tmp = Doo::conf()->PROTECTED_FOLDER;
            if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===true){
                Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI;
                $tmpOri = Doo::conf()->PROTECTED_FOLDER_ORI;
            }
            
            ob_start();
            $this->routeTo();
            $data = ob_get_contents();
            ob_end_clean();
            
            Doo::conf()->PROTECTED_FOLDER = $tmp;
            
            if(isset($tmpOri)===true)
                Doo::conf()->PROTECTED_FOLDER_ORI = $tmpOri;
            
            return $data;
        }
        //if Controller name passed in:  Doo::app()->module('admin/SomeController', 'login',  array('nav'=>'home'));
        else if(is_string($moduleUri)){
            $controller_name = $moduleUri;
            if(strpos($moduleUri, '/')!==false){
                $arr = explode('/', $moduleUri);
                $controller_name = $arr[sizeof($arr)-1];
            }
            require_once Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "controller/$moduleUri.php";
            $controller = new $controller_name;
            $controller->params = $params;
            if($rs = $controller->beforeRun($controller_name, $action)){
                $this->throwHeader( $rs );
                return;
            }

            ob_start();
			$rs = $controller->{$action}();

            if($controller->autorender===true){
                Doo::conf()->AUTO_VIEW_RENDER_PATH = array(strtolower(substr($controller_name, 0, -10)), strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/','-$1', $action)));
            }
            $controller->afterRun($rs);

            $this->throwHeader( $rs );

            $data = ob_get_contents();
            ob_end_clean();
            return $data;
        }
        //if array passed in. For controller file name != controller class name.
        //eg. Doo::app()->module(array('admin/Admin', 'AdminController'), 'login',  array('nav'=>'home'));
        else{
            require_once Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "controller/{$moduleUri[0]}.php";
            $controller = new $moduleUri[1];
            $controller->params = $params;
            if($rs = $controller->beforeRun($moduleUri[1], $action)){
                $this->throwHeader( $rs );
                return;
            }

            ob_start();
			$rs = $controller->{$action}();

			if($controller->autorender===true){
                Doo::conf()->AUTO_VIEW_RENDER_PATH = array(strtolower(substr($controller_name, 0, -10)), strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/','-$1', $action)));
            }
            $controller->afterRun($rs);

            $this->throwHeader( $rs );

            $data = ob_get_contents();
            ob_end_clean();
            return $data;
        }
    }

    /**
     * Advanced version of DooWebApp::module(). Process a module from the main application or other modules.
     *
     * Module rendered using this method is located in SITE_PATH/PROTECTED_FOLDER/module
     *
     * @param string $moduleName Name of the module folder. To execute Controller/method in the main application, pass a null or empty string value for $moduleName.
     * @param string|array $moduleUri URI or Controller name of the module
     * @param string $action Action to be called
     * @param array $params Parameters to be passed in to the Module
     * @return string Output of the module
     */
    public function getModule($moduleName, $moduleUri, $action=null, $params=null){
        if(empty($moduleName)===false){
            if(isset(Doo::conf()->PROTECTED_FOLDER_ORI)===false){
                Doo::conf()->PROTECTED_FOLDER_ORI = $tmp = Doo::conf()->PROTECTED_FOLDER;
                Doo::conf()->PROTECTED_FOLDER = $tmp . 'module/'.$moduleName.'/';
                $result = $this->module($moduleUri, $action, $params);
                Doo::conf()->PROTECTED_FOLDER = $tmp;
            }else{
                $tmp = Doo::conf()->PROTECTED_FOLDER;
                Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI . 'module/'.$moduleName.'/';
                $result = $this->module($moduleUri, $action, $params);
                Doo::conf()->PROTECTED_FOLDER = $tmp;                
            }
        }
        else{
            $tmp = Doo::conf()->PROTECTED_FOLDER;
            Doo::conf()->PROTECTED_FOLDER = Doo::conf()->PROTECTED_FOLDER_ORI;
            $result = $this->module($moduleUri, $action, $params);
            Doo::conf()->PROTECTED_FOLDER = $tmp;
        }
        return $result;
    }

    /**
     * Analyze controller return value and send appropriate headers such as 404, 302, 301, redirect to internal routes.
     *
     * <p>It is very SEO friendly but you would need to know the basics of HTTP status code.</p>
     * <p>Automatically handles 404, include error document or redirect to inner route
     * to handle the error based on config <b>ERROR_404_DOCUMENT</b> and <b>ERROR_404_ROUTE</b></p>
     * <p>Controller return value examples:</p>
     * <code>
     * 404                                  #send 404 header
     * array('/internal/route', 404)        #send 404 header & redirect to an internal route
     * 'http://www.google.com'              #redirect to URL. default 302 Found sent
     * array('http://www.google.com',301)   #redirect to URL. forced 301 Moved Permenantly sent
     * array('/hello/sayhi', 'internal')    #redirect internally, 200 OK
     * </code>
     * @param mixed $code
     */
    public function throwHeader($code){
        if(headers_sent()){
            return;
        }
        if($code!=null){
            if(is_int($code)){
                if($code===404){
                    //Controller return 404, send 404 header, include file if ERROR_404_DOCUMENT is set by user
                    header('HTTP/1.1 404 Not Found');
                    if(!empty(Doo::conf()->ERROR_404_DOCUMENT)){
                        include Doo::conf()->SITE_PATH . Doo::conf()->ERROR_404_DOCUMENT;
                    }
                    //execute route to handler 404 display if ERROR_404_ROUTE is defined, the route handler shouldn't send any headers or return 404
                    elseif(!empty(Doo::conf()->ERROR_404_ROUTE)){
						$this->reroute(Doo::conf()->ERROR_404_ROUTE, true);
                    }
                    exit;
                }
                //if not 404, just send the header code
                else{
                    DooUriRouter::redirect(null,true, $code);
                }
            }
            elseif(is_string($code)){
                //Controller return the redirect location, it sends 302 Found
                DooUriRouter::redirect($code);
            }
            elseif(is_array($code)){
                //Controller return array('/some/routes/here', 'internal')
                if($code[1]=='internal'){
                    $this->reroute($code[0]);
                    exit;
                }
                //Controller return array('http://location.to.redirect', 301)
                elseif($code[1]===404){
                    $this->reroute($code[0],true);
                    exit;
                }
                // if array('http://location.to.redirect', 302), Moved Temporarily is sent before Location:
                elseif($code[1]===302){
                    DooUriRouter::redirect($code[0],true, $code[1], array("HTTP/1.1 302 Moved Temporarily"));
                }
                //else redirect with the http status defined,eg. 307
                else{
                    DooUriRouter::redirect($code[0],true, $code[1]);
                }
            }
        }
    }

    /**
     * To debug variables with DooPHP's diagnostic view
     * @param mixed $var The variable to view in diagnostics.
     */
    public function debug($var){
        throw new DooDebugException($var);
    }

}

/**
 * DooUriRouter class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * DooUriRouter parse the server request URI
 *
 * <p>The uri router parse the URI and returns the matching controller, action and parameters as defined in the routes configuration.
 * Auto routing is also handled if <b>AUTOROUTE</b> is on. A controller can disable autorouting request by writing the
 * <code>public $autoroute = false;</code>
 * </p>
 *
 * <p>The Uri Router is tested and should work in most modern web servers such as <b>Apache</b> and <b>Cherokee</b>
 * in both mod_php or FastCGI mode. Please refer to http://doophp.com/tutorial/setup to see how to used DooPHP with Cherokee web server</p>
 *
 * <p>HTTP digest authentication can be used with the URI router.
 * HTTP digest is much more recommended over the use of HTTP Basic auth which doesn't provide any encryption.
 * If you are running PHP on Apache in CGI/FastCGI mode, you would need to
 * add the following line to your .htaccess for digest auth to work correctly.</p>
 * <code>RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]</code>
 *
 * <p>Almost identical routes can be parsed by setting the matching URI pattern for each of the identical routes.</p>
 * <p>URI ends with an extension name such as .html .php .aspx will also be parsed by setting a extension value in the route definitions. eg.
 * <code>
 * //static route, just add a .html
 * $route['*']['/products/promotion.html'] = array('ProductController', 'promo');
 *
 * //dynamic route, add 'extension'=>'.html'
 * $route['*']['/products/promotion/:month'] = array('ProductController', 'promo', 'extension'=>'.html');
 * </code>
 * </p>
 *
 * <p><b>RESTful API</b>s are supported natively. You can mocked up <b>RESTful API</b>s easily with DooUriRouter.
 * Just defined the routes to be accessed through the specified request method and handles them in different
 * controller or action method. eg. GET/POST/PUT/DELETE/etc.
 * <code>
 * $route['*']['/news/:id']     #can be accessed through any method
 * $route['get']['/news/:id']   #only accessed through GET
 * $route['post']['/news/:id']  #only accessed through POST
 * $route['delete']['/news/:id']#only accessed through DELETE
 * </code>
 * </p>
 *
 * <p>Routes can be redirect either to an external URL or an internal route.
 * <code>
 * //internal redirect
 * $route['*']['/some/route2'] = $route['*']['/some/route1'];
 *
 * //----- external redirect -----
 * $route['*']['/google/go'] = array('redirect', 'http://localhost/index.html');
 * //sends a 301 Moved Permenantly header
 * $route['*']['/google/go2'] = array('redirect', 'http://localhost/index.html', 301);
 * //redirect to a file called error.html on the same domain.
 * $route['*']['/some/error'] = array('redirect', '/error.html');
 * </code>
 * </p>
 *
 * <p>Defining HTTP authentication
 * <code>
 * //authFail can also be a URL for redirection
 * $route['*']['/admin'] = array('AdminController', 'index',
 *                               'authName'=>'My ABC',
 *                               'auth'=>array('admin'=>'1234', 'moderator'=>'123456'),
 *                               'authFail'=>'login first to view!');
 * </code>
 * </p>
 *
 * <p>If you have your controller file name different from its class name, eg. home.php HomeController
 * <code>
 * $route['*']['/'] = array('home', 'index', 'className'=>'HomeController');
 * </code></p>
 *
 * <p>If you need to reverse generate URL based on route ID with DooUrlBuilder in template view, please defined the id along with the routes
 * <code>
 * $route['*']['/'] = array('HomeController', 'index', 'id'=>'home');
 * </code></p>
 *
 * <p>If you need dynamic routes on root domain, such as http://facebook.com/username
 * Use the key 'root':
 * <code>
 * $route['*']['root']['/:username'] = array('UserController', 'showProfile');
 * </code></p>
 *
 * <p>If you need to catch unlimited parameters at the end of the url, eg. http://localhost/paramA/paramB/param1/param2/param.../.../..
 * Use the key 'catchall':
 * <code>
 * $route['*']['catchall']['/:first'] = array('TestController', 'showAllParams');
 * </code></p>
 *
 * <p>If you have placed your controllers in a sub folder, eg. /protected/admin/EditStuffController.php
 * <code>
 * $route['*']['/'] = array('admin/EditStuffController', 'action');
 * </code></p>
 *
 * <p>If you want a module to be publicly accessed (without using Doo::app()->getModule() ) , use [module name] ,   eg. /protected/module/forum/PostController.php
 * <code>
 * $route['*']['/'] = array('[forum]PostController', 'action');
 * </code></p>
 *
 * <p>If you create subfolders in a module,  eg. /protected/module/forum/post/ListController.php, the module here is forum, subfolder is post
 * <code>
 * $route['*']['/'] = array('[forum]post/PostController', 'action');
 * </code></p>
 *
 * <p>Aliasing give you an option to access the action method/controller through a different URL. This is useful when you need a different url than the controller class name.
 * For instance, you have a ClientController::new() . By default, you can access via http://localhost/client/new
 * <code>
 * $route['autoroute_alias']['/customer'] = 'ClientController';
 * $route['autoroute_alias']['/company/client'] = 'ClientController';
 * </code>
 *
 * With the definition above, it allows user to access the same controller::method with the following URLs:
 * http://localhost/company/client/new
 *
 * To define alias for a Controller inside a module, you may use an array:
 * <code>
 * $route['autoroute_alias']['/customer'] = array('controller'=>'ClientController', 'module'=>'example');
 * $route['autoroute_alias']['/company/client'] = array('controller'=>'ClientController', 'module'=>'example');
 * </code>
 * </p>
 *
 * <p>Auto routes can be accessed via URL: http://domain.com/controller/method
 * If you have a camel case method listAllUser(), it can be accessed via http://domain.com/controller/listAllUser or http://domain.com/controller/list-all-user
 * In any case you want to control auto route to be accessed via dashed URL (list-all-user)
 * <code>
 * $route['autoroute_force_dash'] = true;	//setting this to false or not defining it will keep routes accessible with the 2 URLs.
 * </code>
 * </p>
 *
 * <p>If you do not want case sensitive routing you can force all routes to lowercase. Note this will also result in
 * All parmeters being converted to lowercase as well.
 * <code>
 * $route['force_lowercase'] = true;	// Setting this to false or not defining it will keep routes case sensetive.
 * </code>
 * </p>
 *
 * <p>See http://doophp.com/doc/guide/uri-routing for information in configuring Routes</p>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: Doo.php 1000 2009-07-7 18:27:22
 * @package doo.uri
 * @since 1.0
 */
class DooUriRouter{

    /**
     * Main function to be called in order to parse the requested URI.
     *
     * <p>The returned parameter list can be accessed as an assoc array.</p>
     *
     * <code>
     * #Defined in routes.conf.php
     * $route['*']['/news/:year/:month'] = array('NewsController', 'show_news_by_year'
     * </code>
     *
     * @param array $routeArr Routes defined in <i>routes.conf.php</i>
     * @param string $subfolder Relative path of the sub directory where the app is located. eg. http://localhost/doophp, the value should be '/doophp/'
     * @return array returns an array consist of the Controller class, action method and parameters of the route
     */
    public function execute($routeArr,$subfolder='/'){
        list($route, $params) = $this->connect($routeArr,$subfolder);

        if($route[0]==='redirect'){
            if(sizeof($route)===2)
                self::redirect($route[1]);
            else
                self::redirect($route[1],true,$route[2]);
        }

        if(isset($route['auth'])===true){
            $route['authFailURL'] = (!isset($route['authFailURL']))?null:$route['authFailURL'];
            $route['authFail'] = (!isset($route['authFail']))?null:$route['authFail'];
            Doo::loadCore('auth/DooDigestAuth');
            DooDigestAuth::http_auth($route['authName'],$route['auth'], $route['authFail'], $route['authFailURL']);
        }

		if (isset($route['params'])===true) {
			$params = array_merge($params, $route['params']);
		}

        if(isset($route['className'])===true)
			return array($route[0],$route[1],$params,$route['className']);

        //return Controller class, method, parameters of the route
        return array($route[0],$route[1],$params);
    }

    /**
     * Redirect to an external URL with HTTP 302 header sent by default
     *
     * @param string $location URL of the redirect location
     * @param bool $exit to end the application
     * @param code $code HTTP status code to be sent with the header
     * @param array $headerBefore Headers to be sent before header("Location: some_url_address");
     * @param array $headerAfter Headers to be sent after header("Location: some_url_address");
     */
    public static function redirect($location, $exit=true, $code=302, $headerBefore=NULL, $headerAfter=NULL){
        if($headerBefore!==null){
			foreach($headerBefore as $h){
                header($h);
			}
        }
        header("Location: $location", true, $code);
        if($headerAfter!==null){
			foreach($headerAfter as $h){
                header($h);
			}
        }
        if($exit)
            exit;
    }


	/**
     * Matching the route array with the request URI
     *
     * <p>Avoids preg_match for most cases to gain more performance.
     * Trailing slashes '/' are ignored and stripped out. It can be used with or without the <b>index.php</b> in the URI.</p>
     * <p>To use DooUriRouter without index.php, add the following code to your .htaccess file if Apache mod_rewrite is enabled.</p>
     * <code>
     * RewriteEngine On
     *
     * # if a directory or a file exists, use it directly
     * RewriteCond %{REQUEST_FILENAME} !-f
     * RewriteCond %{REQUEST_FILENAME} !-d

     * # otherwise forward it to index.php
     * RewriteRule .* index.php
     * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
     * </code>
     */
	private function connect($routes, $subfolder) {

		$skipNormalRoutes = false;	// Used to allow for parse through to check root and catchall
									// routes if * and type routes do not meet / criteria

		//$this->log('Routes: ', $routes);
		//$this->log('Subfolder: ' . $subfolder);

		$type = strtolower($_SERVER['REQUEST_METHOD']);
		$requestedUri = $_SERVER['REQUEST_URI'];

		if (isset($routes['force_lowercase']) && $routes['force_lowercase'] === true) {
			$requestedUri = strtolower($requestedUri);
		}

		//$this->log('Type: ' . $type);
		//$this->log('Requested Uri: ' . $requestedUri);

		// Remove get part of url (eg example.com/test/?foo=bar trimed to example.com/test/)
		if (false !== ($getPosition = strpos($requestedUri, '?'))) {
			$requestedUri = substr($requestedUri, 0, $getPosition);
			//$this->log('Trimmed off get (?) to give Request Uri: ' . $requestedUri);
		}

		// Remove Subfolder
		$requestedUri = substr($requestedUri, strlen($subfolder)-1);
		//$this->log('Trimmed off subfolder from Request Uri to give: ' . $requestedUri);

		// Remove index.php from URL if it exists
		if (0 === strpos($requestedUri, '/index.php')) {
			$requestedUri = substr($requestedUri, 10);
			//$this->log('Trimmed off the /index.php from Request Uri to give: ' . $requestedUri);
			if ($requestedUri == '') {
				$requestedUri = '/';
			}
		}

		// Remove any trailing slashes from Uri except the first / of a uri (Root)
		//Strip out the additional slashes found at the end. If first character is / then leaves it alone
		$end = strlen($requestedUri) - 1;
		while( $end > 0 && $requestedUri[$end] === '/' ){
			$end--;
		}
		$requestedUri = substr($requestedUri, 0, $end+1);

		//$this->log('Trimmed off trailing slashes from Request Uri: ' . $requestedUri);


		// Got a root url (ie. Homepage)
		if ($requestedUri === '/') {
			//$this->log('Got a root URL');
			if(isset($routes[$type]['/'])===true)
				return array($routes[$type]['/'], null);
			elseif(isset($routes['*']['/'])===true)
				return array($routes['*']['/'], null);
			elseif(isset($routes['*']['catchall'])===true)
				$skipNormalRoutes = true;
			else
				return;
		}

		if ($skipNormalRoutes===false) {
			// Not got root url so we need to get possible routes
			// First look for routes for specific route type and then try for * routes
			// We will merge the 2 together to prevent duplicate checking
			if(isset($routes[$type])===true)
				$possibleRoutes = $routes[$type];
			else
				$possibleRoutes = null;

			if(isset($routes['*'])===true){
				if($possibleRoutes !== null)
					$possibleRoutes = array_merge($routes['*'], $possibleRoutes);
				else
					$possibleRoutes = $routes['*'];
			}

			//$this->log('Possible Routes: ', $possibleRoutes);

			// We if we simply have the full route (ie. No params needed)
			if (isset($possibleRoutes[$requestedUri])===true) {
				// Ensure the url does not contain : in it
				if (false === strpos($requestedUri, ':')) {
					//$this->log('Got Perfect Match');
					Doo::conf()->AUTO_VIEW_RENDER_PATH = $requestedUri;
					return array($possibleRoutes[$requestedUri], null);
				}
			}
		}


		/* Not got a match so now we will loop over all possibleRoutes and see
		 * if we have a matching route using parameters. We carry out some quick checks first
		 * in an attempt to skip past a route which does not match the current route.
		 *
		 * Once we have a route which might work we must then test the route against any
		 * regex (matches) which are to be applied to parameters. This allows for identical uri's to be used
		 * but with each expecting different parameter formats for example
		 * /news/:title     - to show a news by passing the title which will maybe call the controller action News->show_by_title
		 * /news/:id     - to show a news by passing the ID which will maybe call the controller action News->show_by_id
		 *
		 * Note that Identical Routes MUST have different REQUIREMENT (match) for the param,
		 * if not the first which is defined will matched, therefore preventing any others being matched
		 */

		$uriPartsOrig = explode('/', $requestedUri);
		$uriPartsSize = sizeof($uriPartsOrig);

		$uriExtension = false;
		if (false !== ($pos = strpos($uriPartsOrig[$uriPartsSize-1], '.')) ) {
			$uriExtension = substr($uriPartsOrig[$uriPartsSize-1], $pos);
			$uriLastPartNoExtension = substr($uriPartsOrig[$uriPartsSize-1], 0, $pos);
			//$this->log('URI Extension is: ' . $uriExtension);
		}

		if ($skipNormalRoutes===false) {
			foreach($possibleRoutes as $routeKey=>$routeData) {
				//$this->log('Trying routeKey: ' . $routeKey);
				$uriParts = $uriPartsOrig;
				$routeParts = explode('/', $routeKey);

				if ($uriPartsSize !== sizeof($routeParts)) {
					//$this->log('Not Enought Parts: ' . $routeKey);
					continue;	// Not enough parts in route to match our current uri?
				}

				// If first part of uri not match first part of route then skip.
				// We expect ALL routes at this stage to begin with a static segment.
				// Note: We exploded with a leading / so element 0 in both arrays is an empty string
				if ($uriParts[1] !== $routeParts[1]) {
					//$this->log('First path not match');
					continue;
				}

				// If the route allows extensions check that the extension provided is a correct match
				if (isset($routeData['extension'])===true) {
					if ($uriExtension === false) {
						continue;		// We need an extension for this to match so can't be a match
					} else {
						$routeExtension = $routeData['extension'];
						if (is_string($routeExtension)===true && $uriExtension!==$routeExtension ) {
							continue;	// Extensions do not match so can't be a match
						} elseif (is_array($routeExtension)===true && in_array($uriExtension, $routeExtension)===false) {
							continue;	// Extension not in allowed extensions so can't be a match
						}
					}
				}

				// Now check the other statics parts of the url (we deal with parameters later
				foreach ($routeParts as $i=>$routePart) {
					if ($i < 2)
						continue;

					if ($routePart[0] === ':')
						continue;	// This routePart is a parameter in the Uri

					if ($routePart !== $uriParts[$i])
						continue 2; // The static part of this route does not match the route part
				}

				//$this->log('Got a route match. RouteKey: ' . $routeKey);
				if (isset($routeData['extension'])===true && $uriExtension !== false) {
					$uriParts[$uriPartsSize - 1] = $uriLastPartNoExtension;
				}

				$params = $this->parse_params($uriParts, $routeParts);
				//$this->log('Got Parameter Values:', $params);

				if (isset($routeData['match'])===true) {
					//$this->log('Checking Parameter Matches');
					foreach($routeData['match'] as $paramName=>$pattern) {
						if (preg_match($pattern, $params[$paramName]) == 0) {
							continue 2;
						}
					}
				}
				if ($uriExtension !== false) {
					$params['__extension'] = $uriExtension;
				}
				$params['__routematch'] = $routeData;
				//$this->log('Got a Match');
				Doo::conf()->AUTO_VIEW_RENDER_PATH = $routeKey;
				return array($routeData, $params);
			}

			if (isset($routes['*']['root'])===true) {

				// Note: Root Routes should always start with a parameter ie. ['*']['root']['/:param']
				// Therefore we wont look at running some checks used by non root routes
				//$this->log('No Route Yet Found. Trying Root routes');
				$rootRoute = $routes['*']['root'];

				foreach($rootRoute as $routeKey=>$routeData) {
					$uriParts = $uriPartsOrig;
					$routeParts = explode('/', $routeKey);

					if ($uriPartsSize !== sizeof($routeParts)) {
						//$this->log('Not Enought Parts: ' . $routeKey);
						continue;	// Not enough parts in route to match our current uri?
					}

					// If the route allows extensions check that the extension provided is a correct match
					if (isset($routeData['extension'])===true) {
						if ($uriExtension === false) {
							continue;		// We need an extension for this to match so can't be a match
						} else {
							$routeExtension = $routeData['extension'];
							if (is_string($routeExtension)===true && $uriExtension !== $routeExtension) {
								continue;	// Extensions do not match so can't be a match
							} elseif (is_array($routeExtension)===true && in_array($uriExtension, $routeExtension)===false) {
								continue;	// Extension not in allowed extensions so can't be a match
							}
						}
					}

					// Now check the other statics parts of the url (we deal with parameters later
					foreach ($routeParts as $i=>$routePart) {
						if ($i == 0)
							continue;	// The first item is empty

						if ($routePart[0] === ':')
							continue;	// This routePart is a parameter in the Uri

						if ($routePart !== $uriParts[$i])
							continue 2; // The static part of this route does not match the route part
					}

					//$this->log('Got a route match. RouteKey: ' . $routeKey);
					if (isset($routeData['extension'])===true && $uriExtension !== false) {
						$uriParts[$uriPartsSize - 1] = $uriLastPartNoExtension;
					}

					$params = $this->parse_params($uriParts, $routeParts);
					//$this->log('Got Parameter Values:', $params);

					if (isset($routeData['match'])===true) {
						//$this->log('Checking Parameter Matches');
						foreach($routeData['match'] as $paramName=>$pattern) {
							if (preg_match($pattern, $params[$paramName]) == 0) {
								continue 2;
							}
						}
					}
					if ($uriExtension !== false) {
						$params['__extension'] = $uriExtension;
					}
					$params['__routematch'] = $routeData;
					//$this->log('Got a Match');
					Doo::conf()->AUTO_VIEW_RENDER_PATH = $routeKey;
					return array($routeData, $params);
				}
			}
		}


		if(isset($routes['*']['catchall'])===true) {
			//$this->log('No Route Yet Found. Trying Catch All Routes');
			$routeCatch = $routes['*']['catchall'];
			foreach($routes['*']['catchall'] as $routeKey=>$routeData) {

				// If the route allows extensions check that the extension provided is a correct match
				if (isset($routeData['extension'])===true) {
					if ($uriExtension === false) {
						continue;		// We need an extension for this to match so can't be a match
					} else {
						$routeExtension = $routeData['extension'];
						if (is_string($routeExtension)===true && $uriExtension !== $routeExtension) {
							continue;	// Extensions do not match so can't be a match
						} elseif (is_array($routeExtension)===true && in_array($uriExtension, $routeExtension)===false) {
							continue;	// Extension not in allowed extensions so can't be a match
						}
					}
				}

				$uriParts = $uriPartsOrig;
				if ($routeKey === '/'){
					$routeParts = array('');
				} else {
					$routeParts = explode('/', $routeKey);

					// Now check the other statics parts of the url (we deal with parameters later
					foreach ($routeParts as $i=>$routePart) {
						if ($i == 0)
							continue;	// The first item is empty

						if (isset($routePart[0])===true && $routePart[0] === ':')
							continue;	// This routePart is a parameter in the Uri

						if ($routePart !== $uriParts[$i]) {
							continue 2; // The static part of this route does not match the route part
						}
					}
				}

				if (isset($routeData['extension'])===true && $uriExtension !== false) {
					$uriParts[$uriPartsSize - 1] = $uriLastPartNoExtension;
				}

				$params = $this->parse_params_catch($uriParts, $routeParts);

				if (isset($routeData['match'])===true) {
					foreach($routeData['match'] as $paramName=>$pattern) {
						if (preg_match($pattern, $params[$paramName]) == 0) {
							continue 2;
						}
					}
				}

				if ($uriExtension !== false) {
					$params['__extension'] = $uriExtension;
				}

				$params['__routematch'] = $routeData;
				Doo::conf()->AUTO_VIEW_RENDER_PATH = $routeKey;
				return array($routeData, $params);
			}
		}

		//$this->log('Failed to find a matching route');
	}


    /**
     * Handles auto routing.
     *
     * <p>If AUTOROUTE is on, you can access a controller action method by
     * accessing the URL http://localhost/controllername/methodname</p>
     *
     * <p>If your controller class has a Camel Case naming convention for the class name,
     * access it through http://localhost/camel-case/method for a class name CamelCaseController</p>
     *
     * <p>If no method is specified in the URL, <i>index()</i> will be executed by default if available.
     * If no matching controller/method is found, a 404 status will be sent in the header.</p>
     *
     * <p>The returned parameter list is access through an indexed array ( $param[0], $param[1], $param[2] ) instead of a assoc array in the Controller class.</p>
     *
     * @param string $subfolder Relative path of the sub directory where the app is located. eg. http://localhost/doophp, the value should be '/doophp/'
     * @return array returns an array consist of the Controller class, action method and parameters of the route
     */
    public function auto_connect($subfolder='/', $autoroute_alias=null){
        $uri = $_SERVER['REQUEST_URI'];

        //remove Subfolder from the URI if exist
        if( $subfolder!='/' )
            $uri = substr($uri, strlen($subfolder));

        //remove index.php/ from the URI if exist
        if(strpos($uri, 'index.php/')===0)
            $uri = substr($uri, strlen('index.php/'));

        //strip out the GET variable part if start with /?
        if($pos = strpos($uri, '/?')){
            $uri = substr($uri,0,$pos);
        }else if($pos = strpos($uri, '?')) {
            $tmp = explode('?', $uri);
            $uri = $tmp[0];
        }

        if($uri!=='/'){
			$end = strlen($uri) - 1;
			while( $end > 0 && $uri[$end] === '/' ){
				$end--;
			}
			$uri = substr($uri, 0, $end+1);
		}

        //remove the / in the first char in REQUEST URI
        if($uri[0]==='/')
            $uri = substr($uri, 1);

        //spilt out GET variable first
        $uri = explode('/',$uri);

        $module = null;
        if(isset(Doo::conf()->MODULES)===true && in_array($uri[0], Doo::conf()->MODULES)===true){
            $module = $uri[0];
            array_shift($uri);
        }

        //if controller and method not found.
        if(isset($uri[0])===false){
            return;
        }

        $controller_name = $uri[0];
		Doo::conf()->AUTO_VIEW_RENDER_PATH = array($controller_name);

        //controller name can't start with a -, and it can't have more than 1 -
        if( strpos($controller_name, '-')===0 || strpos($controller_name, '--')!==false ){
            return;
        }

        //if - detected, make controller name camelcase
        if(strpos($controller_name, '-')!==false){
            $controller_name = str_replace(' ', '', ucwords( str_replace('-', ' ', $controller_name) ) ) ;
        }
        $controller_name = ucfirst($controller_name);
        $controller_name .= 'Controller' ;

        //if method is in uri, replace - to camelCase. else method is empty, make it access index
        if(empty($uri[1])===false){
            $method_name = $method_name_ori = $uri[1];

            //controller name can't start with a -, and it can't have more than 1 -
            if( strpos($method_name, '-')===0 || strpos($method_name, '--')!==false ){
                return;
            }

            //if - detected, make method name camelcase
			if( strpos($method_name, '-') !== false ){
                //$method_name = lcfirst( str_replace(' ', '', ucwords( str_replace('-', ' ', $method_name) ) ) );
                $method_name =  str_replace(' ', '', ucwords( str_replace('-', ' ', $method_name) ) ) ;
                $method_name{0} = strtolower($method_name{0});
			}

			Doo::conf()->AUTO_VIEW_RENDER_PATH[] = $uri[1];
		}else{
            $method_name = $method_name_ori = 'index';
			Doo::conf()->AUTO_VIEW_RENDER_PATH[] = 'index';
		}

        //the first 2 would be Controller and Method, the others will be params if available, access through Array arr[0], arr[1], arr[3]
        $params = null;
        if(sizeof($uri)>2){
            $params=array_slice($uri, 2);
        }

        //match alias for autoroutes
        if($autoroute_alias!==null){
            $alias = '/'.urldecode($uri[0]);

            if(isset($autoroute_alias[$alias])===true){
                $convertname = $controller_name = $autoroute_alias[$alias];

				//if alias defined as array('controller'=>'TestController', 'module'=>'example')
				if(is_array($convertname)===true){
					$controller_name = $controller_name['controller'];
					if(isset($convertname['module'])===true){
						$module = $convertname['module'];
					}
					$convertname = $controller_name;
				}

                //camel case to dash for controller
                $convertname{0} = strtolower($convertname[0]);
                Doo::conf()->AUTO_VIEW_RENDER_PATH[0] = strtolower(preg_replace('/([A-Z])/', '-$1', substr($convertname, 0, -10)));
            }
            else{
                $uridecode = urldecode(implode('/', $uri));

                $aliaskey = array_keys($autoroute_alias);

                //escape string and convert to regex pattern to match with URI, (alias1|alias 2|alias3\/alias3_2)
                $aliaskey = str_replace("\t", '|', preg_quote( implode("\t", $aliaskey), '/') );

                //use regex to eliminate looping through the list of alias keys
                if( preg_match('/^('. $aliaskey .')\//', '/'.$uridecode.'/', $matchedKey) > 0){
                    //key of the matched autoroute alias
                    $r = $matchedKey[1];
                    $convertname = $controller_name = $autoroute_alias[$r];

					//if alias defined as array('controller'=>'TestController', 'module'=>'example')
					if(is_array($convertname)===true){
						$controller_name = $controller_name['controller'];
						if(isset($convertname['module'])===true){
							$module = $convertname['module'];
						}
						$convertname = $controller_name;
					}

                    //camel case to dash for controller
                    $convertname{0} = strtolower($convertname[0]);
                    Doo::conf()->AUTO_VIEW_RENDER_PATH[0] = strtolower(preg_replace('/([A-Z])/', '-$1', substr($convertname, 0, -10)));

                    //explode and parse the method name + parameters
                    $uridecode = explode('/', substr($uridecode, strlen($r)));
                    $method_name = $method_name_ori = $uridecode[0];

                    if(empty($method_name)===true){
                        $method_name = $method_name_ori = 'index';
                    }else{
                        if(sizeof($uridecode)>1){
                            $params=array_slice($uridecode, 1);
                        }else{
                            $params=null;
                        }
						//controller name can't start with a -, and it can't have more than 1 -
						if( strpos($method_name, '-')===0 || strpos($method_name, '--')!==false ){
							return;
						}

						//if - detected, make method name camelcase
						if( strpos($method_name, '-') !== false ){
							//$method_name = lcfirst( str_replace(' ', '', ucwords( str_replace('-', ' ', $method_name) ) ) );
							$method_name =  str_replace(' ', '', ucwords( str_replace('-', ' ', $method_name) ) ) ;
							$method_name{0} = strtolower($method_name{0});
						}
                    }
                    Doo::conf()->AUTO_VIEW_RENDER_PATH[1] = $method_name;
                }
            }
        }

        return array($controller_name, $method_name, $method_name_ori, $params, $module);
    }

    /**
     * Get the parameter list found in the URI which matched a user defined route
     *
     * @param array $req_route The requested route
     * @param array $defined_route Route defined by the user
     * @return array An array of parameters found in the requested URI
     */
    protected function parse_params($req_route, $defined_route){
        $params = array();
		$size = sizeof($req_route);
        for($i=0; $i<$size; $i++){
            $param_key = $defined_route[$i];
			if ($param_key == '') {
				continue;
			} elseif($param_key[0]===':'){
                $param_key = str_replace(':', '', $param_key);
                $params[$param_key] = $req_route[$i];
            }
        }
        return $params;
    }

    /**
     * Get the parameter list found in the URI (unlimited)
     *
     * @param array $req_route The requested route
     * @param array $defined_route Route defined by the user
     * @return array An array of parameters found in the requested URI
     */
    protected function parse_params_catch($req_route, $defined_route){
        $params = array();
		$size = sizeof($req_route);
        for($i=0;$i<$size;$i++){
            if(isset($defined_route[$i])){
                $param_key = $defined_route[$i];
                if ($param_key == '') {
					continue;
				} elseif($param_key[0]===':'){
                    $param_key = str_replace(':', '', $param_key);
                    $params[$param_key] = $req_route[$i];
                }
            }else{
                $params[] = $req_route[$i];
            }
        }
        return $params;
    }

}

/**
 * DooController class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * Base class of all controller
 *
 * <p>Provides a few shorthand methods to access commonly used component during development. e.g. DooLoader, DooLog, DooSqlMagic.</p>
 *
 * <p>Parameter lists and extension type defined in routes configuration can be accessed through <b>$this->params</b> and <b>$this->extension</b></p>
 *
 * <p>If a client sends PUT request to your controller, you can retrieve the values sent through <b>$this->puts</b></p>
 *
 * <p>GET and POST variables can still be accessed via php $_GET and $_POST. They are not handled/process by Doo framework.</p>
 *
 * <p>Auto routing can be denied from a Controller by setting <b>$autoroute = false</b></p>
 *
 * Therefore, the following class properties & methods is reserved and should not be used in your Controller class.
 * <code>
 * $params
 * $puts
 * $extension
 * $autoroute
 * $vdata
 * $renderMethod
 * init_put_vars()
 * load()
 * language()
 * accept_type()
 * render()
 * renderc()
 * setContentType()
 * is_SSL()
 * view()
 * db()
 * cache()
 * acl()
 * beforeRun()
 * isAjax()
 * renderLayout()
 * clientIP()
 * saveRendered()
 * saveRenderedC()
 * toXML()
 * toJSON()
 * viewRenderAutomation()
 * getKeyParam()
 * afterRun()
 * </code>
 *
 * You still have a lot of freedom to name your methods and properties other than names mentioned.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooController.php 1000 2009-07-7 18:27:22
 * @package doo.controller
 * @since 1.0
 */
class DooController {
    /**
     * Associative array of the parameter list found matched in a URI route.
     * @var array
     */
    public $params;

    /**
     * Associative array of the PUT values sent by client.
     * @var array
     */
    public $puts;

    /**
     * Extension name (.html, .json, .xml ,...) found in the URI. Routes can be specified with a string or an array as matching extensions
     * @var string
     */
    public $extension;

    /**
     * Deny or allow auto routing access to a Controller. By default auto routes are allowed in a controller.
     * @var bool
     */
    public $autoroute = TRUE;

	/**
	 * Data to be pass from controller to view to be rendered
	 * @var mixed
	 */
    public $vdata;

	/**
	 * Enable auto render of view at the end of a controller -> method request
	 * @var bool
	 */
	public $autorender = FALSE;

	/**
	 * Render method for auto render. You can use 'renderc' & 'render' or your own method in the controller.
	 * @var string Default is renderc
	 */
	public $renderMethod = 'renderc';

    protected $_load;
    protected $_view;

    /**
     * Use initPutVars() instead
     * @deprecated deprecated since version 1.3
     */
    public function init_put_vars(){
        parse_str(file_get_contents('php://input'), $this->puts);
    }
    
    /**
     * Set PUT request variables in a controller. This method is to be used by the main web app class.
     */
    public function initPutVars(){
        parse_str(file_get_contents('php://input'), $this->puts);
    }    

    /**
     * The loader singleton, auto create if the singleton has not been created yet.
     * @return DooLoader
     */
    public function load(){
        if($this->_load==NULL){
            Doo::loadCore('uri/DooLoader');
            $this->_load = new DooLoader;
        }

        return $this->_load;
    }

    /**
     * Returns the database singleton, shorthand to Doo::db()
     * @return DooSqlMagic
     */
    public function db(){
        return Doo::db();
    }

    /**
     * Returns the Acl singleton, shorthand to Doo::acl()
     * @return DooAcl
     */
    public function acl(){
        return Doo::acl();
    }

    /**
     * This will be called before the actual action is executed
     */
    public function beforeRun($resource, $action){}

    /**
     * Returns the cache singleton, shorthand to Doo::cache()
     * @return DooFileCache|DooFrontCache|DooApcCache|DooMemCache|DooXCache|DooEAcceleratorCache
     */
    public function cache($cacheType='file'){
        return Doo::cache($cacheType);
    }

    /**
     * Writes the generated output produced by render() to file.
     * @param string $path Path to save the generated output.
     * @param string $templatefile Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @return string|false The file name of the rendered output saved (html).
     */
	public function saveRendered($path, $templatefile, $data=NULL) {
		return $this->view()->saveRendered($path, $templatefile, $data);
	}

    /**
     * Writes the generated output produced by renderc() to file.
     * @param string $path Path to save the generated output.
     * @param string $templatefile Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param bool $enableControllerAccess Enable the view scripts to access the controller property and methods.
     * @param bool $includeTagClass If true, DooView will determine which Template tag class to include. Else, no files will be loaded
     * @return string|false The file name of the rendered output saved (html).
     */
    public function saveRenderedC($path, $templatefile, $data=NULL, $enableControllerAccess=False, $includeTagClass=True){
        if($enableControllerAccess===true){
            return $this->view()->saveRenderedC($file, $data, $this, $includeTagClass);
        }else{
            return $this->view()->saveRenderedC($file, $data, null, $includeTagClass);
        }
	}

    /**
     * The view singleton, auto create if the singleton has not been created yet.
     * @return DooView|DooViewBasic
     */
    public function view(){
        if($this->_view==NULL){
			$engine = Doo::conf()->TEMPLATE_ENGINE;
            Doo::loadCore('view/' . $engine);
            $this->_view = new $engine;
        }

        return $this->_view;
    }

    /**
     * Short hand for $this->view()->render() Renders the view file.
     *
     * @param string $file Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param bool $process If TRUE, checks the template's last modified time against the compiled version. Regenerates if template is newer.
     * @param bool $forceCompile Ignores last modified time checking and force compile the template everytime it is visited.
     */
    public function render($file, $data=NULL, $process=NULL, $forceCompile=false){
        $this->view()->render($file, $data, $process, $forceCompile);
    }

    /**
     * Short hand for $this->view()->renderc() Renders the view file(php) located in viewc.
     *
     * @param string $file Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the php template.
     * @param bool $enableControllerAccess Enable the view scripts to access the controller property and methods.
     * @param bool $includeTagClass If true, DooView will determine which Template tag class to include. Else, no files will be loaded
     */
    public function renderc($file, $data=NULL, $enableControllerAccess=False, $includeTagClass=True){
        if($enableControllerAccess===true){
            $this->view()->renderc($file, $data, $this, $includeTagClass);
        }else{
            $this->view()->renderc($file, $data, null, $includeTagClass);
        }
    }

    /**
     * Get the client accept language from the header
     *
     * @param bool $countryCode to return the language code along with country code
     * @return string The language code. eg. <b>en</b> or <b>en-US</b>
     */
    public function language($countryCode=FALSE){
        $langcode = (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $langcode = (!empty($langcode)) ? explode(';', $langcode) : $langcode;
        $langcode = (!empty($langcode[0])) ? explode(',', $langcode[0]) : $langcode;
        if(!$countryCode)
            $langcode = (!empty($langcode[0])) ? explode('-', $langcode[0]) : $langcode;
        return $langcode[0];
    }

    /**
     * Use acceptType() instead
     * @deprecated deprecated since version 1.3
     * @return string Client accept type
     */
    public function accept_type(){
        return $this->acceptType();
    }

    /**
     * Get the client specified accept type from the header sent
     *
     * <p>Instead of appending a extension name like '.json' to a URL,
     * clients can use 'Accept: application/json' for RESTful APIs.</p>
     * @return string Client accept type
     */
    public function acceptType(){
        $type = array(
            '*/*'=>'*',
            'html'=>'text/html,application/xhtml+xml',
            'xml'=>'application/xml,text/xml,application/x-xml',
            'json'=>'application/json,text/x-json,application/jsonrequest,text/json',
            'js'=>'text/javascript,application/javascript,application/x-javascript',
            'css'=>'text/css',
            'rss'=>'application/rss+xml',
            'yaml'=>'application/x-yaml,text/yaml',
            'atom'=>'application/atom+xml',
            'pdf'=>'application/pdf',
            'text'=>'text/plain',
            'png'=>'image/png',
            'jpg'=>'image/jpg,image/jpeg,image/pjpeg',
            'gif'=>'image/gif',
            'form'=>'multipart/form-data',
            'url-form'=>'application/x-www-form-urlencoded',
            'csv'=>'text/csv'
        );

        $matches = array();

        //search and match, add 1 priority to the key if found matched
        foreach($type as $k=>$v){
            if(strpos($v,',')!==FALSE){
                $tv = explode(',', $v);
                foreach($tv as $k2=>$v2){
                    if (stristr($_SERVER["HTTP_ACCEPT"], $v2)){
                        if(isset($matches[$k]))
                            $matches[$k] = $matches[$k]+1;
                        else
                            $matches[$k]=1;
                    }
                }
            }else{
                if (stristr($_SERVER["HTTP_ACCEPT"], $v)){
                    if(isset($matches[$k]))
                        $matches[$k] = $matches[$k]+1;
                    else
                        $matches[$k]=1;
                }
            }
        }

        if(sizeof($matches)<1)
            return NULL;

        //sort by the highest priority, keep the key, return the highest
        arsort($matches);

        foreach ($matches as $k=>$v){
            return ($k==='*/*')?'html':$k;
        }
    }

    /**
     * Sent a content type header
     *
     * <p>This can be used with your REST api if you allow clients to retrieve result format
     * by sending a <b>Accept type header</b> in their requests. Alternatively, extension names can be
     * used at the end of an URI such as <b>.json</b> and <b>.xml</b></p>
     *
     * <p>NOTE: This method should be used before echoing out your results.
     * Use accept_type() or $extension to determined the desirable format the client wanted to accept.</p>
     *
     * @param string $type Content type of the result. eg. text, xml, json, rss, atom
     * @param string $charset Charset of the result content. Default utf-8.
     */
    public function setContentType($type, $charset='utf-8'){
        if(headers_sent())return;

        $extensions = array('html'=>'text/html',
                            'xml'=>'application/xml',
                            'json'=>'application/json',
                            'js'=>'application/javascript',
                            'css'=>'text/css',
                            'rss'=>'application/rss+xml',
                            'yaml'=>'text/yaml',
                            'atom'=>'application/atom+xml',
                            'pdf'=>'application/pdf',
                            'text'=>'text/plain',
                            'png'=>'image/png',
                            'jpg'=>'image/jpeg',
                            'gif'=>'image/gif',
                            'csv'=>'text/csv'
						);
        if(isset($extensions[$type]))
            header("Content-Type: {$extensions[$type]}; charset=$charset");
    }

    /**
     * Get client's IP
     * @return string
     */
    public function clientIP(){
        if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            return getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            return getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            return getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * This will be called if the action method returns null or success status(200 to 299 not including 204) after the actual action is executed
     * @param mixed $routeResult The result returned by an action
     */    
	public function afterRun($routeResult) {
		if($this->autorender===true && ($routeResult===null || ($routeResult>=200 && $routeResult<300 && $routeResult!=204))){	
            $this->viewRenderAutomation();
		}
	}
    
    /**
     * Retrieve value of a key from URI accessed from an auto route.
     * Example with a controller named UserController and a method named listAll(): 
     * <code>
     * //URI is http://localhost/user/list-all/id/11
     * $this->getKeyParam('id');   //returns 11
     * </code>
     * 
     * @param string $key
     * @return mixed
     */
    public function getKeyParam($key){
        if(!empty($this->params) && in_array($key, $this->params)){
            $valueIndex = array_search($key, $this->params) + 1;
            if($valueIndex<sizeof($this->params))
                return $this->params[$valueIndex];
        }
    }
    
    /**
     * Controls the automated view rendering process.
     */
	public function viewRenderAutomation(){
		if(is_string(Doo::conf()->AUTO_VIEW_RENDER_PATH)){
			$path = Doo::conf()->AUTO_VIEW_RENDER_PATH;
			$path = str_replace(':', '@', substr($path, 1));
			$this->{$this->renderMethod}($path, $this->vdata);
		}else{
            if(isset(Doo::conf()->AUTO_VIEW_RENDER_PATH))
                $this->{$this->renderMethod}(strtolower(Doo::conf()->AUTO_VIEW_RENDER_PATH[0]) .'/'. strtolower(Doo::conf()->AUTO_VIEW_RENDER_PATH[1]), $this->vdata);
            else
                $this->{$this->renderMethod}('index', $this->vdata);                
		}
	}

    /**
     * Check if the request is an AJAX request usually sent with JS library such as JQuery/YUI/MooTools
     * @return bool
     */
    public function isAjax(){
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Check if the connection is a SSL connection
     * @return bool determined if it is a SSL connection
     */
    public function isSSL(){
        if(!isset($_SERVER['HTTPS']))
            return FALSE;

        //Apache
        if($_SERVER['HTTPS'] === 1) {
            return TRUE;
        }
        //IIS
        elseif ($_SERVER['HTTPS'] === 'on') {
            return TRUE;
        }
        //other servers
        elseif ($_SERVER['SERVER_PORT'] == 443){
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * Use isSSL() instead
     * @deprecated deprecated since version 1.3
     */
    public function is_SSL(){
        return $this->isSSL();
    }

    /**
     * Convert DB result into XML string for RESTful api.
     * <code>
     * public function listUser(){
     *     $user = new User;
     *     $rs = $user->find();
     *     $this->toXML($rs, true);
     * }
     * </code>
     * @param mixed $result Result of a DB query. eg. $user->find();
     * @param bool $output Output the result automatically.
     * @param bool $setXMLContentType Set content type.
     * @param string $encoding Encoding of the result content. Default utf-8.
     * @return string XML string
     */
    public function toXML($result, $output=false, $setXMLContentType=false, $encoding='utf-8'){
        $str = '<?xml version="1.0" encoding="utf-8"?><result>';
        foreach($result as $kk=>$vv){
            $cls = get_class($vv);
            $str .= '<' . $cls . '>';
            foreach($vv as $k=>$v){
                if($k!='_table' && $k!='_fields' && $k!='_primarykey'){
                    if(is_array($v)){
                        //print_r($v);
                        //exit;
                        $str .= '<' . $k . '>';
                        foreach($v as $v0){
                            $str .= '<data>';
                            foreach($v0 as $k1=>$v1){
                                if($k1!='_table' && $k1!='_fields' && $k1!='_primarykey'){
                                    if(is_array($v1)){
                                        $str .= '<' . $k1 . '>';
                                        foreach($v1 as $v2){
                                            $str .= '<data>';
                                            foreach($v2 as $k3=>$v3){
                                                if($k3!='_table' && $k3!='_fields' && $k3!='_primarykey'){
                                                    $str .= '<'. $k3 . '><![CDATA[' . $v3 . ']]></'. $k3 . '>';
                                                }
                                            }
                                            $str .= '</data>';
                                        }
                                        $str .= '</' . $k1 . '>';
                                    }else{
                                        $str .= '<'. $k1 . '><![CDATA[' . $v1 . ']]></'. $k1 . '>';
                                    }
                                }
                            }
                            $str .= '</data>';
                        }
                        $str .= '</' . $k . '>';

                    }else{
                        $str .= '<'. $k . '>' . $v . '</'. $k . '>';
                    }
                }
            }
            $str .= '</' . $cls . '>';
        }
        $str .= '</result>';
        if($setXMLContentType===true)
            $this->setContentType('xml', $encoding);
        if($output===true)
            echo $str;
        return $str;
    }

    /**
     * Convert DB result into JSON string for RESTful api.
     * <code>
     * public function listUser(){
     *     $user = new User;
     *     $rs = $user->find();
     *     $this->toJSON($rs, true);
     * }
     * </code>
     * @param mixed $result Result of a DB query. eg. $user->find();
     * @param bool $output Output the result automatically.
     * @param bool $removeNullField Remove fields with null value from JSON string.
     * @param array $exceptField Remove fields that are null except the ones in this list.
     * @param array $mustRemoveFieldList Remove fields in this list.
     * @param bool $setJSONContentType Set content type.
     * @param string $encoding Encoding of the result content. Default utf-8.
     * @return string JSON string
     */
    public function toJSON($result, $output=false, $removeNullField=false, $exceptField=null, $mustRemoveFieldList=null, $setJSONContentType=true, $encoding='utf-8'){
        $rs = preg_replace(array('/\,\"\_table\"\:\".*\"/U', '/\,\"\_primarykey\"\:\".*\"/U', '/\,\"\_fields\"\:\[\".*\"\]/U'), '', json_encode($result));
        if($removeNullField){
            if($exceptField===null)
                $rs = preg_replace(array('/\,\"[^\"]+\"\:null/U', '/\{\"[^\"]+\"\:null\,/U'), array('','{'), $rs);
            else{
                $funca1 =  create_function('$matches',
                            'if(in_array($matches[1], array(\''. implode("','",$exceptField) .'\'))===false){
                                return "";
                            }
                            return $matches[0];');

                $funca2 =  create_function('$matches',
                            'if(in_array($matches[1], array(\''. implode("','",$exceptField) .'\'))===false){
                                return "{";
                            }
                            return $matches[0];');

                $rs = preg_replace_callback('/\,\"([^\"]+)\"\:null/U', $funca1, $rs);
                $rs = preg_replace_callback('/\{\"([^\"]+)\"\:null\,/U', $funca2, $rs);
            }
        }

        //remove fields in this array
        if($mustRemoveFieldList!==null){
            $funcb1 =  create_function('$matches',
                        'if(in_array($matches[1], array(\''. implode("','",$mustRemoveFieldList) .'\'))){
                            return "";
                        }
                        return $matches[0];');

            $funcb2 =  create_function('$matches',
                        'if(in_array($matches[1], array(\''. implode("','",$mustRemoveFieldList) .'\'))){
                            return "{";
                        }
                        return $matches[0];');
            
            $rs = preg_replace_callback(array('/\,\"([^\"]+)\"\:\".*\"/U', '/\,\"([^\"]+)\"\:\{.*\}/U', '/\,\"([^\"]+)\"\:\[.*\]/U', '/\,\"([^\"]+)\"\:([false|true|0-9|\.\-|null]+)/'), $funcb1, $rs);

            $rs = preg_replace_callback(array('/\{\"([^\"]+)\"\:\".*\"\,/U','/\{\"([^\"]+)\"\:\{.*\}\,/U'), $funcb2, $rs);

            preg_match('/(.*)(\[\{.*)\"('. implode('|',$mustRemoveFieldList) .')\"\:\[(.*)/', $rs, $m);
            
            if($m){
                if( $pos = strpos($m[4], '"}],"') ){
                    if($pos2 = strpos($m[4], '"}]},{')){
                        $d = substr($m[4], $pos2+5);
                        if(substr($m[2],-1)==','){
                            $m[2] = substr_replace($m[2], '},', -1);
                        }                
                    }
                    else if(strpos($m[4], ']},{')!==false){
                        $d = substr($m[4], strpos($m[4], ']},{')+3);  
                        if(substr($m[2],-1)==','){
                            $m[2] = substr_replace($m[2], '},', -1);
                        }
                    }
                    else if(strpos($m[4], '],"')===0){
                        $d = substr($m[4], strpos($m[4], '],"')+2);  
                    }                    
                    else if(strpos($m[4], '}],"')!==false){
                        $d = substr($m[4], strpos($m[4], '],"')+2);  
                    }
                    else{
                        $d = substr($m[4], $pos+4);
                    }
                }
                else{
                    $rs = preg_replace('/(\[\{.*)\"('. implode('|',$mustRemoveFieldList) .')\"\:\[.*\]\}(\,)?/U', '$1}', $rs);
                    $rs = preg_replace('/(\".*\"\:\".*\")\,\}(\,)?/U', '$1}$2', $rs);
                }

                if(isset($d)){
                    $rs = $m[1].$m[2].$d;
                }
            }
        }
        
        if($output===true){
			if($setJSONContentType===true)
				$this->setContentType('json', $encoding);
            echo $rs;
		}
        return $rs;
    }

	public function  __call($name,  $arguments) {
		if ($name == 'renderLayout') {
			throw new Exception('renderLayout is no longer supported by DooController. Please use $this->view()->renderLayout instead');
		}
	}

}
