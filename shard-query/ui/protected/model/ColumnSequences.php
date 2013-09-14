<?php
Doo::loadCore('db/DooModel');

class ColumnSequences extends DooModel{

    /**
     * @var int Max length is 11.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $schema_id;

    /**
     * @var enum 'shard_column','sequence').
     */
    public $sequence_type;

    /**
     * @var varchar Max length is 50.
     */
    public $sequence_name;

    /**
     * @var bigint Max length is 20.
     */
    public $next_value;

    /**
     * @var enum 'string','integer').
     */
    public $datatype;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'column_sequences';
    public $_primarykey = 'id';
    public $_fields = array('id','schema_id','sequence_type','sequence_name','next_value','datatype','last_updated');

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

                'sequence_type' => array(
                        array( 'notnull' ),
                ),

                'sequence_name' => array(
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
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                )
            );
    }

}