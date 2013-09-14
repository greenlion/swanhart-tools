<?php
/**
 * DooManageMySqlDb class file.
 *
 * @author Richard Myers <richard.myers@hotmail.co.uk>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 * @package doo.db.manage.adapters
 * @since 1.3
 */
Doo::loadCore('db/manage/DooManageDb');

class DooManageMySqlDb extends DooManageDb {

	const ENGINE_INNODB = 'InnoDB';
	const ENGINE_MYISAM = 'MyISAM';

	/**
	 * A mapping of DooManageDb generic datatypes to RDBMS native datatypes for columns
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
		DooManageDb::COL_TYPE_BOOL		=> 'BOOLEAN',
    	DooManageDb::COL_TYPE_SMALLINT	=> 'SMALLINT',
    	DooManageDb::COL_TYPE_INT		=> 'INTEGER',
    	DooManageDb::COL_TYPE_BIGINT	=> 'BIGINT',
    	DooManageDb::COL_TYPE_DECIMAL	=> 'DECIMAL',
    	DooManageDb::COL_TYPE_FLOAT		=> 'DOUBLE',
    	DooManageDb::COL_TYPE_CHAR		=> 'CHAR',
    	DooManageDb::COL_TYPE_VARCHAR	=> 'VARCHAR',
    	DooManageDb::COL_TYPE_CLOB		=> 'LONGTEXT',
    	DooManageDb::COL_TYPE_DATE		=> 'DATE',
    	DooManageDb::COL_TYPE_TIME		=> 'TIME',
    	DooManageDb::COL_TYPE_TIMESTAMP	=> 'DATETIME',
	);

	protected $identiferQuotePrefix = '`';

	protected $identiferQuoteSuffix = '`';

	/**
	 * Builds the Create Table command to run against MySQL.
	 * We append some extra options in MySQL to inform it to use InnoDB and set the character set (if set in DBConfig or default to utf8)
	 * @param string $table
	 * @param array $cols
	 * @return string A CREATE TABLE string to run against MySQL Server
	 */
	protected function _sqlCreateTable($table, $cols, $options=null) {
		$statement = parent::_sqlCreateTable($table, $cols, $options);

		if (isset($options['engine'])) {
			$statement .= ' ENGINE=' . $options['engine'];
		} else {
			$statement .= ' ENGINE=InnoDB';
		}

		// Use UTF8 encoding
		if (isset($this->dbconfig['charset']) && isset($this->dbconfig['collate'])) {
			$statement .= ' DEFAULT CHARSET=' . $this->dbconfig['charset'] . ' COLLATE=' . $this->dbconfig['collate'];
		} else {
			$statement .= ' DEFAULT CHARSET=utf8 COLLATE=utf8_bin';
		}

		return $statement;
	}

	/**
	 * Drops an index from a table and specifically implemented for each db engine
	 * @param string $table Name of the table the index is for
	 * @param string $name Name of the index to be removed
	 */
	protected function _dropIndex($table, $name) {
		return $this->query("ALTER TABLE $table DROP INDEX $name");
	}

	/**
	 * Adds SQL DB Engine specific auto increment and primary key clauses inplace to the column definition
	 * @param string $columnDefinition Reference to the columnDefention to append to
	 * @param bool $autoinc True if this column should be a primary key
	 * @param bool $primary True if this column should be a primary key
	 * @return void
	 */
	protected function columnDefineAutoincPrimary(&$columnDefinition, $autoinc, $primary) {
		if ($autoinc === true) {
            $columnDefinition .= " AUTO_INCREMENT";
        }

        if ($primary === true) {
            $columnDefinition .= " PRIMARY KEY";
        }
	}
}