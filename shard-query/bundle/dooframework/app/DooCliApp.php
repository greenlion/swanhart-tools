<?php
/**
 * DooCliApp class file.
 *
 * @author Richard Myers <richard.myers@hotmail.co.uk>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2011 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * DooCliApp for CLI application usage
 * @author Richard Myers <richard.myers@hotmail.co.uk>
 * @package doo.app
 * @since 1.4 
 */
class DooCliApp {

	const STREAM_STD_IN = "STD_IN";
	const STREAM_STD_OUT = "STD_OUT";

	public static $streamStdIn = false;

	public $route = array();

	public function __construct() {
		self::$streamStdIn = fopen('php://stdin', 'r');
	}

	public function  __destruct() {
		fclose(self::$streamStdIn);
	}

	public function run($args) {

		$arguments = $this->readArguments($args);

		if (isset($arguments[0])) {
			// Map to a specific route defined in route configuration
			// ie. php run.php shortcut
			// where $route['cli']['shortcut'] = array('ControllerName', 'ActionName');

			if (isset($this->route['cli'][$arguments[0]])) {
				$controllerName = $functionName = '';

				list($controllerName, $functionName) = $this->route['cli'][$arguments[0]];
				return $this->runController($controllerName, $functionName, $arguments);
				
			} else {
				self::writeLine('Could not find the route specified.');
			}
		
		} elseif (isset($arguments['r']) && strpos($arguments['r'], ':') !== false) {
			// Allow user to run specified controller using format
			// php run.php -r=MyController:functionName

			$parts = explode(':', $arguments['r']);
			return $this->runController($parts[0], $parts[1], $arguments);
		}

		$this->writeLine("Avalaible Routes are: ");
		foreach($this->route['cli'] as $routeKey => $routeInfo) {
			$info = isset($routeInfo['about']) ? " :: " . $routeInfo['about'] : '';
			self::writeLine(" - " . $routeKey . $info);
		}
		
		self::exitApp(1, "\nExiting DooPHP CLI Application");
	}
	

	/**
	 * Exit the application
	 * @param int $code Exit code from 0 to 255
	 * @param string $message Message to display on exit
	 */
	public static function exitApp($code=0, $message=null) {
		if ($message !== null) {
			self::writeLine($message);
		}
		if ($code < 0 || $code > 255) {
			$code = 1;
		}
		exit($code);
	}

	/**
	 * Clear the console screen
	 */
	public static function clearScreen() {
		system('clear');
	}

	/**
	 * Output the specified text on the specified output stream
	 * @param string $text Text to output
	 * @param string The output stream to output message on. Default Std Out
	 */
	public static function write($text, $stream='STD_OUT') {
		echo $text;
	}

	/**
	 * Output the specified string and prepend a new line to the end
	 * @param string $text Text to output
	 * @param string The output stream to output message on. Default Std Out
	 */
	public static function writeLine($text, $stream='STD_OUT') {
		self::write($text . "\n", $stream);
	}

	/**
	 * Display a title on the consol
	 * @param string $title The title to be displayed
	 * @param bool $clearScreen Should the screen be cleared (so title sits at top of title)
	 * @param int $width Title will be positioned in the middle of this width
	 */
	public static function displayTitle($title, $clearScreen=true, $width=80, $char='=') {
		if ($clearScreen) {
			self::clearScreen();
		}
		$lines = str_repeat($char, $width);
		$titlePadding = str_repeat(' ', ($width / 2 - strlen($title) / 2));
		self::writeLine($lines);
		self::writeLine($titlePadding . $title);
		self::writeLine($lines);
	}

	/**
	 * Run a command line task
	 *
	 * @param string $cmd The command to execute.
	 * @param string $input Any input not in arguments.
	 * @return array An array of execution data; stdout, stderr and return "error" code.
	 */
	public static function runCommandLineTask($command, $input='') {

		$pipes = null;

		$process = proc_open($command, array(
										0 => array('pipe','r'),
										1 => array('pipe','w'),
										2 => array('pipe','w'))
							, $pipes);

		fwrite($pipes[0], $input);
		fclose($pipes[0]);

		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$rtn = proc_close($process);

		return array(
			'stdout'=>$stdout,
			'stderr'=>$stderr,
			'return'=>$rtn
		);
	}

	

	/**
	 * Execute the specified controller
	 * @param string $controller Name of controller class
	 * @param string $action Name of the function to run
	 * @param array $arguments The arguments passed in from CLI
	 * @return mixed Result of executing the controller action
	 */
	private function runController($controller, $action, $arguments) {
		$controllerFile = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "controller/{$controller}.php";

		if (file_exists($controllerFile)) {
			require_once Doo::conf()->BASE_PATH ."controller/DooCliController.php";
			require_once($controllerFile);
			$controller = new $controller;

			if (method_exists($controller, $action)) {
				$controller->arguments = $arguments;
				return $controller->$action();
			} else {
				$this->exitApp(1, "Could not find specified action");
			}
		} else {
			$this->exitApp(1, "Could not find specified controller");
		}
	}

	/**
	 * Read arguments provided on Command Line and return them in an array
	 * Supprts flags, switchs and other arguments
	 * Flags ie. --foo=bar will come out as $out['foo'] = 'bar'
	 * Switches ie. -ab will come out as $out['a'] = true, $out['b'] = true
	 *		  OR IF -a=123 will come out as $out['a'] = 123
	 * Other Args ie. one.txt two.txt will come out as $out[0] = 'one.txt', $out[1] = 'two.txt'
	 *
	 * Function from : http://www.php.net/manual/en/features.commandline.php#93086
	 *
	 * @param array $args The command arguments from PHP's $argv variable
	 * @return array The arguments in a formatted array
	 */
	private function readArguments($args) {
		array_shift($args); // Remove the file name
		$out = array();
		foreach ($args as $arg){
			if (substr($arg, 0, 2) == '--') {	// Got a 'switch' (ie. --DEBUG_MODE=false OR --verbose)
				$eqPos = strpos($arg, '=');  // Has a value
				if ($eqPos === false){
					$key = substr($arg, 2);
					$out[$key] = isset($out[$key]) ? $out[$key] : true;
				} else {
					$key = substr($arg, 2, $eqPos-2);
					$out[$key] = substr($arg, $eqPos+1);
				}
			} else if (substr($arg, 0, 1) == '-') { // Got an argument (ie. -h OR -cfvr [shorthand for -c -f -v -r] OR -i=123)
				if (substr($arg, 2, 1) == '='){
					$key = substr($arg, 1, 1);
					$out[$key] = substr($arg, 3);
				} else {
					$chars = str_split(substr($arg, 1));
					foreach ($chars as $char){
						$key = $char;
						$out[$key] = isset($out[$key]) ? $out[$key] : true;
					}
				}
			} else {	// Just an argument ie (foo bar me.txt)
				$out[] = $arg;
			}
		}
		return $out;
	}
	
}

