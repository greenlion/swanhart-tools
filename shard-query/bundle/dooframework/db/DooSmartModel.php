<?php
/**
 * DooSmartModel class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooSmartModel is a smarter version of DooModel that provides smart caching of the Model data.
 *
 * <p>The model classes can extend DooSmartModel for more powerful ORM features which enable you to write shorter codes.
 * Extending this class is optional.</p>
 *
 * <p>All the extra ORM methods can be accessed in a static and non-static way. Example:</p>
 *
 * <code>
 * $food = new Food;
 * $food->getOne();
 * Food::_getOne();
 *
 * $food->count();
 * Food::_count();
 *
 * //Dynamic querying
 * $food->getById(14);
 * $food->getById_location(14, 'Malaysia');
 *
 * //Get only one item
 * $food->getById_first(14);
 * $food->getById_location_first(14, 'Malaysia');
 *
 * //Gets list of food with its food type
 * $food->relateFoodType();
 * $food->relateFoodType($food, array('limit'=>'first'));
 * $food->relateFoodType_first();
 *
 * //Static querying needs PHP 5.3 or above
 * Food::getById(14);
 * Food::getById_first(14);
 * Food::getById_location_first(14, 'Malaysia');
 * Food::relateFoodType();
 * Food::relateFoodType_first();
 * </code>
 *
 * <p>If you are using static querying methods such as Food::_count(), Food::getById(), you would need to setup the class name in your Model constructor.</p>
 * <code>
 * Doo::loadCore('db/DooSmartModel');
 *
 * class Model extends DooSmartModel{
 *     function __construct(){
 *         parent::$className = __CLASS__;
 *         //OR parent::setupModel(__CLASS__);
 *     }
 * }
 * </code>
 *
 * <p>Cache are deleted automatically when Update/Insert/Delete operations occured.
 * If you need to manually clearing the Model cache, use purgeCache()
 * </p>
 * <code>
 * $food->purgeCache();
 * //OR
 * Food::_purgeCache($food);
 * </code>
 *
 * Please check the database demo MainController's test() method for some example usage.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooSmartModel.php 1000 2009-08-28 11:43:26
 * @package doo.db
 * @since 1.2
 */
class DooSmartModel{
    /**
     * Determine whether the DB field names should be case sensitive.
     * @var bool
     */
    protected static $caseSensitive = false;

    /**
     * The class name of the Model
     * @var string
     */
    protected static $className = __CLASS__;

    /**
     * Data cache mode: file, apc, memcache, xcache, eaccelerator.
     * @var string
     */
    public static $cacheMode;

    /**
     * Constructor of a Model. Sets the model class properties with a list of keys & values.
     * @param array $properties Array of data (keys and values) to set the model properties
     */
    public function __construct($properties=null){
        if($properties!==null){
            foreach($properties as $k=>$v){
                if(in_array($k, $this->_fields))
                    $this->{$k} = $v;
            }
        }
    }

    /**
     * Setup the model. Use if needed with constructor.
     *
     * @param string $class Class name of the Model
     * @param string $cacheMode Data cache mode: file, apc, memcache, xcache, eaccelerator
     * @param bool $caseSensitive Determine whether the DB field names should be case sensitive.
     */
    protected function setupModel( $class=__CLASS__, $cacheMode='file', $caseSensitive=false ){
        self::$className = $class;
        self::$cacheMode = $cacheMode;
        self::$caseSensitive = $caseSensitive;
    }

    /**
     * Validate the Model with the rules defined in getVRules()
     *
     * @param string $checkMode Validation mode. all, all_one, skip
	 * @param string $requireMode Require Check Mode. null, nullempty
     * @return array Return array of errors if exists. Return null if data passes the validation rules.
     */
    public function validate($checkMode='all', $requireMode='null'){
        //all, all_one, skip
        Doo::loadHelper('DooValidator');
        $v = new DooValidator;
        $v->checkMode = $checkMode;
		$v->requireMode = $requireMode;
        return $v->validate(get_object_vars($this), $this->getVRules());
    }

    /**
     * Validate the Model with the rules defined in getVRules()
     *
     * @param object $model Model object to be validated.
     * @param string $checkMode Validation mode. all, all_one, skip
	 * @param string $requireMode Require Check Mode. null, nullempty
     * @return array Return array of errors if exists. Return null if data passes the validation rules.
     */
    public static function _validate($model, $checkMode='all', $requireMode='null'){
        //all, all_one, skip
        Doo::loadHelper('DooValidator');
        $v = new DooValidator;
        $v->checkMode = $checkMode;
		$v->requireMode = $requireMode;
        return $v->validate(get_object_vars($model), $model->getVRules());
    }

    /**
     * Generate unique ID for a certain query based on Model name and method & options.
     * @param object $model The model object to be query
     * @param string $accessMethod Accessed query method name
     * @param array $options Options used in the query
     * @return string Unique cache ID
     */
    protected static function toCacheId($model, $accessMethod, $options=null){
        if($options!==null){
            ksort($options);
            $id = self::$className.'-'. $accessMethod . serialize($options);
        }else
            $id = self::$className.'-'. $accessMethod ;

        $obj = get_object_vars($model);
        $wheresql ='';
        foreach($obj as $o=>$v){
            if(isset($v) && in_array($o, $model->_fields)){
               $id .= '#'.$o.'='.$v;
            }
        }
        //echo '<h1>'.md5($id).'</h1>';
        return $id;
    }

    /**
     * Retrieve cache by ID
     * @param string $id
     * @return mixed
     */
    protected static function getCache($id){
        if(self::$cacheMode===null || self::$cacheMode=='file'){
            if($rs = Doo::cache()->getIn('mdl_'.self::$className, $id)){
                //echo '<br>File cached version<br>';
                return $rs;
            }
        }
        else if(self::$cacheMode=='php'){
            if($rs = Doo::cache('php')->getIn('mdl_'.self::$className, $id)){
                //echo '<br>PHP cached version<br>';
                return $rs;
            }
        }
        else{
            if($rs = Doo::cache(self::$cacheMode)->get(Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_PATH . $id)){
                if($rs instanceof ArrayObject)
                    return $rs->getArrayCopy();
                return $rs;
            }
        }
    }

    /**
     * Store cache with a unique ID
     * @param string $id
     * @param mixed $value
     */
    protected static function setCache($id, $value){
        //if file based cache then store in seperate folder
        if(self::$cacheMode===null || self::$cacheMode=='file'){
            Doo::cache()->setIn('mdl_'.self::$className, $id, $value);
        }
        else if(self::$cacheMode=='php'){
            Doo::cache('php')->setIn('mdl_'.self::$className, $id, $value);
        }
        else{
            //need to store the list of Model cache to be purged later on for Memory based cache.
            $keysId = Doo::conf(self::$cacheMode)->SITE_PATH . 'mdl_'.self::$className;
            if($keys = Doo::cache(self::$cacheMode)->get($keysId)){
                $listOfModelCache = $keys->getArrayCopy();
                $listOfModelCache[] = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_PATH . $id;
            }else{
                $listOfModelCache = array();
                $listOfModelCache[] = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_PATH . $id;
            }
            if(is_array($value))
                Doo::cache(self::$cacheMode)->set(Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_PATH . $id, new ArrayObject($value));
            else
                Doo::cache(self::$cacheMode)->set(Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_PATH . $id, $value);
            Doo::cache(self::$cacheMode)->set($keysId, new ArrayObject($listOfModelCache));
        }
    }

    /**
     * Delete all the cache on the Model
     * @param array $rmodels Related model names to be deleted from cache
     */
    public function purgeCache($rmodels=null){
        self::$className = get_class($this);
        self::_purgeCache($rmodels);
    }

    /**
     * Delete all the cache on the Model
     * @param array $rmodels Related model names to be deleted from cache
     */
    public static function _purgeCache($rmodels=null){
        if(self::$cacheMode===null || self::$cacheMode=='file'){
            Doo::cache()->flushAllIn('mdl_'.self::$className);
        }
        else if(self::$cacheMode=='php'){
            Doo::cache('php')->flushAllIn('mdl_'.self::$className);
        }
        else{
            //loop and get the list and delete those start with the Model name, then delete them
            $keysId = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_PATH . 'mdl_'.self::$className;
            if($keys = Doo::cache(self::$cacheMode)->get($keysId)){
                $listOfModelCache = $keys->getArrayCopy();
                foreach($listOfModelCache as $k){
                    //echo '<br>Deleting '. $k .' from memory';
                    Doo::cache(self::$cacheMode)->flush($k);
                }
            }
            Doo::cache(self::$cacheMode)->flush($keysId);
        }
        if($rmodels!==null){
            if(self::$cacheMode===null || self::$cacheMode=='file'){
                foreach($rmodels as $r)
                    Doo::cache()->flushAllIn('mdl_'.get_class($r));
            }else{
                foreach($rmodels as $r){
                    //loop and get the list and delete those start with the Model name, then delete them
                    $keysId = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_PATH . 'mdl_'.get_class($r);
                    if($keys = Doo::cache(self::$cacheMode)->get($keysId)){
                        $listOfModelCache = $keys->getArrayCopy();
                        foreach($listOfModelCache as $k){
                            //echo '<br>Deleting '. $k .' from memory';
                            Doo::cache(self::$cacheMode)->flush($k);
                        }
                        Doo::cache(self::$cacheMode)->flush($keysId);
                    }
                }
            }
        }
    }

    /**
     * Change cache mode of the model.
     * @param string $cacheMode Data cache mode: file, apc, memcache, xcache, eaccelerator
     */
    public function toggleCacheMode($cacheMode){
        self::$cacheMode = $cacheMode;
    }

    /**
     * Change cache mode of the model.
     * @param string $cacheMode Data cache mode: file, apc, memcache, xcache, eaccelerator
     */
    public static function _toggleCacheMode($cacheMode){
        self::$cacheMode = $cacheMode;
    }

    //-------------- shorthands --------------------------
    /**
     * Shorthand for Doo::db()
     * @return DooSqlMagic
     */
    public function db(){
        return Doo::db();
    }

    /**
     * Commits a transaction. Transactions can be nestable.
     */
    public function commit(){
        Doo::db()->commit();
    }

    /**
     * Initiates a transaction. Transactions can be nestable.
     */
    public function beginTransaction(){
        Doo::db()->beginTransaction();
    }

    /**
     * Rolls back a transaction. Transactions can be nestable.
     */
    public function rollBack(){
        Doo::db()->rollBack();
    }

    /**
     * Retrieve the total records in a table. COUNT()
     *
     * @param array $options Options for the query. Available options see @see find() and additional 'distinct' option
     * @return int total of records
     */
    public function count($options=null){
		$options['select'] = isset($options['having']) ? $options['select'] . ', ' : '';        
		if (isset($options['distinct']) && $options['distinct'] == true) {
			$options['select'] = 'COUNT(DISTINCT '. $this->_table . '.' . $this->_fields[0] .') as _doototal';
		} else {
			$options['select'] = 'COUNT('. $this->_table . '.' . $this->_fields[0] .') as _doototal';
		}
        $options['asArray'] = true;
        $options['limit'] = 1;

        $id = self::toCacheId($this, 'count', $options);
        if($rs = self::getCache($id)) return $rs;

        $value = Doo::db()->find($this, $options);
        $value =  $value['_doototal'];

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Retrieve the total records in a table. COUNT()
     *
     * @param object $model The model object to be select.
     * @param array $options Options for the query. Available options see @see find() and additional 'distinct' option
     * @return int total of records
     */
    public static function _count($model=null, $options=null){
        if($model===null)
            $model = new self::$className;

		$options['select'] = isset($options['having']) ? $options['select'] . ', ' : '';
		if (isset($options['distinct']) && $options['distinct'] == true) {
			$options['select'] = 'COUNT(DISTINCT '. $model->_table . '.' . $model->_fields[0] .') as _doototal';
		} else {
			$options['select'] = 'COUNT('. $model->_table . '.' . $model->_fields[0] .') as _doototal';
		}

		$options['asArray'] = true;
        $options['limit'] = 1;

        $id = self::toCacheId($model, 'count', $options);
        if($rs = self::getCache($id)) return $rs;

        $value = Doo::db()->find($model, $options);
        $value = $value['_doototal'];

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Find a record. (Prepares and execute the SELECT statements)
     * @param array $opt Associative array of options to generate the SELECT statement. Supported: <i>where, limit, select, param, asc, desc, custom, asArray, groupby,</i>
     * @return mixed A model object or associateve array of the queried result
     */
    public function find($opt=null){
        $id = self::toCacheId($this, 'find', $opt);
        if($rs = self::getCache($id)) return $rs;

        $value = Doo::db()->find($this, $opt);

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Find a record. (Prepares and execute the SELECT statements)
     * @param mixed $model The model class name or object to be select
     * @param array $opt Associative array of options to generate the SELECT statement. Supported: <i>where, limit, select, param, asc, desc, custom, groupby, asArray</i>
     * @return mixed A model object or associateve array of the queried result
     */
    public static function _find($model, $opt=null){
        if($model===null)
            $model = new self::$className;

        $id = self::toCacheId($model, 'find', $opt);
        if($rs = self::getCache($id)) return $rs;

        $value = Doo::db()->find($model, $opt);

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Retrieve model by one record.
     *
     * @param array $options Options for the query. Available options see @see find()
     * @return mixed A model object or associateve array of the queried result
     */
    public function getOne($opt=null){
        if($opt!==null){
            $opt['limit'] = 1;
            $id = self::toCacheId($this, 'find', $opt);
            if($rs = self::getCache($id)) return $rs;
            $value = Doo::db()->find($this, $opt);
        }else{
            $id = self::toCacheId($this, 'find', array('limit'=>1));
            if($rs = self::getCache($id)) return $rs;
            $value = Doo::db()->find($this, array('limit'=>1));
        }

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Retrieve model by one record.
     *
     * @param object $model The model object to be select.
     * @param array $options Options for the query. Available options see @see find()
     * @return mixed A model object or associateve array of the queried result
     */
    public static function _getOne($model=null, $opt=null){
        if($model===null)
            $model = new self::$className;

        if($opt!==null){
            $opt['limit'] = 1;
            $id = self::toCacheId($model, 'find', $opt);
            if($rs = self::getCache($id)) return $rs;
            $value = Doo::db()->find($model, $opt);
        }else{
            $id = self::toCacheId($model, 'find', array('limit'=>1));
            if($rs = self::getCache($id)) return $rs;
            $value = Doo::db()->find($model, array('limit'=>1));
        }

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Retrieve a list of paginated data. To be used with DooPager
     *
     * @param string $limit String for the limit query, eg. '6,10'
     * @param string $asc Fields to be sorted Ascendingly. Use comma to seperate multiple fields, eg. 'name,timecreated'
     * @param string $desc Fields to be sorted Descendingly. Use comma to seperate multiple fields, eg. 'name,timecreated'
     * @param array $options Options for the query. Available options see @see find()
     * @return mixed A model object or associateve array of the queried result
     */
    public function limit($limit=1, $asc='', $desc='', $options=null){
        if($asc!='' || $desc!='' || $options!==null){
            $options['limit'] = $limit;
            if($asc!=''){
                $options['asc'] = $asc;
            }
            if($desc!=''){
                $options['desc'] = $desc;
            }
            if($asc!='' && $desc!=''){
                $options['asc'] = $asc;
                $options['custom'] = ','. $desc .' DESC';
            }
            $id = self::toCacheId($this, 'find', $options);
            if($rs = self::getCache($id)) return $rs;
            $value = Doo::db()->find($this, $options);
        }else{
            $id = self::toCacheId($this, 'find', array('limit'=>$limit));
            if($rs = self::getCache($id)) return $rs;
            $value = Doo::db()->find($this, array('limit'=>$limit));
        }

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Retrieve a list of paginated data. To be used with DooPager
     *
     * @param object $model The model object to be select.
     * @param string $limit String for the limit query, eg. '6,10'
     * @param string $asc Fields to be sorted Ascendingly. Use comma to seperate multiple fields, eg. 'name,timecreated'
     * @param string $desc Fields to be sorted Descendingly. Use comma to seperate multiple fields, eg. 'name,timecreated'
     * @param array $options Options for the query. Available options see @see find()
     * @return mixed A model object or associateve array of the queried result
     */
    public static function _limit($limit=1, $asc='', $desc='', $options=null){
        if($asc!='' || $desc!='' || $options!==null){
            $options['limit'] = $limit;
            if($asc!=''){
                $options['asc'] = $asc;
            }
            if($desc!=''){
                $options['desc'] = $desc;
            }
            if($asc!='' && $desc!=''){
                $options['asc'] = $asc;
                $options['custom'] = ','. $desc .' DESC';
            }
            $id = self::toCacheId(new self::$className, 'find', $options);
            if($rs = self::getCache($id)) return $rs;
            $value = Doo::db()->find(self::$className, $options);
        }else{
            $id = self::toCacheId(new self::$className, 'find', array('limit'=>$limit));
            if($rs = self::getCache($id)) return $rs;
            $value = Doo::db()->find(self::$className, array('limit'=>$limit));
        }

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Find a record and its associated model. Relational search. (Prepares and execute the SELECT statements)
     * @param string $rmodel The related model class name.
     * @param array $opt Associative array of options to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, groupby, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model object(s) or associateve array of the queried result
     */
    public function relate($rmodel, $options=null){
        if(is_string($rmodel)){
            Doo::loadModel($rmodel);
            $id = self::toCacheId($this, 'relate' . $rmodel, $options);
        }else{
            $rcls = get_class($rmodel);
            Doo::loadModel($rcls);
            $id = self::toCacheId($this, 'relate' . $rcls, $options);
        }
        if($rs = self::getCache($id)) return $rs;
        $value = Doo::db()->relate($this, $rmodel, $options);

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }

    /**
     * Combine relational search results (combine multiple relates).
     *
     * Example:
     * <code>
     * $food = new Food;
     * $food->relateMany(array('Recipe','Article','FoodType'))
     * </code>
     *
     * @param array $rmodel The related models class names.
     * @param array $opt Array of options for each related model to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, groupby, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model objects of the queried result
     */
	public function relateMany($rmodel, $opt=null){
        return Doo::db()->relateMany($this, $rmodel, $opt);
    }

    /**
     * Expand related models (Tree Relationships).
     *
     * Example:
     * <code>
     * $recipe = new Recipe;
     * $recipe->relateExpand(array('Food','Article'))
     * </code>
     *
     * @param array $rmodel The related models class names.
     * @param array $opt Array of options for each related model to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, groupby, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model objects of the queried result
     */
	public function relateExpand($rmodel, $opt=null){
        return Doo::db()->relateExpand($this, $rmodel, $opt);
    }

    /**
     * Find a record and its associated model. Relational search. (Prepares and execute the SELECT statements)
     * @param mixed $model The model class name or object to be select.
     * @param string $rmodel The related model class name.
     * @param array $opt Associative array of options to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, groupby, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model object(s) or associateve array of the queried result
     */
    public static function _relate($model, $rmodel, $options=null){
        if($model==null || $model=='')
            $model = self::$className;

        if(is_string($rmodel)){
            if(is_string($model)){
                Doo::loadModel($rmodel);
                $id = self::toCacheId(new $model, 'relate' . $rmodel, $options);
            }else{
                Doo::loadModel($rmodel);
                $id = self::toCacheId($model, 'relate' . $rmodel, $options);
            }
        }else{
            if(is_string($model)){
                $rcls = get_class($rmodel);
                Doo::loadModel($rcls);
                $id = self::toCacheId(new $model, 'relate' . $rcls, $options);
            }else{
                $rcls = get_class($rmodel);
                Doo::loadModel($rcls);
                $id = self::toCacheId($model, 'relate' . $rcls, $options);
            }
        }

        if($rs = self::getCache($id)) return $rs;
        $value = Doo::db()->relate($model, $rmodel, $options);

        //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
        if($value) self::setCache($id, $value);
        return $value;
    }


    //--------------- dynamic querying --------------
    public function __call($name, $args){

        // $food->getById( $id );
        // $food->getById(14);
        // $food->getById(14, array('limit'=>1)) ;
        // $food->getById_location(14, 'Malaysia') ;
        // $food->getById_location(14, 'Malaysia', array('limit'=>1)) ;
        if(strpos($name, 'get')===0){
            if(self::$caseSensitive==false){
                $field = strtolower( substr($name,5));
            }else{
                $field = substr($name,5);
            }

            // if end with _first, add 'limit'=>'first' to Option array
            if( substr($name,-6,strlen($field)) == '_first' ){
                $field = str_replace('_first', '', $field);
                $first['limit'] = 1;
            }

            // underscore _ as AND in SQL
            if(strpos($field, '_')!==false){
                $field = explode('_', $field);
            }

            $clsname = get_class($this);
            $obj = new $clsname;

            if(is_string($field)){
                $obj->{$field} = $args[0];

                //if more than the field total, it must be an option array
                if(sizeof($args)>1){
                    if(isset($first))
                        $args[1]['limit'] = 1;
                    $id = self::toCacheId($obj, 'find', $args[1]);
                    if($rs = self::getCache($id)) return $rs;
                    $value = Doo::db()->find($obj, $args[1]);
                }else{
                    if(isset($first)){
                        $id = self::toCacheId($obj, 'find', $first);
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->find($obj, $first);
                    }else{
                        $id = self::toCacheId($obj, 'find');
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->find($obj);
                    }
                }
            }
            else{
                $i=0;
                foreach($field as $f){
                    $obj->{$f} = $args[$i++];
                }

                //if more than the field total, it must be an option array
                if(sizeof($args)>$i){
                    if(isset($first))
                        $args[$i]['limit'] = 1;
                    $id = self::toCacheId($obj, 'find', $args[$i]);
                    if($rs = self::getCache($id)) return $rs;
                    $value = Doo::db()->find($obj, $args[$i]);
                }else{
                    if(isset($first)){
                        $id = self::toCacheId($obj, 'find', $first);
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->find($obj, $first);
                    }else{
                        $id = self::toCacheId($obj, 'find');
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->find($obj);
                    }
                }
            }

            //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
            if($value) self::setCache($id, $value);
            return $value;
        }

        # relateTheRelatedModelClassName
        //$food->relateFoodType();
        //$food->relateFoodType( $optionsOrObject );  if 1 args, it will be option or object
        //$food->relateFoodType( $food, $options );  if more than 1
        # You can get only one by
        //$food->relateFoodType_first();    this adds the 'limit'=>'first' to the Options
        else if(strpos($name, 'relate')===0){
            $relatedClass = substr($name,6);

            // if end with _first, add 'limit'=>'first' to Option array
            if( substr($name,-6,strlen($relatedClass)) == '_first' ){
                $relatedClass = str_replace('_first', '', $relatedClass);
                $first['limit'] = 'first';
                if(sizeof($args)===0){
                    $args[0] = $first;
                }
                else{
                    if(is_array($args[0])){
                        $args[0]['limit'] = 'first';
                    }else{
                        $args[1]['limit'] = 'first';
                    }
                }
            }

            if(sizeof($args)===0){
                Doo::loadModel($relatedClass);
                $id = self::toCacheId($this, 'relate' . $relatedClass);
                if($rs = self::getCache($id)) return $rs;
                $value = Doo::db()->relate( $this, $relatedClass);
            }
            else if(sizeof($args)===1){
                if(is_array($args[0])){
                    Doo::loadModel($relatedClass);
                    $id = self::toCacheId($this, 'relate' . $relatedClass, $args[0]);
                    if($rs = self::getCache($id)) return $rs;
                    $value = Doo::db()->relate( $this, $relatedClass, $args[0]);
                }else{
                    if(isset($first)){
                        Doo::loadModel($relatedClass);
                        $id = self::toCacheId($args[0], 'relate' . $relatedClass, $first);
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->relate( $args[0], $relatedClass, $first);
                    }else{
                        Doo::loadModel($relatedClass);
                        $id = self::toCacheId($args[0], 'relate' . $relatedClass);
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->relate( $args[0], $relatedClass);
                    }
                }
            }else{
                Doo::loadModel($relatedClass);
                $id = self::toCacheId($args[0], 'relate' . $relatedClass, $args[1]);
                if($rs = self::getCache($id)) return $rs;
                $value = Doo::db()->relate( $args[0], $relatedClass, $args[1]);
            }

            //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
            if($value) self::setCache($id, $value);
            return $value;
        }
    }

    public static function __callStatic($name, $args){
        // Food::getById( $id );
        // Food::getById(14);
        // Food::getById(14, array('limit'=>1)) ;
        // Food::getById__location(14, 'Malaysia') ;
        // Food::getById__location(14, 'Malaysia', array('limit'=>1)) ;
        if(strpos($name, 'get')===0){
            if(self::$caseSensitive==false){
                $field = strtolower( substr($name,5));
            }else{
                $field = substr($name,5);
            }

            // if end with _first, add 'limit'=>'first' to Option array
            if( substr($name,-7,strlen($field)) == '__first' ){
                $field = str_replace('__first', '', $field);
                $first['limit'] = 1;
            }

            // underscore _ as AND in SQL
            if(strpos($field, '__')!==false){
                $field = explode('__', $field);
            }

            $clsname = self::$className;
            $obj = new $clsname;

            if(is_string($field)){
                $obj->{$field} = $args[0];

                //if more than the field total, it must be an option array
                if(sizeof($args)>1){
                    if(isset($first))
                        $args[1]['limit'] = 1;
                    $id = self::toCacheId($obj, 'find', $args[1]);
                    if($rs = self::getCache($id)) return $rs;
                    $value = Doo::db()->find($obj, $args[1]);
                }else{
                    if(isset($first)){
                        $id = self::toCacheId($obj, 'find', $first);
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->find($obj, $first);
                    }else{
                        $id = self::toCacheId($obj, 'find');
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->find($obj);
                    }
                }
            }
            else{
                $i=0;
                foreach($field as $f){
                    $obj->{$f} = $args[$i++];
                }

                //if more than the field total, it must be an option array
                if(sizeof($args)>$i){
                    if(isset($first))
                        $args[$i]['limit'] = 1;

                    $id = self::toCacheId($obj, 'find', $args[$i]);
                    if($rs = self::getCache($id)) return $rs;
                    $value = Doo::db()->find($obj, $args[$i]);
                }else{
                    if(isset($first)){
                        $id = self::toCacheId($obj, 'find', $first);
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->find($obj, $first);
                    }else{
                        $id = self::toCacheId($obj, 'find');
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->find($obj);
                    }
                }
            }

            //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
            if($value) self::setCache($id, $value);
            return $value;
        }

        # relateTheRelatedModelClassName
        // Food::relateFoodType();
        // Food::relateFoodType( $optionsOrObject );  if 1 args, it will be option or object
        // Food::relateFoodType( $food, $options );  if more than 1
        # You can get only one by
        //$food->relateFoodType__first();    this adds the 'limit'=>'first' to the Options
        else if(strpos($name, 'relate')===0){
            $relatedClass = substr($name,6);

            // if end with _first, add 'limit'=>'first' to Option array
            if( substr($name,-7,strlen($relatedClass)) == '__first' ){
                $relatedClass = str_replace('__first', '', $relatedClass);
                $first['limit'] = 'first';
                if(sizeof($args)===0){
                    $args[0] = $first;
                }
                else{
                    if(is_array($args[0])){
                        $args[0]['limit'] = 'first';
                    }else{
                        $args[1]['limit'] = 'first';
                    }
                }
            }

            if(sizeof($args)===0){
                Doo::loadModel($relatedClass);
                $id = self::toCacheId(new self::$className, 'relate' . $relatedClass);
                if($rs = self::getCache($id)) return $rs;
                $value =  Doo::db()->relate( self::$className, $relatedClass);
            }
            else if(sizeof($args)===1){
                if(is_array($args[0])){
                    Doo::loadModel($relatedClass);
                    $id = self::toCacheId(new self::$className, 'relate' . $relatedClass, $args[0]);
                    if($rs = self::getCache($id)) return $rs;
                    $value = Doo::db()->relate( self::$className, $relatedClass, $args[0]);
                }else{
                    if(isset($first)){
                        Doo::loadModel($relatedClass);
                        $id = self::toCacheId(new self::$className, 'relate' . $relatedClass, $first);
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->relate( $args[0], $relatedClass, $first);
                    }else{
                        Doo::loadModel($relatedClass);
                        $id = self::toCacheId($args[0], 'relate' . $relatedClass);
                        if($rs = self::getCache($id)) return $rs;
                        $value = Doo::db()->relate( $args[0], $relatedClass);
                    }
                }
            }else{
                Doo::loadModel($relatedClass);
                $id = self::toCacheId($args[0], 'relate' . $relatedClass);
                if($rs = self::getCache($id)) return $rs;
                $value = Doo::db()->relate( $args[0], $relatedClass, $args[1]);
            }

            //if is null or false or 0 then dun store it because the cache can't differenciate the Empty values
            if($value) self::setCache($id, $value);
            return $value;
        }
    }

    //---- queries that clear the cache ----

    /**
     * Adds a new record. (Prepares and execute the INSERT statements)
     * @return int The inserted record's Id
     */
    public function insert(){
        $this->purgeCache();
        return Doo::db()->insert($this);
    }

    /**
     * Adds a new record with a list of keys & values (assoc array) (Prepares and execute the INSERT statements)
     * @param array $data Array of data (keys and values) to be insert
     * @return int The inserted record's Id
     */
    public function insertAttributes($data){
        $this->purgeCache();
        return Doo::db()->insertAttributes($this, $data);
    }
    
    /**
     * Use insertAttributes() instead
     * @deprecated deprecated since version 1.3
     */
    public function insert_attributes($data){
        return $this->insertAttributes($data);
    }

    /**
     * Adds a new record with its associated models. Relational insert. (Prepares and execute the INSERT statements)
     * @param array $rmodels A list of associated model objects to be insert along with the main model.
     * @return int The inserted record's Id
     */
    public function relatedInsert($rmodels){
        $this->purgeCache($rmodels);
        return Doo::db()->relatedInsert($this, $rmodels);
    }

    /**
     * Update an existing record. (Prepares and execute the UPDATE statements)
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, field, param</i>
     * @return int Number of rows affected
     */
    public function update($opt=NULL){
        $this->purgeCache();
        return Doo::db()->update($this, $opt);
    }

    /**
     * Update an existing record with a list of keys & values (assoc array). (Prepares and execute the UPDATE statements)
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, field, param</i>
     * @return int Number of rows affected
     */
    public function update_attributes($data, $opt=NULL){
        $this->purgeCache();
        return Doo::db()->update_attributes($this, $data, $opt);
    }

    /**
     * Update an existing record with its associated models. Relational update. (Prepares and execute the UPDATE statements)
     * @param array $rmodels A list of associated model objects to be updated or insert along with the main model.
     * @param array $opt Assoc array of options to update the main model. Supported: <i>where, limit, field, param</i>
     */
    public function relatedUpdate($rmodels, $opt=NULL){
        $this->purgeCache($rmodels);
        return Doo::db()->relatedUpdate($this, $rmodels, $opt);
    }

    /**
     * Returns the last inserted record's id
     * @return int
     */
    public function lastInsertId(){
        return Doo::db()->lastInsertId();
    }

	/**
	 * Delete ALL existing records. (Prepares and executes the DELETE statement)
	 */
	public function deleteAll() {
		$this->purgeCache();
		return Doo::db()->deleteAll($this);
	}

    /**
     * Delete an existing record. (Prepares and execute the DELETE statements)
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, param</i>
     */
    public function delete($opt=NULL){
        $this->purgeCache();
        return Doo::db()->delete($this, $opt);
    }


    /**
     * Adds a new record. (Prepares and execute the INSERT statements)
     * @param object $model The model object to be insert.
     * @return int The inserted record's Id
     */
    public static function _insert($model){
        $model->purgeCache();
        return Doo::db()->insert($model);
    }

    /**
     * Adds a new record with a list of keys & values (assoc array) (Prepares and execute the INSERT statements)
     * @param object $model The model object to be insert.
     * @param array $data Array of data (keys and values) to be insert
     * @return int The inserted record's Id
     */
    public static function _insertAttributes($model, $data){
        $model->purgeCache();
        return Doo::db()->insertAttributes($model, $data);
    }

    /**
     * Use DooSmartModel::_insertAttributes() instead.
     * @deprecated deprecated since version 1.3
     */
    public static function _insert_attributes($model, $data){
        $model->purgeCache();
        return Doo::db()->insertAttributes($model, $data);
    }

    /**
     * Adds a new record with its associated models. Relational insert. (Prepares and execute the INSERT statements)
     * @param object $model The model object to be insert.
     * @param array $rmodels A list of associated model objects to be insert along with the main model.
     * @return int The inserted record's Id
     */
    public static function _relatedInsert($model, $rmodels){
        $model->purgeCache($rmodels);
        return Doo::db()->relatedInsert($model, $rmodels);
    }

    /**
     * Update an existing record with its associated models. Relational update. (Prepares and execute the UPDATE statements)
     * @param mixed $model The model object to be updated.
     * @param array $rmodels A list of associated model objects to be updated or insert along with the main model.
     * @param array $opt Assoc array of options to update the main model. Supported: <i>where, limit, field, param</i>
     */
    public static function _relatedUpdate($model, $rmodels, $opt=NULL){
        $model->purgeCache($rmodels);
        return Doo::db()->relatedUpdate($model, $rmodels, $opt);
    }

    /**
     * Update an existing record. (Prepares and execute the UPDATE statements)
     * @param mixed $model The model object to be updated.
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, field, param</i>
     */
    public static function _update($model, $opt=NULL){
        $model->purgeCache();
        return Doo::db()->update($model, $opt);
    }

    /**
     * Update an existing record with a list of keys & values (assoc array). (Prepares and execute the UPDATE statements)
     * @param mixed $model The model object to be updated.
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, field, param</i>
     */
    public static function _update_attributes($model, $data, $opt=NULL){
        $model->purgeCache();
        return Doo::db()->update($model, $data, $opt);
    }

    /**
     * Returns the last inserted record's id
     * @return int
     */
    public static function _lastInsertId(){
        return Doo::db()->lastInsertId();
    }

	/**
	 * Delete ALL existing records. (Prepares and executes the DELETE statement)
	 */
	public static function _deleteAll($model, $opt) {
		$model->purgeCache();
		return Doo::db()->deleteAll($model);
	}

    /**
     * Delete an existing record. (Prepares and execute the DELETE statements)
     * @param mixed $model The model object to be deleted.
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, param</i>
     */
    public static function _delete($model, $opt=NULL){
        $model->purgeCache();
        return Doo::db()->delete($model, $opt);
    }

    public static function __set_state($properties){
        $obj = new self::$className;
        foreach($properties as $k=>$v){
            $obj->{$k} = $v;
        }
        return $obj;
    }
}
