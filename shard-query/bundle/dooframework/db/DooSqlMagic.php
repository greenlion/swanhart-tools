<?php
/**
 * DooSqlMagic class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooSqlMagic is a fast ORM tool which handles relational data.
 *
 * <p>It generates SQL query on the fly and escaping/quoting record values to prevent sql injection.
 * You can do CRUD operations in an easier way with DooSqlMagic instead of writing queries manually.</p>
 *
 * <p>Database models does not need to extend any classes with DooSqlMagic. Database table relationships are defined in a
 * centralized way in <i>SITE_PATH/protected/config/db.conf.php</i> instead of defining them in each Model class.</p>
 *
 * <p>Unlike most active records, DooSqlMagic class does not use any php Magic methods in order to gain higher performance.</p>
 *
 * <p>It does not implements the active record design pattern, a popular Object-Relational Mapping (ORM) technique.
 * However, it can still be used with some flavours of active record features.</p>
 *
 * <p>DooSqlMagic is more like a blend between Active record and DataMapper design pattern.</p>
 *
 * <p>Creating an instance of an DooSqlMagic class does not immediately connect to the RDBMS server.
 *  Use connect() or reconnect() to make the actual connection on demand.</p>
 *
 * Please check {@link http://www.doophp.com/doc/guide/database the Guide} for more details
 * about this class.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooSqlMagic.php 1000 2009-07-7 18:27:22
 * @package doo.db
 * @since 1.0
 */
class DooSqlMagic {

    /**
     * Enable/disable SQL tracking, to view SQL which has been queried, use showSQL()
     * @var bool
     */
    public $sql_tracking=false;

    /**
     * Determined whether the database connection is made.
     * @var bool
     */
    public $connected = false;
    
    /**
     * Path to the folder where the model class files are located. Ends with a slash. eg. /var/www/modelfiles/
     * @var string
     */
    public $modelPath;

    protected $map;
    protected $dbconfig;
    protected $dbconfig_list;
    protected $pdo;

    protected $transactionLevel = 0;

    protected $sql_list;

    const JOIN_LEFT = 'LEFT';
    const JOIN_RIGHT = 'RIGHT';
    const JOIN_LEFT_OUTER = 'LEFT OUTER';
    const JOIN_RIGHT_OUTER = 'RIGHT OUTER';
    const JOIN_INNER = 'INNER';
    const JOIN = '';
    
    public function loadModel($className, $createObj=false){
        if( class_exists($className, false)===true ){
            if($createObj===true){           
                return new $className;
            }
            return;
        }
        
        if(empty($this->modelPath)===true) {
            return Doo::loadModel($className, $createObj);
        }
        else {
            require_once $this->modelPath . $className . '.php';
            
            if($createObj===true){
                return new $className;
            }
        }
    }

    /**
     * Set the database configuration
     * @param array $dbconfig Associative array of the configurations
     * @param string $default_name Default configuration to be used
     */
    public function setDb($dbconfig, $default_name){
        $this->dbconfig_list = $dbconfig;
        $this->dbconfig = $dbconfig[$default_name];
    }

    /**
     * Get the default database configuration
     * @return array array(db_host, db_name, db_user, db_pwd, db_driver, db_connection_cache)
     */
    public function getDefaultDbConfig(){
        return $this->dbconfig;
    }

    /**
     * Set the database relationship mapping
     * @param array $dbmap Database relationship mapping
     */
    public function setMap($dbmap){
        $this->map = $dbmap;
    }

    /**
     * Append to DB relationship mapping
     * @param array $dbmap Database relationship mapping
     */
    public function appendMap( $dbmap ){
        if(is_array($this->map)===True)
            $this->map = array_merge( $this->map, $dbmap );
        else
            $this->map = $dbmap;
    }

    /**
     * Get DB relationship mapping
     * @return array Database relationship mapping
     */
    public function getMap(){
        return $this->map;
    }

    /**
     * Connects to the database with the default database connection configuration
     */
    public function connect(){
        if($this->dbconfig==NULL)return;

        try{
            if ($this->dbconfig[4]=='sqlite')
                $this->pdo = new PDO("{$this->dbconfig[4]}:{$this->dbconfig[0]}");
            else
                $this->pdo = new PDO("{$this->dbconfig[4]}:host={$this->dbconfig[0]};dbname={$this->dbconfig[1]}", $this->dbconfig[2], $this->dbconfig[3],array(PDO::ATTR_PERSISTENT => $this->dbconfig[5]));
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connected = true;
            if(isset($this->dbconfig['charset']) && isset($this->dbconfig['collate'])){
                $this->pdo->exec("SET NAMES '". $this->dbconfig['charset']. "' COLLATE '". $this->dbconfig['collate'] ."'");
            }
            else if(isset($this->dbconfig['charset']) ){
                $this->pdo->exec("SET NAMES '". $this->dbconfig['charset']. "'");
            }
        }catch(PDOException $e){
            throw new SqlMagicException('Failed to open the DB connection', SqlMagicException::DBConnectionError);
        }
    }

    /**
     * Reconnect to a database which has been defined in the database connection configurations
     * @param string $db_config_name Name/key of the database configuration
     */
    public function reconnect($db_config_name=Null){
        //$host='localhost', $db='', $user='', $password='', $driver='mysql', $persist=true
        if(isset($db_config_name)){
            $this->dbconfig = $dbconfig = $this->dbconfig_list[$db_config_name];
        }else{
            $dbconfig = $this->dbconfig;
        }
        try{
            if ($dbconfig[4]=='sqlite')
                $this->pdo = new PDO("{$dbconfig[4]}:{$dbconfig[0]}");
            else
                $this->pdo = new PDO("{$dbconfig[4]}:host={$dbconfig[0]};dbname={$dbconfig[1]}", $dbconfig[2], $dbconfig[3],array(PDO::ATTR_PERSISTENT => $dbconfig[5]));
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connected = true;
            if(isset($dbconfig['charset']) && isset($dbconfig['collate'])){
                $this->pdo->exec("SET NAMES '". $dbconfig['charset']. "' COLLATE '". $dbconfig['collate'] ."'");
            }
            else if(isset($dbconfig['charset']) ){
                $this->pdo->exec("SET NAMES '". $dbconfig['charset']. "'");
            }
        }catch(PDOException $e){
            throw new SqlMagicException('Failed to open the DB connection', SqlMagicException::DBConnectionError);
        }
    }

    /**
     * Close a database connection
     */
    public function disconnect(){
        $this->pdo = null;
        $this->connected = false;
    }
    
    /**
     * Initiates a transaction. Transactions can be nestable.
     */
    public function beginTransaction() {
        if($this->transactionLevel === 0){
            $this->pdo->beginTransaction();
        }
        else{
            $this->pdo->exec("SAVEPOINT LEVEL{$this->transactionLevel}");
        }
        $this->transactionLevel++;
    }

    /**
     * Commits a transaction. Transactions can be nestable.
     */
    public function commit() {
        $this->transactionLevel--;
        if($this->transactionLevel === 0){
            $this->pdo->commit();
        }
        else{
            $this->pdo->exec("RELEASE SAVEPOINT LEVEL{$this->transactionLevel}");
        }
    }

    /**
     * Rolls back a transaction. Transactions can be nestable.
     */
    public function rollBack() {
        $this->transactionLevel--;
        if($this->transactionLevel === 0){
            $this->pdo->rollBack();
        }
        else{
            $this->pdo->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->transactionLevel}");
        }
    }


    /**
     * Execute a query to the connected database
     * @param string $query SQL query prepared statement
     * @param array $param Values used in the prepared SQL
     * @return PDOStatement
     */
    public function query($query, $param=null){
        if($this->sql_tracking===true){
            $querytrack = $query;
            //if params used in sql, replace them into the sql string for logging
            if($param!=null){
                if(isset($param[0])){
                    $querytrack = explode('?',$querytrack);
                    $q = $querytrack[0];
                    foreach($querytrack as $k=>$v){
                        if($k===0)continue;
						if (isset($param[$k-1])) {
							$q .=  "'".$param[$k-1]."'" . $querytrack[$k];
						} else {
							throw new Exception('Insufficent paramters provided for query');
						}
                    }
                    $querytrack = $q;
                }else{
                    //named param used
                    foreach($param as $k=>$v)
                        $querytrack = str_replace($k, "'$v'", $querytrack);
                }
            }
            $this->sql_list[] = $querytrack;
			//echo '<pre>' . print_r($querytrack, true) . '</pre>';
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        if($param==null)
            $stmt->execute();
        else
            $stmt->execute($param);
        return $stmt;
    }

    /*
    * Execute a query and Fetch single row
    * @param string $query SQL query prepared statement
    * @param array $param Values used in the prepared SQL
    * @param int $fetchMode  PDO Fetch mode for the statement
	* @param mixed $fetchModeOptionalParam1 First Optional Param for PDOStatement::setFetchMode
    * @param mixed $fetchModeOptionalParam2 Second Optional Param for PDOStatement::setFetchMode
    * @return Returns an array a row from the result set
    */
    public function fetchRow($query, $param = null, $fetchMode = null, $fetchModeOptionalParam1=null, $fetchModeOptionalParam2=array()) {
        $stmt = $this->query($query, $param);
		if($fetchMode!==null) {
			switch($fetchMode) {
				case PDO::FETCH_COLUMN:
					$stmt->setFetchMode($fetchMode, ($fetchModeOptionalParam1 !== null) ? $fetchModeOptionalParam1 : 0);
					break;
				case PDO::FETCH_CLASS:
					$stmt->setFetchMode($fetchMode, $fetchModeOptionalParam1, $fetchModeOptionalParam2);
					break;
				case PDO::FETCH_INTO:
					$stmt->setFetchMode($fetchMode, $fetchModeOptionalParam1);
					break;
				default:
					$stmt->setFetchMode($fetchMode);
			}
		}
        return $stmt->fetch();
    }

   /*
    * Execute a query and Fetch multiple rows
    * @param string $query SQL query prepared statement
    * @param array $param Values used in the prepared SQL
    * @param int $fetchMode  PDO Fetch mode for the statement
    * @param mixed $fetchModeOptionalParam1 First Optional Param for PDOStatement::setFetchMode
    * @param mixed $fetchModeOptionalParam2 Second Optional Param for PDOStatement::setFetchMode
    * @return Returns an array containing all of the result set rows
    */
    public function fetchAll($query, $param = null, $fetchMode = null, $fetchModeOptionalParam1=null, $fetchModeOptionalParam2=array()) {
        $stmt = $this->query($query, $param);
		if($fetchMode!==null) {
			switch($fetchMode) {
				case PDO::FETCH_COLUMN:
					$stmt->setFetchMode($fetchMode, ($fetchModeOptionalParam1 !== null) ? $fetchModeOptionalParam1 : 0);
					break;
				case PDO::FETCH_CLASS:
					$stmt->setFetchMode($fetchMode, $fetchModeOptionalParam1, $fetchModeOptionalParam2);
					break;
				case PDO::FETCH_INTO:
					$stmt->setFetchMode($fetchMode, $fetchModeOptionalParam1);
					break;
				default:
					$stmt->setFetchMode($fetchMode);
			}
		}
        return $stmt->fetchAll();
    }

    /**
     * Use showSQL() instead.
     * @deprecated deprecated since version 1.3
     * @return array
     */
    public function show_sql(){
        return $this->showSQL();
    }

    /**
     * Retrieve a list of executed SQL queries
     * @return array
     */
    public function showSQL(){
        return $this->sql_list;
    }

    /**
     * Use getQueryCount() instead
     * @deprecated deprecated since version 1.3
     */
    public function get_query_count(){
        return $this->getQueryCount();
    }
    
    /**
     * Get the number of queries executed
     * @return int
     */
    public function getQueryCount(){
        return sizeof($this->sql_list);
    }

    /**
     * Quotes a string for use in a query.
     *
     * Places quotes around the input string and escapes and single quotes within the input string, using a quoting style appropriate to the underlying driver.
     * @param string $string The string to be quoted.
     * @param int $type Provides a data type hint for drivers that have alternate quoting styles. The default value is PDO::PARAM_STR.
     * @return string Returns a quoted string that is theoretically safe to pass into an SQL statement. Returns FALSE if the driver does not support quoting in this way.
     */
    public function quote($string, $type=null) {
        return $this->pdo->quote($string, $type);
    }

	public function quoteArray($array, $type=null) {
		if (!is_array($array)) {
			return $this->quote($array, $type);
		}
		$numItems = count($array);
		for($i=0; $i<$numItems; $i++) {
			if (is_array($array[$i])) {
				$array[$i] = $this->quoteArray($array[$i], $type);
			} else {
				$array[$i] = $this->quote($array[$i]);
			}
		}
		return $array;
	}

    /**
     * Returns the last inserted record's id
     * @return int
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Returns the underlying PDO object used in DooSqlMagic
     * @return PDO
     */
    public function getDbObject(){
        return $this->pdo;
    }

    //---------------------------------- SQL generator functions ------------------------

	/**
     * Retrieve model by one record.
     *
     * @param array $opt Options for the query. Available options see @see find()
     * @return mixed A model object or associateve array of the queried result
     */
	public function getOne($model, $opt=null){
        if($opt!==null){
            $opt['limit'] = 1;
            return $this->find($model, $opt);
        }
        return $this->find($model, array('limit'=>1));
    }

    /**
     * Find a record. (Prepares and execute the SELECT statements)
     * @param mixed $model The model class name or object to be select
     * @param array $opt Associative array of options to generate the SELECT statement. Supported: <i>where, limit, select, groupby, having, param, asc, desc, custom, asArray, filters</i>
     * @return mixed A model object or associateve array of the queried result
     */
    public function find($model, $opt=null){
        if(is_object($model)){
            $class_name = get_class($model);

            //auto search(add conditions to WHERE) if the model propertie(s) are/is set
            $obj = get_object_vars($model);

            $wheresql ='';
            $where_values = array();
            foreach($obj as $o=>$v){
                if(isset($v) && in_array($o, $model->_fields)){
                    if( is_object($v) ){
                        $firstChr = substr($v, 0, 1);
						$andOrStr = ($v->useOrStatement) ? 'OR ' : 'AND';

                        if(ctype_punct($firstChr)){
							if(ctype_punct(substr($v, 1, 1))){
								$firstChr .= substr($v, 1, 1);
								$wvalue = substr($v, 2);
							}else{
								$wvalue = substr($v, 1);
							}
							if($v->skipBinding === TRUE){
								$wheresql .= " {$andOrStr} {$obj['_table']}.$o $firstChr " . $wvalue;
							}else{
								$wheresql .= " {$andOrStr} {$obj['_table']}.$o $firstChr ?";
								$where_values[] = substr($v, 1);
							}
                        }else{
							if($v->skipBinding === TRUE){
                                $wheresql .= " {$andOrStr} {$obj['_table']}.$o $v";
							}else{
								if(strpos(strtoupper($v), 'LIKE')===0){
									preg_match('/^LIKE[ ]{1,}[\'\"]{1}(.+)[\'\"]{1}[ ]{1,}$/i', $v, $matches);
									$wheresql .= " {$andOrStr} {$obj['_table']}.$o LIKE ?";
									$where_values[] = $matches[1];
								}
								else if(strpos(strtoupper($v), 'IS')===0){
									$wheresql .= " {$andOrStr} {$obj['_table']}.$o $v";
								}
								else{
									$wheresql .= " {$andOrStr} {$obj['_table']}.$o=?";
									$where_values[] = $v;
								}
							}
                        }
                    }else{
                        $wheresql .= " AND {$obj['_table']}.$o=?";
                        $where_values[] = $v;
                    }
                }
            }

            if($wheresql!=''){
                if(isset($opt['where'])){
                    $opt['where'] .= $wheresql;
                }else{
                    $opt['where'] = substr($wheresql, 5);
                }
            }
        }else{
            //require file only when String is passed in
            $this->loadModel($model);
            $class_name = $model;
            $model = new $model;
        }
        #$sqladd = array();

        //select
        if(isset($opt['select'])){
            $sqladd['select']=$opt['select'];
        }else{
            $sqladd['select'] = "{$model->_table}.*";
        }


        //limit
        if(isset($opt['limit'])){
            $sqladd['limit'] = 'LIMIT ' . $opt['limit'];
        }else{
            $opt['limit']='';
            $sqladd['limit']='';
        }

        //conditions WHERE
        if(isset($opt['where'])){
            $sqladd['where']= 'WHERE ' . $opt['where'];
        }else{
            $sqladd['where'] ='';
        }

		if (isset($opt['filters']) && is_array($opt['filters'])) {
			$sqladd['filter'] = '';
			foreach ($opt['filters'] as $filter) {
				$fmodel = null;
				if(is_object($filter['model'])){
					$fmodel = $filter['model'];
					$fTableName = $fmodel->_table;
					$fmodel_class = get_class($fmodel);
				}else{
					$fmodel = $this->loadModel($filter['model'], true);
					$fTableName = $fmodel->_table;
					$fmodel_class = $filter['model'];
				}

				list($fmodel_rtype, $fparams ) = self::relationType($this->map, $class_name, $fmodel_class);

				if(isset($filter['joinType'])){
					$joinType = $filter['joinType'] . ' JOIN';
				}else{
					$joinType = 'JOIN';
				}

				switch($fmodel_rtype) {
					case 'has_one':
						$sqladd['filter'] .= "{$joinType} {$fTableName} ON {$fmodel->_table}.{$fparams['foreign_key']} = {$model->_table}.{$model->_primarykey} ";
						break;
					case 'belongs_to':
						list($frtype, $ffkey ) = self::relationType($this->map, $fmodel_class, $class_name);
						$sqladd['filter'] .= "{$joinType} {$fTableName} ON {$fmodel->_table}.{$fparams['foreign_key']} = {$model->_table}.{$ffkey['foreign_key']} ";
						break;
					case 'has_many':
						list($fmtype, $fmparams) = self::relationType($this->map, $fmodel_class, $class_name);
						if ($fmtype == 'has_many') {
							$sqladd['filter'] .= "{$joinType} {$fparams['through']} ON {$model->_table}.{$model->_primarykey} = {$fparams['through']}.{$fparams['foreign_key']}\n";
							$sqladd['filter'] .= "{$joinType} {$fTableName} ON {$fmodel->_table}.{$fmodel->_primarykey} = {$fparams['through']}.{$fmparams['foreign_key']} ";
						} else {
							$sqladd['filter'] .= "{$joinType} {$fTableName} ON {$model->_table}.{$fmparams['foreign_key']} = {$fTableName}.{$fparams['foreign_key']} ";
						}
						break;
					default:
						throw new Exception('Table Relationship not defined for ' . $class_name . ' to ' . $fmodel_class);
				}

				if(isset($filter['where'])){
					if($sqladd['where']==''){
						$sqladd['where'] .= ' WHERE '.$filter['where'];
					}else{
						$sqladd['where'] .= ' AND '.$filter['where'].' ';
					}
					//merge the include param with the Where params, at the end
					if(isset($filter['param'])){
						if(isset($opt['param']) && isset($where_values))
							 $where_values = array_merge( $where_values, $filter['param']);
						else if(isset($opt['param']))
							$opt['param'] = array_merge( $opt['param'], $filter['param']);
						else if(isset($where_values))
							$where_values = array_merge( $where_values, $filter['param']);
						else
							$where_values = $filter['param'];
					}
				}
			}
		} else {
			$sqladd['filter'] = '';
		}

		//GROUP BY
		if (isset($opt['groupby'])) {
			$sqladd['groupby'] = 'GROUP BY ' . $opt['groupby'];
		} else {
			$sqladd['groupby'] = '';
		}

		//HAVINGs
		if (isset($opt['having'])) {
			$sqladd['having'] = 'HAVING ' . $opt['having'];
		} else {
			$sqladd['having'] = '';
		}
        
        //if asc is defined first then ORDER BY xxx ASC, xxx DESC
        //else Order by xxx DESC, xxx ASC
        if(isset($opt['asc']) && isset($opt['desc']) && $opt['asc']!='' && $opt['desc']!=''){
            $optkeys = array_keys($opt);
            $posDesc = array_search('desc', $optkeys);
            $posAsc = array_search('asc', $optkeys);
            if($posDesc < $posAsc)
                $sqladd['order']= 'ORDER BY '. $opt['desc'] .' DESC, '. $opt['asc'] . ' ASC';
            else
                $sqladd['order']= 'ORDER BY '. $opt['asc'] .' ASC, '. $opt['desc'] . ' DESC';
        }
        //ASC ORDER
        else if(isset($opt['asc'])){
            $sqladd['order']= 'ORDER BY ' . $opt['asc'] . ' ASC';
        }
        //DESC ORDER
        else if(isset($opt['desc'])){
            $sqladd['order']= 'ORDER BY ' . $opt['desc'] . ' DESC';
        }
        else{
            $sqladd['order'] ='';
        }

        //Custom ending
        if(isset($opt['custom'])){
            $sqladd['custom']= $opt['custom'];
        }else{
            $sqladd['custom'] ='';
        }


        $sql ="SELECT {$sqladd['select']} FROM {$model->_table} {$sqladd['filter']} {$sqladd['where']} {$sqladd['groupby']} {$sqladd['having']} {$sqladd['order']} {$sqladd['custom']} {$sqladd['limit']}";

        //conditions WHERE param
        if(isset($opt['param']) && isset($where_values))
            $rs = $this->query($sql, array_merge( $opt['param'], $where_values));
        else if(isset($opt['param']))
            $rs = $this->query($sql, $opt['param']);
        else if(isset($where_values))
            $rs = $this->query($sql, $where_values);
        else
            $rs = $this->query($sql);


        //if limit is only one/first, then just return the single result, we don't need an array
        if($opt['limit']===1){
            if(isset($opt['asArray']) && $opt['asArray']===true)
                return $rs->fetch();
            else
                return $rs->fetchObject($class_name);
        }else{

            //return as Array of objects / assoc arrays

            if(isset($opt['asArray']) && $opt['asArray']===true){
                return $rs->fetchAll();
            }else{
                return $rs->fetchAll(PDO::FETCH_CLASS, $class_name);
            }
        }
    }

    /**
     * Find a record and its associated model. Relational search. (Prepares and execute the SELECT statements)
     * @param mixed $model The model class name or object to be select.
     * @param string $rmodel The related model class name.
     * @param array $opt Associative array of options to generate the SELECT statement. Supported: <i>where, limit, select, param, groupby, having, joinType, match, asc, desc, custom, asArray, include, includeWhere, includeParam, filters</i>
     * @return mixed A list of model object(s) or associateve array of the queried result
     */
    public function relate($model, $rmodel, $opt=null){
        if(is_object($model)){
            $class_name = get_class($model);
            //auto search(add conditions to WHERE) if the model propertie(s) are/is set
            $obj = get_object_vars($model);

            $wheresql ='';
            $where_values = array();
            foreach($obj as $o=>$v){
                if(isset($v) && in_array($o, $model->_fields)){
                    if( is_object($v) ){
                        $firstChr = substr($v, 0, 1);
						$andOrStr = ($v->useOrStatement) ? 'OR ' : 'AND';

                        if(ctype_punct($firstChr)){
							if(ctype_punct(substr($v, 1, 1))){
								$firstChr .= substr($v, 1, 1);
								$wvalue = substr($v, 2);
							}else{
								$wvalue = substr($v, 1);
							}
							if($v->skipBinding === TRUE){
								$wheresql .= " {$andOrStr} {$obj['_table']}.$o $firstChr " . $wvalue;
							}else{
								$wheresql .= " {$andOrStr} {$obj['_table']}.$o $firstChr ?";
								$where_values[] = substr($v, 1);
							}
                        }else{
							if($v->skipBinding === TRUE){
                                $wheresql .= " {$andOrStr} {$obj['_table']}.$o $v";
							}else{
								if(strpos(strtoupper($v), 'LIKE')===0){
									preg_match('/^LIKE[ ]{1,}[\'\"]{1}(.+)[\'\"]{1}[ ]{1,}$/i', $v, $matches);
									$wheresql .= " {$andOrStr} {$obj['_table']}.$o LIKE ?";
									$where_values[] = $matches[1];
								}
								else if(strpos(strtoupper($v), 'IS')===0){
									$wheresql .= " {$andOrStr} {$obj['_table']}.$o $v";
								}
								else{
									$wheresql .= " {$andOrStr} {$obj['_table']}.$o=?";
									$where_values[] = $v;
								}
							}
                        }
                    }else{
                        $wheresql .= " AND {$obj['_table']}.$o=?";
                        $where_values[] = $v;
                    }
                }
            }

            if($wheresql!=''){
                if(isset($opt['where'])){
                    $opt['where'] .= $wheresql;
                }else{
                    $opt['where'] = substr($wheresql, 5);
                }
            }
        }else{
            //require file only when String is passed in
            $this->loadModel($model);
            $class_name = $model;
            $model = new $model;
        }

        //select
        if(isset($opt['select'])){
            $sqladd['select']=$opt['select'];
        }else{
            $sqladd['select'] ='*';
        }

        //joinType
        if(isset($opt['joinType'])){
            $sqladd['joinType']=$opt['joinType'] . ' JOIN';
        }else{
            $sqladd['joinType'] = self::JOIN_LEFT_OUTER . ' JOIN';
        }


        //limit, value 'first' does not add LIMIT/TOP to the SQL, but will return a single result object instead of array
        if(isset($opt['limit'])){
            if($opt['limit']!='first')
                $sqladd['limit'] = 'LIMIT ' . $opt['limit'];
            else
                $sqladd['limit'] = '';
        }else{
            $opt['limit']='';
            $sqladd['limit']='';
        }


        //conditions WHERE
        if(isset($opt['where'])){
            $sqladd['where']= 'WHERE ' . $opt['where'];
        }else{
            $sqladd['where'] ='';
        }

		//GROUP BY
		if (isset($opt['groupby'])) {
			$sqladd['groupby'] = 'GROUP BY ' . $opt['groupby'];
		} else {
			$sqladd['groupby'] = '';
		}

		//HAVINGs
		if (isset($opt['having'])) {
			$sqladd['having'] = 'HAVING ' . $opt['having'];
		} else {
			$sqladd['having'] = '';
		}

        //ASC ORDER
        if(isset($opt['asc'])){
            $sqladd['order']= 'ORDER BY ' . $opt['asc'] . ' ASC';
        }else{
            $sqladd['order'] ='';
        }

        //DESC ORDER
        if(isset($opt['desc'])){
            $sqladd['order']= 'ORDER BY ' . $opt['desc'] . ' DESC';
        }

        //Custom ending
        if(isset($opt['custom'])){
            $sqladd['custom']= $opt['custom'];
        }else{
            $sqladd['custom'] ='';
        }


        //get define relation, what type, which model
        #list($rtype,$rparams) = $model->relationType($rmodel);
		if (is_object($rmodel)) {
			$relatedmodel = $rmodel;
			$rmodel =  get_class($relatedmodel);
			$rtable = $relatedmodel->_table;

			list($rtype,$rparams) = self::relationType($this->map, $class_name, $rmodel);
			if($rtype==NULL)
				throw new SqlMagicException("Model $class_name does not relate to $rmodel", SqlMagicException::RelationNotFound);

			// As we have an object we will check to see what query params are set
			// This will be used to run a nested select
			$obj = get_object_vars($relatedmodel);
            $nestedwheresql ='';
            $nested_where_values = array();
            foreach($obj as $o=>$v){
                if(isset($v) && in_array($o, $relatedmodel->_fields)){

                    if( is_object($v) ){
                        $firstChr = substr($v, 0, 1);
						$andOrStr = ($v->useOrStatement) ? 'OR ' : 'AND';

                        if(ctype_punct($firstChr)){
							if(ctype_punct(substr($v, 1, 1))){
								$firstChr .= substr($v, 1, 1);
								$wvalue = substr($v, 2);
							}else{
								$wvalue = substr($v, 1);
							}
							if($v->skipBinding === TRUE){
								$nestedwheresql .= " {$andOrStr} {$obj['_table']}.$o $firstChr " . $wvalue;
							}else{
								$nestedwheresql .= " {$andOrStr} {$obj['_table']}.$o $firstChr ?";
								$nestedwheresql[] = substr($v, 1);
							}
                        }else{
							if($v->skipBinding === TRUE){
                                $nestedwheresql .= " {$andOrStr} {$obj['_table']}.$o $v";
							}else{
								if(strpos(strtoupper($v), 'LIKE')===0){
									preg_match('/^LIKE[ ]{1,}[\'\"]{1}(.+)[\'\"]{1}[ ]{1,}$/i', $v, $matches);
									$nestedwheresql .= " {$andOrStr} {$obj['_table']}.$o LIKE ?";
									$nested_where_values[] = $matches[1];
								}
								else if(strpos(strtoupper($v), 'IS')===0){
									$nestedwheresql .= " {$andOrStr} {$obj['_table']}.$o $v";
								}
								else{
									$nestedwheresql .= " {$andOrStr} {$obj['_table']}.$o=?";
									$nested_where_values[] = $v;
								}
							}
                        }
                    }else{
                        $nestedwheresql .= " AND {$obj['_table']}.$o=?";
                        $nested_where_values[] = $v;
                    }
                }
			}

			if($nestedwheresql!=''){
				$nestedwheresql = substr($nestedwheresql, 5);
                $sqladd['rNestedQuery'] = " (SELECT * FROM {$relatedmodel->_table} WHERE {$nestedwheresql}) AS ";
            } else {
				$sqladd['rNestedQuery'] = '';
			}

			if (isset($where_values) && isset($nested_where_values)) {
				$where_values = array_merge($nested_where_values, $where_values);
			} elseif (isset($nested_where_values)) {
				$where_values = $nested_where_values;
			}

		} else {
			list($rtype,$rparams) = self::relationType($this->map, $class_name, $rmodel);
			if($rtype==NULL)
				throw new SqlMagicException("Model $class_name does not relate to $rmodel", SqlMagicException::RelationNotFound);

			$this->loadModel($rmodel);
			$relatedmodel = new $rmodel;
			$rtable = $relatedmodel->_table;

			#echo "$class_name $rtype $rmodel( $rtable )";

			$sqladd['rNestedQuery'] = '';
		}

        //reverse relation (belongs_to), checking params such as foreign_key
        #list($mtype,$mparams) = $relatedmodel->relationType($class_name);
        list($mtype,$mparams) = self::relationType($this->map, $rmodel, $class_name);
        if($mtype==NULL)
            throw new SqlMagicException("Model $rmodel does not relate to $class_name. This is a reverse check.", SqlMagicException::RelationNotFound);

        #echo "\n$rmodel $mtype $class_name( {$model->_table} )";


        //Matched true, related reference keys cannot be NULL in relationship, add Check if not null to WHERE condition
        //No need to match if it's a m to m relationship
        if(isset($opt['match']) && !($mtype=='has_many' && $rtype=='has_many'))
            if($opt['match']==true)
                self::addWhereNotNull($sqladd['where'], "{$relatedmodel->_table}.{$rparams['foreign_key']}", "{$model->_table}.{$mparams['foreign_key']}");
            elseif($opt['match']==false)
                self::addWhereIsNull($sqladd['where'], "{$relatedmodel->_table}.{$rparams['foreign_key']}", "{$model->_table}.{$mparams['foreign_key']}");


        //need to seperate the related model vars from the caller model, and add it as a Caller model's property
        //all properties of the caller model class
        $model_vars = array_keys(self::toArray($model));
        $rmodel_vars = array_keys(self::toArray($relatedmodel));

        $retrieved_pk_key = "_{$model->_table}__{$mparams['foreign_key']}";
        $model_vars[] = $retrieved_pk_key;

        $rretrieved_pk_key = "_{$relatedmodel->_table}__{$rparams['foreign_key']}";
        $rmodel_vars[] = $rretrieved_pk_key;


        //get a list of repeated vars in the 2 models, _table _relation is NOT a model property
        $repeated_vars = array_intersect($rmodel_vars, $model_vars);
        $repeated_vars = array_diff($repeated_vars, array('_table','_primarykey','_fields'));

        //if there's repeat vars(field names) in the 2 models, SELECT the 2nd model as an Alias, _table__fieldname
        //keep a set of records of the Alias names, to be replaced back later when mapping to the model object
        #$alias_vars = array();
        #$ralias_vars = array();
        $defined_class_vars = array_merge($model->_fields, $relatedmodel->_fields);
        if(isset($opt['select']))
            $oriSel = $opt['select'];
        else
            $oriSel = '*';

        foreach($repeated_vars as $r){
            //dun add user defined class properties that are not used in database
            if(!in_array($r, $defined_class_vars))continue;
			if($r==$relatedmodel->_primarykey){
				$alias = "_{$model->_table}__$r";
				$ralias = "_{$relatedmodel->_table}__$r";
				$sqladd['select'] .= ", {$model->_table}.$r AS $alias, {$relatedmodel->_table}.$r AS $ralias";
				$alias_vars[$alias] = $r;
				$ralias_vars[$ralias] = $r;
				$model_vars[] = $alias;
				$rmodel_vars[] = $ralias;

				if(isset($opt['select'])){
					if($opt['select'][0]==='*'){
						$sqladd['select'] .= ", {$relatedmodel->_table}.$r";
						$opt['select'] .= ", {$relatedmodel->_table}.$r";
					}
					else if(preg_match('/,(\s+)?'.$relatedmodel->_table .'\.\*/', ','.$opt['select'])){
						$sqladd['select'] .= ", {$relatedmodel->_table}.$r";
						$opt['select'] .= ", {$relatedmodel->_table}.$r";
					}
				}else{
					$sqladd['select'] .= ", {$relatedmodel->_table}.$r";
					$opt['select'] = "{$relatedmodel->_table}.$r";
				}
			}
			else {
				if(isset($opt['select']) && strpos($opt['select'], ',')!==false){
					$modelHasSelectField = strpos($opt['select'], "{$model->_table}.$r")!==false;
					$rmodelHasSelectField = strpos($opt['select'], "{$relatedmodel->_table}.$r")!==false;

					if($opt['select'][0]==='*'){
						$modelHasSelectField = $rmodelHasSelectField = true;
					}else{
						if($modelHasSelectField===false && preg_match('/,(\s+)?'.$model->_table .'\.\*/', ',' . $opt['select'])){
							$modelHasSelectField = true;
						}
						if($rmodelHasSelectField===false && preg_match('/,(\s+)?'.$relatedmodel->_table .'\.\*/', ',' . $opt['select'])){
							$rmodelHasSelectField = true;
						}
					}
				}
				else{
					//select *
					$modelHasSelectField = $rmodelHasSelectField = true;
				}

				if($modelHasSelectField===true && $rmodelHasSelectField===true){
//					echo "\n+{$model->_table}.$r\n";
//					echo "+{$relatedmodel->_table}.$r\n\n";
					$alias = "_{$model->_table}__$r";
					$ralias = "_{$relatedmodel->_table}__$r";
					$sqladd['select'] .= ", {$model->_table}.$r AS $alias, {$relatedmodel->_table}.$r AS $ralias";
					$alias_vars[$alias] = $r;
					$ralias_vars[$ralias] = $r;
					$model_vars[] = $alias;
					$rmodel_vars[] = $ralias;
				}
				else if($modelHasSelectField===true){
//					echo "+{$model->_table}.$r\n\n";
					$alias = "_{$model->_table}__$r";
					$sqladd['select'] .= ", {$model->_table}.$r AS $alias";
					$alias_vars[$alias] = $r;
					$model_vars[] = $alias;
					$ralias_vars[$r] = false;
				}
				else if($rmodelHasSelectField===true){
//					echo "+{$relatedmodel->_table}.$r\n\n";
					$ralias = "_{$relatedmodel->_table}__$r";
					$sqladd['select'] .= ", {$relatedmodel->_table}.$r AS $ralias";
					$ralias_vars[$ralias] = $r;
					$rmodel_vars[] = $ralias;
					$alias_vars[$r] = false;
				}
			}
        }

        #print_r($model_vars);
        #print_r($rmodel_vars);
        #print_r($repeated_vars);
        
        //include model, reduce a lot of queries, merge 3 related models together, eg. Post, PostCategory, PostComment
        if(isset($opt['include'])){
            $tmodel = null;
            if(is_object($opt['include'])){
                $tmodel = $opt['include'];
                $sqladd['include'] = $tmodel->_table;
                $tmodel_class = get_class($tmodel);
            }else{
                $tmodel = $this->loadModel($opt['include'], true);
                $sqladd['include'] = $tmodel->_table;
                $tmodel_class = $opt['include'];
            }

			list($tmodel_rtype, $tmodel_fk ) = self::relationType($this->map, $class_name, $tmodel_class);
			$tmodel_fk = $tmodel_fk['foreign_key'];

			if ($rtype == 'has_many' && $mtype == 'has_many' && $tmodel->_table == $rparams['through']) { // Is the include for the joining table?
				$sqladd['include'] = '';
			} else {
				//echo $tmodel_rtype.'<br/>';
				//include Join Type
				if(isset($opt['includeType'])){
					$sqladd['include'] = $opt['includeType'] . ' JOIN '. $sqladd['include'];
				}else{
					$sqladd['include'] = ' JOIN ' . $sqladd['include'];
				}
				if($rtype=='belongs_to'){
					list($trtype, $tfkey ) = self::relationType($this->map, $tmodel_class, $class_name);
					//echo '<h1>TMODEL '.$tfkey['foreign_key'].'</h1>';
					$sqladd['include'] .= " ON {$tmodel->_table}.$tmodel_fk = {$model->_table}.{$tfkey['foreign_key']} ";
				}else{
					$sqladd['include'] .= " ON {$tmodel->_table}.$tmodel_fk = {$model->_table}.{$model->_primarykey} ";
				}
				#print_r($sqladd['where']);
				if(isset($opt['includeWhere'])){
					if($sqladd['where']==''){
						$sqladd['where'] .= ' WHERE '.$opt['includeWhere'];
					}else{
						$sqladd['where'] .= ' AND '.$opt['includeWhere'].' ';
					}
					//merge the include param with the Where params, at the end
					if(isset($opt['includeParam'])){
						if(isset($opt['param']) && isset($where_values))
							 $where_values = array_merge( $where_values, $opt['includeParam']);
						else if(isset($opt['param']))
							$opt['param'] = array_merge( $opt['param'], $opt['includeParam']);
						else if(isset($where_values))
							$where_values = array_merge( $where_values, $opt['includeParam']);
						else
							$where_values = $opt['includeParam'];
					}
				}
			}
			//edit the select part since now 3 tables are involved, might have 3 repeating field names in all tables.
			//SELECT model.*, related_model.*, includemodel.id AS _t_includemodel_id, includemodel.title AS _t_includemodel_title, ...
			$tselect_field = '';
			foreach($tmodel->_fields as $tfname){
				$tselect_field .= $tmodel->_table.'.'.$tfname . ' AS _t_' . $tmodel->_table . '_' . $tfname . ', ';
			}
			if(strpos($sqladd['select'], '*,')===0){
				$sqladd['select'] = substr($sqladd['select'], 2);
				$sqladd['select'] = "{$model->_table}.*, {$relatedmodel->_table}.*, $tselect_field" . $sqladd['select'];
			}

        }else{
            $sqladd['include']='';
        }


		if (isset($opt['filters']) && is_array($opt['filters'])) {
			$sqladd['filter'] = '';
			foreach ($opt['filters'] as $filter) {
				$fmodel = null;
				if(is_object($filter['model'])){
					$fmodel = $filter['model'];
					$fTableName = $fmodel->_table;
					$fmodel_class = get_class($fmodel);
				}else{
					$fmodel = $this->loadModel($filter['model'], true);
					$fTableName = $fmodel->_table;
					$fmodel_class = $filter['model'];
				}


				list($fmodel_rtype, $fparams ) = self::relationType($this->map, $class_name, $fmodel_class);

				if(isset($filter['joinType'])){
					$joinType = $filter['joinType'] . ' JOIN';
				}else{
					$joinType = 'JOIN';
				}

				switch($fmodel_rtype) {
					case 'has_one':
						$sqladd['filter'] .= "{$joinType} {$fTableName} ON {$fmodel->_table}.{$fparams['foreign_key']} = {$model->_table}.{$model->_primarykey} ";
						break;
					case 'belongs_to':
						list($frtype, $ffkey ) = self::relationType($this->map, $fmodel_class, $class_name);
						$sqladd['filter'] .= "{$joinType} {$fTableName} ON {$fmodel->_table}.{$fparams['foreign_key']} = {$model->_table}.{$ffkey['foreign_key']} ";
						break;
					case 'has_many':
						list($fmtype, $fmparams) = self::relationType($this->map, $fmodel_class, $class_name);
						if ($fmtype == 'has_many') {
							$sqladd['filter'] .= "{$joinType} {$fparams['through']} ON {$model->_table}.{$model->_primarykey} = {$fparams['through']}.{$fparams['foreign_key']}\n";
							$sqladd['filter'] .= "{$joinType} {$fTableName} ON {$fmodel->_table}.{$fmodel->_primarykey} = {$fparams['through']}.{$fmparams['foreign_key']} ";
						} else {
							$sqladd['filter'] .= "{$joinType} {$fTableName} ON {$model->_table}.{$fmparams['foreign_key']} = {$fTableName}.{$fparams['foreign_key']} ";
						}
				}


				#print_r($sqladd['where']);
				if(isset($filter['where'])){
					if($sqladd['where']==''){
						$sqladd['where'] .= ' WHERE '.$filter['where'];
					}else{
						$sqladd['where'] .= ' AND '.$filter['where'].' ';
					}
					//merge the include param with the Where params, at the end
					if(isset($filter['param'])){
						if(isset($opt['param']) && isset($where_values))
							 $where_values = array_merge( $where_values, $filter['param']);
						else if(isset($opt['param']))
							$opt['param'] = array_merge( $opt['param'], $filter['param']);
						else if(isset($where_values))
							$where_values = array_merge( $where_values, $filter['param']);
						else
							$where_values = $filter['param'];
					}
				}
				//edit the select part since now we do not want the filter table fields. Not needed if using include as its adjusted select already
				//SELECT model.*, related_model.*, includemodel.id AS _t_includemodel_id, includemodel.title AS _t_includemodel_title, ...
				if(strpos($sqladd['select'], '*')===0){
					$sqladd['select'] = substr($sqladd['select'], 1);
					$sqladd['select'] = "{$model->_table}.*, {$relatedmodel->_table}.* " . $sqladd['select'];
				}
			}
		} else {
			$sqladd['filter'] = '';
		}


        //generate SQL based on $rtype (has_one, has_many, belongs_to)
        switch($rtype){
            case 'has_one':
                $sql = "SELECT {$sqladd['select']},  {$relatedmodel->_table}.{$rparams['foreign_key']} AS _{$relatedmodel->_table}__{$rparams['foreign_key']}
                    FROM {$model->_table}
                    {$sqladd['include']}
					{$sqladd['filter']}
                    {$sqladd['joinType']} {$sqladd['rNestedQuery']} {$relatedmodel->_table}
                    ON {$model->_table}.{$mparams['foreign_key']} = {$relatedmodel->_table}.{$rparams['foreign_key']}
                    {$sqladd['where']}
					{$sqladd['groupby']}
					{$sqladd['having']}
                    {$sqladd['order']} {$sqladd['custom']} {$sqladd['limit']}";
                break;
            case 'belongs_to':
                //for belongs_to Object reverse relate()
                $sql = "SELECT {$sqladd['select']},  {$relatedmodel->_table}.{$rparams['foreign_key']} AS _{$relatedmodel->_table}__{$rparams['foreign_key']}
                    FROM {$model->_table}
                    {$sqladd['include']}
					{$sqladd['filter']}
                    {$sqladd['joinType']} {$sqladd['rNestedQuery']} {$relatedmodel->_table}
                    ON {$model->_table}.{$mparams['foreign_key']} = {$relatedmodel->_table}.{$rparams['foreign_key']}
                    {$sqladd['where']}
					{$sqladd['groupby']}
					{$sqladd['having']}
                    {$sqladd['order']} {$sqladd['custom']} {$sqladd['limit']}";
                break;
            case 'has_many':
                if($mtype=='has_many'){

                    //automatically matched for M:M relationship
                    if(!isset($opt['match']) || $opt['match']==true){
                        if($sqladd['where']===''){
                            $sqladd['where'] = "WHERE {$relatedmodel->_table}.{$relatedmodel->_primarykey} IS NOT NULL AND {$model->_table}.{$model->_primarykey} IS NOT NULL";
                        }else{
                            $sqladd['where'] .= " AND {$relatedmodel->_table}.{$relatedmodel->_primarykey} IS NOT NULL AND {$model->_table}.{$model->_primarykey} IS NOT NULL";
                        }
                    }
                    //remove the extra vars which is only exist in the link table. A_has_B
                    $model_vars = array_diff($model_vars, array("_{$model->_table}__{$mparams['foreign_key']}"));
                    $rmodel_vars = array_diff($rmodel_vars, array("_{$relatedmodel->_table}__{$rparams['foreign_key']}"));

                    if(isset($opt['asc'])){
                        $opt['asc'] = $opt['asc'] . ' ASC, ';
                    }else{
                        $opt['asc'] ='';
                    }

                    //DESC ORDER
                    if(isset($opt['desc'])){
                        $opt['desc'] = $opt['desc'] . ' DESC, ';
                    }else{
                        $opt['desc'] = '';
                    }
                    //if asc is defined first then ORDER BY xxx ASC, xxx DESC
                    //else Order by xxx DESC, xxx ASC
                    if($opt['asc']!='' && $opt['desc']!=''){
                        $optkeys = array_keys($opt);
                        $posDesc = array_search('desc', $optkeys);
                        $posAsc = array_search('asc', $optkeys);

                        if($posDesc < $posAsc)
                            $addonOrder = $opt['desc'].' '.$opt['asc'];
                        else
                            $addonOrder = $opt['asc'].' '.$opt['desc'];
                    }else{
                        $addonOrder = $opt['desc'].' '.$opt['asc'];
                    }

                    if($opt['limit']!=''){
                        if(!empty ($addonOrder)){
                            if(substr($addonOrder, strlen($addonOrder)-4)=='SC, '){
                                //remove ', ' at the end for the order
                                $orderLimit = 'ORDER BY '.substr($addonOrder, 0, strlen($addonOrder)-2);
                                //remove related Model field names from the limit for the main Model
                            }else{
                                $orderLimit = 'ORDER BY '.$addonOrder;
                            }
                            $orderLimit = preg_replace("/[, ]*$relatedmodel->_table\.[a-z0-9_-]{1,64}/i", '', $orderLimit);
                            $orderLimit = str_replace('DESC ASC', 'DESC', $orderLimit);
                            $orderLimit = str_replace('ASC DESC', 'ASC', $orderLimit);
                            if($orderLimit=='ORDER BY ASC' || $orderLimit=='ORDER BY DESC'){
                                $orderLimit = '';
                            }
                            if(substr(trim($orderLimit), strlen(trim($orderLimit))-1)==',')
                                $orderLimit = substr(trim($orderLimit), 0, strlen(trim($orderLimit))-1). ' ';
                        }

                        if(isset($opt['where']) && $opt['where']!=''){
                            //remove Rmodel field names from the WHERE statement
                            $whrLimit = preg_replace("/[,|AND|OR ]*$relatedmodel->_table\.[a-z0-9_-]{1,64}[^{$model->_table}\.]*/i", '', $opt['where']);
                            if($whrLimit!=$opt['where']){
                                if(strpos(strtoupper($whrLimit), 'AND')===0){
                                    $whrLimit = substr($whrLimit, 3);
                                }
                                else{
                                    $tmpAnd = strpos($whrLimit, 'D ');
                                    if($tmpAnd!==False){
                                        $whrLimit = substr($whrLimit, $tmpAnd+2);
                                    }
                                }

                                //check for relatedModel WHERE statement
                                if(preg_match_all("/[,|AND|OR ]*($relatedmodel->_table\.[a-z0-9_-]{1,64}[^{$model->_table}\.]*)/i", $opt['where'], $rlimitMatch)>0){
                                    $rlimitMatch = $rlimitMatch[0];

                                    $rlimitMatch = implode(' ', $rlimitMatch);

                                    if( substr($rlimitMatch, strlen($rlimitMatch)-4)=='AND '){
                                        $rlimitMatch = substr($rlimitMatch, 0, strlen($rlimitMatch)-4);
                                    }
                                    else if($rlimitMatch[strlen($rlimitMatch)-1]=='A'){
                                        $rlimitMatch = substr($rlimitMatch, 0, strlen($rlimitMatch)-1);
                                    }

                                    if(isset($opt['param'])){
                                        $rStmtLimit = $this->query("SELECT {$relatedmodel->_table}.{$relatedmodel->_primarykey} FROM {$relatedmodel->_table} WHERE {$rlimitMatch} LIMIT 1", $opt['param']);
                                        $rStmtId = $rStmtLimit->fetch();
                                        $rStmtId = $rStmtId[$relatedmodel->_primarykey];
                                    }else{
                                        $rStmtLimit = $this->query("SELECT {$relatedmodel->_table}.{$relatedmodel->_primarykey} FROM {$relatedmodel->_table} WHERE {$rlimitMatch} LIMIT 1");
                                        $rStmtId = $rStmtLimit->fetch();
                                        $rStmtId = $rStmtId[$relatedmodel->_primarykey];
                                    }

                                    $mIdStmt = $this->query("SELECT {$mparams['through']}.{$rparams['foreign_key']} FROM {$mparams['through']} WHERE {$rparams['through']}.{$mparams['foreign_key']}=? ORDER BY {$mparams['through']}.{$rparams['foreign_key']} DESC", array($rStmtId));
                                    //need to combine these ids with the model limit ids
                                    $m_in_id = array();
                                    foreach($mIdStmt as $m){
                                        $m_in_id[] = $m[$rparams['foreign_key']];
                                    }
                                }
                            }
                        }

                        if($opt['limit']==1 || $opt['limit']=='first'){
                            $opt['limit'] = 'first';
                            $limitstr = 1;
                        }else{
                            $limitstr = $opt['limit'];
                        }

                        //conditions WHERE param for the Limit
                        if(isset($opt['param']) && isset($where_values) && !empty($whrLimit)){
                            $countQ = 0;
                            str_replace('?', '', $whrLimit, $countQ);
                            if($countQ==sizeof($where_values)){
                                $varsLimit = $where_values;
                            }
                            else{
                                $varsLimit = array_merge( $opt['param'], $where_values);
                            }

                            $stmtLimit = $this->query("SELECT {$model->_table}.{$model->_primarykey} FROM {$model->_table} WHERE $whrLimit $orderLimit LIMIT {$limitstr}", $varsLimit);
                        }else if(isset($opt['param']) && !empty($opt['param']) && !empty($whrLimit)){
                            $stmtLimit = $this->query("SELECT {$model->_table}.{$model->_primarykey} FROM {$model->_table} WHERE $whrLimit $orderLimit LIMIT {$limitstr}", $opt['param']);
                        }else if(isset($where_values) && !empty($where_values) && !empty($whrLimit)){
                            $stmtLimit = $this->query("SELECT {$model->_table}.{$model->_primarykey} FROM {$model->_table} WHERE $whrLimit $orderLimit LIMIT {$limitstr}", $where_values);
                        }else{
                            if($orderLimit=='ORDER BY  ') $orderLimit = '';
                            $stmtLimit = $this->query("SELECT {$model->_table}.{$model->_primarykey} FROM {$model->_table} $orderLimit LIMIT {$limitstr}");
                        }

                        $limitModelStr = array();
                        foreach($stmtLimit as $rlimit){
                            $limitModelStr[] = $rlimit[$model->_primarykey];
                        }

                        //combine if exists
                        if(isset($m_in_id)){
                            $removeIds = array_diff($limitModelStr, $m_in_id);
                            foreach($removeIds as $k=>$rid){
                                unset($limitModelStr[$k]);
                            }
                            $limitModelStr = array_unique(array_merge($limitModelStr, $m_in_id));
                            $limitLength = explode(',', $opt['limit']);
                            if(isset($limitLength[1])){
                                array_splice($limitModelStr, $limitLength[1]);
                            }else{
                                array_splice($limitModelStr, $limitLength[0]);
                            }
                        }
                        $limitModelStr = implode(',', $limitModelStr);

                        if ($limitModelStr !== ''){
                            if($sqladd['where']===''){
                                $sqladd['where'] = "WHERE {$model->_table}.{$model->_primarykey} IN ($limitModelStr)";
                            }else{
                                $sqladd['where'] .= " AND {$model->_table}.{$model->_primarykey} IN ($limitModelStr)";
                            }
                        }
                    }


                    $sql = "SELECT {$sqladd['select']}, {$mparams['through']}.{$mparams['foreign_key']}, {$mparams['through']}.{$rparams['foreign_key']}
                        ,{$rparams['through']}.{$mparams['foreign_key']} AS _{$relatedmodel->_table}__{$rparams['foreign_key']}
                        ,{$rparams['through']}.{$rparams['foreign_key']} AS _{$model->_table}__{$mparams['foreign_key']}
                        FROM {$model->_table}
						{$sqladd['include']}
						{$sqladd['filter']}
                        {$sqladd['joinType']} {$mparams['through']}
                        ON {$model->_table}.{$model->_primarykey} = {$mparams['through']}.{$rparams['foreign_key']}
                        {$sqladd['joinType']} {$relatedmodel->_table}
                        ON {$relatedmodel->_table}.{$relatedmodel->_primarykey} = {$rparams['through']}.{$mparams['foreign_key']}
                        {$sqladd['where']}
						{$sqladd['groupby']}
                        {$sqladd['having']}
                        ORDER BY {$addonOrder} {$rparams['through']}.{$mparams['foreign_key']},{$rparams['through']}.{$rparams['foreign_key']} ASC
                         {$sqladd['custom']}";
                }
                else if($mtype=='belongs_to'){
                    $sql = "SELECT {$sqladd['select']},
                        {$model->_table}.{$mparams['foreign_key']} AS _{$model->_table}__{$mparams['foreign_key']},
                        {$relatedmodel->_table}.{$rparams['foreign_key']} AS _{$relatedmodel->_table}__{$rparams['foreign_key']}
                        FROM {$model->_table}
                        {$sqladd['include']}
						{$sqladd['filter']}
                        {$sqladd['joinType']} {$sqladd['rNestedQuery']} {$relatedmodel->_table}
                        ON {$model->_table}.{$mparams['foreign_key']} = {$relatedmodel->_table}.{$rparams['foreign_key']}
                        {$sqladd['where']}
						{$sqladd['groupby']}
                        {$sqladd['having']}
                        {$sqladd['order']} {$sqladd['custom']} {$sqladd['limit']}";
                }
                break;
        }

        //conditions WHERE param
        if(isset($opt['param']) && isset($where_values))
            $rs = $this->query($sql, array_merge( $opt['param'], $where_values));
        else if(isset($opt['param']))
            $rs = $this->query($sql, $opt['param']);
        else if(isset($where_values))
            $rs = $this->query($sql, $where_values);
        else
            $rs = $this->query($sql);

        //if limit is only one, then just return the single result, we don't need an array
        if($opt['limit']===1){
            if(isset($opt['asArray']) && $opt['asArray']===true)
                return $rs->fetch();
            else
                return $rs->fetchObject($class_name);
        }else{

            //return as Array of objects / assoc arrays

            if(isset($opt['asArray']) && $opt['asArray']===true){
                return $rs->fetchAll();
            }else{

                #$arr = array();
                if(isset($tmodel_class) && isset($record->{$tmodel_class}) ){
                    $tmodelArray=null;
                }
                switch($rtype){
                    case 'has_one':
					case 'belongs_to':
                        foreach($rs as $k=>$v){
                            if(isset($v[$retrieved_pk_key]))
                                $fk = $v[$retrieved_pk_key];

                            //related model foreign key, if is Null means no relation!
                            $rfk = $v[$rretrieved_pk_key];

                            $record = new $class_name;

                            $record->{$rmodel} = ($rfk!=NULL)? new $rmodel : NULL;
                            if(isset($tmodel_class) && !isset($record->{$tmodel_class}) ){
                                if($tmodel_rtype=='has_many'){
                                    #echo 'Has Many 3rd';
                                    if(!isset($tmodelArray)){
                                        $tmodelArray = array();
                                        #echo '+++++ Create new 3rd Model Array';
                                    }
                                    $newtmodel = new $tmodel_class;
                                }else{
                                    #echo 'Has One 3rd';
                                    $newtmodel = new $tmodel_class;
                                }
                            }

                            foreach($v as $k2=>$v2){

                                //check and add to 3rd included Model
                                if(isset($newtmodel)){
                                    //found then add to 3rd Model, create the Model object if not yet created
                                    $tpart1 =  '_t_'.$tmodel->_table.'_';
                                    if( strpos($k2, $tpart1)===0 ){
                                        $tmodel_var = str_replace($tpart1, '', $k2);
                                        $newtmodel->{$tmodel_var} = $v2;
										continue;
                                    }
                                }

								$gotoRelateSect = true;
                                $inAsSelect = preg_match('/[\s]+(as|AS|aS|As)[\s]+'. $k2 .',/', $oriSel.',');

								if($inAsSelect || in_array($k2, $model_vars)){
										if( !$inAsSelect && $oriSel[0]!=='*' && 
                                            !preg_match("/,(\s+)?({$model->_table}\.)?$k2(\s+)?,/", ','.$oriSel.',') &&
											!preg_match('/,(\s+)?'.$model->_table .'\.\*/', ','. str_replace($model->_table .'.'. str_replace('_'.$model->_table.'__', '', $k2), $model->_table.'.*', $oriSel)) ){

											$gotoRelateSect = true;
										}else{
											$gotoRelateSect = false;
										}

									if($gotoRelateSect===false){
										if($k2===$retrieved_pk_key){
											$record->{$mparams['foreign_key']} = $v2;
										}else{
											//if it's a repeated var which is rename earlier(alias _table__fieldname), replace the original var with its value
											if(isset($alias_vars[$k2])){
												$record->{$alias_vars[$k2]} = $v2;
											}else{
												$record->{$k2} = $v2;
											}
										}
									}
                                }

								if($gotoRelateSect===true){

                                    if( isset($ralias_vars[$k2]) ){
                                        $k2 = $ralias_vars[$k2];
                                    }	
	
                                    if($oriSel[0]!=='*'){
                                        if(	in_array($k2, $rmodel_vars)===false &&
                                            !preg_match('/,(\s+)?'.$relatedmodel->_table .'\.\*/', ','. str_replace($relatedmodel->_table .'.'. $k2, $relatedmodel->_table .'.*', $oriSel)) ){
                                            continue;
                                        } else if( !preg_match("/,(\s+)?({$relatedmodel->_table}\.)?($k2|\*)(\s+)?,/", ','.$oriSel.',') ){
                                            continue;
                                        }
                                        else if( isset($alias_vars[$k2]) ){
                                            continue;
                                        }
                                    }
	

                                    if($rfk!=NULL){
                                        if($k2===$rretrieved_pk_key){
                                             $record->{$rmodel}->{$rparams['foreign_key']} = $v2;
                                             #echo "<h3>{$rparams['foreign_key']}</h3>";
                                         }else{
                                            //if it's a repeated var which is rename earlier, replace it to its original var by spilting it _table__field '__'
                                            if(isset($ralias_vars[$k2])){
                                                 #echo "<h1>{$ralias_vars[$k2]}</h1>";
                                                 $record->{$rmodel}->{$ralias_vars[$k2]} = $v2;
                                            }else{
												 #echo "<h2>$rmodel $k2</h2>";
                                                 $record->{$rmodel}->{$k2} = $v2;
                                            }
                                        }
                                    }
                                }
                            }

                            //add in the 3rd Model to the 3rdModel key if created
                            if(isset($newtmodel)){
                                if($tmodel_rtype=='has_many'){
                                    $tmodelArray[]= $newtmodel;
                                    #echo '----Added 3rd Model<br/>';
                                    $record->{$tmodel_class} = $tmodelArray;
                                }else{
                                    $record->{$tmodel_class} = $newtmodel;
                                }
                            }
                            $arr[] = $record;
                        }
                    break;

                    case 'has_many':
                            $mnull=-1;
                            $model_pk_arr = array();

                            foreach($rs as $k=>$v){

                                $fk = $v[$retrieved_pk_key];
                                if($v[$retrieved_pk_key]==Null){
                                    //for many to many usage, if unmatched
                                    $fk = $mnull--;
                                }

                                //related model foreign key, if is Null means no relation!
                                $rfk = $v[$rretrieved_pk_key];

                                if($rfk!=NULL)
                                    $assoc_model = new $rmodel;

                                //if not a repeated record, add it on to the result array
                                if(!in_array($fk, $model_pk_arr)){
                                    $model_pk_arr[]=$fk;
                                    $record = new $class_name;
                                    $record->{$rmodel} = ($rfk!=NULL)? array() : NULL;

									if(isset($tmodel_class) && !isset($record->{$tmodel_class}) ){
										if($tmodel_rtype=='has_many'){
											#echo 'Has Many 3rd';
											if(!isset($tmodelArray)){
												$tmodelArray = array();
												#echo '+++++ Create new 3rd Model Array';
											}
											$newtmodel = new $tmodel_class;
										}else{
											#echo 'Has One 3rd';
											$newtmodel = new $tmodel_class;
										}
									}

                                    foreach($v as $k2=>$v2){

										//check and add to 3rd included Model
										if(isset($newtmodel)){
											//found then add to 3rd Model, create the Model object if not yet created
											$tpart1 =  '_t_'.$tmodel->_table.'_';
											if( strpos($k2, $tpart1)===0 ){
												$tmodel_var = str_replace($tpart1, '', $k2);
												$newtmodel->{$tmodel_var} = $v2;
												continue;
											}
										}

										$gotoRelateSect = true;
                                        
                                        $inAsSelect = preg_match('/[\s]+(as|AS|aS|As)[\s]+'. $k2 .',/', $oriSel.',');
                                        
                                        if($inAsSelect || in_array($k2, $model_vars)){  
                                            
											if( !$inAsSelect && $oriSel[0]!=='*' && 
                                                !preg_match("/,(\s+)?({$model->_table}\.)?$k2(\s+)?,/", ','.$oriSel.',') &&
                                                !preg_match('/,(\s+)?'.$model->_table .'\.\*/', ','. str_replace($model->_table .'.'. str_replace('_'.$model->_table.'__', '', $k2), $model->_table .'.*', $oriSel)) ){

                                                $gotoRelateSect = true;
											}else{
												$gotoRelateSect = false;
											}


											if($gotoRelateSect===false){
												if($k2===$retrieved_pk_key)
													$record->{$mparams['foreign_key']} = $v2;
												else{
													//if it's a repeated var which is rename earlier(alias _table__fieldname), replace the original var with its value
													if(isset($alias_vars[$k2])){
														$record->{$alias_vars[$k2]} = $v2;
													}else{
														$record->{$k2} = $v2;
													}
												}
											}
                                        }

                                        if($gotoRelateSect===true){

                                            if( isset($ralias_vars[$k2]) ){
                                                $k2 = $ralias_vars[$k2];
                                            }

                                            if($oriSel[0]!=='*'){
                                                if(	in_array($k2, $rmodel_vars)===false &&
                                                    !preg_match('/,(\s+)?'.$relatedmodel->_table .'\.\*/', ','. str_replace($relatedmodel->_table .'.'. $k2, $relatedmodel->_table .'.*', $oriSel)) ){
                                                        continue;
                                                }
                                                else if(!preg_match("/,(\s+)?({$relatedmodel->_table}\.)?($k2|\*)(\s+)?,/", ','.$oriSel.',') ){
                                                    continue;
                                                }
                                            }

                                            if($rfk!=NULL){
                                                if($k2===$rretrieved_pk_key){
                                                    if(in_array($rparams['foreign_key'], $assoc_model->_fields)){
                                                        $assoc_model->{$rparams['foreign_key']} = $v2;
                                                    }
                                                }else{
                                                    //if it's a repeated var which is rename earlier, replace it to its original var by spilting it _table__field '__'
                                                    if(isset($ralias_vars[$k2])){
                                                        $assoc_model->{$ralias_vars[$k2]} = $v2;
                                                    }else if( in_array($k2, $assoc_model->_fields) ){
                                                        $assoc_model->{$k2} = $v2;
                                                    }
                                                }
                                            }
                                        }
                                    }

									//add in the 3rd Model to the 3rdModel key if created
									if(isset($newtmodel)){
										if($tmodel_rtype=='has_many'){
											$tmodelArray[]= $newtmodel;
											#echo '----Added 3rd Model<br/>';
											$record->{$tmodel_class} = $tmodelArray;
										}else{
											$record->{$tmodel_class} = $newtmodel;
										}
									}

                                    //do not add to the associated object Array if, relation not found, means empty Array, no record! if not it will create an Array with an empty Model Object
                                    if($rfk!=NULL)
                                        array_push($record->{$rmodel}, $assoc_model);
                                    else{
                                        $record->{$rmodel} = array();
                                    }

                                    $arr[] = $record;
                                }
                                //if already exist, then modify the model record by appending the a related object to the model class properties
                                else{
                                    $indexToChg = array_search($fk, $model_pk_arr);
                                    $record = $arr[ $indexToChg ];

                                    foreach($v as $k2=>$v2){
                                        //add only vars of the second(related) model
                                        if(in_array($k2, $rmodel_vars)){

                                            if( isset($ralias_vars[$k2]) ){
                                                $k2 = $ralias_vars[$k2];
                                            }

                                            if($oriSel[0]!=='*'){
                                                if(	in_array($k2, $rmodel_vars)===false &&
                                                    !preg_match('/,(\s+)?'.$relatedmodel->_table .'\.\*/', ','. str_replace($relatedmodel->_table .'.'. $k2, $relatedmodel->_table .'.*', $oriSel)) ){
                                                    continue;
                                                }
                                                else if( !preg_match("/,(\s+)?({$relatedmodel->_table}\.)?($k2|\*)(\s+)?,/", ','.$oriSel.',') ){
                                                    continue;
                                                }
                                            }

                                            if($rfk!=NULL){
                                                if($k2===$rretrieved_pk_key)
                                                    $assoc_model->{$rparams['foreign_key']} = $v2;
                                                else{
                                                    //if it's a repeated var which is rename earlier, replace it to its original var by spilting it _table__field '__'
                                                    if(isset($ralias_vars[$k2])){
//														echo "<h3>$k2 = $v2</h3>";
                                                        $assoc_model->{$ralias_vars[$k2]} = $v2;
                                                    }else{
//														echo "<h3>$k2 = $v2</h3>";
                                                        $assoc_model->{$k2} = $v2;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    array_push($record->{$rmodel}, $assoc_model);
                                    $arr[ $indexToChg ] = $record;
                                }
                            }

                    break;
                }

                //return the first object instead of an array of objects
                if(isset($opt['limit']) && $opt['limit']=='first'){
                    if(isset($newtmodel)){
                        $last = sizeof($arr)-1;
                        return (isset($arr[$last]))?$arr[$last] : NULL;
                    }else{
                        return (isset($arr[0]))?$arr[0] : NULL;
                    }
                }

                return (isset($arr))?$arr : NULL;
            }
        }

    }


    /**
     * Combine relational search results (combine multiple relates).
     *
     * Example:
     * <code>
     * Doo::db()->relateMany('Food',array('Recipe','Article','FoodType'))
     * </code>
     *
     * @param mixed $model The model class name or object to be select.
     * @param array $rmodel The related models class names.
     * @param array $opt Array of options for each related model to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model objects of the queried result
     */
    public function relateMany($model, $rmodel, $opt=null){
        //---------------------Model has many other rmodels (has_many, has_one & belongs_to relationship only) ----------------
		//add main model primary key to select list to link the related model results

		$modelObj = $model;
		if (is_string($modelObj)) {
			$this->loadModel($modelObj);
			$modelObj = new $modelObj();
		}
		$mdl_pk = $modelObj->_primarykey;
        $mdl_tbl = $modelObj->_table;

		$rOpt = null;
		if( isset($opt[$rmodel[0]]) ){
			$rOpt = $opt[$rmodel[0]];
			if(isset($rOpt['select'])){
				$rOpt['select'] = "$mdl_tbl.$mdl_pk, " . $rOpt['select'];
			}
		}
		$mainR = $this->relate($model, $rmodel[0], $rOpt);

        if($mainR===null)
            return;

        $r=array();

        foreach($rmodel as $rm){
			if($rm==$rmodel[0])
				continue;

			$rOpt = (isset($opt[$rm])) ? $opt[$rm] : null;
			if(isset($rOpt['select'])){
				$rOpt['select'] = "$mdl_tbl.$mdl_pk, " . $rOpt['select'];
			}else{
				$this->loadModel($rm);
                $newrm = new $rm;
				$rOpt['select'] = "$mdl_tbl.$mdl_pk, {$newrm->_table}.*";
			}
			$r[] = $this->relate($model, $rm, $rOpt);
		}

        $relatedClass = $rmodel;

        foreach($mainR as $k1=>$v1){
            foreach($r as $k2=>$v2){
                $cls = $relatedClass[$k2+1];
				foreach((array)$v2 as $k3=>$v3){
					if( $v3->{$mdl_pk} == $v1->{$mdl_pk}){
						$mainR[$k1]->{$cls} = $v3->{$cls};
						break;
					}
				}
				if (!isset($mainR[$k1]->{$cls})) {
					if( is_string($model) ) {
						$relationType = self::relationType($this->map, $model, $cls);
					} else {
						$relationType = self::relationType($this->map, get_class($model), $cls);
					}

					$relationType = $relationType[0];

					if( $relationType == 'has_one' || $relationType == 'belongs_to' ) {
						$mainR[$k1]->{$cls} = null;
					} else {
						$mainR[$k1]->{$cls} = array();
					}
				}
            }
        }

        return $mainR;
	}


    /**
     * Expand related models (Tree Relationships).
     *
     * Example:
     * <code>
     * Doo::db()->relateExpand('Recipe', array('Food','Article'))
     * </code>
     *
     * @param mixed $model The model class name or object to be select.
     * @param array $rmodel The related models class names.
     * @param array $opt Array of options for each related model to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model objects of the queried result
     */
    public function relateExpand($model, $rmodel, $opt=null){
        $rm = $rmodel[0];
        $mainR = $this->relate($model, $rm, (isset($opt[$rm])) ? $opt[$rm] : null );
        $id = array();

        if($mainR===NULL) return;

        foreach($mainR as $mr){
            if($m = $mr->{$rm}){
                if(is_array($m)){
                    foreach($m as $m2){
                        $id[] = $m2->{$m2->_primarykey};
                    }
                }else{
                    $id[] = $m->{$m->_primarykey};
                }
            }
        }

        $this->loadModel($rm);
        $newrm = new $rm;

        $rm2 = $rmodel[1];
        $this->loadModel($rm2);
        $newrm2 = new $rm2;

        $rOpt = (isset($opt[$rm2])) ? $opt[$rm2] : null;
        if(isset($rOpt['select'])){
            $rOpt['select'] = $newrm->_table .".". $newrm->_primarykey .", " . $rOpt['select'];
        }else{
            $rOpt['select'] = $newrm->_table .".". $newrm->_primarykey .", {$newrm2->_table}.*";
        }

        if(isset($rOpt['where'])){
           $rOpt['where'] = $newrm->_table.'.'. $newrm->_primarykey .' IN ('. implode(',', $id) .') AND ( ' . $rOpt['where'] .' )';
        }else{
           $rOpt['where'] = $newrm->_table.'.'. $newrm->_primarykey .' IN ('. implode(',', $id) .') ';
        }

        $r = $this->relate($rm, $rm2, $rOpt);

		if (!empty($r)) {
			foreach($mainR as $k=>$v){
				foreach($r as $k2=>$v2){
					$rml = $v->{$rm};
					if( is_array($rml) ){
						foreach($rml as $k3=>$v3){
							if($v3->{$v3->_primarykey} == $v2->{$v2->_primarykey}){
								$mainR[$k]->{$rm}[$k3]->{$rm2} = $v2->{$rm2};
							}
						}
					}else{
						if($v->{$rm}->{$v2->_primarykey} == $v2->{$v2->_primarykey}){
							$mainR[$k]->{$rm}->{$rm2} = $v2->{$rm2};
						}
					}
				}
			}
		}

        return $mainR;
    }


    /**
     * Adds a new record. (Prepares and execute the INSERT statements)
     * @param object $model The model object to be insert.
     * @return int The inserted record's Id
     */
    public function insert($model){
        $class_name = get_class($model);

        //add values to fields where the model propertie(s) are/is set
        $obj = get_object_vars($model);

        $values = array();
        $valuestr = '';
        $fieldstr = '';

        foreach($obj as $o=>$v){
            if(isset($v) && in_array($o, $model->_fields)){
                if(is_object($v)){
                    $valuestr .= "$v,";
                }else{
                    $values[] = $v;
                    $valuestr .= '?,';
                }
                if($this->dbconfig[4]=='mysql'){
                    $fieldstr .= '`'.$o .'`,';
                }else{
                    $fieldstr .= $o .',';
                }
            }
        }

        $valuestr = substr($valuestr, 0, strlen($valuestr)-1);
        $fieldstr = substr($fieldstr, 0, strlen($fieldstr)-1);

        $sql ="INSERT INTO {$obj['_table']} ($fieldstr) VALUES ($valuestr)";
        $this->query($sql, $values);
        return $this->pdo->lastInsertId();
    }

    /**
     * Use insertAttributes() instead
     * @deprecated deprecated since version 1.3
     * @param string|object $model The model object to be insert.
     * @param array Array of data (keys and values) to be insert
     * @return int The inserted record's Id
     */
    public function insert_attributes($model, $data){
        return $this->insertAttributes($model, $data);
    }
    
    /**
     * Adds a new record with a list of keys & values (assoc array) (Prepares and execute the INSERT statements)
     * @param string|object $model The model object to be insert.
     * @param array Array of data (keys and values) to be insert
     * @return int The inserted record's Id
     */
    public function insertAttributes($model, $data){
        if(is_string($model)){
            $model = $this->loadModel($model,true);
            $table = $model->_table;
        }else{
            $table = $model->_table;
        }

        $values = array();
        $valuestr = '';
        $fieldstr = '';

        foreach($data as $o=>$v){
            if(isset($v) && in_array($o, $model->_fields)){
                if(is_object($v)){
                    $valuestr .= "$v,";
                }else{
                    $values[] = $v;
                    $valuestr .= '?,';
                }
                if($this->dbconfig[4]=='mysql'){
                    $fieldstr .= '`'.$o .'`,';
                }else{
                    $fieldstr .= $o .',';
                }
            }
        }

        $valuestr = substr($valuestr, 0, strlen($valuestr)-1);
        $fieldstr = substr($fieldstr, 0, strlen($fieldstr)-1);

        $sql ="INSERT INTO {$model->_table} ($fieldstr) VALUES ($valuestr)";
        $this->query($sql, $values);
        return $this->pdo->lastInsertId();
    }


    //all rmodel belong to the first model, only 1 level relationship supported,
    //all type except BELONGS_TO (no use in INSERT)
    //if many-to-many, insert into 3 tables, the model's table, related model's table and the 'through' table defined in db.config
    //many to many need to check if the related model already exist, if so,use back that r model record's ID, add the model record and add both id to the 'through' table
    //if related model not exist, add to all 3 tables

    /**
     * Adds a new record with its associated models. Relational insert. (Prepares and execute the INSERT statements)
     * @param object $model The model object to be insert.
     * @param array $rmodels A list of associated model objects to be insert along with the main model.
     * @return int The inserted record's Id
     */
    public function relatedInsert($model, $rmodels){

        //insert the main model first and save its id
        $main_id = $this->insert($model);
        $model->{$model->_primarykey} = $main_id;       //for later use in many-to-many relationship, need the primary key

        //loop and get their relationship, set the foreign key to $main_id
        foreach($rmodels as $rmodel){
            $class_name = get_class($model);
            $rclass_name = get_class($rmodel);
            //get how the related model relates to the main model object
            list($rtype,$rparams) = self::relationType($this->map, $rclass_name, $class_name);
            if($rtype==NULL)
                throw new SqlMagicException("Model $class_name does not relate to $rclass_name", SqlMagicException::RelationNotFound);
            #print_r(array($rtype,$rparams));

            if($rtype=='has_many' && isset($rparams['foreign_key']) && isset($rparams['through'])){
                //echo '<h2>Insert MAny to many</h2>';
                //select only the primary key (id) and the set properties
                $obj = get_object_vars($rmodel);
                $fieldstr ='';
                foreach($obj as $o=>$v){
                    if(isset($v) && in_array($o, $model->_fields)){
                        $fieldstr .= ','.$o;
                    }
                }
                $fieldstr = "{$rmodel->_primarykey}$fieldstr";

                //get the linked key(Model's foreign key) for the $model from the relationship defined. It's not always primary key of the Model
                $reversed_relation = self::relationType($this->map, $class_name, $rclass_name);
                $model_linked_key = $reversed_relation[1]['foreign_key'];
                //$mId = $model->{$rparams['foreign_key']};
                $mId = $model->{$model->_primarykey};

                //check if the related model already exist it true than insert to the 'through' table with the 2 ids.
                $chk_rmodel = $this->find($rmodel, array('select'=>$fieldstr, 'limit'=>1));
                if($chk_rmodel!=NULL){
                    //echo '<h1>'.$chk_rmodel->{$rparams['foreign_key']}.'</h1>';
                    $rId = $chk_rmodel->{$chk_rmodel->_primarykey};
                }else{
                    //echo '<h2>Not found this related model in many-to-many. Insert it!</h2>';
                    $rId = $this->insert($rmodel);
                }

                //insert into the 'through' Table, faster with parameterized prepared statements
                $this->query( "INSERT INTO {$rparams['through']} ({$model_linked_key},{$rparams['foreign_key']})  VALUES (?,?)", array($mId,$rId));
            }
            else if(isset($rparams['foreign_key'])){
				list($rtype,$rparams) = self::relationType($this->map, $class_name, $rclass_name);
                $rmodel->{$rparams['foreign_key']} = $main_id;
                $this->insert($rmodel);
            }
        }

        return $main_id;
    }

    /**
     * Update an existing record. (Prepares and execute the UPDATE statements)
	 * If you want to set null values during the update use the setnulls option
     * @param mixed $model The model object to be updated.
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, field, param, setnulls</i
     * @return int Number of rows affected
     */
    public function update($model, $opt=NULL){
        //add values to fields where the model propertie(s) are/is set
        $obj = get_object_vars($model);

        $values = array();
        $field_and_value = '';

		$opt['setnulls'] = isset($opt['setnulls']) ? $opt['setnulls'] : false;

        if(isset($opt['field'])){
            $opt['field'] = explode(',', str_replace(' ', '', $opt['field']));
            foreach($obj as $o=>$v) {
                if(in_array($o, $opt['field'])) {
                    if (($opt['setnulls'] === true || isset($v)) && in_array($o, $model->_fields)) {
                        if (is_object($v)){
                            $field_and_value .= "$o=$v,";
                        } elseif ($v === null) {
							$field_and_value .= "$o=null,";
						} else {
                            $values[] = $v;
                            $field_and_value .= $o .'=?,';
                        }
                    }
                }
            }
        }else{
            foreach($obj as $o=>$v){
                if(($opt['setnulls'] === true || isset($v)) && in_array($o, $model->_fields)){
                    if (is_object($v)) {
                        $field_and_value .= "$o=$v,";
					} elseif ($v === null) {
						$field_and_value .= "$o=null,";
                    } else {
                        $values[] = $v;
                        $field_and_value .= $o .'=?,';
                    }
                }
            }
        }

        $field_and_value = substr($field_and_value, 0, strlen($field_and_value)-1);

        if(isset($model->{$obj['_primarykey']})){
            $where = $obj['_primarykey'] .'=?';
            $values[] = $model->{$obj['_primarykey']};
            $sql ="UPDATE {$obj['_table']} SET {$field_and_value} WHERE {$where}";
        }else{
            $where = $opt['where'];
            if(isset($opt['param']))
                $values = array_merge($values, $opt['param']);

            if(isset($opt['limit'])){
                $sql ="UPDATE {$obj['_table']} SET {$field_and_value} WHERE {$where} LIMIT {$opt['limit']}";
            }else{
                $sql ="UPDATE {$obj['_table']} SET {$field_and_value} WHERE {$where}";
            }
        }

        return $this->query($sql, $values)->rowCount();
    }

    /**
     * Update an existing record with a list of keys & values (assoc array). (Prepares and execute the UPDATE statements)
     * @param mixed $model The model object to be updated.
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, field, param</i>
     * @return int Number of rows affected
     */
    public function update_attributes($model, $data, $opt=NULL){
        if(is_string($model)){
            $model = $this->loadModel($model,true);
            $table = $model->_table;
        }else{
            $table = $model->_table;
        }

        $values = array();
        $field_and_value = '';

        if(isset($opt['field'])){
            $opt['field'] = explode(',', str_replace(' ', '', $opt['field']));
            foreach($data as $o=>$v){
                if(in_array($o, $opt['field'])){
                    if(isset($v) && in_array($o, $model->_fields)){
                        if(is_object($v)){
                            $field_and_value .= "$o=$v,";
                        }else{
                            $values[] = $v;
                            $field_and_value .= $o .'=?,';
                        }
                    }
                }
            }
        }else{
            foreach($data as $o=>$v){
                if(isset($v) && in_array($o, $model->_fields)){
                    if(is_object($v)){
                        $field_and_value .= "$o=$v,";
                    }else{
                        $values[] = $v;
                        $field_and_value .= $o .'=?,';
                    }
                }
            }
        }

        $field_and_value = substr($field_and_value, 0, strlen($field_and_value)-1);

        if(isset($data[$model->_primarykey])){
            $where = $model->_primarykey .'=?';
            $values[] = $data[$model->_primarykey];
            $sql ="UPDATE {$model->_table} SET {$field_and_value} WHERE {$where}";
        }else{
            $where = $opt['where'];
            if(isset($opt['param']))
                $values = array_merge($values, $opt['param']);

            if(isset($opt['limit'])){
                $sql ="UPDATE {$model->_table} SET {$field_and_value} WHERE {$where} LIMIT {$opt['limit']}";
            }else{
                $sql ="UPDATE {$model->_table} SET {$field_and_value} WHERE {$where}";
            }
        }
        return $this->query($sql, $values)->rowCount();
    }

    /**
     * Update an existing record with its associated models. Relational update. (Prepares and execute the UPDATE statements)
     * @param mixed $model The model object to be updated.
     * @param array $rmodels A list of associated model objects to be updated or insert along with the main model.
     * @param array $opt Assoc array of options to update the main model. Supported: <i>where, limit, field, param</i>
     */
    public function relatedUpdate($model, $rmodels, $opt=NULL){
        $this->update($model, $opt);
        foreach($rmodels as $rmodel){
            #echo $rmodel->{$rmodel->_primarykey} . '<br>';
            //if related model id is set, means update the existing record!
            if(isset($rmodel->{$rmodel->_primarykey})){
                $this->update($rmodel);
            }
            //insert the related model with the relation's foreign key
            else{
                $class_name = get_class($model);
                $rclass_name = get_class($rmodel);

                //get how the related model relates to the main model object
                list($rtype,$rparams) = self::relationType($this->map, $rclass_name, $class_name);
                if($rtype==NULL)
                    throw new SqlMagicException("Model $class_name does not relate to $rclass_name", SqlMagicException::RelationNotFound);
                #print_r(array($rtype,$rparams));

                if($rtype=='has_many' && isset($rparams['foreign_key']) && isset($rparams['through'])){
                    //echo '<h2>Insert MAny to many</h2>';
                    //select only the primary key (id) and the set properties
                    $obj = get_object_vars($rmodel);
                    $fieldstr ='';
                    foreach($obj as $o=>$v){
                        if(isset($v) && in_array($o, $model->_fields)){
                            $fieldstr .= ','.$o;
                        }
                    }
                    $fieldstr = "{$rmodel->_primarykey}$fieldstr";

                    //get the linked key(Model's foreign key) for the $model from the relationship defined. It's not always primary key of the Model
                    $reversed_relation = self::relationType($this->map, $class_name, $rclass_name);
                    $model_linked_key = $reversed_relation[1]['foreign_key'];
                    //$mId = $model->{$rparams['foreign_key']};
                    $mId = $model->{$model->_primarykey};

                    //check if the related model already exist it true than insert to the 'through' table with the 2 ids.
                    $chk_rmodel = $this->find($rmodel, array('select'=>$fieldstr, 'limit'=>1));
                    if($chk_rmodel!=NULL){
                        //echo '<h1>'.$chk_rmodel->{$rparams['foreign_key']}.'</h1>';
                        $rId = $chk_rmodel->{$chk_rmodel->_primarykey};
                    }else{
                        //echo '<h2>Not found this related model in many-to-many. Insert it!</h2>';
                        $rId = $this->insert($rmodel);
                    }

                    //insert into the 'through' Table, if exists dun create duplicates
                    if($this->query("SELECT {$rparams['foreign_key']},{$model_linked_key} FROM {$rparams['through']} WHERE {$rparams['foreign_key']}=? AND {$model_linked_key}=?", array($rId,$mId))->fetch()==NULL){
                        $this->query( "INSERT INTO {$rparams['through']} ({$model_linked_key},{$rparams['foreign_key']}) VALUES (?,?)", array($mId,$rId));
                    }
                }
                else if(isset($rparams['foreign_key'])){
                    //echo '<h2>Insert 1to1 or 1tomany</h2>';
					list($rtype,$rparams) = self::relationType($this->map, $class_name, $rclass_name);
                    $rmodel->{$rparams['foreign_key']} = $model->{$model->_primarykey};
                    $this->insert($rmodel);
                }
            }
        }
    }

	/**
	 * Delete all records from table (Prepares and executes the DELETE statement)
	 * @param mixed $model The model object from which to delete all records
	 */
	public function deleteAll($model) {
		if (!is_object($model)) {
			$this->loadModel($model);
			$model = new $model;
		}

		$sql = "DELETE FROM {$model->_table}";
		$rs = $this->query($sql);
	}

    /**
     * Delete an existing record. (Prepares and execute the DELETE statements)
     * @param mixed $model The model object to be deleted.
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, param</i>
     */
    public function delete($model, $opt=NULL){
        if(is_object($model)){
            //add values to fields where the model propertie(s) are/is set
            $obj = get_object_vars($model);

            //WHERE statement needs to change to parameterized stament =? instead of =value
            $wheresql ='';
            $where_values = array();

            foreach($obj as $o=>$v){
                if(isset($v) && in_array($o, $model->_fields)){
                    $wheresql .= " AND {$obj['_table']}.$o=?";
                    $where_values[] = $v;
                }
            }

            if($wheresql!=''){
                if(isset($opt['where'])){
                    $opt['where'] .= $wheresql;
                }else{
                    $opt['where'] = substr($wheresql, 5);
                }
            }
        }else{
            //require file only when String is passed in
            $this->loadModel($model);
            $model = new $model;
        }

        if(isset($opt['limit']))
            $sql ="DELETE FROM {$model->_table} WHERE {$opt['where']} LIMIT {$opt['limit']}";
        else
            $sql ="DELETE FROM {$model->_table} WHERE {$opt['where']}";

        //conditions WHERE param
        if(isset($opt['param']) && isset($where_values))
            $rs = $this->query($sql, array_merge( $opt['param'], $where_values));
        else if(isset($opt['param']))
            $rs = $this->query($sql, $opt['param']);
        else if(isset($where_values))
            $rs = $this->query($sql, $where_values);
        else
            $rs = $this->query($sql);
    }

    /**
     * Retrieve the relationship between 2 model class
     * @param array $map The relationship mapping
     * @param string $model_name Main model name
     * @param string $relate_model_name Related model name
     * @return array Relationship with details such as relationship type, foreign key, linked table
     */
    public static function relationType($map, $model_name, $relate_model_name){
		if (!isset($map[$model_name])) {
			throw new SqlMagicException("No relationship mapping found between '{$model_name}' and '{$relate_model_name}'");
		}
        $r1 = $map[$model_name];
        #print_r($r1);
        $rtype = NULL;

        foreach($r1 as $k=>$v){
            #echo "$k<ul>";
            foreach($v as $m=>$n){
                #echo "<li>$m</li>";
                if($m == $relate_model_name){
                    $rtype = $k;
                    break 2;
                }
            }
            #echo "</ul>";
        }

        //return relation type, relation params (foreign_key, through)
        return array($rtype, $n);
    }

    protected static function addWhereNotNull(&$where, $relatedKey, $modelKey){
        if(empty($where)){
            $where = "WHERE ($relatedKey IS NOT NULL AND $modelKey IS NOT NULL)";
        }else{
            $where .= " AND ($relatedKey IS NOT NULL AND $modelKey IS NOT NULL)";
        }
    }

    protected static function addWhereIsNull(&$where, $relatedKey, $modelKey){
        if(empty($where)){
            $where = "WHERE ($relatedKey IS NULL OR $modelKey IS NULL)";
        }else{
            $where .= " AND ($relatedKey IS NULL OR $modelKey IS NULL)";
        }
    }

    public static function toObject($array, $class_name) {
       $object = new $class_name;
       if (is_array($array) && count($array) > 0) {
          foreach ($array as $name=>$value) {
             $name = strtolower(trim($name));
             if (!empty($name)) {
                $object->$name = $value;
             }
          }
       }
       return $object;
    }

    public static function toArray($object) {
       if (is_object($object)) {
          $array = get_object_vars($object);
       }
       return $array;
    }

}

class SqlMagicException extends Exception {
  const UnexpectedClass   = 0;
  const RelationNotFound = 1;
  const DBConfigNotFound = 2;
  const DBConnectionError = 3;
}
