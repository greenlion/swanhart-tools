<?php
Doo::loadCore('db/DooModel');

class SchemataConfigItems extends DooModel{

    /**
     * @var varchar Max length is 255.
     */
    public $name;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'schemata_config_items';
    public $_primarykey = 'name';
    public $_fields = array('name','last_updated');

    public function getVRules() {
        return array(
                'name' => array(
                        array( 'maxlength', 255 ),
                        array( 'notnull' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                )
            );
    }

}