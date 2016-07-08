<?php

/**
 * Interface for Danga's Gearman job scheduling system
 *
 * PHP version 5.1.0+
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php. If you did not receive  
 * a copy of the New BSD License and are unable to obtain it through the web, 
 * please send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Net
 * @package   Net_Gearman
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/Net_Gearman
 * @link      http://www.danga.com/gearman/
 */

/**
 * Task class for creating Net_Gearman tasks
 *
 * @category  Net
 * @package   Net_Gearman
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://www.danga.com/gearman/
 * @see       Net_Gearman_Set, Net_Gearman_Client
 */
class Net_Gearman_Task
{
    /**
     * The function/job to run
     *
     * @var string $func
     */
    public $func = '';

    /**
     * Arguments to pass to function/job
     *
     * @var array $arg
     */
    public $arg = array();

    /**
     * Type of job
     *
     * Which type of job you wish this task to be ran as. Keep in mind that
     * background jobs are "fire and forget" and DO NOT return results to the
     * job server in a manner that you can actually retrieve. 
     *
     * @var integer $type
     * @see Net_Gearman_Task::JOB_NORMAL
     * @see Net_Gearman_Task::JOB_BACKGROUND
     * @see Net_Gearman_Task::JOB_HIGH
     */
    public $type = self::JOB_NORMAL;

    /**
     * Handle returned from job server
     *
     * @var string $handle
     * @see Net_Gearman_Client
     */
    public $handle = '';

    /**
     * The unique identifier for this job
     *
     * Keep in mind that a unique job is only unique to the job server it is 
     * submitted to. Gearman servers don't communicate with each other to 
     * ensure a job is unique across all workers.
     *
     * That being said, Gearman does group identical jobs sent to it and runs
     * that job only once. If you send the job Sum with args 1, 2, 3 to the
     * server 10 times in a second Gearman will only run that job once and then
     * return the result 10 times.
     *
     * @var string $uniq
     */
    public $uniq = '';

    /**
     * Is this task finished?
     * 
     * @var boolean $finished
     * @see Net_Gearman_Set::finished()
     * @see Net_Gearman_Task::complete()
     * @see Net_Gearman_Task::fail()
     */
    public $finished = false;

    /**
     * The result returned from the worker
     *
     * @var object $result
     */
    public $result = '';

    /**
     * Callbacks registered for each state
     *
     * @var array $callback
     * @see Net_Gearman_Task::attachCallback()
     * @see Net_Gearman_Task::complete()
     * @see Net_Gearman_Task::status()
     * @see Net_Gearman_Task::fail()
     */
    protected $callback = array(
        self::TASK_COMPLETE => array(),
        self::TASK_FAIL     => array(),
        self::TASK_STATUS   => array()
    );

    /**
     * Normal job
     *
     * Normal jobs are ran against a worker with the result being returned
     * all in the same thread (e.g. Your page will sit there waiting for the
     * job to finish and return it's result).
     *
     * @var integer JOB_NORMAL
     */
    const JOB_NORMAL = 1;

    /**
     * Background job
     *
     * Background jobs in Gearman are "fire and forget". You can check a job's
     * status periodically, but you can't get a result back from it.
     *
     * @var integer JOB_BACKGROUND
     */
    const JOB_BACKGROUND = 2;

    /**
     * High priority job
     *
     * @var integer JOB_HIGH
     */
    const JOB_HIGH = 2;

    /**
     * Callback of type complete
     *
     * The callback provided should be ran when the task has been completed. It
     * will be handed the result of the task as its only argument.
     *
     * @var integer TASK_COMPLETE
     * @see Net_Gearman_Task::complete()
     */
    const TASK_COMPLETE = 1;

    /**
     * Callback of type fail
     *
     * The callback provided should be ran when the task has been reported to
     * have failed by Gearman. No arguments are provided.
     *
     * @var integer TASK_FAIL
     * @see Net_Gearman_Task::fail()
     */
    const TASK_FAIL = 2;

    /**
     * Callback of type status
     *
     * The callback provided should be ran whenever the status of the task has
     * been updated. The numerator and denominator are passed as the only
     * two arguments.
     *
     * @var integer TASK_STATUS
     * @see Net_Gearman_Task::status()
     */
    const TASK_STATUS = 3;

    /**
     * Constructor
     *
     * @param string  $func Name of job to run
     * @param array   $arg  List of arguments for job
     * @param string  $uniq The unique id of the job 
     * @param integer $type Type of job to run task as
     *
     * @return      void
     */
    public function __construct($func, $arg, $uniq = null,
                                $type = self::JOB_NORMAL) 
    {
        $this->func = $func;
        $this->arg  = $arg;

        if (is_null($uniq)) {
            $this->uniq = md5($func . serialize($arg) . $type);
        } else {
            $this->uniq = $uniq;
        }

        $this->type = $type; 
    }

    /**
     * Attach a callback to this task   
     * 
     * @param callback $callback A valid PHP callback
     * @param integer  $type     Type of callback 
     * 
     * @return void
     * @throws Net_Gearman_Exception
     */
    public function attachCallback($callback, $type = self::TASK_COMPLETE) 
    {
        if (!is_callable($callback)) {
            throw new Net_Gearman_Exception('Invalid callback specified'); 
        } 

        $this->callback[$type][] = $callback;
    }

    /**
     * Run the complete callbacks
     *
     * Complete callbacks are passed the name of the job, the handle of the 
     * job and the result of the job (in that order). 
     *
     * @param object $result JSON decoded result passed back
     * 
     * @return void
     * @see Net_Gearman_Task::attachCallback()
     */
    public function complete($result)
    {
        $this->finished = true;
        $this->result   = $result;
        
        if (!count($this->callback[self::TASK_COMPLETE])) {
            return;
        }

        foreach ($this->callback[self::TASK_COMPLETE] as $callback) {
            call_user_func($callback, $this->func, $this->handle, $result);
        }
    }

    /**
     * Run the failure callbacks
     *
     * Failure callbacks are passed the name of the job and the handle of the
     * job that failed (in that order).
     *
     * @return void
     * @see Net_Gearman_Task::attachCallback()
     */
    public function fail()
    {
        $this->finished = true;
        if (!count($this->callback[self::TASK_FAIL])) {
            return;
        }

        foreach ($this->callback[self::TASK_FAIL] as $callback) {
            call_user_func($callback, $this);
        }
    }

    /**
     * Run the status callbacks
     *
     * Status callbacks are passed the name of the job, handle of the job and 
     * the numerator/denominator as the arguments (in that order).
     *
     * @param integer $numerator   The numerator from the status
     * @param integer $denominator The denominator from the status
     * 
     * @return void
     * @see Net_Gearman_Task::attachCallback()
     */
    public function status($numerator, $denominator)
    {
        if (!count($this->callback[self::TASK_STATUS])) {
            return;
        }

        foreach ($this->callback[self::TASK_STATUS] as $callback) {
            call_user_func($callback, 
                           $this->func, 
                           $this->handle, 
                           $numerator, 
                           $denominator);
        }
    }
}

?>
