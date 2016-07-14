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

require_once 'Net/Gearman/Connection.php';

/**
 * A client for managing Gearmand servers
 *
 * This class implements the administrative text protocol used by Gearman to do
 * a number of administrative tasks such as collecting stats on workers, the
 * queue, shutting down the server, version, etc.
 *
 * @category  Net
 * @package   Net_Gearman
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://www.danga.com/gearman/
 */
class Net_Gearman_Manager
{
    /**
     * Connection resource
     *
     * @var resource $conn Connection to Gearman server
     * @see Net_Gearman_Manager::sendCommand()
     * @see Net_Gearman_Manager::recvCommand()
     */
    protected $conn = null;

    /**
     * The server is shutdown
     *
     * We obviously can't send more commands to a server after it's been shut
     * down. This is set to true in Net_Gearman_Manager::shutdown() and then
     * checked in Net_Gearman_Manager::sendCommand().
     *
     * @var boolean $shutdown 
     */ 
    protected $shutdown = false;

    /**
     * Constructor
     *
     * @param string  $server  Host and port (e.g. 'localhost:7003')
     * @param integer $timeout Connection timeout
     *
     * @throws Net_Gearman_Exception
     * @see Net_Gearman_Manager::$conn
     */
    public function __construct($server, $timeout = 5)
    {
        if (strpos($server, ':')) {
            list($host, $port) = explode(':', $server);
        } else {
            $host = $server;
            $port = 7003;
        }

        $errCode    = 0;
        $errMsg     = '';
        $this->conn = @fsockopen($host, $port, $errCode, $errMsg, $timeout);
        if ($this->conn === false) {
            throw new Net_Gearman_Exception(
                'Could not connect to ' . $host . ':' . $port
            );
        } 
    }

    /**
     * Get the version of Gearman running
     *
     * @return string
     * @see Net_Gearman_Manager::sendCommand()
     * @see Net_Gearman_Manager::checkForError()
     */
    public function version()
    {
        $this->sendCommand('version');
        $res = fgets($this->conn, 4096);
        $this->checkForError($res);
        return trim($res);
    }

    /**
     * Shut down Gearman
     *
     * @param boolean $graceful Whether it should be a graceful shutdown
     *
     * @return boolean
     * @see Net_Gearman_Manager::sendCommand()
     * @see Net_Gearman_Manager::checkForError()
     * @see Net_Gearman_Manager::$shutdown
     */
    public function shutdown($graceful = false)
    {
        $cmd = ($graceful) ? 'shutdown graceful' : 'shutdown';
        $this->sendCommand($cmd);
        $res = fgets($this->conn, 4096);
        $this->checkForError($res);
        
        $this->shutdown = (trim($res) == 'OK');
        return $this->shutdown;
    }

    /**
     * Get worker status and info
     *
     * Returns the file descriptor, IP address, client ID and the abilities
     * that the worker has announced.
     *
     * @return array A list of workers connected to the server
     * @throws Net_Gearman_Exception
     */
    public function workers()
    {
        $this->sendCommand('workers');
        $res     = $this->recvCommand();
        $workers = array();
        $tmp     = explode("\n", $res);
        foreach ($tmp as $t) {
            if (!Net_Gearman_Connection::stringLength($t)) {
                continue;
            }

            list($info, $abilities) = explode(" : ", $t);
            list($fd, $ip, $id)     = explode(' ', $info);

            $abilities = trim($abilities);

            $workers[] = array(
                'fd' => $fd,
                'ip' => $ip,
                'id' => $id,
                'abilities' => empty($abilities) ? array() : explode(' ', $abilities)
            );
        }

        return $workers;
    }

    /**
     * Set maximum queue size for a function
     *
     * For a given function of job, the maximum queue size is adjusted to be 
     * max_queue_size jobs long. A negative value indicates unlimited queue 
     * size.
     * 
     * If the max_queue_size value is not supplied then it is unset (and the 
     * default maximum queue size will apply to this function).
     *
     * @param string  $function Name of function to set queue size for
     * @param integer $size     New size of queue
     *
     * @return boolean
     * @throws Net_Gearman_Exception
     */
    public function setMaxQueueSize($function, $size)
    {
        if (!is_numeric($size)) {
            throw new Net_Gearman_Exception('Queue size must be numeric');
        }

        if (preg_match('/[^a-z0-9_]/i', $function)) {
            throw new Net_Gearman_Exception('Invalid function name');
        }

        $this->sendCommand('maxqueue ' . $function . ' ' . $size);
        $res = fgets($this->conn, 4096);
        $this->checkForError($res); 
        return (trim($res) == 'OK');
    }

    /**
     * Get queue/worker status by function
     *
     * This function queries for queue status. The array returned is keyed by
     * the function (job) name and has how many jobs are in the queue, how 
     * many jobs are running and how many workers are capable of performing 
     * that job.
     *
     * @return array An array keyed by function name 
     * @throws Net_Gearman_Exception
     */
    public function status()
    {
        $this->sendCommand('status');
        $res = $this->recvCommand();

        $status = array();
        $tmp    = explode("\n", $res);
        foreach ($tmp as $t) {
            if (!Net_Gearman_Connection::stringLength($t)) {
                continue;
            }

            list($func, $inQueue, $jobsRunning, $capable) = explode("\t", $t);

            $status[$func] = array(
                'in_queue' => $inQueue,
                'jobs_running' => $jobsRunning,
                'capable_workers' => $capable
            );
        }

        return $status;
    }

    /**
     * Send a command
     *
     * @param string $cmd The command to send
     *
     * @return void
     * @throws Net_Gearman_Exception
     */
    protected function sendCommand($cmd)
    {
        if ($this->shutdown) {
            throw new Net_Gearman_Exception('This server has been shut down');
        }

        fwrite($this->conn, 
               $cmd . "\r\n", 
               Net_Gearman_Connection::stringLength($cmd . "\r\n"));
    }

    /**
     * Receive a response
     *
     * For most commands Gearman returns a bunch of lines and ends the 
     * transmission of data with a single line of ".\n". This command reads
     * in everything until ".\n". If the command being sent is NOT ended with
     * ".\n" DO NOT use this command.
     *
     * @throws Net_Gearman_Exception
     * @return string
     */
    protected function recvCommand()
    {
        $ret = '';
        while (true) {
            $data = fgets($this->conn, 4096);
            $this->checkForError($data);
            if ($data == ".\n") {
                break;
            }

            $ret .= $data;
        }

        return $ret;
    }

    /**
     * Check for an error
     *
     * Gearman returns errors in the format of 'ERR code_here Message+here'. 
     * This method checks returned values from the server for this error format
     * and will throw the appropriate exception.
     *
     * @param string $data The returned data to check for an error
     *
     * @return void
     * @throws Net_Gearman_Exception
     */
    protected function checkForError($data)
    {
        $data = trim($data); 
        if (preg_match('/^ERR/', $data)) {
            list(, $code, $msg) = explode(' ', $data);
            throw new Net_Gearman_Exception($msg, urldecode($code));
        }
    }

    /**
     * Disconnect from server
     *
     * @return void
     * @see Net_Gearman_Manager::$conn
     */
    public function disconnect()
    {
        if (is_resource($this->conn)) {
            fclose($this->conn);
        }
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}

?>
