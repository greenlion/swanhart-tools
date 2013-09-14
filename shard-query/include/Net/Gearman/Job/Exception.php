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

require_once 'PEAR/Exception.php';

/**
 * Exception class for Gearman jobs
 *
 * Your Gearman jobs should throw this from their run() method if they run 
 * into any kind of error. 
 *
 * @category  Net
 * @package   Net_Gearman
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://www.danga.com/gearman/
 * @see       Net_Gearman_Job_Common, Net_Gearman_Worker
 */
class Net_Gearman_Job_Exception extends PEAR_Exception
{

}

?>
