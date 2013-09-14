<?php
/**
 * DooCliController class file.
 *
 * @author Richard Myers <richard.myers@hotmail.co.uk>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2011 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * DooCliController is the controller for CLI application should be use with Doo::app('DooCliApp')->run();
 * @author Richard Myers <richard.myers@hotmail.co.uk>
 * @package doo.controller
 * @since 1.4 
 */
Doo::loadCore('app/DooCliApp');
class DooCliController {

	const STREAM_STD_IN = "STD_IN";
	const STREAM_STD_OUT = "STD_OUT";

	/**
	 * Arguments from the comment line
	 */
	public $arguments = array();


	/**
	 * Display a title in the consol
	 * @param string $title The title to be displayed
	 * @param bool $clearScreen Should the screen be cleared (so title sits at top of title)
	 * @param int $width Title will be positioned in the middle of this width
	 */
	protected function displayTitle($title, $clearScreen=true, $width=80, $char='=') {
		if ($clearScreen) {
			$this->clearScreen();
		}
		$lines = str_repeat($char, $width);
		$titlePadding = str_repeat(' ', ($width / 2 - strlen($title) / 2));
		$this->writeLine($lines);
		$this->writeLine($titlePadding . $title);
		$this->writeLine($lines);
	}

	/**
	 * Presents the users with a series of menu options defined as
	 * $options['h'] = "View Help";
	 * Will continue asking user for an option until they provide one
	 * from the list presented to them
	 *
	 * @param string $question A question for the user to answer
	 * @param array $options An associative array of options with key being option and value being description
	 * @param bool $useNumericalValueSelection Should options be given numerical indexes? Will be converted back to original key on selection
	 * @param bool $clearScreenBeforeDisplay Should the screen be cleared before showing the menu ?
	 * @param bool $clearScreenAfterDisplay Should the screen be cleared after showing the menu ?
	 * @return mixed The users choice (a key in the options array)
	 */
	protected function displayMenu($question, $options, $useNumericalValueSelection = false,
					 $clearScreenBeforeDisplay = false,
					 $clearScreenAfterDisplay = false) {

		if ($clearScreenBeforeDisplay) {
			$this->clearScreen();
		}

		// If we are displaying numerical option keys convert options and store the old keys for later
		$origKeys = array();
		if ($useNumericalValueSelection) {
			$i=0;
			$temp = array();
			foreach($options as $key=>$value) {
				$temp[$i] = $value;
				$origKeys[$i] = $key;
				$i++;
			}
			$options = $temp;
		}
		
		// Display the menu options
		$this->writeLine($question);
		foreach($options as $key=>$answer) {
			$this->writeLine("   {$key}) {$answer}");
		}
		$this->write("Please select an option: ");

		// Get the users choice
		$choice = '';
		$commands = array_keys($options);
		do {
			if ($choice != '')
				$this->write("\nUnknown Option. Please select another option: ");
			$choice = trim(fgets(DooCliApp::$streamStdIn));
		} while ($choice == '' || !in_array($choice, $commands));

		if ($clearScreenAfterDisplay) {
			$this->clearScreen();
		}

		// If we gave numerical options convert it back to actual key
		if ($useNumericalValueSelection) {
			return $origKeys[$choice];
		} else {
			return $choice;
		}
	}

	protected function displayMultiSelectMenu($question, $options,
					 $clearScreenBeforeDisplay = false,
					 $clearScreenAfterDisplay = false) {

		if ($clearScreenBeforeDisplay) {
			$this->clearScreen();
		}

		// If we are displaying numerical option keys convert options and store the old keys for later
		$origKeys = array();

		// We let user make multiple selections using numerical references
		$i=0;
		$temp = array();
		foreach($options as $key=>$value) {
			$temp[$i] = $value;
			$origKeys[$i] = $key;
			$i++;
		}
		$options = $temp;


		// Display the menu options
		$this->writeLine($question);
		foreach($options as $key=>$answer) {
			$this->writeLine("   {$key}) {$answer}");
		}
		$this->write("Please select an option(s): ");

		// Get the users choice
		$choices = array();
		$allPass = false;
		$commands = array_keys($options);
		do {
			if (!empty($choices))
				$this->write("\nUnknown or Invalid Option(s). Please select another option(s): ");
			$choices = explode(',', trim(fgets(Doo::app()->streamStdIn)));
			
			$allPass = true;
			foreach($choices as $choice) {
				$allPass = $allPass && in_array($choice, $commands);
			}
		} while (empty($choices) || $allPass == false);

		if ($clearScreenAfterDisplay) {
			$this->clearScreen();
		}

		$selection = array();
		foreach($choices as $choice) {
			$selection[] = $origKeys[$choice];
		}

		return $selection;
	}

	protected function getConfirmationToContinue($message="Are you sure you want to continue?") {
		return $this->displayMenu($message, array(
			'y' => "Yes - Continue...",
			'n' => "No - Cancel what I'm doing..."
		));
	}

	/**
	 * Ask the user a question and get there response. If default is not false
	 * the question is optional and the user may accept the default response
	 *
	 * @param string $question The question being asked
	 * @param string $default Optional default option. If not false then this value will be accepted if the user presses enter
	 */
	protected function askQuestion($question, $default=false) {

		if ($default === false) {
			$this->writeLine("{$question}:");
		} else {
			$this->writeLine($question);
			$this->writeLine("OR <enter> to use default ({$default}):");
		}

		while(true) {
			$choice = trim(fgets(DooCliApp::$streamStdIn));
			if ($choice == '' && $default !== false) {
				return $default;
			} elseif ($choice != '') {
				return $choice;
			} else {
				$this->writeLine("You must enter a value. Please try again:");
			}
		}
	}

	/**
	 * Pause execution and await the user pressing enter
	 * 
	 * @param string $message Message to display informing user execution paused
	 * @param bool $clearScreenAfter Should the screen be cleared when they continue?
	 */
	protected function pause($message='Please press <enter> to continue', $clearScreenAfter = true) {
		$this->writeLine($message);
		fgets(DooCliApp::$streamStdIn);
		if ($clearScreenAfter) {
			$this->clearScreen();
		}
	}

	/**
	 * Clear the console screen
	 */
	protected function clearScreen() {
		DooCliApp::clearScreen();
	}

	/**
	 * Exit the application
	 * @param int $code Exit code from 0 to 255
	 * @param string $message Message to display on exit
	 */
	protected function exitApp($code=0, $message=null) {
		DooCliApp::exitApp($code, $message);
	}

	/**
	 * Output the specified text on the specified output stream
	 * @param string $text Text to output
	 * @param string The output stream to output message on. Default Std Out
	 */
	protected function write($text='', $stream='STD_OUT') {
		DooCliApp::write($text, $stream);
	}

	/**
	 * Output the specified string and prepend a new line to the end
	 * @param string $text Text to output
	 * @param string The output stream to output message on. Default Std Out
	 */
	protected function writeLine($text='', $stream='STD_OUT') {
		DooCliApp::writeLine($text, $stream);
	}

	/**
	 * Run a command line task
	 *
	 * @param string $cmd The command to execute.
	 * @param string $input Any input not in arguments.
	 * @return array An array of execution data; stdout, stderr and return "error" code.
	 */
	public function runCommandLineTask($command, $input='') {
		DooCliApp::runCommandLineTask($command, $input);
	}

}