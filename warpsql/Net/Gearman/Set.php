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

require_once 'Net/Gearman/Task.php';

/**
 * A class for creating sets of tasks
 *
 * <code>
 * <?php
 * require_once 'Net/Gearman/Client.php';
 *
 * // This is the callback function for our tasks
 * function echoResult($result) {
 *     echo 'The result was: ' . $result . "\n";
 * } 
 *
 * // Job name is the key, arguments to job are in the value array
 * $jobs = array(
 *     'AddTwoNumbers' => array('1', '2'),
 *     'Multiply' => array('3', '4')
 * );
 * 
 * $set = new Net_Gearman_Set();
 * foreach ($jobs as $job => $args) {
 *     $task = new Net_Gearman_Task($job, $args);
 *     $task->attachCallback('echoResult');
 *     $set->addTask($task);
 * }
 *
 * $client = new Net_Gearman_Client(array(
 *     '127.0.0.1:7003', '127.0.0.1:7004'
 * ));
 * 
 * $client->runSet($set);
 *
 * ?>
 * </code>
 *
 * @category  Net
 * @package   Net_Gearman
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://www.danga.com/gearman/
 * @see       Net_Gearman_Job_Common, Net_Gearman_Worker
 */
class Net_Gearman_Set implements IteratorAggregate, Countable
{
    /**
     * Tasks count
     *
     * @var integer $tasksCount
     */
    public $tasksCount = 0;

    /**
     * Tasks to run
     *
     * @var array $tasks
     */
    public $tasks = array();

    /**
     * Handle to task mapping
     *
     * @var array $handles
     */
    public $handles = array();

    /**
     * Callback registered for set
     *
     * @var mixed $callback
     */
    protected $callback = null;

    /**
     * Constructor
     *
     * @param array $tasks Array of tasks to run
     *
     * @return void
     * @see Net_Gearman_Task
     */
    public function __construct(array $tasks = array())
    {
        foreach ($tasks as $task) {
            $this->addTask($task);
        }
    }

    /**
     * Add a task to the set
     *
     * @param object $task Task to add to the set
     *
     * @return void
     * @see Net_Gearman_Task, Net_Gearman_Set::$tasks
     */
    public function addTask(Net_Gearman_Task $task)
    {
        if (!isset($this->tasks[$task->uniq])) {
            $this->tasks[$task->uniq] = $task;
            $this->tasksCount++;
        }
    }

    /**
     * Get a task
     *
     * @param string $handle Handle of task to get
     * 
     * @return object Instance of task
     * @throws Net_Gearman_Exception 
     */
    public function getTask($handle)
    {
        if (!isset($this->handles[$handle])) {
            throw new Net_Gearman_Exception('Unknown handle');
        }

        if (!isset($this->tasks[$this->handles[$handle]])) {
            throw new Net_Gearman_Exception('No task by that handle');
        }

        return $this->tasks[$this->handles[$handle]];
    }

    /**
     * Is this set finished running?
     *
     * This function will return true if all of the tasks in the set have 
     * finished running. If they have we also run the set callbacks if there
     * is one.
     *
     * @return boolean
     */
    public function finished()
    {
        if ($this->tasksCount == 0) {
            if (isset($this->callback)) {
                foreach ($this->tasks as $task) {
                    $results[] = $task->result;
                }              

                call_user_func($this->callback, $results);
            }

            return true;
        }

        return false;
    }

    /**
     * Attach a callback to this set   
     * 
     * @param callback $callback A valid PHP callback
     * 
     * @return void
     * @throws Net_Gearman_Exception
     */
    public function attachCallback($callback) 
    {
        if (!is_callable($callback)) {
            throw new Net_Gearman_Exception('Invalid callback specified'); 
        } 

        $this->callback = $callback;
    }

    /**
     * Get the iterator
     *
     * @return ArrayIterator Tasks
     */
    public function getIterator()
    {
        return new ArrayIterator($this->tasks);
    }

    /**
     * Get the task count
     *
     * @return int Number of tasks in the set
     * @see    {@link Countable::count()}
     */
    public function count()
    {
        return $this->tasksCount;
    }
}

?>
