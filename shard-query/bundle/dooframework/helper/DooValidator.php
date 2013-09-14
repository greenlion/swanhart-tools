<?php
/**
 * DooValidator class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * A helper class that helps validating data.
 *
 * <p>DooValidator can be used for form and Model data validation before saving/inserting/deleting a data.</p>
 *
 * <p>To use DooValidator, you have to create an instance of it and defined the validation rules.
 * All the methods start with 'test' can be used as a rule for validating data. Rule names are case insensitive.</p>
 *
 * <p>You can pass in custom error message along with the rules. By default all fields in the rules are <b>required</b></p>
 * <code>
 * $rules = array(
 *              'creattime'=>array(
 *                    array('datetime'),
 *                    array('email'),
 *                    array('optional')   //Optional field
 *              ),
 *              'username'=>array(
 *                    array('username',6,16),
 *                    //Custom error message will be used
 *                    array('lowercase', 'Username must only be lowercase.')
 *               ),
 *
 *               //Only one rule
 *               'pwd'=>array('password'),
 *               'email'=>array('email'),
 *               'age'=>array('between',13,200),
 *               'today'=>array('date','mm/dd/yy'),
 *
 *               //Custom rules, static method required
 *               'a'=>array('custom', 'MainController::isA'),
 *
 *               //Custom Required field message.
 *               'content'=>array('required', 'Content is required!')
 *        );
 * </code>
 *
 * <p>Rules are defined based on the validation method's parameters. For example:</p>
 * <code>
 * //Validation method
 * testBetween($value, $min, $max, $msg=null)
 *
 * $rule = array(
 *     'field'=>array('between', 0, 20)
 *     'field2'=>array('between', 0, 20, 'Custom Err Msg!')
 * );
 * </code>
 *
 * <p>You can get the list of available validation rules by calling DooValidator::getAvailableRules()</p>
 *
 * <p>To validate the data, create an instance of DooValidator and call validate() in your Controller/Model.</p>
 * <code>
 * $v = new DooValidator();
 *
 * # There are 3 different validation Mode.
 * //$v->checkMode = DooValidator::CHECK_SKIP;
 * //$v->checkMode = DooValidator::CHECK_ALL_ONE;
 * //$v->checkMode = DooValidator::CHECK_ALL;
 *
 * //The $_POST or data pass in need to be an assoc array
 * //$data = array('username'=>'doophp', 'pwd'=>'12345');
 * if($error = $v->validate($_POST, $rules)){
 *      print_r($error);
 * }
 * </code>
 *
 * <p>You can pass in a string to load predefined Rules which located at SITE_PATH/protected/config/forms/</p>
 * <code>
 * <?php
 * //in protected/config/forms/example.php
 * return array(
 *      'username'=>array(
 *                      array('username',4,5,'username invalid'),
 *                      array('maxlength',6,'This is too long'),
 *                      array('minlength',6)
 *                  ),
 *      'pwd'=>array('password',3,5),
 *      'email'=>array('email')
 *  );
 * ?>
 *
 * //in your Controller/Model
 * $error = $v->validate($data, 'example');
 * </code>
 *
 * <p>If nothing is returned from the validate() call, it means that all the data passed the validation rules.</p>
 *
 * <p>The Model validation rules are automatically generated when you used the framework's model generator feature.
 * If your Models are extending DooModel or DooSmartModel, you can validate the data by calling DooModel::validate()</p>
 * <code>
 * $f = new Food;
 * $f->name = 'My Food name for validation';
 * $error = $f->validate();
 *
 * //Or...
 * $error = Food::_validate($f);
 * </code>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooValidator.php 1000 2009-08-30 11:37:22
 * @package doo.helper
 * @since 1.2
 */
class DooValidator {
    /**
     * Checks All and returns All errors
     */
    const CHECK_ALL = 'all';

    /**
     * Checks All and returns one error for each data field
     */
    const CHECK_ALL_ONE = 'all_one';

    /**
     * Returns one error once the first is detected
     */
    const CHECK_SKIP = 'skip';

	/**
	 * Use PHP empty method to test for required (or optional)
	 */
	const REQ_MODE_NULL_EMPTY = 'nullempty';

	/**
	 * Only ensure required fields are non null / accept not null on required
	 */
	const REQ_MODE_NULL_ONLY = 'null';
    
    /**
     * Default require message to display field name "first_name is required."
     */
    const REQ_MSG_FIELDNAME = 'fieldname';
    
    /**
     * Default require message to display "This is required."
     */
    const REQ_MSG_THIS = 'this';
    
    /**
     * Default require message to convert field name with underscore to words. eg(field = first_name). "First name is required."
     */    
    const REQ_MSG_UNDERSCORE_TO_SPACE = 'underscore';
    
    /**
     * Default require message to convert field name with camelcase to words. eg(field = firstName). "First name is required."
     */    
    const REQ_MSG_CAMELCASE_TO_SPACE = 'camelcase';

    /**
     * Validation mode
     * @var string all/all_one/skip
     */
    public $checkMode = 'all';

	/**
	 * How should required fields be tested (or be considered left out on optional)
	 * @var string empty/null
	 */
	public $requireMode = 'nullempty';
    
    /**
     * Default method to generate error message for a required field.
     * @var string
     */
    public $requiredMsgDefaultMethod = 'underscore';
    
    /**
     * Default error message suffix for a required field.
     * @var string
     */
    public $requiredMsgDefaultSuffix = ' field is required';

    /**
     * Trim the data fields. The data will be modified.
     * @param array $data assoc array to be trimmed
	 * @param int $maxDepth Maximum number of recursive calls. Defaults to a max nesting depth of 5.
     */
    public function trimValues(&$data, $maxDepth = 5) {
		foreach($data as $k=>&$v) {
			if (is_array($v)) {
				if ($maxDepth > 0) {
					$this->trimValues($v, $maxDepth - 1);
				}
			} else {
				$v = trim($v);
			}
		}
	}

    /**
     * Get a list of available rules
     * @return array
     */
    public static function getAvailableRules(){
        return array('alpha', 'alphaNumeric', 'between', 'betweenInclusive', 'ccAmericanExpress', 'ccDinersClub', 'ccDiscover', 'ccMasterCard',
                    'ccVisa', 'colorHex', 'creditCard', 'custom', 'date', 'dateBetween', 'datetime', 'digit', 'email', 'equal', 'equalAs', 'float',
                    'greaterThan', 'greaterThanOrEqual', 'ip', 'integer', 'lessThan', 'lessThanOrEqual', 'lowercase', 'max',
                    'maxlength', 'min', 'minlength', 'notEmpty', 'notEqual', 'notNull', 'password', 'passwordComplex', 'price', 'regex',
                    'uppercase', 'url', 'username','dbExist','dbNotExist','alphaSpace','notInList','inList'
                );
    }

    /**
     * Get appropriate rules for a certain DB data type
     * @param string $dataType
     * @return string Rule name for the data type
     */
    public static function dbDataTypeToRules($type){
        $dataType = array(
                        //integers
                        'tinyint'=>'integer',
                        'smallint'=>'integer',
                        'mediumint'=>'integer',
                        'int'=>'integer',
                        'bigint'=>'integer',

                        //float
                        'float'=>'float',
                        'double'=>'float',
                        'decimal'=>'float',

                        //datetime
                        'date'=>'date',
                        'datetime'=>'datetime',
                        'timestamp'=>'datetime',
                        'time'=>'datetime'
                    );
        if(isset($dataType[$type]))
            return $dataType[$type];
    }

    /**
     * Validate the data with the defined rules.
     *
     * @param array $data Data to be validate. One dimension assoc array, eg. array('user'=>'leng', 'email'=>'abc@abc.com')
     * @param string|array $rules Validation rule. Pass in a string to load the predefined rules in SITE_PATH/protected/config/forms
     * @return array Returns an array of errors if errors exist.
     */
    public function validate($data=null, $rules=null){
        //$data = array('username'=>'leng s', 'pwd'=>'234231dfasd', 'email'=>'asdb12@#asd.com.my');
        //$rules = array('username'=>array('username'), 'pwd'=>array('password',6,32), 'email'=>array('email'));
        if(is_string($rules)){
            $rules = include(Doo::conf()->SITE_PATH .  Doo::conf()->PROTECTED_FOLDER . 'config/forms/'.$rules.'.php');
        }

        $optErrorRemove = array();

        foreach($data as $dk=>$dv){
            if($this->requireMode == DooValidator::REQ_MODE_NULL_EMPTY && ($dv === null || $dv === '') ||
			   $this->requireMode == DooValidator::REQ_MODE_NULL_ONLY  && $dv === null){
                unset($data[$dk]);
			}
        }

        if($missingKey = array_diff_key($rules, $data) ){
                $fieldnames = array_keys($missingKey);
                $customRequireMsg = null;

                foreach($fieldnames as $fieldname){
                    if(isset($missingKey[$fieldname])){
                        if( in_array('required', $missingKey[$fieldname]) ){
                            $customRequireMsg = $missingKey[$fieldname][1];
                        }
                        else if(is_array($missingKey[$fieldname][0])){
                            foreach($missingKey[$fieldname] as $f)
                                if($f[0]=='required'){
                                    if(isset($f[1]))
                                        $customRequireMsg = $f[1];
                                    break;
                                }
                        }
                    }

                    //remove optional fields from error
                    if(is_array($missingKey[$fieldname][0])){
                       foreach($missingKey[$fieldname] as $innerArrayRules){
                           if($innerArrayRules[0]=='optional'){
                               //echo $fieldname.' - 1 this is not set and optional, should be removed from error';
                               $optErrorRemove[] = $fieldname;
                               break;
                           }
                       }
                    }

                    if($this->checkMode==DooValidator::CHECK_ALL){
                        if($customRequireMsg!==null)
                            $errors[$fieldname] = $customRequireMsg;
                        else
                            $errors[$fieldname] = $this->getRequiredFieldDefaultMsg($fieldname);
                    }else if($this->checkMode==DooValidator::CHECK_SKIP){
                        if(in_array($fieldname, $optErrorRemove))
                            continue;
                        if($customRequireMsg!==null)
                            return $customRequireMsg;
                        return $this->getRequiredFieldDefaultMsg($fieldname);
                    }else if($this->checkMode==DooValidator::CHECK_ALL_ONE){
                        if($customRequireMsg!==null)
                            $errors[$fieldname] = $customRequireMsg;
                        else
                            $errors[$fieldname] = $this->getRequiredFieldDefaultMsg($fieldname);
                    }
                }
        }

        foreach($data as $k=>$v){
            if(!isset($rules[$k])) continue;
            $cRule = $rules[$k];
            foreach($cRule as $v2){
                if(is_array($v2)){
                    //print_r(array_slice($v2, 1));
                    $vv = array_merge(array($v),array_slice($v2, 1));

					$vIsEmpty = ($this->requireMode == DooValidator::REQ_MODE_NULL_EMPTY && ($v === null || $v === '') ||
								 $this->requireMode == DooValidator::REQ_MODE_NULL_ONLY  && $v === null) ? true : false;

                    //call func
                    if($vIsEmpty && $v2[0]=='optional'){
                        //echo $k.' - this is not set and optional, should be removed from error';
                        $optErrorRemove[] = $k;
                    }
                    if($err = call_user_func_array(array(&$this, 'test'.$v2[0]), $vv) ){
                        if($this->checkMode==DooValidator::CHECK_ALL)
                            $errors[$k][$v2[0]] = $err;
                        else if($this->checkMode==DooValidator::CHECK_SKIP && !$vIsEmpty && $v2[0]!='optional'){
                            return $err;
                        }else if($this->checkMode==DooValidator::CHECK_ALL_ONE)
                            $errors[$k] = $err;
                    }
                }
                else if(is_string($cRule[0])){
                    if(sizeof($cRule)>1){
                        //print_r(array_slice($cRule, 1));
                        $vv = array_merge(array($v),array_slice($cRule, 1));

                        if($err = call_user_func_array(array(&$this, 'test'.$cRule[0]), $vv) ){
                            if($this->checkMode==DooValidator::CHECK_ALL || $this->checkMode==DooValidator::CHECK_ALL_ONE)
                                $errors[$k] = $err;
                            else if($this->checkMode==DooValidator::CHECK_SKIP){
                                return $err;
                            }
                        }
                    }else{
                        if($err = $this->{'test'.$cRule[0]}($v) ){
                            if($this->checkMode==DooValidator::CHECK_ALL || $this->checkMode==DooValidator::CHECK_ALL_ONE)
                                $errors[$k] = $err;
                            else if($this->checkMode==DooValidator::CHECK_SKIP){
                                return $err;
                            }
                        }
                    }
                    continue 2;
                }
            }
        }
        if(isset($errors)){
            if(sizeof($optErrorRemove)>0){
                foreach($errors as $ek=>$ev){
                    if(in_array($ek, $optErrorRemove)){
                        //echo '<h3>Removing error '.$ek.'</h3>';
                        unset($errors[$ek]);
                    }
                }
            }
            return $errors;
        }
    }
    
    /**
     * Set default settings to display the default error message for required fields
     * @param type $displayMethod Default error message display method. use: DooValidator::REQ_MSG_UNDERSCORE_TO_SPACE, DooValidator::REQ_MSG_CAMELCASE_TO_SPACE, DooValidator::REQ_MSG_THIS, DooValidator::REQ_MSG_FIELDNAME
     * @param type $suffix suffix for the error message. Default is ' field is required'
     */
    public function setRequiredFieldDefaults( $displayMethod = DooValidator::REQ_MSG_UNDERSCORE_TO_SPACE, $suffix = ' field is required'){
        $this->requiredMsgDefaultMethod = $displayMethod;
        $this->requiredMsgDefaultSuffix = $suffix;
    }
    
    /**
     * Get the default error message for required field
     * @param string $fieldname Name of the field
     * @return string Error message
     */
    public function getRequiredFieldDefaultMsg($fieldname){
        if($this->requiredMsgDefaultMethod==DooValidator::REQ_MSG_UNDERSCORE_TO_SPACE)
            return ucfirst(str_replace('_', ' ', $fieldname)) . $this->requiredMsgDefaultSuffix;
        
        if($this->requiredMsgDefaultMethod==DooValidator::REQ_MSG_THIS)
            return 'This ' . $this->requiredMsgDefaultSuffix;        
        
        if($this->requiredMsgDefaultMethod==DooValidator::REQ_MSG_CAMELCASE_TO_SPACE)
            return ucfirst(strtolower(preg_replace('/([A-Z])/', ' $1', $fieldname))) . $this->requiredMsgDefaultSuffix;
        
        if($this->requiredMsgDefaultMethod==DooValidator::REQ_MSG_FIELDNAME)
            return $fieldname . $this->requiredMsgDefaultSuffix;
    }

    public function testOptional($value){}
    public function testRequired($value, $msg){
		if ($this->requireMode == DooValidator::REQ_MODE_NULL_EMPTY && ($value === null || $value === '') ||
			$this->requireMode == DooValidator::REQ_MODE_NULL_ONLY  && $value === null) {

            if($msg!==null) return $msg;
            return 'This field is required!';
        }
    }

    /**
     * Validate data with your own custom rules.
     *
     * Usage in Controller:
     * <code>
     * public static function isA($value){
     *      if($value!='a'){
     *          return 'Value must be A';
     *      }
     * }
     *
     * public function test(){
     *     $rules = array(
     *          'email'=>array('custom', 'TestController::isA')
     *     );
     *
     *     $v = new DooValidator();
     *     if($error = $v->validate($_POST, $rules)){
     *          //display error
     *     }
     * }
     * </code>
     *
     * @param string $value Value of data to be validated
     * @param string $function Name of the custom function
     * @param string $msg Custom error message
     * @return string
     */
    public function testCustom($value, $function, $options=null ,$msg=null){
        if($options==null){
            if($err = call_user_func($function, $value)){
                if($err!==true){
                    if($msg!==null) return $msg;
                    return $err;
                }
            }
        }else{
            //if array, additional parameters
            if($err = call_user_func_array($function, array_merge(array($value), $options)) ){
                if($err!==true){
                    if($msg!==null) return $msg;
                    return $err;
                }
            }
        }
    }

    /**
     * Validate against a Regex rule
     *
     * @param string $value Value of data to be validated
     * @param string $regex Regex rule to be tested against
     * @param string $msg Custom error message
     * @return string
     */
    public function testRegex($value, $regex, $msg=null){
        if(!preg_match($regex, $value) ){
            if($msg!==null) return $msg;
            return 'Error in field.';
        }
    }

    /**
     * Validate username format.
     *
     * @param string $value Value of data to be validated
     * @param int $minLength Minimum length
     * @param int $maxLength Maximum length
     * @param string $msg Custom error message
     * @return string
     */
    public function testUsername($value, $minLength=4, $maxLength=12, $msg=null){
        if(!preg_match('/^[a-zA-Z][a-zA-Z.0-9_-]{'. ($minLength-1) .','.$maxLength.'}$/i', $value)){
            if($msg!==null) return $msg;
            return "User name must be $minLength-$maxLength characters. Only characters, dots, digits, underscore & hyphen are allowed.";
        }
        else if(strpos($value, '..')!==False){
            if($msg!==null) return $msg;
            return "User name cannot consist of 2 continuous dots.";
        }
        else if(strpos($value, '__')!==False){
            if($msg!==null) return $msg;
            return "User name cannot consist of 2 continuous underscore.";
        }
        else if(strpos($value, '--')!==False){
            if($msg!==null) return $msg;
            return "User name cannot consist of 2 continuous dash.";
        }
        else if(strpos($value, '.-')!==False || strpos($value, '-.')!==False ||
                strpos($value, '._')!==False || strpos($value, '_.')!==False ||
                strpos($value, '_-')!==False || strpos($value, '-_')!==False){
            if($msg!==null) return $msg;
            return "User name cannot consist of 2 continuous punctuation.";
        }
        else if(ctype_punct($value[0])){
            if($msg!==null) return $msg;
            return "User name cannot start with a punctuation.";
        }
        else if(ctype_punct( substr($value, strlen($value)-1) )){
            if($msg!==null) return $msg;
            return "User name cannot end with a punctuation.";
        }
    }

    /**
     * Validate password format
     *
     * @param string $value Value of data to be validated
     * @param int $minLength Minimum length
     * @param int $maxLength Maximum length
     * @param string $msg Custom error message
     * @return string
     */
    public function testPassword($value, $minLength=6, $maxLength=32, $msg=null){
        if(!preg_match('/^[a-zA-Z.0-9_-]{'.$minLength.','.$maxLength.'}$/i', $value)){
            if($msg!==null) return $msg;
            return "Only characters, dots, digits, underscore & hyphen are allowed. Password must be at least $minLength characters long.";
        }
    }

    /**
     * Validate against a complex password format
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testPasswordComplex($value, $msg=null){
        if(!preg_match('A(?=[-_a-zA-Z0-9]*?[A-Z])(?=[-_a-zA-Z0-9]*?[a-z])(?=[-_a-zA-Z0-9]*?[0-9])[-_a-zA-Z0-9]{6,32}z', $value)){
            if($msg!==null) return $msg;
            return 'Password must contain at least one upper case letter, one lower case letter and one digit. It must consists of 6 or more letters, digits, underscores and hyphens.';
        }
    }

    /**
     * Validate email address
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testEmail($value, $msg=null){
		// Regex based on best solution from here: http://fightingforalostcause.net/misc/2006/compare-email-regex.php
        if(!preg_match('/^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$/i', $value) ||
            strpos($value, '--')!==False || strpos($value, '-.')!==False
        ){
            if($msg!==null) return $msg;
            return 'Invalid email format!';
        }
    }

    /**
     * Validate a URL
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testUrl($value, $msg=null){
        if(!preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $value)){
            if($msg!==null) return $msg;
            return 'Invalid URL!';
        }
    }

    /**
     * Validate an IP address (198.168.1.101)
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testIP($value, $msg=null){
        //198.168.1.101
        if (!preg_match('/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/',$value)) {
            if($msg!==null) return $msg;
            return 'Invalid IP address!';
        }
    }

    /**
     * Validate a credit card number
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testCreditCard($value, $msg=null){
        //568282246310632
        if (!preg_match('/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6011[0-9]{12}|3(?:0[0-5]|[68][0-9])[0-9]{11}|3[47][0-9]{13})$/', $value)) {
            if($msg!==null) return $msg;
            return 'Invalid credit card number!';
        }
    }

    /**
     * Validate an American Express credit card number
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testCcAmericanExpress($value, $msg=null){
        if (!preg_match('/^3[47][0-9]{13}$/', $value)) {
            if($msg!==null) return $msg;
            return 'Invalid American Express credit card number!';
        }
    }

    /**
     * Validate an Discover credit card number
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testCcDiscover($value, $msg=null){
        if (!preg_match('/^6011[0-9]{12}$/', $value)) {
            if($msg!==null) return $msg;
            return 'Invalid Discover credit card number!';
        }
    }

    /**
     * Validate an Diners Club credit card number
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testCcDinersClub($value, $msg=null){
        if (!preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $value)) {
            if($msg!==null) return $msg;
            return 'Invalid Diners Club credit card number!';
        }
    }

    /**
     * Validate an Master Card number
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testCcMasterCard($value, $msg=null){
        if (!preg_match('/^5[1-5][0-9]{14}$/', $value)) {
            if($msg!==null) return $msg;
            return 'Invalid Master Card number!';
        }
    }

    /**
     * Validate an Visa Card number
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testCcVisa($value, $msg=null){
        if (!preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $value)) {
            if($msg!==null) return $msg;
            return 'Invalid Visa card number!';
        }
    }

    /**
     * Validate Color hex #ff0000
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testColorHex($value, $msg=null){
        //#ff0000
        if (!preg_match('/^#([0-9a-f]{1,2}){3}$/i', $value)) {
            if($msg!==null) return $msg;
            return 'Invalid color code!';
        }
    }

    //------------------- Common data validation ---------------------

    /**
     * Validate Date Time
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testDateTime($value, $msg=null){
        $rs = strtotime($value);

        if ($rs===false || $rs===-1){
            if($msg!==null) return $msg;
            return 'Invalid date time format!';
        }
    }

    /**
     * Validate Date format. Default yyyy/mm/dd.
     *
     * <p>Date format: yyyy-mm-dd, yyyy/mm/dd, yyyy.mm.dd
     * Date valid from 1900-01-01 through 2099-12-31</p>
     *
     * @param string $value Value of data to be validated
     * @param string $dateFormat Date format
     * @param string $msg Custom error message
     * @return string
     */
    public function testDate($value, $format='yyyy/mm/dd', $msg=null, $forceYearLength=false){
        //Date yyyy-mm-dd, yyyy/mm/dd, yyyy.mm.dd
        //1900-01-01 through 2099-12-31

		$yearFormat = "(19|20)?[0-9]{2}";
		if ($forceYearLength == true) {
			if (strpos($format, 'yyyy') !== false) {
				$yearFormat = "(19|20)[0-9]{2}";
			} else {
				$yearFormat = "[0-9]{2}";
			}
		}

        switch($format){
            case 'dd/mm/yy':
                $format = "/^\b(0?[1-9]|[12][0-9]|3[01])[- \/.](0?[1-9]|1[012])[- \/.]{$yearFormat}\b$/";
                break;
            case 'mm/dd/yy':
                $format = "/^\b(0?[1-9]|1[012])[- \/.](0?[1-9]|[12][0-9]|3[01])[- \/.]{$yearFormat}\b$/";
                break;
            case 'mm/dd/yyyy':
                $format = "/^(0[1-9]|1[012])[- \/.](0[1-9]|[12][0-9]|3[01])[- \/.]{$yearFormat}$/";
                break;
            case 'dd/mm/yyyy':
                $format = "/^(0[1-9]|[12][0-9]|3[01])[- \/.](0[1-9]|1[012])[- \/.]{$yearFormat}$/";
                break;
            case 'yy/mm/dd':
                $format = "/^\b{$yearFormat}[- \/.](0?[1-9]|1[012])[- \/.](0?[1-9]|[12][0-9]|3[01])\b$/";
                break;
            case 'yyyy/mm/dd':
            default:
                $format = "/^\b{$yearFormat}[- \/.](0?[1-9]|1[012])[- \/.](0?[1-9]|[12][0-9]|3[01])\b$/";
        }

        if (!preg_match($format, $value)) {
            if($msg!==null) return $msg;
            return 'Invalid date format!';
        }
    }

    /**
     * Validate if given date is between 2 dates.
     *
     * @param string $value Value of data to be validated
     * @param string $dateStart Starting date
     * @param string $dateEnd Ending date
     * @param string $msg Custom error message
     * @return string
     */
    public function testDateBetween($value, $dateStart, $dateEnd, $msg=null){
		$value = strtotime($value);
        if(!( $value > strtotime($dateStart) && $value < strtotime($dateEnd) ) ) {
            if($msg!==null) return $msg;
            return "Date must be between $dateStart and $dateEnd";
        }
    }

    /**
     * Validate integer
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testInteger($value, $msg=null){
        if(intval($value)!=$value || strlen(intval($value))!=strlen($value)){
            if($msg!==null) return $msg;
            return 'Input is not an integer.';
        }
    }

    /**
     * Validate price. 2 decimal points only
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testPrice($value, $msg=null){
        // 2 decimal
        if (!preg_match('/^[0-9]*\\.?[0-9]{0,2}$/', $value)){
            if($msg!==null) return $msg;
            return 'Input is not a valid price amount.';
        }
    }

    /**
     * Validate float value.
     *
     * @param string $value Value of data to be validated
     * @param int $decimal Number of Decimal points
     * @param string $msg Custom error message
     * @return string
     */
    public function testFloat($value, $decimal='', $msg=null){
        // any amount of decimal
        if (!preg_match('/^[0-9]*\\.?[0-9]{0,'.$decimal.'}$/', $value)){
            if($msg!==null) return $msg;
            return 'Input is not a valid float value.';
        }
    }

    /**
     * Validate digits.
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testDigit($value, $msg=null){
        if(!ctype_digit($value)){
            if($msg!==null) return $msg;
            return 'Input is not a digit.';
        }
    }

    /**
     * Validate Alpha numeric values.
     *
     * Input string can only consist of only Letters or Digits.
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testAlphaNumeric($value, $msg=null){
        if(!ctype_alnum($value)){
            if($msg!==null) return $msg;
            return 'Input can only consist of letters or digits.';
        }
    }

    /**
     * Validate Alpha values.
     *
     * Input string can only consist of only Letters.
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testAlpha($value, $msg=null){
        if(!ctype_alpha($value)){
            if($msg!==null) return $msg;
            return 'Input can only consist of letters.';
        }
    }

    /**
     * Validate if string only consist of letters and spaces
     *
     * Input string can only consist of only Letters and spaces.
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testAlphaSpace($value, $msg=null){
        if(!ctype_alpha(str_replace(' ','',$value))){
            if($msg!==null) return $msg;
            return 'Input can only consist of letters and spaces.';
        }
    }


    /**
     * Validate lowercase string.
     *
     * Input string can only be lowercase letters.
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testLowercase($value, $msg=null){
        if(!ctype_lower($value)){
            if($msg!==null) return $msg;
            return 'Input can only consists of lowercase letters.';
        }
    }

    /**
     * Validate uppercase string.
     *
     * Input string can only be uppercase letters.
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testUppercase($value, $msg=null){
        if(!ctype_upper($value)){
            if($msg!==null) return $msg;
            return 'Input can only consists of uppercase letters.';
        }
    }

    /**
     * Validate Not Empty. Input cannot be empty.
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testNotEmpty($value, $msg=null){
        if(empty($value)){
            if($msg!==null) return $msg;
            return 'Value cannot be empty!';
        }
    }

    /**
     * Validate Max length of a string.
     *
     * @param string $value Value of data to be validated
     * @param int $length Maximum length of the string
     * @param string $msg Custom error message
     * @return string
     */
    public function testMaxLength($value, $length=0, $msg=null){
        if(mb_strlen($value) > $length){
            if($msg!==null) return $msg;
            return "Input cannot be longer than the $length characters.";
        }
    }

    /**
     * Validate Minimum length of a string.
     *
     * @param string $value Value of data to be validated
     * @param int $length Minimum length of the string
     * @param string $msg Custom error message
     * @return string
     */
    public function testMinLength($value, $length=0, $msg=null){
        if(strlen($value) < $length){
            if($msg!==null) return $msg;
            return "Input cannot be shorter than the $length characters.";
        }
    }

    /**
     * Validate Not Null. Value cannot be null.
     *
     * @param string $value Value of data to be validated
     * @param string $msg Custom error message
     * @return string
     */
    public function testNotNull($value, $msg=null){
        if(is_null($value)){
            if($msg!==null) return $msg;
            return 'Value cannot be null.';
        }
    }

    /**
     * Validate Minimum value of a number.
     *
     * @param string $value Value of data to be validated
     * @param int $min Minimum value
     * @param string $msg Custom error message
     * @return string
     */
    public function testMin($value, $min, $msg=null){
        if( $value < $min){
            if($msg!==null) return $msg;
            return "Value cannot be less than $min";
        }
    }

    /**
     * Validate Maximum value of a number.
     *
     * @param string $value Value of data to be validated
     * @param int $max Maximum value
     * @param string $msg Custom error message
     * @return string
     */
    public function testMax($value, $max, $msg=null){
        if( $value > $max){
            if($msg!==null) return $msg;
            return "Value cannot be more than $max";
        }
    }

    /**
     * Validate if a value is Between 2 values (inclusive)
     *
     * @param string $value Value of data to be validated
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @param string $msg Custom error message
     * @return string
     */
    public function testBetweenInclusive($value, $min, $max, $msg=null){
        if( $value < $min || $value > $max ){
            if($msg!==null) return $msg;
            return "Value must be between $min and $max inclusively.";
        }
    }

    /**
     * Validate if a value is Between 2 values
     *
     * @param string $value Value of data to be validated
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @param string $msg Custom error message
     * @return string
     */
    public function testBetween($value, $min, $max, $msg=null){
        if( $value < $min+1 || $value > $max-1 ){
            if($msg!==null) return $msg;
            return "Value must be between $min and $max.";
        }
    }

    /**
     * Validate if a value is greater than a number
     *
     * @param string $value Value of data to be validated
     * @param int $number Number to be compared
     * @param string $msg Custom error message
     * @return string
     */
    public function testGreaterThan($value, $number, $msg=null){
        if( !($value > $number)){
            if($msg!==null) return $msg;
            return "Value must be greater than $number.";
        }
    }

    /**
     * Validate if a value is greater than or equal to a number
     *
     * @param string $value Value of data to be validated
     * @param int $number Number to be compared
     * @param string $msg Custom error message
     * @return string
     */
    public function testGreaterThanOrEqual($value, $number, $msg=null){
        if( !($value >= $number)){
            if($msg!==null) return $msg;
            return "Value must be greater than or equal to $number.";
        }
    }

    /**
     * Validate if a value is less than a number
     *
     * @param string $value Value of data to be validated
     * @param int $number Number to be compared
     * @param string $msg Custom error message
     * @return string
     */
    public function testLessThan($value, $number, $msg=null){
        if( !($value < $number)){
            if($msg!==null) return $msg;
            return "Value must be less than $number.";
        }
    }

    /**
     * Validate if a value is less than or equal to a number
     *
     * @param string $value Value of data to be validated
     * @param int $number Number to be compared
     * @param string $msg Custom error message
     * @return string
     */
    public function testLessThanOrEqual($value, $number, $msg=null){
        if( !($value <= $number)){
            if($msg!==null) return $msg;
            return "Value must be less than $number.";
        }
    }

    /**
     * Validate if a value is equal to a number
     *
     * @param string $value Value of data to be validated
     * @param int $equalValue Number to be compared
     * @param string $msg Custom error message
     * @return string
     */
    public function testEqual($value, $equalValue, $msg=null){
        if(!($value==$equalValue && strlen($value)==strlen($equalValue))){
            if($msg!==null) return $msg;
            return 'Both values must be the same.';
        }
    }

    /**
     * Validate if a value is Not equal to a number
     *
     * @param string $value Value of data to be validated
     * @param int $equalValue Number to be compared
     * @param string $msg Custom error message
     * @return string
     */
    public function testNotEqual($value, $equalValue, $msg=null){
        if( $value==$equalValue && strlen($value)==strlen($equalValue) ){
            if($msg!==null) return $msg;
            return 'Both values must be different.';
        }
    }

   /**
    * Validate if value Exists in database
    *
    * @param string $value Value of data to be validated
    * @param string $table Name of the table in DB
    * @param string $field Name of field you want to check
    * @return string
    */
    public function testDbExist($value, $table, $field, $msg=null) {
        $result = Doo::db()->fetchRow("SELECT COUNT($field) AS count FROM " . $table . ' WHERE '.$field.' = ? LIMIT 1', array($value));
        if ((!isset($result['count'])) || ($result['count'] < 1)) {
            if($msg!==null) return $msg;
            return 'Value does not exist in database.';
        }
    }

   /**
    * Validate if value does Not Exist in database
    *
    * @param string $value Value of data to be validated
    * @param string $table Name of the table in DB
    * @param string $field Name of field you want to check
    * @return string
    */
    public function testDbNotExist($value, $table, $field, $msg=null) {
        $result = Doo::db()->fetchRow("SELECT COUNT($field) AS count FROM " . $table . ' WHERE '.$field.' = ? LIMIT 1', array($value));
        if ((isset($result['count'])) && ($result['count'] > 0)) {
            if($msg!==null) return $msg;
            return 'Same value exists in database.';
        }
    }



    /**
     * Validate if a value is in a list of values
     *
     * @param string $value Value of data to be validated
     * @param int $equalValue List of values to be checked
     * @param string $msg Custom error message
     * @return string
     */
    public function testInList($value, $valueList, $msg=null){
        if(!(in_array($value, $valueList))){
            if($msg!==null) return $msg;
            return 'Unmatched value.';
        }
    }

    /**
     * Validate if a value is NOT in a list of values
     *
     * @param string $value Value of data to be validated
     * @param int $equalValue List of values to be checked
     * @param string $msg Custom error message
     * @return string
     */
    public function testNotInList($value, $valueList, $msg=null){
        if(in_array($value, $valueList)){
            if($msg!==null) return $msg;
            return 'Unmatched value.';
        }
    }

	/**
	* Validate field if it is equal with some other field from $_GET or $_POST method
	* This method is used for validating form
	*
	* @param string $value Value of data to be validated
	* @param string $method Method (get or post), default $_POST
	* @param string $field Name of field that you want to check
	* @return string
	*/

	public function testEqualAs($value, $method, $field, $msg=null) {
		if ($method == "get") {
		  $method = $_GET;
		} else if ($method == "post") {
		  $method = $_POST;
		} else {
		  $method = $_POST;
		}
		if (!isset($method[$field]) || $value != $method[$field]) {
		    if($msg!==null) return $msg;
            return 'Value '.$value.' is not equal with "'.$field.'".';
		}
	}

}
