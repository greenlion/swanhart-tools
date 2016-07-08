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

require_once 'Net/Gearman/Exception.php';

/**
 * The base connection class
 *
 * @category  Net
 * @package   Net_Gearman
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://www.danga.com/gearman/
 */
class Net_Gearman_Connection
{
    /**
     * A list of valid Gearman commands
     *
     * This is a list of valid Gearman commands (the key of the array), their
     * integery type (first key in second array) used in the binary header, and
     * the arguments / order of arguments to send/receive.
     *
     * @var array $commands       
     * @see Net_Gearman_Connection::$magic
     * @see Net_Gearman_Connection::connect()
     */
    static protected $commands = array(
        'can_do' => array(1, array('func')),
        'can_do_timeout' => array(23, array('func', 'timeout')),
        'cant_do' => array(2, array('func')),
        'reset_abilities' => array(3, array()),
        'set_client_id' => array(22, array('client_id')),
        'pre_sleep' => array(4, array()),
        'noop' => array(6, array()),
        'submit_job' => array(7, array('func', 'uniq', 'arg')),
        'submit_job_high' => array(21, array('func', 'uniq', 'arg')),
        'submit_job_bg' => array(18, array('func', 'uniq', 'arg')),
        'job_created' => array(8, array('handle')),
        'grab_job' => array(9, array()),
        'no_job' => array(10, array()),
        'job_assign' => array(11, array('handle', 'func', 'arg')),
        'work_status' => array(12, array('handle', 'numerator', 'denominator')),
        'work_complete' => array(13, array('handle', 'result')),
        'work_fail' => array(14, array('handle')),
        'get_status' => array(15, array('handle')),
        'status_res' => array(20, array('handle', 'known', 'running', 'numerator', 'denominator')),
        'echo_req' => array(16, array('text')),
        'echo_res' => array(17, array('text')),
        'error' => array(19, array('err_code', 'err_text')),
        'all_yours' => array(24, array())
    );

    /**
     * The reverse of Net_Gearman_Connection::$commands
     *
     * This is the same as the Net_Gearman_Connection::$commands array only
     * it's keyed by the magic (integer value) value of the command.
     *
     * @var array $magic
     * @see Net_Gearman_Connection::$commands 
     * @see Net_Gearman_Connection::connect()
     */
    static protected $magic = array();

    /**
     * Tasks waiting for a handle
     *
     * Tasks are popped onto this queue as they're submitted so that they can
     * later be popped off of the queue once a handle has been assigned via
     * the job_created command.
     *
     * @access      public
     * @var         array           $waiting
     * @static 
     */
    static public $waiting = array();

    /**
     * Is PHP's multibyte overload turned on?
     *
     * @var integer $multiByteSupport
     */
    static protected $multiByteSupport = null;

    /**
     * Constructor
     *
     * @return void
     */
    final private function __construct()
    {
        // Don't allow this class to be instantiated
    }

    /**
     * Connect to Gearman
     *
     * Opens the socket to the Gearman Job server. It throws an exception if
     * a socket error occurs. Also populates Net_Gearman_Connection::$magic.
     *
     * @param string $host    e.g. 127.0.0.1 or 127.0.0.1:7003
     * @param int    $timeout Timeout in milliseconds
     * 
     * @return resource A connection to a Gearman server
     * @throws Net_Gearman_Exception when it can't connect to server
     * @see Net_Gearman_Connection::$waiting
     * @see Net_Gearman_Connection::$magic
     * @see Net_Gearman_Connection::$commands
     */
    static public function connect($host, $timeout = 2000)
    {
        if (!count(self::$magic)) {
            foreach (self::$commands as $cmd => $i) {
                self::$magic[$i[0]] = array($cmd, $i[1]);
            }
        }

        $err   = '';
        $errno = 0;
        $port  = 7003;

        if (strpos($host, ':')) {
            list($host, $port) = explode(':', $host);
        }

        $start = microtime(true);
        do {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            @socket_connect($socket, $host, $port);
            $errorCode = socket_last_error($socket);
            
            socket_set_nonblock($socket);
            socket_set_option($socket, SOL_TCP, 1, 1);
            $timeLeft = ((microtime(true) - $start) * 1000);
        } while (!is_resource($socket) && $timeLeft < $timeout);

        if ($errorCode == 111) {
            throw new Net_Gearman_Exception("Can't connect to server");
        }

        self::$waiting[(int)$socket] = array();
        return $socket;
    }

    /**
     * Send a command to Gearman
     *
     * This is the command that takes the string version of the command you
     * wish to run (e.g. 'can_do', 'grab_job', etc.) along with an array of
     * parameters (in key value pairings) and packs it all up to send across
     * the socket.
     *
     * @param resource $socket  The socket to send the command to
     * @param string   $command Command to send (e.g. 'can_do')
     * @param array    $params  Params to send
     * 
     * @see Net_Gearman_Connection::$commands, Net_Gearman_Connection::$socket
     * @return boolean
     * @throws Net_Gearman_Exception on invalid command or unable to write 
     */
    static public function send($socket, $command, array $params = array())
    {
        if (!isset(self::$commands[$command])) {
            throw new Net_Gearman_Exception('Invalid command: ' . $command);
        }

        $data = array();
        foreach (self::$commands[$command][1] as $field) {
            if (isset($params[$field])) {
                $data[] = $params[$field];
            }
        }

        $d = implode("\x00", $data);

        $cmd = "\0REQ" . pack("NN", 
                              self::$commands[$command][0], 
                              self::stringLength($d)) . $d;

        $cmdLength = self::stringLength($cmd); 
        $written = 0;
        $error = false;
        do {
            $check = @socket_write($socket, 
                                   self::subString($cmd, $written, $cmdLength), 
                                   $cmdLength);

            if ($check === false) {
                if (socket_last_error($socket) == SOCKET_EAGAIN or            
                    socket_last_error($socket) == SOCKET_EWOULDBLOCK or
                    socket_last_error($socket) == SOCKET_EINPROGRESS) 
                {
                  // skip this is okay
                }
                else
                {
                   $error = true;
                   break;   
                }
            }

            $written += (int)$check;
        } while ($written < $cmdLength);

        if ($error === true) {
            throw new Net_Gearman_Exception(
                'Could not write command to socket'
            );
        }
    }

    /**
     * Read command from Gearman
     *
     * @param resource $socket The socket to read from
     * 
     * @see Net_Gearman_Connection::$magic
     * @return array Result read back from Gearman
     * @throws Net_Gearman_Exception connection issues or invalid responses
     */
    static public function read($socket)
    {
        $header = '';
        do {
            $buf = socket_read($socket, 12 - self::stringLength($header));
            $header .= $buf;
        } while ($buf !== false && 
                 $buf !== '' && self::stringLength($header) < 12);
        
        if ($buf === '') {
            throw new Net_Gearman_Exception("Connection was reset");
        }        
        
        if (self::stringLength($header) == 0) {
            return array();
        }
        $resp = @unpack('a4magic/Ntype/Nlen', $header);
          
        if (!count($resp) == 3) {
            throw new Net_Gearman_Exception('Received an invalid response');
        }

        if (!isset(self::$magic[$resp['type']])) {
            throw new Net_Gearman_Exception(
                'Invalid response magic returned: ' . $resp['type']
            );
        }

        $return = array();
        if ($resp['len'] > 0) {
            $data = '';
            while (self::stringLength($data) < $resp['len']) {
                $data .= socket_read($socket, $resp['len'] - self::stringLength($data));
            }

            $d = explode("\x00", $data);
            foreach (self::$magic[$resp['type']][1] as $i => $a) {
                $return[$a] = $d[$i]; 
            }
        }

        $function = self::$magic[$resp['type']][0];
        if ($function == 'error') {
            if (!self::stringLength($return['err_text'])) {
                $return['err_text'] = 'Unknown error; see error code.';
            }

            throw new Net_Gearman_Exception(
                $return['err_text'], $return['err_code']
            );
        }

        return array('function' => self::$magic[$resp['type']][0],
                     'type' => $resp['type'],
                     'data' => $return);
    }

    /**
     * Blocking socket read
     * 
     * @param resource $socket  The socket to read from
     * @param float    $timeout The timeout for the read
     * 
     * @throws Net_Gearman_Exception on timeouts
     * @return array
     */
    static public function blockingRead($socket, $timeout = 500) 
    {
        static $cmds = array();

        $tv_sec  = floor(($timeout % 1000));
        $tv_usec = ($timeout * 1000);

        $start = microtime(true);
        while (count($cmds) == 0) { 
            if (((microtime(true) - $start) * 1000) > $timeout) {
                throw new Net_Gearman_Exception('Blocking read timed out');
            }

            $write  = null;
            $except = null;
            $read   = array($socket);

            socket_select($read, $write, $except, $tv_sec, $tv_usec);
            foreach ($read as $s) {
                $cmds[] = Net_Gearman_Connection::read($s);
            }
        } 

        return array_shift($cmds);
    }

    /**
     * Close the connection
     *
     * @param resource $socket The connection/socket to close
     * 
     * @return void
     */
    static public function close($socket)
    {
        if (is_resource($socket)) {
            socket_close($socket);
        }
    }

    /**
     * Are we connected?
     *
     * @param resource $conn The connection/socket to check
     *
     * @return boolean False if we aren't connected
     */
    static public function isConnected($conn)
    {
        return (is_null($conn) !== true &&
                is_resource($conn) === true && 
                strtolower(get_resource_type($conn)) == 'socket');
    }

    /**
     * Determine if we should use mb_strlen or stock strlen
     *
     * @param string $value The string value to check
     * 
     * @return integer Size of string
     * @see Net_Gearman_Connection::$multiByteSupport
     */
    static public function stringLength($value)
    {
        if (is_null(self::$multiByteSupport)) {
            self::$multiByteSupport = intval(ini_get('mbstring.func_overload'));
        }

        if (self::$multiByteSupport & 2) { 
            return mb_strlen($value, '8bit');
        } else {
            return strlen($value);
        }
    }

    /**
     * Multibyte substr() implementation
     *
     * @param string  $str    The string to substr()
     * @param integer $start  The first position used
     * @param integer $length The maximum length of the returned string
     *
     * @return string Portion of $str specified by $start and $length
     * @see Net_Gearman_Connection::$multiByteSupport
     * @link http://us3.php.net/mb_substr
     * @link http://us3.php.net/substr
     */
    static public function subString($str, $start, $length)
    {
        if (is_null(self::$multiByteSupport)) {
            self::$multiByteSupport = intval(ini_get('mbstring.func_overload'));
        }

        if (self::$multiByteSupport & 2) { 
            return mb_substr($str, $start, $length, '8bit');
        } else {
            return substr($str, $start, $length);
        }
    }
}

?>
