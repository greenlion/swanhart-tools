<?php
Doo::loadCore('db/DooModel');

class JobWorkerStatus extends DooModel{

    /**
     * @var bigint Max length is 20.
     */
    public $id;

    /**
     * @var bigint Max length is 20.
     */
    public $job_id;

    /**
     * @var enum 'store_resultset','function','loader').
     */
    public $worker_type;

    /**
     * @var enum 'ok','error').
     */
    public $completion_type;

    /**
     * @var text
     */
    public $completion_message;

    public $_table = 'job_worker_status';
    public $_primarykey = 'id';
    public $_fields = array('id','job_id','worker_type','completion_type','completion_message');

    public function getVRules() {
        return array(
                'id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'optional' ),
                ),

                'job_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'notnull' ),
                ),

                'worker_type' => array(
                        array( 'notnull' ),
                ),

                'completion_type' => array(
                        array( 'optional' ),
                ),

                'completion_message' => array(
                        array( 'optional' ),
                )
            );
    }

}