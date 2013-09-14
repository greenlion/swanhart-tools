<?php
Doo::loadCore('db/DooModel');

class GearmanFunctionNames extends DooModel{

    /**
     * @var int Max length is 11.
     */
    public $id;

    /**
     * @var varchar Max length is 50.
     */
    public $function_name;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'gearman_function_names';
    public $_primarykey = 'id';
    public $_fields = array('id','function_name','last_updated');

    public function getVRules() {
        return array(
                'id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'optional' ),
                ),

                'function_name' => array(
                        array( 'maxlength', 50 ),
                        array( 'notnull' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                )
            );
    }

}