<?php
/**
 * DooFlashMessenger class file
 * @package doo.helper
 * @author Milos Kovacki <kovacki@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
* DooFlashMessenger
*
* @author Milos Kovacki <kovacki@gmail.com>
* @copyright &copy; 2009 Milos Kovacki
* @license http://www.doophp.com/license
*
* <p>Working with this class is very simple, first you need to init class</p>
* <code>
* $flash = new DooFlashMessenger();
* </code>
* <p>Adding message</p>
* <code>
* $flash->addMessage('This is some test message.');
* </code>
* <p>This will be stored in session and when displayMessages method is called
* it will echo all messages that are stored.
* Method for displaying messages should be inside of layout script or some other
* type of view script.</p>
*
* <p>First you need to asign flash messenger to your view, so you can access it
* from your template, or some other view file.</p>
* <code>
* $flash = new DooFlashMessenger();
* DooController::view()->flashMessenger = $flash;
* </code>
*
* <p>Now in your template file just do:</p>
* <code>$this->flashMessenger->displayMessages();</code>
*/
class DooFlashMessenger {

	/**
	* Namespace for session, default is dooFlashMessenger
	* @var string namespace
	*/
	protected $_namespace = 'dooFlashMessenger';

	/**
	* Array of messages
	* @var array messages
	*/
	static protected $_messages = array();

	/**
	* Constructor
	* @return void
	*/
	public function __construct() {

		if (!isset($_SESSION)) {
			session_start();
		}

		if (!isset($_SESSION[$this->_namespace])) {
			$_SESSION[$this->_namespace] = array();
		}

		if (count($_SESSION[$this->_namespace]) > 0) {
			foreach ($_SESSION[$this->_namespace] as $k => $message) {
				self::$_messages[] .= $message;
				unset($_SESSION[$this->_namespace][$k]);
			}
		}
	}

	/**
	* Set namespace, default is doo
	* @param string $namespace Namespace
	*/
	public function setNamespace($namespace = 'doo') {
        $this->_namespace = $namespace;
        return $this;
    }

	/**
	* Returns true if there are messages and false if not
	* @return bool
	*/
	public function hasMessages() {
		if (count(self::$_messages) > 0) {
			return true;
		}
		return false;
	}

	/**
	* Add message to message array
	* @param string $message Message
	*/
    public function addMessage($message) {
		if ($message != "") {
			$_SESSION[$this->_namespace][] = $message;
		}
        return $this;
    }

	/**
	* Get messages that are stored
	* @return array Messages in array
	*/

    public function getMessages() {
        if ($this->hasMessages()) {
            return self::$_messages;
        }
        return array();
    }

	/**
	* Clear all messages in array
	* @return bool
	*/
    public function clearMessages() {
        if ($this->hasMessages()) {
            self::$_messages = null;
            return true;
        }
        return false;
    }

	/**
	* Returns how many messages are stored in message array
	* @return int
	*/
	public function countMessages() {
		return count(self::$_messages);
	}

	/**
	* Display messages echoing all messages, use this from view script
	*/
	public function displayMessages() {
		if ($this->hasMessages()) {
			$output = '<div class="'.$this->_namespace.'_flashm">';
			if ($this->countMessages() == 1) {
				$output .= '<span class="'.$this->_namespace . '_inner">'
				. self::$_messages[0] . '</span>';
			} else {
			$output .= '<ul>';
				foreach (self::$_messages as $message) {
					$output .= '<li>' . $message . '</li>';
				}
				$output .= '</ul>';
			}
			$output .= '</div>';
			echo $output;
			// clear messages
			$this->clearMessages();
		}
	}

	public function addWrapper() {

	}
}
