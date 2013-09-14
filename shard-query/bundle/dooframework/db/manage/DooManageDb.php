<?php

/**
 * DooManageDb class file.
 *
 * @author Richard Myers <richard.myers@hotmail.co.uk>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 * @package doo.db
 * @since 1.3
 */
 
/**
 * An abstract Database manager for specific RDBMS adapters.
 * For each specific engine being supported the following functions and properties must be overriden
 *
 * {{code: php
 * 	protected $colTypeMapping;
 * 	protected $identiferQuotePrefix;
 * 	protected $identiferQuoteSuffix;
 * 	abstract columnDefineAutoincPrimary(&$columnDefinition, $autoinc, $primary);
 * }}
 *
 *
 *
 */
abstract class DooManageDb {

	/**
	 *  Usable Column Types
	 */
	const COL_TYPE_BOOL		 = 'bool';
	const COL_TYPE_SMALLINT  = 'smallint';
	const COL_TYPE_INT		 = 'int';
	const COL_TYPE_BIGINT	 = 'bigint';
	const COL_TYPE_DECIMAL	 = 'decimal';
	const COL_TYPE_FLOAT	 = 'float';
	const COL_TYPE_CHAR		 = 'char';
	const COL_TYPE_VARCHAR	 = 'varchar';
	const COL_TYPE_CLOB		 = 'clob';
	const COL_TYPE_DATE		 = 'date';
	const COL_TYPE_TIME		 = 'time';
	const COL_TYPE_TIMESTAMP = 'timestamp';

	/**
     * Determined whether the database connection is made.
     * @var bool
     */
    public $connected = false;

	/**
	 * A mapping of Doo generic datatypes to RDBMS native datatypes for columns
	 * These must be defined in each specific adapter
	 *
	 * The datatypes are
	 * COL_TYPE_BOOL		: A true or false boolean
	 * COL_TYPE_SMALLINT	: 2-byte integer (-32,767 to 32,768)
	 * COL_TYPE_INT			: 4-byte integer (-2,147,483,648 to 2,147,483,647)
	 * COL_TYPE_BIGINT		: 8-byte integer (about -9,000 trilllion to 9,000 trillion)
	 * COL_TYPE_DECIMAL		: Fixed point decimal of specific size (total digits) and scope (num digits after decimal point)
	 * COL_TYPE_FLOAT		: A double-percision floating point decimal number
	 * COL_TYPE_CHAR		: A fixed length string of 1-255 characters
	 * COL_TYPE_VARCHAR		: A variable length string of 1-255 characters
	 * COL_TYPE_CLOB		: A large character object of up to about 2Gb
	 * COL_TYPE_DATE		: an ISO 8601 date eg. 2009-09-27
	 * COL_TYPE_TIME		: an ISO 8601 time eg. 18:38:49
	 * COL_TYPE_TIMESTAMP	: an ISO 8601 timestamp without a timezone eg. 2009-09-27 18:38:49
	 *
	 * @var array
	 */
	protected $colTypeMapping = array (
		DooManageDb::COL_TYPE_BOOL		=> null,
    	DooManageDb::COL_TYPE_SMALLINT	=> null,
    	DooManageDb::COL_TYPE_INT		=> null,
    	DooManageDb::COL_TYPE_BIGINT	=> null,
    	DooManageDb::COL_TYPE_DECIMAL	=> null,
    	DooManageDb::COL_TYPE_FLOAT		=> null,
    	DooManageDb::COL_TYPE_CHAR		=> null,
    	DooManageDb::COL_TYPE_VARCHAR	=> null,
    	DooManageDb::COL_TYPE_CLOB		=> null,
    	DooManageDb::COL_TYPE_DATE		=> null,
    	DooManageDb::COL_TYPE_TIME		=> null,
    	DooManageDb::COL_TYPE_TIMESTAMP	=> null,
	);

	/**
	 * Array of all SQL commands run against db
	 * @var array
	 */
	protected $sqlList = array();

	/**
     * The RDBMS specific quote character before an identifiers name
     * This must be defined in each specific adapter
     * @var string
     */
    protected $identiferQuotePrefix = null;

    /**
     * The RDBMS specific quote character after an identifiers name
     * This must be defined in each specific adapter
     * @var string
     */
    protected $identiferQuoteSuffix = null;

	/**
	 * Database configuration to use
	 * @var array
	 */
	protected $dbconfig;


	/**
	 * A PDO Object for accessing the Database
	 * @var object
	 */
	protected $pdo = null;

	/**
     * Enable/disable SQL tracking, to view SQL which has been queried, use showSQL()
     * @var bool
     */
	private $sqlTracking=false;

	/**
     * Set the database configuration
     * @param array $dbconfig Connection information (db_host, db_name, db_user, db_pwd, db_driver, db_connection_cache)
     */
    public function setDb($dbconfig){
        $this->dbconfig = $dbconfig;
    }

	/**
     * Connects to the database with the default database connection configuration
     */
    public function connect(){
        if($this->dbconfig == NULL) {
			return;
		}
        try {
            if ($this->dbconfig[4] == 'sqlite') {
                $this->pdo = new PDO("{$this->dbconfig[4]}:{$this->dbconfig[0]}");
			} else {
                $this->pdo = new PDO("{$this->dbconfig[4]}:host={$this->dbconfig[0]};dbname={$this->dbconfig[1]}", $this->dbconfig[2], $this->dbconfig[3],array(PDO::ATTR_PERSISTENT => $this->dbconfig[5]));
			}
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connected = true;
            if (isset($this->dbconfig['charset']) && isset($this->dbconfig['collate'])) {
                $this->pdo->exec("SET NAMES '". $this->dbconfig['charset']. "' COLLATE '". $this->dbconfig['collate'] ."'");
            } else if (isset($this->dbconfig['charset']) ) {
                $this->pdo->exec("SET NAMES '". $this->dbconfig['charset']. "'");
            }
        } catch(PDOException $ex) {
            throw new DooManageDbException('Failed to open the DB connection', $ex->getMessage());
        }
    }


	/**
     * Execute a query to the connected database
     * @param string $query SQL query prepared statement
     * @param array $param Values used in the prepared SQL
     * @return PDOStatement
     */
    public function query($query){
        if($this->sqlTracking === true) {
            $this->sqlList[] = $query;
        }
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt;
    }

	/**
	 * Should SQL querys being executed be logged?
	 * @param bool $enable True if tracking should be on, false otherwise
	 */
	public function enableSqlHistory($enable = true) {
		$this->sqlTracking = $enable;
	}

	/**
     * Retrieve a list of executed SQL queries
     * @return array
     */
    public function getSqlHistory(){
        return $this->sqlList;
    }

    /**
     * Get the number of queries executed
     * @return int
     */
    public function getQueryCount(){
        return sizeof($this->sqlList);
    }

	/**
     * Reset list of executed SQL queries
     * @return void
     */
    public function resetSqlHistory(){
        $this->sqlList = array();
    }

	/**
	 * Checks to see if the specified table
	 *
	 * @param string $table Name of the table to check existance of
	 */
	public function tableExists($table) {
		$stmt = $this->query("show tables like '{$table}'");
		$result = $stmt->fetch();
		return !empty($result);
	}
	
	/**
	 * Creates a new table within the active database
	 *
	 * The $cols parameter should be an array of column information in the following format
	 *
	 * {{code: php
	 * 	$cols = array(
	 * 		'column1' => array(
	 * 			'type'    => DooManageDb::COL_TYPE_*
	 * 			'size'    => (int) size limitation for char|varchar|numeric
	 * 			'scope'   => (int) decimal places for numeric
	 * 			'require' => (bool) is the value required ie. NOT NULL
	 * 			'default' => (mixed) default value for this column
	 * 			'autoinc' => (bool) is this value to be auto incremented?
	 * 			'primary' => (bool) is this a primary column?
	 *      ),
	 *      'column2' => array(...)
	 *  );
	 * }}
	 *
	 * The field types are defined in the DOO_DB_COL_TYPE_* definitions which are defined in the DooDbManager.php file
	 *
	 * @param string $table Name of the table to create
	 * @param array $cols Array of the columns to create
	 * @return bool on success of the tables creation
	 */
	public function createTable($table, $cols, $options=null) {
		$statement = $this->_sqlCreateTable($table, $cols, $options);
		try {
			$this->query($statement);
		} catch (PDOException $ex) {
			throw new DooManageDbException("Error Creating Table : $table", $ex->getMessage());
		}
	}

	/**
	 * Generates an SQL CREATE TABLE statement for the active database type
	 * @param string $table Name of the table to create
	 * @param array $cols Array of column defenitions
	 * @return string SQL CREATE TABLE statement for the active database engine
	 */
	protected function _sqlCreateTable($table, $columnDefinitions, $options=null) {

		$this->checkIdentifier('table', $table);

		$columnDefs = array();
		$errors = array();

		foreach($columnDefinitions as $name => $attributes) {
			try {
				$columnDefs[] = $this->buildColumnDefinition($name, $attributes);
			} catch (DooDbAdminException $ex) {
				$errors[$name] = $ex->getMessage();
			}
		}
		if ($errors) {
			throw new DooManageDbException("Could not create table", implode("\n", $errors));
		}

		$columnDefinitions = implode(",\n   ", $columnDefs);
		$table = $this->quoteName($table);
		return "CREATE TABLE $table (\n   $columnDefinitions\n)";
	}

	/**
	 * Drops a table from the database if it exists
	 * @param string $table Name of the table to be dropped
	 * @return bool true if the table was dropped (or does not exist), false if drop fails
	 */
	public function dropTable($table) {
		$tableQuotted = $this->quoteName($table);
		$statement = $this->_sqlDropTable($tableQuotted);
		try {
			$this->query($statement);
		} catch (PDOException $ex) {
			throw new DooManageDbException("Error Dropping Table : $table", $ex->getMessage());
		}
	}

	/**
	 * Generates the SQL for the active database to delete a table
	 * @param object $table
	 * @return
	 */
	protected function _sqlDropTable($table) {
		return "DROP TABLE IF EXISTS $table";
	}

	/**
	 * Adds a new column $name to $table with $attributes
	 *
	 * The $attributes should be formatted as...
	 *
	 * {{code: php
	 * 	$attributes = array(
	 * 		'type'    => DOO_DB_COL_TYPE_*
	 * 		'size'    => (int) size limitation for char|varchar|numeric
	 * 		'scope'   => (int) decimal places for numeric
	 * 		'require' => (bool) is the value required ie. NOT NULL
	 * 		'default' => (mixed) default value for this column
	 * 		'autoinc' => (bool) is this value to be auto incremented?
	 * 		'primary' => (bool) is this a primary column?
	 *  );
	 * }}
	 *
	 * @param object $table Name of the table the column is to be added to
	 * @param object $name Name of the new column to add
	 * @param object $attributes The column attributes
	 * @return
	 */
	public function addColumn($table, $name, array $attributes) {
		$statement = $this->_sqlAddColumn($table, $name, $attributes);
		try {
			$this->query($statement);
		} catch (PDOException $ex) {
			throw new DooManageDbException("Error Adding Column ($name) to Table ($table)", $ex->getMessage());
		}
	}

	protected function _sqlAddColumn($table, $name, array $attributes) {
		$columnDefinition = $this->buildColumnDefinition($name, $attributes);
		$table = $this->quoteName($table);
		return "ALTER TABLE $table ADD COLUMN $columnDefinition";
	}

	/**
	 * Removes the $colum from $table if it exists
	 * @param object $table Name of the table the column is in
	 * @param object $column Name of the column to be dropped
	 * @return bool true if the column is dropped (or does not exist), false if drop fails
	 */
	public function dropColumn($table, $name) {
		$statement = $this->_sqlDropColumn($this->quoteName($table), $name);
		try {
			$this->query($statement);
		} catch (PDOException $ex) {
			throw new DooManageDbException("Error Dropping Column ($name) from Table ($table)", $ex->getMessage());
		}
	}

	protected function _sqlDropColumn($table, $name) {
		return "ALTER TABLE $table DROP COLUMN $name";
	}


	protected function buildColumnDefinition($name, $attributes) {

        $this->checkIdentifier('column', $name);

        // If we have a string use this as the type and it will use 'defaults' for said type
        if (is_string($attributes)) {
            $attributes = array('type' => $attributes);
        }

        // set default values for these variables
        $temp = array(
            'type'    => null,
            'size'    => null,
            'scope'   => null,
            'require' => null,
			'default' => null,
            'autoinc' => false,
			'primary' => false
        );

        $attributes = array_merge($temp, $attributes);

        // force values
        $name    = trim(strtolower($name));
        $type    = strtolower(trim($attributes['type']));
        $size    = (int) $attributes['size'];
        $scope   = (int) $attributes['scope'];
        $require = (bool) $attributes['require'];
		$autoinc = (bool) $attributes['autoinc'];
		$primary = (bool) $attributes['primary'];
		$default = $attributes['default'];

		$columnDefinition = '';

        // is it a recognized column type?
        if (array_key_exists($type, $this->colTypeMapping) == false) {
            throw new DooManageDbException('Unrecognised column type specified', "$type is not a recognised database type. Please check DooDbManager::colTypeMapping for supported column types");
        }

        // get the column type defenition
        switch ($type) {

        	case DooManageDb::COL_TYPE_CHAR:
        	case DooManageDb::COL_TYPE_VARCHAR:
	            if ($size < 1 || $size > 255) {
	                throw new DooManageDbException("Invalid Column Size ($size) for $name", "char and varchar require a column size between 1 and 255");
	            } else {
					$columnDefinition = $this->colTypeMapping[$type] . "($size)";
				}
	            break;

	        case DooManageDb::COL_TYPE_DECIMAL:
	            if ($size < 1 || $size > 255) {
	                throw new DooManageDbException("Invalid Column Size ($size) for $name", "decimal requires size to be between 1 and 255");
	            } elseif ($scope < 0 || $scope > $size) {
	                throw new DooManageDbException("Invalid Column Scope ($scope) for $name", "Scope must be between 0 and the Column Size ($size)");
	            } else {
	            	$columnDefinition = $this->colTypeMapping[$type] . "($size,$scope)";
				}
	            break;

	        default:
	            $columnDefinition = $this->colTypeMapping[$type];
	            break;
        }

        $columnDefinition .= ($require) ? ' NOT NULL' : ' NULL';

        // use isset() to allow for '0' and '' values.
        if (isset($default)) {
			if (is_bool($default)) {
				$default = $default === false ? 0 : 1;
			}
            $columnDefinition .= ' DEFAULT ' . $this->quote($default);
        }

        // modify with auto-increment and primary-key portions
        $this->columnDefineAutoincPrimary($columnDefinition, $autoinc, $primary);

        // done
        $name = $this->quoteName($name);
        return "$name $columnDefinition";
	}

	/**
	 * Creates an index on a table.
	 *
	 * It is recommended you check when and how you should use indexes for your database engine
	 * before you create indexes or you may hurt the performance of your application
	 * @param string $table Name of the table to create the index on
	 * @param string $name Name for the index
	 * @param bool $unique Should the index be unique (eg no two rows have the same values in index columns)
	 * @param array $colNames The names of the columns to be indexed. If null use the index name
	 * @return void
	 */
	public function createIndex($table, $name, $unique, $colNames = null) {

		if ($colNames == null || empty($colNames)) {
			$colNames = $name;
		}

		$this->checkIdentifier('table', $table);
		$this->checkIdentifier('index', $name);

		// Mod Index name
		$name = $this->modifyIndexName($table, $name);

		$table = $this->quoteName($table);
		$name = $this->quoteName($name);
		$colNames = $this->quoteName($colNames);

		if (is_array($colNames)) {
			$colNames = implode(', ', $colNames);
		}

		if ($unique) {
			return $this->query("CREATE UNIQUE INDEX $name ON $table ($colNames)");
		} else {
			return $this->query("CREATE INDEX $name ON $table ($colNames)");
		}
	}


	/**
	 * Drops a database from a table in the database
	 * @param string $table Name of the table the index belongs to
	 * @param string $name Name of the index to be dropped
	 * @return mixed
	 */
	public function dropIndex($table, $name) {
		$name = $this->modifyIndexName($table, $name);
		return $this->_dropIndex($table, $name);
	}


	/**
	 * Used to allow an index name to be modified if required by a specific db engine
	 * @param string $table Name of the table
	 * @param string $name  Name of the index
	 */
	protected function modifyIndexName($table, $name) {
		return $name;
	}

	
	/**
	 * Drops an index from a table and specifically implemented for each db engine
	 * @param string $table Name of the table the index is for
	 * @param string $name Name of the index to be removed
	 */
	abstract protected function _dropIndex($table, $name);


	/**
	 * Adds SQL DB Engine specific auto increment and primary key clauses inplace to the column definition
	 * @param string $columnDefinition Reference to the columnDefention to append to
	 * @param bool $autoinc True if this column should be a primary key
	 * @param bool $primary True if this column should be a primary key
	 * @return void
	 */
	abstract protected function columnDefineAutoincPrimary(&$columnDefinition, $autoinc, $primary);



	/**
     * Quotes a string for use in a query.
     *
     * Places quotes around the input string and escapes and single quotes within the input string, using a quoting style appropriate to the underlying driver.
     * @param string|array $string The string to be quoted. Or if an array itterate over each element and quote it
     * @param int $type Provides a data type hint for drivers that have alternate quoting styles
     * @return string Returns a quoted string that is theoretically safe to pass into an SQL statement. Returns FALSE if the driver does not support quoting in this way.
     */
	public function quote($value, $type=null) {
		if (is_array($value)) {
			for($i = 0; $i < count($value); $i++) {
				$value[$i] = $this->quote($value[$i], $type);
			}
			return $value;
		} else {
			return $this->pdo->quote($value, $type);
		}
	}

	/**
	 * Quotes an identifier name (table, column etc); ignores values of just '*'
	 * @param object $name
	 * @return
	 */
	public function quoteName($name) {

		if (is_array($name)) {
			for($i=0; $i < count($name); $i++) {
				$name[$i] = $this->quoteName($name[$i]);
			}
			return $name;
		} else {
			$name = trim($name);
			if ($name == '*') {
				return $name;
			} else {
				return $this->identiferQuotePrefix . $name . $this->identiferQuoteSuffix;
			}
		}
	}


	/**
	 * Checks if a database identifier is allowed
	 * @param string $type the type of identified table|column
	 * @param string $name the identifier to be checked
	 * @return bool true if the identifier is allowed, false otherwise
	 */
	protected function checkIdentifier($type, $name) {

		// validate identifier length
        $len = strlen($name);
        if ($len < 1 || $len > 30) {
            throw new DooManageDbException("Invalid Identifer for $type : $name", "Identifiers must be between 1 and 30 characters long");
        }

        // only a-z, 0-9, and _ are allowed in words
        // must start with a letter, not a number or underscore
        $regex = '/^[a-z][a-z0-9_]*$/';
        if (preg_match($regex, $name) == false) {
            throw new DooManageDbException("Invalid Identifer for $type : $name", "Identifer must start with a letter a-z and contain only letters, numbers and underscores");
        }

        // must not have two or more underscores in a row
        if (strpos($name, '__') !== false) {
            throw new DooManageDbException("Invalid Identifer for $type : $name", "Identifier can not contain 2 or more underscores consecutavily");
        }
	}
}


class DooManageDbException extends Exception {
	/**
	 * An exception thrown by the DooDbAdmin
	 * @param string $error The error which occured
	 * @param string $reason [optional] Reason for the error
	 * @return
	 */
	function __construct($error, $info = "Unknown") {

		parent::__construct($error . "\n" . $info);
	}
}