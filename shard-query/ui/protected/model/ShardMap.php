<?php
Doo::loadCore('db/DooModel');

class ShardMap extends DooModel{

    /**
     * @var bigint Max length is 20.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $column_id;

    /**
     * @var bigint Max length is 20.
     */
    public $key_value;

    /**
     * @var varchar Max length is 255.
     */
    public $key_string_value;

    /**
     * @var int Max length is 11.
     */
    public $shard_id;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'shard_map';
    public $_primarykey = 'id';
    public $_fields = array('id','column_id','key_value','key_string_value','shard_id','last_updated');

    public function getVRules() {
        return array(
                'id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'optional' ),
                ),

                'column_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'key_value' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'optional' ),
                ),

                'key_string_value' => array(
                        array( 'maxlength', 255 ),
                        array( 'optional' ),
                ),

                'shard_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                )
            );
    }

}