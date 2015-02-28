<?php
/**
 * @package RewriteEngine
 * RewriteEngine Base Class
 * 
 * The RewriteEngine allows SQL queries to be re-written with ease. 
 * @author Justin Swanhart <greenlion@gmail.com>
 * @version 1.0
 */

/**
 * Include the SQL parser 
 */
require_once('parser/php-sql-parser.php');
require_once('util.php');

/**
 * @global array $GLOBALS['REWRITE_PARSER']
 * @name $REWRITE_PARSER
 */
$GLOBALS['REWRITE_PARSER'] = new PHPSQLParser();

/**
 * @package RewriteEngine
 * @subpackage classes
 */
class RewriteBaseRule {
/**
 * A place for optimizer messages like explain plans.
 * @var array
 */
	var $messages = array();
/**
 * A place for stronger messages from the optimizer
 * @var array
 */
	var $warnings = array();
/**
 * Information messages (like notices)
 * @var array
 */
	var $info = array();
/**
 * Query plan (array of queries that represents a DAG)
 * @var array
 */
	var $plan = array();
/**
 * Errors
 * @var array
 */
	var $errors = array();


/**
* Capability list
* @access private
* @var array
*/
	protected $caps = array ();
/**
* Capability list
* @access private
* @var array
*/
	private $settings = array();

	public function __construct($settings=array()) {
		$this->settings = $settings;
		$this->set_capability('MULTI_INPUT', FALSE);
		$this->set_capability('MULTI_OUTPUT', TRUE);
		$this->set_capability('RULE_NAME', 'BASE_RULE');
	}

    /**
     * Rewrite a query returing a DAG
     * @param string|array $sql 
     * @param array $table_info 
     * @param array $process_info 
     * @param array $settings
     * @return array
     */
	public function rewrite($sql, $table_info, $process_info=null, $settings = null) {
		if(is_array($sql)) return false;

		/* DEFAULT BEHAVIOR IS TO DO NOTHING */
		return array('has_rewrites'=>0, 'plan'=>array(0=>$sql));
	}
    /**
     * This static function is used as the entry point for execution in most cases. Returns a DAG.
     * Specify a SUBCLASS in RULE to rewrite a query. Default is to not subclass which returns the same query as input.
     * @param string|array $s
     * @param array $t
     * @param array $p
     * @param array $settings
     * @param string $RULE
     * @return array
     */
	static function _ENTRY($s, $t, $p, $settings,$RULE='RewriteBaseRule') {

		/* Handle a chain of filters */
		if(strstr($RULE, ',')) {
			$plan = $s;
			$RULES = explode(',', $RULE);
			foreach($RULES as $new_rule) {
				$plan = self::_ENTRY($plan, $t, $p, $settings, $new_rule);
			}	
			return($plan);
		}

		/* Unchained filter */
		$planner = new $RULE($s, $t, $p, $settings);
		$plan = $planner->rewrite($s, $t, $p, $settings);

		if(!empty($planner->errors)) {
			$plan['has_errors']=true;
			$plan['errors'] = $planner->errors;
		} else {
			$plan['has_errors']=false;
		}
		$plan['errors'] = $planner->errors;

		return $plan;
	}

	/*TODO: add fingerprint(), extract_comments(), add_comments(), and other useful functions*/
    /**
     * This static function can be used by rules to remove comments.
     * @param string $sql
     * @return string 
     */
	static function remove_comments($sql) {
		return(preg_replace( array("%-- [^\\n](?:\\n|$)%", "%/\\*.*\\*/%"), '', $sql ));
	}

    /**
     * This static function returns true when a parsed query is SELECT only, with no CREATE or INSERT, etc associated with it.
     * @param array $parsed
     * @return boolean
     */
	static function is_select($parsed) {
		return(!empty($parsed['SELECT']) && empty($parsed['CREATE']) && empty($parsed['INSERT']) && empty($parsed['UPDATE']) && empty($parsed['REPLACE']) && empty($parsed['DELETE']));
	}

    /**
     * This static function returns the requested capabilitity
     * @param string $cap
     * @return boolean
     */
	static function has_capability($cap) {
		return(!empty($this->caps[$cap]) && $this->caps[$cap] == TRUE);
	}

    /**
     * This static function sets the given capabilitity to the given value
     * @param string $cap
     * @param string $value
     * @return boolean
     */
	protected function set_capability($cap, $value) {
		return($this->caps[$cap] = $value);
	}

}
