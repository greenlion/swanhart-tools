<?php
Doo::loadCore('db/DooModel');

class GearmanJobServers extends DooModel{

    /**
     * @var int Max length is 11.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $schema_id;

    /**
     * @var varchar Max length is 50.
     */
    public $hostname;

    /**
     * @var smallint Max length is 5.  unsigned.
     */
    public $port;

    /**
     * @var tinyint Max length is 1.
     */
    public $local;

    /**
     * @var tinyint Max length is 1.
     */
    public $enabled;

    /**
     * @var int Max length is 11.
     */
    public $pid;

    /**
     * @var timestamp
     */
    public $last_updated;

    public $_table = 'gearman_job_servers';
    public $_primarykey = 'id';
    public $_fields = array('id','schema_id','hostname','port','local','enabled','pid','last_updated');

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

                'hostname' => array(
                        array( 'maxlength', 50 ),
                        array( 'notnull' ),
                ),

                'port' => array(
                        array( 'integer' ),
                        array( 'min', 0 ),
                        array( 'maxlength', 5 ),
                        array( 'notnull' ),
                ),

                'local' => array(
                        array( 'integer' ),
                        array( 'maxlength', 1 ),
                        array( 'notnull' ),
                ),

                'enabled' => array(
                        array( 'integer' ),
                        array( 'maxlength', 1 ),
                        array( 'notnull' ),
                ),

                'pid' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'optional' ),
                ),

                'last_updated' => array(
                        array( 'datetime' ),
                        array( 'optional' ),
                )
            );
    }

}