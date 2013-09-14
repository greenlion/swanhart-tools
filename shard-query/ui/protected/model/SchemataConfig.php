<?php
Doo::loadCore('db/DooModel');

class SchemataConfig extends DooModel{

    /**
     * @var int Max length is 11.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $schema_id;

    /**
     * @var varchar Max length is 20.
     */
    public $var_name;

    /**
     * @var text
     */
    public $var_value;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'schemata_config';
    public $_primarykey = 'id';
    public $_fields = array('id','schema_id','var_name','var_value','last_updated');

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

                'var_name' => array(
                        array( 'maxlength', 20 ),
                        array( 'notnull' ),
                ),

                'var_value' => array(
                        array( 'notnull' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                )
            );
    }

}