<?php
/**
 * DooManageSqliteDb class file.
 *
 * @author Richard Myers <richard.myers@hotmail.co.uk>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 * @package doo.db.manage.adapters
 * @since 1.3
 */
Doo::loadCore('db/manage/DooManageDb');

class DooManageSqliteDb extends DooManageDb {


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
    	DooManageDb::COL_TYPE_DECIMAL	=> 'NUMERIC',
    	DooManageDb::COL_TYPE_FLOAT		=> 'DOUBLE',
    	DooManageDb::COL_TYPE_CHAR		=> 'CHAR',
    	DooManageDb::COL_TYPE_VARCHAR	=> 'VARCHAR',
    	DooManageDb::COL_TYPE_CLOB		=> 'CLOB',
    	DooManageDb::COL_TYPE_DATE		=> 'DATE',
    	DooManageDb::COL_TYPE_TIME		=> 'TIME',
    	DooManageDb::COL_TYPE_TIMESTAMP	=> 'TIMESTAMP',
	);

	protected $identiferQuotePrefix = '"';

	protected $identiferQuoteSuffix = '"';

	/**
	 * SQLite does not quote numeric values so we need to overide the quoting mechanism
	 */
	public function quote($value, $type=null) {
		if (is_numeric($value)) {
			return $value;
		} else {
			return parent::quote($value, $type);
		}
	}

	/**
	 * Drops an index from a table and specifically implemented for each db engine
	 * @param string $table Name of the table the index is for
	 * @param string $name Name of the index to be removed
	 */
	protected function _dropIndex($table, $name) {
		// SQLite indexes relate to the whole db so we ensure the $name refrences the table
		$name = $this->quote($name);
		return $this->query("DROP INDEX $name");
	}


	/**
	 * Used to allow an index name to be modified if required by a specific db engine
	 * @param string $table Name of the table
	 * @param string $name  Name of the index
	 */
	protected function modifyIndexName($table, $name) {
		// SQLite indexes relate to the whole db so we ensure the $name reference the table name
		// so we can not go and define multiple indexes with the same name
		return "$table__$name";
	}


	/**
	 * Adds SQL DB Engine specific auto increment and primary key clauses inplace to the column definition
	 * @param string $columnDefinition Reference to the columnDefention to append to
	 * @param bool $autoinc True if this column should be a primary key
	 * @param bool $primary True if this column should be a primary key
	 * @return void
	 */
	protected function columnDefineAutoincPrimary(&$columnDefinition, $autoinc, $primary) {
		if ($autoinc) {
            $columnDefinition = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        } elseif ($primary) {
            $columnDefinition .= ' PRIMARY KEY';
        }
	}
}