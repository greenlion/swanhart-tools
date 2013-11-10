<?php
require_once('PHP-SQL-Parser/php-sql-parser.php');
$REWRITE_PARSER = new PHPSQLParser();


class RewriteBaseRule {
	var $messages = array();
	var $warnings = array();
	var $info = array();
	var $plan = array();
	var $errors = array();

	protected $caps = array ();
	protected $defaults = array();
	private $settings = array();

	static $depth=0;

	public function __construct($settings=array()) {
		$this->settings = $settings;
		$this->set_capability('MULTI_INPUT', FALSE);
		$this->set_capability('MULTI_OUTPUT', TRUE);
		$this->set_capability('RULE_NAME', 'BASE_RULE');
	}

	public function rewrite($sql, $table_info, $process_info=null, $settings = null) {
		if(is_array($sql)) return false;

		/* DEFAULT BEHAVIOR IS TO DO NOTHING */
		return array('has_rewrites'=>0, 'plan'=>array(0=>$sql));
	}

	/* Use the requested SUBCLASS to rewrite a query. Default is to not subclass which returns the same query as input.*/
	static function _ENTRY($s, $t, $p, $settings,$RULE='RewriteBaseRule') {

		/* Handle a chain of filters */
		if(strstr($RULE, ',')) {
			$plan = $s;
			$RULES = explode(',', $RULE);
			foreach($RULES as $new_rule) {
				$plan = SELF::_ENTRY($plan, $t, $p, $settings, $new_rule);
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
	static function remove_comments($sql) {
		return(preg_replace( array("%-- [^\\n](?:\\n|$)%", "%/\\*.*\\*/%"), '', $sql ));
	}

	static function is_select($parsed) {
		return(!empty($parsed['SELECT']) && empty($parsed['CREATE']) && empty($parsed['INSERT']) && empty($parsed['UPDATE']) && empty($parsed['REPLACE']) && empty($parsed['DELETE']));
	}

	static function has_capability($cap) {
		return(!empty($this->caps[$cap]) && $this->caps[$cap] == TRUE);
	}

	protected function set_capability($cap, $value) {
		return($this->caps[$cap] = $value);
	}

	public function get_setting($key) {
		if(!empty($this->settings[$key])) return($this->settings[$key]);
		if(!empty($this->defaults[$key])) return($this->defaults[$key]);
		return(false);
	}

	public function set_setting($key, $val=false) {
		$this->settings[$key] = $val;
		return true;
	}
}
