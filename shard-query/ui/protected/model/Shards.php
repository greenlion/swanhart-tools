<?php
Doo::loadCore('db/DooModel');

class Shards extends DooModel{

    /**
     * @var int Max length is 11.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $schema_id;

    /**
     * @var varchar Max length is 50.
     */
    public $shard_name;

    /**
     * @var text
     */
    public $extra_info;

    /**
     * @var enum 'mysql','pdo-pgsql','pdo-mysql').
     */
    public $shard_rdbms;

    /**
     * @var tinyint Max length is 1.
     */
    public $coord_shard;

    /**
     * @var tinyint Max length is 1.
     */
    public $accepts_new_rows;

    /**
     * @var varchar Max length is 64.
     */
    public $username;

    /**
     * @var varchar Max length is 255.
     */
    public $password;

    /**
     * @var varchar Max length is 255.
     */
    public $host;

    /**
     * @var smallint Max length is 6.
     */
    public $port;

    /**
     * @var varchar Max length is 45.
     */
    public $db;

    /**
     * @var tinyint Max length is 1.
     */
    public $enabled;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'shards';
    public $_primarykey = 'id';
    public $_fields = array('id','schema_id','shard_name','extra_info','shard_rdbms','coord_shard','accepts_new_rows','username','password','host','port','db','enabled','last_updated');

    public function getVRules() {
        return array(
                'id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'optional' ),
                ),

                'schema_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'shard_name' => array(
                        array( 'maxlength', 50 ),
                        array( 'notnull' ),
                ),

                'extra_info' => array(
                        array( 'optional' ),
                ),

                'shard_rdbms' => array(
                        array( 'notnull' ),
                ),

                'coord_shard' => array(
                        array( 'integer' ),
                        array( 'maxlength', 1 ),
                        array( 'notnull' ),
                ),

                'accepts_new_rows' => array(
                        array( 'integer' ),
                        array( 'maxlength', 1 ),
                        array( 'notnull' ),
                ),

                'username' => array(
                        array( 'maxlength', 64 ),
                        array( 'optional' ),
                ),

                'password' => array(
                        array( 'maxlength', 255 ),
                        array( 'optional' ),
                ),

                'host' => array(
                        array( 'maxlength', 255 ),
                        array( 'optional' ),
                ),

                'port' => array(
                        array( 'integer' ),
                        array( 'maxlength', 6 ),
                        array( 'optional' ),
                ),

                'db' => array(
                        array( 'maxlength', 45 ),
                        array( 'optional' ),
                ),

                'enabled' => array(
                        array( 'integer' ),
                        array( 'maxlength', 1 ),
                        array( 'notnull' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                )
            );
    }

}