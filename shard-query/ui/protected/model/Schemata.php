<?php
Doo::loadCore('db/DooModel');

class Schemata extends DooModel{

    /**
     * @var int Max length is 11.
     */
    public $id;

    /**
     * @var varchar Max length is 50.
     */
    public $schema_name;

    /**
     * @var tinyint Max length is 1.
     */
    public $is_default_schema;

    /**
     * @var timestamp
     */
    public $last_updated;

    /**
     * @var tinyint Max length is 1.
     */
    public $enabled;

    public $_table = 'schemata';
    public $_primarykey = 'id';
    public $_fields = array('id','schema_name','is_default_schema','last_updated','enabled');

    public function getVRules() {
        return array(
                'id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'optional' ),
                ),

                'schema_name' => array(
                        array( 'maxlength', 50 ),
                        array( 'notnull' ),
                ),

                'is_default_schema' => array(
                        array( 'integer' ),
                        array( 'maxlength', 1 ),
                        array( 'notnull' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                ),

                'enabled' => array(
                        array( 'integer' ),
                        array( 'maxlength', 1 ),
                        array( 'notnull' ),
                )
            );
    }

}