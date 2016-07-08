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

require_once 'Net/Gearman/Job/Common.php';
require_once 'Net/Gearman/Exception.php';

// Define this if you want your Jobs to be stored in a different
// path than the default.
if (!defined('NET_GEARMAN_JOB_PATH')) {
    define('NET_GEARMAN_JOB_PATH', 'Net/Gearman/Job');
}

// Define this if you want your Jobs to have a prefix requirement
if (!defined('NET_GEARMAN_JOB_CLASS_PREFIX')) {
    define('NET_GEARMAN_JOB_CLASS_PREFIX', 'Net_Gearman_Job_');
}

/**
 * Job creation class
 *
 * @category  Net
 * @package   Net_Gearman
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://www.danga.com/gearman/
 * @see       Net_Gearman_Job_Common, Net_Gearman_Worker
 */
abstract class Net_Gearman_Job
{
    /**
     * Create an instance of a job
     *
     * The Net_Geraman_Worker class creates connections to multiple job servers
     * and then fires off jobs using this function. It hands off the connection
     * which made the request for the job so that the job can communicate its
     * status from there on out.
     *
     * @param string $job    Name of job (func in Gearman terms)
     * @param object $conn   Instance of Net_Gearman_Connection
     * @param string $handle Gearman job handle of job
     * 
     * @return object Instance of Net_Gearman_Job_Common child
     * @see Net_Gearman_Job_Common
     * @throws Net_Gearman_Exception
     */
    static public function factory($job, $conn, $handle)
    {
        $file = NET_GEARMAN_JOB_PATH . '/' . $job . '.php';
        include_once $file;
        $class = NET_GEARMAN_JOB_CLASS_PREFIX . $job;
        if (!class_exists($class)) {
            throw new Net_Gearman_Job_Exception('Invalid Job class');
        }
    
        $instance = new $class($conn, $handle);
        if (!$instance instanceof Net_Gearman_Job_Common) {
            throw new Net_Gearman_Job_Exception('Job is of invalid type');
        }

        return $instance;
    }
}

?>
