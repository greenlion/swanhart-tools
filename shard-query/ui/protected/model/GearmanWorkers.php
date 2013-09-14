<?php
Doo::loadCore('db/DooModel');

class GearmanWorkers extends DooModel{

    /**
     * @var bigint Max length is 20.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $pid;

    /**
     * @var varchar Max length is 255.
     */
    public $function_name;

    /**
     * @var int Max length is 11.
     */
    public $schema_id;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'gearman_workers';
    public $_primarykey = 'id';
    public $_fields = array('id','pid','function_name','schema_id','last_updated');

    public function getVRules() {
        return array(
                'id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'optional' ),
                ),

                'pid' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'function_name' => array(
                        array( 'maxlength', 255 ),
                        array( 'notnull' ),
                ),

                'schema_id' => array(
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