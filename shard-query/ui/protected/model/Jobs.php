<?php
Doo::loadCore('db/DooModel');

class Jobs extends DooModel{

    /**
     * @var bigint Max length is 20.
     */
    public $id;

    /**
     * @var int Max length is 11.
     */
    public $shard_id;

    /**
     * @var enum 'query','load').
     */
    public $job_type;

    /**
     * @var int Max length is 11.
     */
    public $resultset_jobs;

    /**
     * @var int Max length is 11.
     */
    public $function_jobs;

    /**
     * @var int Max length is 11.
     */
    public $loader_jobs;

    /**
     * @var text
     */
    public $tables_used;

    /**
     * @var enum 'starting','running','completed','error').
     */
    public $job_status;

    /**
     * @var text
     */
    public $job_result_sql;

    /**
     * @var decimal Max length is 5. ,2).
     */
    public $completion_percent;

    public $_table = 'jobs';
    public $_primarykey = 'id';
    public $_fields = array('id','shard_id','job_type','resultset_jobs','function_jobs','loader_jobs','tables_used','job_status','job_result_sql','completion_percent');

    public function getVRules() {
        return array(
                'id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 20 ),
                        array( 'optional' ),
                ),

                'shard_id' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'optional' ),
                ),

                'job_type' => array(
                        array( 'optional' ),
                ),

                'resultset_jobs' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'function_jobs' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'loader_jobs' => array(
                        array( 'integer' ),
                        array( 'maxlength', 11 ),
                        array( 'notnull' ),
                ),

                'tables_used' => array(
                        array( 'notnull' ),
                ),

                'job_status' => array(
                        array( 'notnull' ),
                ),

                'job_result_sql' => array(
                        array( 'notnull' ),
                ),

                'completion_percent' => array(
                        array( 'float' ),
                        array( 'optional' ),
                )
            );
    }

}