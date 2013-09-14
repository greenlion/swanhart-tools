<?php
Doo::loadCore('db/DooModel');

class GearmanFunctions extends DooModel{

    /**
     * @var int Max length is 11.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $schema_id;

    /**
     * @var int Max length is 10.  unsigned.
     */
    public $worker_count;

    /**
     * @var tinyint Max length is 1.
     */
    public $enabled;

    /**
     * @var timestamp
     */
    public $last_updated;

    /**
     * @var int Max length is 11.
     */
    public $function_name_id;

    public $_table = 'gearman_functions';
    public $_primarykey = 'id';
    public $_fields = array('id','schema_id','worker_count','enabled','last_updated','function_name_id');

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

                'worker_count' => array(
                        array( 'integer' ),
                        array( 'min', 0 ),
                        array( 'maxlength', 10 ),
                        array( 'notnull' ),
                ),

                'enabled' => array(
                        array( 'integer' ),
                        array( 'maxlength', 1 ),
                        array( 'notnull' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                ),

                'function_name_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                )
            );
    }

}