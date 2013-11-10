<?php
require_once('PHP-SQL-Parser/php-sql-parser.php');
$REWRITE_PARSER = new PHPSQLParser();


class RewriteBaseRule {
	var $messages = array();
	var $warnings = array();
	var $info = array();
	var $queries = array();
	var $plan = array();
	var $errors = array();

	private $caps = array ( 'MULTI_INPUT' => FALSE, 'MULTI_OUTPUT'=>TRUE, 'RULE_NAME' => 'BASE_RULE' );
	private $defaults = array();
	private $settings = array();

	public function __construct($process_info, $settings=array()) {
		$this->$process_info;
		$this->settings = $settings;
	}

	public function rewrite($sql, $table_info, $process_info=null, $settings = null) {
		/* DEFAULT BEHAVIOR IS TO DO NOTHING */
		return array('has_rewrites'=>0, 'queries'=>array(0=>$sql));
	}

	/* Use the requested SUBCLASS to rewrite a query. Default is to not subclass which returns the same query as input.*/
	static function _ENTRY($s, $t, $p, $settings,$RULE='RewriteBaseRule') {

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



	static function has_capability($cap) {
		return(!empty($this->caps[$cap]) && $this->caps[$cap] == TRUE);
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
