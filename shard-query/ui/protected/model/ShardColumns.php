<?php
Doo::loadCore('db/DooModel');

class ShardColumns extends DooModel{

    /**
     * @var int Max length is 11.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $column_id;

    /**
     * @var int Max length is 11.
     */
    public $schema_id;

    /**
     * @var varchar Max length is 50.
     */
    public $column_name;

    /**
     * @var bigint Max length is 20.
     */
    public $next_value;

    /**
     * @var enum 'string','integer').
     */
    public $datatype;

    public $_table = 'shard_columns';
    public $_primarykey = '';
    public $_fields = array('id','column_id','schema_id','column_name','next_value','datatype');

    public function getVRules() {
        return array(
                'id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'column_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'schema_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'column_name' => array(
                        array( 'maxlength', 50 ),
                        array( 'notnull' ),
                ),

                'next_value' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'optional' ),
                ),

                'datatype' => array(
                        array( 'notnull' ),
                )
            );
    }

}