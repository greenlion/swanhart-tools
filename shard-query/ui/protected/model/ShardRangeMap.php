<?php
Doo::loadCore('db/DooModel');

class ShardRangeMap extends DooModel{

    /**
     * @var bigint Max length is 20.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $column_id;

    /**
     * @var int Max length is 11.
     */
    public $shard_id;

    /**
     * @var bigint Max length is 20.
     */
    public $key_value_min;

    /**
     * @var bigint Max length is 20.
     */
    public $key_value_max;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'shard_range_map';
    public $_primarykey = 'id';
    public $_fields = array('id','column_id','shard_id','key_value_min','key_value_max','last_updated');

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

                'shard_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'key_value_min' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'optional' ),
                ),

                'key_value_max' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'optional' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                )
            );
    }

}