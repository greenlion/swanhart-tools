<?php
/**
 * DooController class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * Base class of all controller
 *
 * <p>Provides a few shorthand methods to access commonly used component during development. e.g. DooLoader, DooLog, DooSqlMagic.</p>
 *
 * <p>Parameter lists and extension type defined in routes configuration can be accessed through <b>$this->params</b> and <b>$this->extension</b></p>
 *
 * <p>If a client sends PUT request to your controller, you can retrieve the values sent through <b>$this->puts</b></p>
 *
 * <p>GET and POST variables can still be accessed via php $_GET and $_POST. They are not handled/process by Doo framework.</p>
 *
 * <p>Auto routing can be denied from a Controller by setting <b>$autoroute = false</b></p>
 *
 * Therefore, the following class properties & methods is reserved and should not be used in your Controller class.
 * <code>
 * $params
 * $puts
 * $extension
 * $autoroute
 * $vdata
 * $renderMethod
 * init_put_vars()
 * load()
 * language()
 * accept_type()
 * render()
 * renderc()
 * setContentType()
 * is_SSL()
 * view()
 * db()
 * cache()
 * acl()
 * beforeRun()
 * isAjax()
 * renderLayout()
 * clientIP()
 * saveRendered()
 * saveRenderedC()
 * toXML()
 * toJSON()
 * viewRenderAutomation()
 * getKeyParam()
 * afterRun()
 * </code>
 *
 * You still have a lot of freedom to name your methods and properties other than names mentioned.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooController.php 1000 2009-07-7 18:27:22
 * @package doo.controller
 * @since 1.0
 */
class DooController {
    /**
     * Associative array of the parameter list found matched in a URI route.
     * @var array
     */
    public $params;

    /**
     * Associative array of the PUT values sent by client.
     * @var array
     */
    public $puts;

    /**
     * Extension name (.html, .json, .xml ,...) found in the URI. Routes can be specified with a string or an array as matching extensions
     * @var string
     */
    public $extension;

    /**
     * Deny or allow auto routing access to a Controller. By default auto routes are allowed in a controller.
     * @var bool
     */
    public $autoroute = TRUE;

	/**
	 * Data to be pass from controller to view to be rendered
	 * @var mixed
	 */
    public $vdata;

	/**
	 * Enable auto render of view at the end of a controller -> method request
	 * @var bool
	 */
	public $autorender = FALSE;

	/**
	 * Render method for auto render. You can use 'renderc' & 'render' or your own method in the controller.
	 * @var string Default is renderc
	 */
	public $renderMethod = 'renderc';

    protected $_load;
    protected $_view;

    /**
     * Use initPutVars() instead
     * @deprecated deprecated since version 1.3
     */
    public function init_put_vars(){
        parse_str(file_get_contents('php://input'), $this->puts);
    }
    
    /**
     * Set PUT request variables in a controller. This method is to be used by the main web app class.
     */
    public function initPutVars(){
        parse_str(file_get_contents('php://input'), $this->puts);
    }    

    /**
     * The loader singleton, auto create if the singleton has not been created yet.
     * @return DooLoader
     */
    public function load(){
        if($this->_load==NULL){
            Doo::loadCore('uri/DooLoader');
            $this->_load = new DooLoader;
        }

        return $this->_load;
    }

    /**
     * Returns the database singleton, shorthand to Doo::db()
     * @return DooSqlMagic
     */
    public function db(){
        return Doo::db();
    }

    /**
     * Returns the Acl singleton, shorthand to Doo::acl()
     * @return DooAcl
     */
    public function acl(){
        return Doo::acl();
    }

    /**
     * This will be called before the actual action is executed
     */
    public function beforeRun($resource, $action){}

    /**
     * Returns the cache singleton, shorthand to Doo::cache()
     * @return DooFileCache|DooFrontCache|DooApcCache|DooMemCache|DooXCache|DooEAcceleratorCache
     */
    public function cache($cacheType='file'){
        return Doo::cache($cacheType);
    }

    /**
     * Writes the generated output produced by render() to file.
     * @param string $path Path to save the generated output.
     * @param string $templatefile Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @return string|false The file name of the rendered output saved (html).
     */
	public function saveRendered($path, $templatefile, $data=NULL) {
		return $this->view()->saveRendered($path, $templatefile, $data);
	}

    /**
     * Writes the generated output produced by renderc() to file.
     * @param string $path Path to save the generated output.
     * @param string $templatefile Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param bool $enableControllerAccess Enable the view scripts to access the controller property and methods.
     * @param bool $includeTagClass If true, DooView will determine which Template tag class to include. Else, no files will be loaded
     * @return string|false The file name of the rendered output saved (html).
     */
    public function saveRenderedC($path, $templatefile, $data=NULL, $enableControllerAccess=False, $includeTagClass=True){
        if($enableControllerAccess===true){
            return $this->view()->saveRenderedC($file, $data, $this, $includeTagClass);
        }else{
            return $this->view()->saveRenderedC($file, $data, null, $includeTagClass);
        }
	}

    /**
     * The view singleton, auto create if the singleton has not been created yet.
     * @return DooView|DooViewBasic
     */
    public function view(){
        if($this->_view==NULL){
			$engine = Doo::conf()->TEMPLATE_ENGINE;
            Doo::loadCore('view/' . $engine);
            $this->_view = new $engine;
        }

        return $this->_view;
    }

    /**
     * Short hand for $this->view()->render() Renders the view file.
     *
     * @param string $file Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param bool $process If TRUE, checks the template's last modified time against the compiled version. Regenerates if template is newer.
     * @param bool $forceCompile Ignores last modified time checking and force compile the template everytime it is visited.
     */
    public function render($file, $data=NULL, $process=NULL, $forceCompile=false){
        $this->view()->render($file, $data, $process, $forceCompile);
    }

    /**
     * Short hand for $this->view()->renderc() Renders the view file(php) located in viewc.
     *
     * @param string $file Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the php template.
     * @param bool $enableControllerAccess Enable the view scripts to access the controller property and methods.
     * @param bool $includeTagClass If true, DooView will determine which Template tag class to include. Else, no files will be loaded
     */
    public function renderc($file, $data=NULL, $enableControllerAccess=False, $includeTagClass=True){
        if($enableControllerAccess===true){
            $this->view()->renderc($file, $data, $this, $includeTagClass);
        }else{
            $this->view()->renderc($file, $data, null, $includeTagClass);
        }
    }

    /**
     * Get the client accept language from the header
     *
     * @param bool $countryCode to return the language code along with country code
     * @return string The language code. eg. <b>en</b> or <b>en-US</b>
     */
    public function language($countryCode=FALSE){
        $langcode = (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $langcode = (!empty($langcode)) ? explode(';', $langcode) : $langcode;
        $langcode = (!empty($langcode[0])) ? explode(',', $langcode[0]) : $langcode;
        if(!$countryCode)
            $langcode = (!empty($langcode[0])) ? explode('-', $langcode[0]) : $langcode;
        return $langcode[0];
    }

    /**
     * Use acceptType() instead
     * @deprecated deprecated since version 1.3
     * @return string Client accept type
     */
    public function accept_type(){
        return $this->acceptType();
    }

    /**
     * Get the client specified accept type from the header sent
     *
     * <p>Instead of appending a extension name like '.json' to a URL,
     * clients can use 'Accept: application/json' for RESTful APIs.</p>
     * @return string Client accept type
     */
    public function acceptType(){
        $type = array(
            '*/*'=>'*',
            'html'=>'text/html,application/xhtml+xml',
            'xml'=>'application/xml,text/xml,application/x-xml',
            'json'=>'application/json,text/x-json,application/jsonrequest,text/json',
            'js'=>'text/javascript,application/javascript,application/x-javascript',
            'css'=>'text/css',
            'rss'=>'application/rss+xml',
            'yaml'=>'application/x-yaml,text/yaml',
            'atom'=>'application/atom+xml',
            'pdf'=>'application/pdf',
            'text'=>'text/plain',
            'png'=>'image/png',
            'jpg'=>'image/jpg,image/jpeg,image/pjpeg',
            'gif'=>'image/gif',
            'form'=>'multipart/form-data',
            'url-form'=>'application/x-www-form-urlencoded',
            'csv'=>'text/csv'
        );

        $matches = array();

        //search and match, add 1 priority to the key if found matched
        foreach($type as $k=>$v){
            if(strpos($v,',')!==FALSE){
                $tv = explode(',', $v);
                foreach($tv as $k2=>$v2){
                    if (stristr($_SERVER["HTTP_ACCEPT"], $v2)){
                        if(isset($matches[$k]))
                            $matches[$k] = $matches[$k]+1;
                        else
                            $matches[$k]=1;
                    }
                }
            }else{
                if (stristr($_SERVER["HTTP_ACCEPT"], $v)){
                    if(isset($matches[$k]))
                        $matches[$k] = $matches[$k]+1;
                    else
                        $matches[$k]=1;
                }
            }
        }

        if(sizeof($matches)<1)
            return NULL;

        //sort by the highest priority, keep the key, return the highest
        arsort($matches);

        foreach ($matches as $k=>$v){
            return ($k==='*/*')?'html':$k;
        }
    }

    /**
     * Sent a content type header
     *
     * <p>This can be used with your REST api if you allow clients to retrieve result format
     * by sending a <b>Accept type header</b> in their requests. Alternatively, extension names can be
     * used at the end of an URI such as <b>.json</b> and <b>.xml</b></p>
     *
     * <p>NOTE: This method should be used before echoing out your results.
     * Use accept_type() or $extension to determined the desirable format the client wanted to accept.</p>
     *
     * @param string $type Content type of the result. eg. text, xml, json, rss, atom
     * @param string $charset Charset of the result content. Default utf-8.
     */
    public function setContentType($type, $charset='utf-8'){
        if(headers_sent())return;

        $extensions = array('html'=>'text/html',
                            'xml'=>'application/xml',
                            'json'=>'application/json',
                            'js'=>'application/javascript',
                            'css'=>'text/css',
                            'rss'=>'application/rss+xml',
                            'yaml'=>'text/yaml',
                            'atom'=>'application/atom+xml',
                            'pdf'=>'application/pdf',
                            'text'=>'text/plain',
                            'png'=>'image/png',
                            'jpg'=>'image/jpeg',
                            'gif'=>'image/gif',
                            'csv'=>'text/csv'
						);
        if(isset($extensions[$type]))
            header("Content-Type: {$extensions[$type]}; charset=$charset");
    }

    /**
     * Get client's IP
     * @return string
     */
    public function clientIP(){
        if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            return getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            return getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            return getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * This will be called if the action method returns null or success status(200 to 299 not including 204) after the actual action is executed
     * @param mixed $routeResult The result returned by an action
     */    
	public function afterRun($routeResult) {
		if($this->autorender===true && ($routeResult===null || ($routeResult>=200 && $routeResult<300 && $routeResult!=204))){	
            $this->viewRenderAutomation();
		}
	}
    
    /**
     * Retrieve value of a key from URI accessed from an auto route.
     * Example with a controller named UserController and a method named listAll(): 
     * <code>
     * //URI is http://localhost/user/list-all/id/11
     * $this->getKeyParam('id');   //returns 11
     * </code>
     * 
     * @param string $key
     * @return mixed
     */
    public function getKeyParam($key){
        if(!empty($this->params) && in_array($key, $this->params)){
            $valueIndex = array_search($key, $this->params) + 1;
            if($valueIndex<sizeof($this->params))
                return $this->params[$valueIndex];
        }
    }
    
    /**
     * Controls the automated view rendering process.
     */
	public function viewRenderAutomation(){
		if(is_string(Doo::conf()->AUTO_VIEW_RENDER_PATH)){
			$path = Doo::conf()->AUTO_VIEW_RENDER_PATH;
			$path = str_replace(':', '@', substr($path, 1));
			$this->{$this->renderMethod}($path, $this->vdata);
		}else{
            if(isset(Doo::conf()->AUTO_VIEW_RENDER_PATH))
                $this->{$this->renderMethod}(strtolower(Doo::conf()->AUTO_VIEW_RENDER_PATH[0]) .'/'. strtolower(Doo::conf()->AUTO_VIEW_RENDER_PATH[1]), $this->vdata);
            else
                $this->{$this->renderMethod}('index', $this->vdata);                
		}
	}

    /**
     * Check if the request is an AJAX request usually sent with JS library such as JQuery/YUI/MooTools
     * @return bool
     */
    public function isAjax(){
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Check if the connection is a SSL connection
     * @return bool determined if it is a SSL connection
     */
    public function isSSL(){
        if(!isset($_SERVER['HTTPS']))
            return FALSE;

        //Apache
        if($_SERVER['HTTPS'] === 1) {
            return TRUE;
        }
        //IIS
        elseif ($_SERVER['HTTPS'] === 'on') {
            return TRUE;
        }
        //other servers
        elseif ($_SERVER['SERVER_PORT'] == 443){
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * Use isSSL() instead
     * @deprecated deprecated since version 1.3
     */
    public function is_SSL(){
        return $this->isSSL();
    }

    /**
     * Convert DB result into XML string for RESTful api.
     * <code>
     * public function listUser(){
     *     $user = new User;
     *     $rs = $user->find();
     *     $this->toXML($rs, true);
     * }
     * </code>
     * @param mixed $result Result of a DB query. eg. $user->find();
     * @param bool $output Output the result automatically.
     * @param bool $setXMLContentType Set content type.
     * @param string $encoding Encoding of the result content. Default utf-8.
     * @return string XML string
     */
    public function toXML($result, $output=false, $setXMLContentType=false, $encoding='utf-8'){
        $str = '<?xml version="1.0" encoding="utf-8"?><result>';
        foreach($result as $kk=>$vv){
            $cls = get_class($vv);
            $str .= '<' . $cls . '>';
            foreach($vv as $k=>$v){
                if($k!='_table' && $k!='_fields' && $k!='_primarykey'){
                    if(is_array($v)){
                        //print_r($v);
                        //exit;
                        $str .= '<' . $k . '>';
                        foreach($v as $v0){
                            $str .= '<data>';
                            foreach($v0 as $k1=>$v1){
                                if($k1!='_table' && $k1!='_fields' && $k1!='_primarykey'){
                                    if(is_array($v1)){
                                        $str .= '<' . $k1 . '>';
                                        foreach($v1 as $v2){
                                            $str .= '<data>';
                                            foreach($v2 as $k3=>$v3){
                                                if($k3!='_table' && $k3!='_fields' && $k3!='_primarykey'){
                                                    $str .= '<'. $k3 . '><![CDATA[' . $v3 . ']]></'. $k3 . '>';
                                                }
                                            }
                                            $str .= '</data>';
                                        }
                                        $str .= '</' . $k1 . '>';
                                    }else{
                                        $str .= '<'. $k1 . '><![CDATA[' . $v1 . ']]></'. $k1 . '>';
                                    }
                                }
                            }
                            $str .= '</data>';
                        }
                        $str .= '</' . $k . '>';

                    }else{
                        $str .= '<'. $k . '>' . $v . '</'. $k . '>';
                    }
                }
            }
            $str .= '</' . $cls . '>';
        }
        $str .= '</result>';
        if($setXMLContentType===true)
            $this->setContentType('xml', $encoding);
        if($output===true)
            echo $str;
        return $str;
    }

    /**
     * Convert DB result into JSON string for RESTful api.
     * <code>
     * public function listUser(){
     *     $user = new User;
     *     $rs = $user->find();
     *     $this->toJSON($rs, true);
     * }
     * </code>
     * @param mixed $result Result of a DB query. eg. $user->find();
     * @param bool $output Output the result automatically.
     * @param bool $removeNullField Remove fields with null value from JSON string.
     * @param array $exceptField Remove fields that are null except the ones in this list.
     * @param array $mustRemoveFieldList Remove fields in this list.
     * @param bool $setJSONContentType Set content type.
     * @param string $encoding Encoding of the result content. Default utf-8.
     * @return string JSON string
     */
    public function toJSON($result, $output=false, $removeNullField=false, $exceptField=null, $mustRemoveFieldList=null, $setJSONContentType=true, $encoding='utf-8'){
        $rs = preg_replace(array('/\,\"\_table\"\:\".*\"/U', '/\,\"\_primarykey\"\:\".*\"/U', '/\,\"\_fields\"\:\[\".*\"\]/U'), '', json_encode($result));
        if($removeNullField){
            if($exceptField===null)
                $rs = preg_replace(array('/\,\"[^\"]+\"\:null/U', '/\{\"[^\"]+\"\:null\,/U'), array('','{'), $rs);
            else{
                $funca1 =  create_function('$matches',
                            'if(in_array($matches[1], array(\''. implode("','",$exceptField) .'\'))===false){
                                return "";
                            }
                            return $matches[0];');

                $funca2 =  create_function('$matches',
                            'if(in_array($matches[1], array(\''. implode("','",$exceptField) .'\'))===false){
                                return "{";
                            }
                            return $matches[0];');

                $rs = preg_replace_callback('/\,\"([^\"]+)\"\:null/U', $funca1, $rs);
                $rs = preg_replace_callback('/\{\"([^\"]+)\"\:null\,/U', $funca2, $rs);
            }
        }

        //remove fields in this array
        if($mustRemoveFieldList!==null){
            $funcb1 =  create_function('$matches',
                        'if(in_array($matches[1], array(\''. implode("','",$mustRemoveFieldList) .'\'))){
                            return "";
                        }
                        return $matches[0];');

            $funcb2 =  create_function('$matches',
                        'if(in_array($matches[1], array(\''. implode("','",$mustRemoveFieldList) .'\'))){
                            return "{";
                        }
                        return $matches[0];');
            
            $rs = preg_replace_callback(array('/\,\"([^\"]+)\"\:\".*\"/U', '/\,\"([^\"]+)\"\:\{.*\}/U', '/\,\"([^\"]+)\"\:\[.*\]/U', '/\,\"([^\"]+)\"\:([false|true|0-9|\.\-|null]+)/'), $funcb1, $rs);

            $rs = preg_replace_callback(array('/\{\"([^\"]+)\"\:\".*\"\,/U','/\{\"([^\"]+)\"\:\{.*\}\,/U'), $funcb2, $rs);

            preg_match('/(.*)(\[\{.*)\"('. implode('|',$mustRemoveFieldList) .')\"\:\[(.*)/', $rs, $m);
            
            if($m){
                if( $pos = strpos($m[4], '"}],"') ){
                    if($pos2 = strpos($m[4], '"}]},{')){
                        $d = substr($m[4], $pos2+5);
                        if(substr($m[2],-1)==','){
                            $m[2] = substr_replace($m[2], '},', -1);
                        }                
                    }
                    else if(strpos($m[4], ']},{')!==false){
                        $d = substr($m[4], strpos($m[4], ']},{')+3);  
                        if(substr($m[2],-1)==','){
                            $m[2] = substr_replace($m[2], '},', -1);
                        }
                    }
                    else if(strpos($m[4], '],"')===0){
                        $d = substr($m[4], strpos($m[4], '],"')+2);  
                    }                    
                    else if(strpos($m[4], '}],"')!==false){
                        $d = substr($m[4], strpos($m[4], '],"')+2);  
                    }
                    else{
                        $d = substr($m[4], $pos+4);
                    }
                }
                else{
                    $rs = preg_replace('/(\[\{.*)\"('. implode('|',$mustRemoveFieldList) .')\"\:\[.*\]\}(\,)?/U', '$1}', $rs);
                    $rs = preg_replace('/(\".*\"\:\".*\")\,\}(\,)?/U', '$1}$2', $rs);
                }

                if(isset($d)){
                    $rs = $m[1].$m[2].$d;
                }
            }
        }
        
        if($output===true){
			if($setJSONContentType===true)
				$this->setContentType('json', $encoding);
            echo $rs;
		}
        return $rs;
    }

	public function  __call($name,  $arguments) {
		if ($name == 'renderLayout') {
			throw new Exception('renderLayout is no longer supported by DooController. Please use $this->view()->renderLayout instead');
		}
	}

}
