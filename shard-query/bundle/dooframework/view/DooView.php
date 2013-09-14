<?php
/**
 * DooView class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooView is a class for working with the "view" portion of the model-view-controller pattern.
 *
 * <p>That is, it exists to help keep the view script separate from the model and controller scripts.
 * It provides a system of helpers, output filters, and variable escaping which is known as Template tags where you can defined them in <i>SITE_PATH/protected/plugin/TemplateTag.php</i>.</p>
 *
 * <p>DooView is a compiling template engine. It parses the HTML templates and convert them into PHP scripts which will then be included and rendered.
 * In production mode, DooView will not processed the template but include the compiled PHP file instead. Otherwise,
 * it will compare the last modified time of both template and compiled file, then regenerate the compiled file if the template file is newer.
 * Compiled files are located at <i>SITE_PATH/protected/viewc</i> while HTML templates should be placed at <i>SITE_PATH/protected/view</i></p>
 *
 * <p>Loops, variables, function calls with parameters, include files are supported in DooView. DooView only allows a template to use functions defined in <i>TemplateTag.php</i>.
 * The first parameter needs to be the variable passed in from the template file and it should return a value to be printed out. Functions are case insensitive.
 * </p>
 * <code>
 * //register functions to be used with your template files
 * $template_tags = array('upper', 'toprice');
 *
 * function upper($str){
 *     return strtoupper($str);
 * }
 * function toprice($str, $currency='$'){
 *     return $currency . sprintf("%.2f", $str);
 * }
 *
 * //usage in template
 * Welcome, Mr. {{upper(username)}}
 * Your account has: {{TOPRICE(amount, 'RM')}}
 * </code>
 *
 * <p>Included files in template are automatically generated if it doesn't exist. To include other template files in your template, use:</p>
 * <code>
 * <!-- include "header" -->
 * //Or you can use a variable in the include tag too. say, $data['file']='header'
 * <!-- include "{{file}}" -->
 * </code>
 *
 * <p>Since 1.1, If statement supported. Function used in IF can be controlled.</p>
 * <code>
 * <!-- if -->
 * <!-- elseif -->
 * <!-- else -->
 * <!-- endif -->
 *
 * //Examples
 * <!-- if {{MyVar}}==100 -->
 *
 * //With function
 * <!-- if {{checkVar(MyVar)}} -->
 *
 * //&& || and other operators
 * <!-- if {{isGender(gender, 'female')}}==true && {{age}}>=14 -->
 * <!-- if {{isAdmin(user)}}==true && ({{age}}>=14 || {{age}}<54) -->
 *
 * //Write in one line
 * <!-- if {{isAdmin(username)}} --><h2>Success!</h2><!-- endif -->
 * </code>
 *
 * <p>Since 1.1, Partial caching in view:</p>
 * <code>
 * <!-- cache('mydata', 60) --> 
 * <ul>{{userList}}</ul>
 * <!-- endcache -->
 * </code>
 * <p>DooView can be called from the controller: </p>
 * <code>
 * class SampleController extends DooController{
 *      public viewme(){
 *          $data['myname'] = 'My name is Doo';
 *          $this->view()->render('viewmepage', $data);
 *          //viewmepage instead of viewmepage.html
 *          //files in subfolder, use: render('foldername/viewmepage')
 *      }
 * }
 * </code>
 *
 * Since 1.3, you can have comments block in the template which will not be output/processed unless
 * SHOW_TEMPLATE_COMMENT is True in common.conf.php
 * <code>
 * <!-- comment -->
 *    this is a comment
 *    testing {{debug(myVar)}}
 *    <!-- include 'debugger' -->
 * <!-- endcomment -->
 * </code>
 *
 * <p>You can use <b>native PHP</b> as view templates in DooPHP. Use DooView::renderc() instead of render.</p>
 * <p>In your Controller:</p>
 * <code>
 * $data['phone'] = 012432456;
 * $this->view()->abc = 'ABCDE';
 *
 * //pass in true to enable access to controller if you need it.
 * $this->renderc('example', $data, true);
 * </code>
 *
 * <p>In your view scrips located in <b>SITE_PATH/protected/viewc/</b></p>
 * <code>
 * echo $this->data['phone'];     //012432456
 * echo $this->abc;             //ABC
 *
 * //call controller methods if enabled.
 * $this->thisIsFromController();
 * echo $this->thisIsControllerProperty;
 * </code>
 *
 * <p>To include a template script in viewc folder</p>
 * <code>
 * $this->inc('this_is_another_view_php');
 * </code>
 *
 * To write variable's value as static strings to the template, use a plus sign in front of the variable
 * <code>
 * //in controller
 * $this->data['siteurl'] = 'www.doophp.com';
 * $this->render('template', $this->data);
 *
 * //in template
 * <p>{{+siteurl}}</p>
 *
 * //The compiled template will look like:
 * <p>www.doophp.com</p>
 *
 * //Used with function call
 * {{+time(true)}}
 *
 * //compiled source
 * 1262115252
 * </code>
 *
 * Short tags with native PHP is allowed
 * <code>
 * <? echo $data; ?>
 * <?=$data;?>
 *
 * //The code above will be converted to
 * <?php echo $data; ?>
 * </code>
 *
 * To write variable's value as static string (using native PHP), use a plus sign
 * <code>
 * //Example:
 * <p><?+$data;?></p>
 *
 * //result:
 * <p>www.doophp.com</p>
 * </code>
 * 
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooView.php 1000 2009-07-7 18:27:22
 * @package doo.view
 * @since 1.0
 */
class DooView {
    public $controller;
    public $data;
    protected $tags;
    protected $mainRenderFolder;
    protected $tagClassName;
    protected $tagModuleName;

    /**
     * Determine which class to use as template tag.
     *
     * If $module is equal to '/', the main app's template tag class will be used.
     *
     * @param string $class Template tag class name
     * @param string $module Folder name of the module. Define this module name if the tag class is from another module.
     */
    public function setTagClass($class, $module=Null){
        $this->tagClassName = $class;
        $this->tagModuleName = $module;
    }

    /**
     * Includes the native PHP template file to be output.
     * 
     * @param string $file PHP template file name without extension .php
     * @param array $data Associative array of the data to be used in the template view.
     * @param object $controller The controller object, pass this in so that in views you can access the controller.
     * @param bool $includeTagClass If true, DooView will determine which Template tag class to include. Else, no files will be loaded
     */
    public function renderc($file, $data=NULL, $controller=NULL, $includeTagClass=TRUE){
        $this->data = $data;
        $this->controller = $controller;
        if($includeTagClass===TRUE)
            $this->loadTagClass();
        include Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "/viewc/$file.php";
    }

    /**
     * Include template view files
     * @param string $file File name without extension (.php)
     */
    public function inc($file){
        include Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "viewc/$file.php";
    }

    public function  __call($name,  $arguments) {
        if($this->controller!=NULL){
            return call_user_func_array(array(&$this->controller, $name), $arguments);
        }
    }

    public function  __get($name) {
        if($this->controller!=NULL){
            return $this->controller->{$name};
        }
    }

    /**
     * Writes the generated output produced by render() to file.
     * @param string $path Path to save the generated output.
     * @param string $templatefile Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @return string|false The file name of the rendered output saved (html).
     */
    public function saveRendered($path, $templatefile, $data=NULL){
        ob_start();
        $this->render($templatefile, $data, null, true);
        $data = ob_get_contents();
        ob_end_clean();
        if(file_put_contents($path, $data)>0){
            $filename = explode('/',$path);
            return $filename[sizeof($filename)-1];
        }
        return false;
    }

    /**
     * Writes the generated output produced by renderc() to file.
     * @param string $path Path to save the generated output.
     * @param string $templatefile Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param object $controller The controller object, pass this in so that in views you can access the controller.
     * @param bool $includeTagClass If true, DooView will determine which Template tag class to include. Else, no files will be loaded
     * @return string|false The file name of the rendered output saved (html).
     */
    public function saveRenderedC($path, $templatefile, $data=NULL, $controller=NULL, $includeTagClass=TRUE){
        ob_start();
        $this->renderc($templatefile, $data, $controller, $includeTagClass);
        $data = ob_get_contents();
        ob_end_clean();
        if(file_put_contents($path, $data)>0){
            $filename = explode('/',$path);
            return $filename[sizeof($filename)-1];
        }
        return false;
    }
    
    /**
     * Renders the view file, generates compiled version of the view template if necessary
     * @param string $file Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param bool $process If TRUE, checks the template's last modified time against the compiled version. Regenerates if template is newer.
     * @param bool $forceCompile Ignores last modified time checking and force compile the template everytime it is visited.
     */
    public function render($file, $data=NULL, $process=NULL, $forceCompile=false){

        if(isset(Doo::conf()->TEMPLATE_COMPILE_ALWAYS) && Doo::conf()->TEMPLATE_COMPILE_ALWAYS==true){
            $process = $forceCompile = true;
        }
        //if process not set, then check the app mode, if production mode, skip the process(false) and just include the compiled files
        else if($process===NULL){
            $process = (Doo::conf()->APP_MODE!='prod');
        }

        //just include the compiled file if process is false
        if($process!=true){
            //includes user defined template tags for template use
            $this->loadTagClass();
            include Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "viewc/$file.php";
        }
        else{
            $cfilename = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "viewc/$file.php";
            $vfilename = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "view/$file.html";
            
            //if file exist and is not older than the html template file, include the compiled php instead and exit the function
            if(!$forceCompile){
                if(file_exists($cfilename)){
                    if(filemtime($cfilename)>=filemtime($vfilename)){
                        $this->setTags();
                        include $cfilename;
                        return;
                    }
                }
            }
            $this->data = $data;
            $this->compile($file, $vfilename, $cfilename);
            include $cfilename;
        }
    }

    /**
     * Renders layouts
     * @param string $layoutName Name of the layout
     * @param string $viewFile View file name (without extension name .html)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param bool $process If TRUE, checks the template's last modified time against the compiled version. Regenerates if template is newer.
     * @param bool $forceCompile Ignores last modified time checking and force compile the template everytime it is visited.
     */
    public function renderLayout($layoutName, $viewFile, $data=NULL, $process=NULL, $forceCompile=false) {

        $compiledViewFile = $layoutName . '/' . $viewFile;

        if(isset(Doo::conf()->TEMPLATE_COMPILE_ALWAYS) && Doo::conf()->TEMPLATE_COMPILE_ALWAYS==true){
            $process = $forceCompile = true;
        }
        //if process not set, then check the app mode, if production mode, skip the process(false) and just include the compiled files
        else if($process===NULL){
            $process = (Doo::conf()->APP_MODE!='prod');
        }

        //just include the compiled file if process is false
        if($process!=true){
            //includes user defined template tags for template use
            $this->loadTagClass();
            include Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "viewc/$compiledViewFile.php";
        }
        else{
            $lfilename = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "layout/$layoutName.html";
            $vfilename = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "view/$viewFile.html";
            $cfilename = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "viewc/$compiledViewFile.php";

            //if file exist and is not older than the html template file AND layout file, include the compiled php instead and exit the function
            if(!$forceCompile){
                if(file_exists($cfilename)){
                    if(filemtime($cfilename)>=filemtime($vfilename) && filemtime($cfilename)>=filemtime($lfilename)){
                        $this->setTags();
                        include $cfilename;
                        return;
                    }
                }
            }
            $this->data = $data;
            $this->compileLayout($compiledViewFile, $lfilename, $vfilename, $cfilename);
            include $cfilename;
        }

    }

    /**
     * Contains the contents of view blocks used with layouts
     * @var array
     */
    private $viewBlocks = null;

    /**
     * Parses and compiled a view into a layout to fill in placeholders and
     * stores the resulting view file to then be processed as normal by DooView::compile
     * @param string $viewFile The original location of the view without extension .html
     * @param string $lfilename Full path to the layout file
     * @param string $vfilename Full path to the view to be merged into the layout
     * @param string $cfilename Full path of the compiled file to be saved 
     */
    protected function compileLayout($viewFile, $lfilename, $vfilename, $cfilename) {

        $layout = file_get_contents($lfilename);
        $view = file_get_contents($vfilename);

        // Identify the blocks within a view file
        // <!-- block:NAME -->CONTENT<!-- endblock -->
        $this->viewBlocks = array();
        // We use \s\S to get ANY character including newlines etc as '.' will not get new lines
        // Also use +? and *? so as to use non greedy matching
        preg_replace_callback('/<!-- block:([^\t\r\n]+?) -->([\s\S]*?)<!-- endblock -->/', array( &$this, 'storeViewBlock'), $view);
        $compiledLayoutView = preg_replace_callback('/<!-- placeholder:([^\t\r\n]+?) -->([\s\S]*?)<!-- endplaceholder -->/', array( &$this, 'replacePlaceholder'), $layout);


        $this->mainRenderFolder = $viewFile;

        //--------------------------- Parsing -----------------------------
        //if no compiled file exist or compiled file is older, generate new one
        $str = $this->compileTags($compiledLayoutView);

		Doo::loadHelper('DooFile');
		$fileManager = new DooFile(0777);
		$fileManager->create($cfilename, $str, 'w+');

    }


    /**
     * Parse and compile the template file. Templates generated in protected/viewc folder
     * @param string $file Template file name without extension .html
     * @param string $vfilename Full path of the template file
     * @param string $cfilename Full path of the compiled file to be saved
     */
    protected function compile($file, $vfilename, $cfilename){
        $this->mainRenderFolder = $file;

        //--------------------------- Parsing -----------------------------
        //if no compiled file exist or compiled file is older, generate new one
        $str = $this->compileTags(file_get_contents($vfilename));

		Doo::loadHelper('DooFile');
		$fileManager = new DooFile(0777);
		$fileManager->create($cfilename, $str, 'w+');
    }

    /**
     * Load the template class and returns the class name.
     * @return string Name of the class that is loaded.
     */
    public function loadTagClass(){
        /* if include tag class is not defined load TemplateTag for main app
         * else if render() is called from a module, load ModulenameTag */

		$tagFile = '';

        if( !isset($this->tagClassName) ){
            if( !isset(Doo::conf()->PROTECTED_FOLDER_ORI) ){
                $tagFile = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'plugin/TemplateTag.php';
                $tagcls = 'TemplateTag';
            }else{
                $tagcls = explode('/', Doo::conf()->PROTECTED_FOLDER);
                $tagcls = ucfirst($tagcls[sizeof($tagcls)-2]) . 'Tag';
                $tagFile = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'plugin/' . $tagcls .'.php';
            }
        }else{
            //load the main app's TemplateTag if module is '/'
            if($this->tagModuleName=='/'){
                $tagFile = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER_ORI . 'plugin/'. $this->tagClassName .'.php';
            }
            else if($this->tagModuleName===Null){
                $tagFile = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'plugin/'. $this->tagClassName .'.php';
            }
            else{
                if(isset(Doo::conf()->PROTECTED_FOLDER_ORI))
                    $tagFile = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER_ORI .'module/'. $this->tagModuleName . '/plugin/'. $this->tagClassName .'.php';
                else
                    $tagFile = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER .'module/'. $this->tagModuleName . '/plugin/'. $this->tagClassName .'.php';
            }
            $tagcls = $this->tagClassName;
        }
		if (file_exists($tagFile)) {
			require_once $tagFile;
			return $tagcls;
		} else {
			return false;
		}
    }

    private function setTags(){
        $tagcls = $this->loadTagClass();

		if ($tagcls === false) {
			$template_tags = array();
		} else {

			$tagMethod = get_class_methods($tagcls);

			if(!empty($tagMethod)){
				if( !empty(Doo::conf()->TEMPLATE_GLOBAL_TAGS) )
					$template_tags = array_merge(Doo::conf()->TEMPLATE_GLOBAL_TAGS, $tagMethod);
				else
					$template_tags = $tagMethod;

				$template_tags['_methods'] = $tagMethod;
				$template_tags['_class'] = $tagcls;
			}
			else if( !empty(Doo::conf()->TEMPLATE_GLOBAL_TAGS) ){
				$template_tags = Doo::conf()->TEMPLATE_GLOBAL_TAGS;
			}
			else{
				$template_tags = array();
			}

			foreach($template_tags as $k=>$v ){
				if(is_int($k))
					$template_tags[$k] = strtolower($v);
				else
					$template_tags[$k] = $v;
			}
		}
        Doo::conf()->add('TEMPLATE_TAGS', $template_tags);
        return $template_tags;
    }

    /**
     * Processes a string containing DooPHP Template tags and replaces them with the relevant PHP code required
     * @param string $str This is the html template markup from View files
     * @return string The PHP markedup version of the View file
     */
    private function compileTags($str) {

        //includes user defined template tags and checks for the tag and compile.
        if($this->tags===NULL){
            if(!isset(Doo::conf()->TEMPLATE_TAGS)){
                $this->tags = $this->setTags();
            }else{
                $this->tags = Doo::conf()->TEMPLATE_TAGS;
            }
        }

        if( isset(Doo::conf()->TEMPLATE_ALLOW_PHP) ){
            if( Doo::conf()->TEMPLATE_ALLOW_PHP === False ){
                $str = preg_replace('/<\?(php|\=|\+)?([\S|\s]*)\?>/Ui', '', $str);
            }
        }else{
            $str = preg_replace_callback('/<\?(php|\=|\+)?([\S|\s]*)\?>/Ui', array( &$this, 'convertPhpFunction'), $str);
        }

        //convert end loop
        $str = str_replace('<!-- endloop -->', '<?php endforeach; ?>', $str);

        //convert end for
        $str = str_replace('<!-- endfor -->', '<?php endforeach; ?>', $str);

        //convert variables to static string <p>{{+username}}</p> becomes <p>myusernamevalue</p>
        $str = preg_replace_callback('/{{\+([^ \t\r\n\(\)\.}]+)}}/', array( &$this, 'writeStaticVar'), $str);

        //convert variables {{username}}
        $str = preg_replace('/{{([^ \t\r\n\(\)\.}]+)}}/', "<?php echo \$data['$1']; ?>", $str);

		//convert non $data key variables {{$user.john}} {{$user.total.male}}
        $str = preg_replace_callback('/{{\$([^ \t\r\n\(\)\.}]+)\.([^ \t\r\n\(\)}]+)}}/', array( &$this, 'convertNonDataVarKey'), $str);

        //convert key variables {{user.john}} {{user.total.male}}
        $str = preg_replace_callback('/{{([^ \t\r\n\(\)\.}]+)\.([^ \t\r\n\(\)}]+)}}/', array( &$this, 'convertVarKey'), $str);

        //convert start loop <!--# loop users --> <!--# loop users' value --> <!--# loop users' value' value -->
        $str = preg_replace_callback('/<!-- loop ([^ \t\r\n\(\)}\']+).* -->/', array( &$this, 'convertLoop'), $str);

        //convert variable in loop {{user' value}}  {{user' value' value}}
        $str = preg_replace_callback('/{{([^ \t\r\n\(\)\.}\']+)([^\t\r\n\(\)}{]+)}}/', array( &$this, 'convertVarLoop'), $str);

		$str = preg_replace_callback('/{{([^ \t\r\n\(\)}]+?)\((.*?)\)}}/', array( &$this, 'convertFunction'), $str);

        //convert start of for loop
        $str = preg_replace_callback('/<!-- for ([^\t\r\n\(\)}{]+) -->/', array( &$this, 'convertFor'), $str);

        //convert else
        $str = str_replace('<!-- else -->', '<?php else: ?>', $str);

        //convert end if
        $str = str_replace('<!-- endif -->', '<?php endif; ?>', $str);

        // convert set
        $str = preg_replace_callback('/<!-- set ([^ \t\r\n\(\)\.}]+) as (.*?) -->/U', array( &$this, 'convertSet'), $str);

        //convert if and else if condition <!-- if expression --> <!-- elseif expression -->  only functions in template_tags are allowed
        $str = preg_replace_callback('/<!-- (if|elseif) ([^\t\r\n}]+) -->/U', array( &$this, 'convertCond'), $str);

        //convert else
        $str = str_replace('<!-- continue -->', '<?php continue; ?>', $str);

        //convert else
        $str = str_replace('<!-- break -->', '<?php break; ?>', $str);

        //convert end cache <!-- endcache -->
        $str = str_replace('<!-- endcache -->', "\n<?php Doo::cache('front')->end(); ?>\n<?php endif; ?>", $str);

        //convert cache <!-- cache('partial_id', 60) -->
        $str = preg_replace_callback('/<!-- cache\(([^\t\r\n}\)]+)\) -->/', array( &$this, 'convertCache'), $str);

        //convert include to php include and parse & compile the file, if include file not exist Echo error and exit application
        // <?php echo $data['file']; chars allowed for the grouping
        $str = preg_replace_callback('/<!-- include [\'\"]{1}([^\t\r\n\"]+).*[\'\"]{1} -->/', array( &$this, 'convertInclude'), $str);

        //remove comments
        if(!isset(Doo::conf()->TEMPLATE_SHOW_COMMENT) || Doo::conf()->TEMPLATE_SHOW_COMMENT!=true){
            //$str = preg_replace('/<!-- comment -->.+<!-- endcomment -->/s', '', $str);
            $str = str_replace('<!-- comment -->', '<?php /** ', $str);
            $str = str_replace('<!-- endcomment -->', ' */ ?>', $str);
        }

        return $str;
    }

    private function writeStaticVar($matches){
        return $this->data[$matches[1]];
    }

    private function convertPhpFunction($matches){
        if(stripos($matches[0], '<?php')!==0 && strpos($matches[0], '<?=')!==0 && strpos($matches[0], '<?+')!==0  && strpos($matches[0], '<? ')!==0 ){
            return $matches[0];
        }

        $str = preg_replace_callback('/([^ \t\r\n\(\)}]+)([\s\t]*?)\(/', array( &$this, 'parseFunc'), $matches[2]);
        if(strpos($str, 'php')===0)
            $str = substr($str, 3);

        //if short tag <?=, convert to <?php echo
        if($matches[2][0]=='='){
            $str = substr($str, 1);
            return '<?php echo ' . $str .' ?>';
        }
        //write the variable value
        else if($matches[2][0]=='+'){
            $str = substr($str, 1);
            return eval('return ' . $str);
        }

        return '<?php ' . $str .' ?>';
    }

    private function parseFunc($matches){
        //matches and check function name against template tag
        if(!empty($matches[1])){
            $funcname = trim(strtolower($matches[1]));
            if($funcname[0]=='+' || $funcname[0]=='=')
                $funcname = substr($funcname, 1);
                
            $controls = array('if','elseif','else if','while','switch','for','foreach','switch','return','include','require','include_once','require_once','declare','define','defined','die','constant','array');

            //skip checking static method usage: TemplateTag::go(), Doo::conf()
            if(stripos($funcname, $this->tags['_class'] . '::')===False && stripos($funcname, 'Doo')===False){
                //$funcname = str_ireplace($this->tags['_class'] . '::', '', $funcname);
                if(!in_array($funcname, $controls)){
                    if(!in_array($funcname, $this->tags)) {
                        return 'function_deny(';
                    }
                }
            }
        }
        return $matches[1].'(';
    }

    private function stripCommaStr($matches){
        $str = implode('\/\.\;', explode(',', $matches[0]) );
        $str = substr($str, 1, strlen($str)-2);
        return "'".$str."'";
    }

    private function convertFunction($matches) {
        if($matches[1][0]=='+'){
            $matches[1] = substr($matches[1], 1);
            $writeStaticValue = true;
        }
        if(!in_array(strtolower($matches[1]), $this->tags)) {
            return '<span style="color:#ff0000;">Function '.$matches[1].'() Denied</span>';
        }

        $functionName = $matches[1];
        if(isset($this->tags['_methods']) && in_array($functionName, $this->tags['_methods'])===True){
            $functionName = $this->tags['_class'] . '::' . $functionName;
        }

        //replace , to something else if it's in a string parameter
        if(strpos($matches[2], ',')!==False){
            $matches[2] = preg_replace_callback('/\"(.+)\"/', array( &$this, 'stripCommaStr'), $matches[2]);
        }

		$stmt = str_replace('<?php echo ', '', $matches[2]);
        $stmt = str_replace('; ?>', '', $stmt);
        $parameters = explode(',', $stmt);

        $args = '';

        foreach ($parameters as $param) {
            $param = trim($param);
            if (strlen($args) > 0) {
                $args .= ', ';
            }

            // Is a number
            if (preg_match('/^[0-9]*\\.?[0-9]{0,}$/', $param)) {
                $args .= $param;
            }
            // Is a string 'anything' OR "anything"
            elseif (preg_match('/^[\'\"].*[\'\"]$/', $param)) {
                $args .= str_replace('\/\.\;', ',', $param);
            }
            elseif (strtolower($param)=='true' || strtolower($param)=='false') {
                $args .= $param;
            }
            // Got parameter values to handle
            else {
                $args .= $this->extractObjectVariables($param);
            }
        }

        //if + in front, write the value of the function call
        if(!empty($writeStaticValue)){
            return eval("return {$functionName}($args);");
        }

        return "<?php echo {$functionName}($args); ?>";

    }

    private function checkFuncAllowed($matches){
        if(!in_array(strtolower($matches[1]), $this->tags))
            return 'function_deny('. $matches[2] .')';
        return $matches[1].'('. $matches[2] .')';
    }

    private function storeViewBlock($matches){
        // Store blocks as blockName => blockContent
        $this->viewBlocks[$matches[1]] = $matches[2];
        return '';
    }

    private function replacePlaceholder($matches) {
        $blockName = $matches[1];
        // If the block has been defined in the view then use it otherwise
        // use the default from the layout
        if (isset( $this->viewBlocks[$matches[1]] )) {
            return $this->viewBlocks[$matches[1]];
        } else {
            return $matches[2];
        }
    }

    private function convertCache($matches){
		$data = str_replace(array('<?php echo ', '; ?>'), '', $matches[1]);
        $data = explode(',', $data);
        if(sizeof($data)==2){
            $data[1] = intval($data[1]);
            return "<?php if (!Doo::cache('front')->getPart({$data[0]}, {$data[1]})): ?>\n<?php Doo::cache('front')->start({$data[0]}); ?>";
        }else{
            return "<?php if (!Doo::cache('front')->getPart({$data[0]})): ?>\n<?php Doo::cache('front')->start({$data[0]}); ?>";
        }
    }

    private function convertCond($matches){
        //echo '<h1>'.str_replace('>', '&gt;', str_replace('<', '&lt;', $matches[2])).'</h1>';
        $stmt = str_replace('<?php echo ', '', $matches[2]);
        $stmt = str_replace('; ?>', '', $stmt);
        //echo '<h1>'.$stmt.'</h1>';

        //prevent malicious HTML designers to use function with spaces
        //eg. unlink        ( 'allmyfiles.file'  ), php allows this to happen!!
        $stmt = preg_replace_callback('/([a-z0-9\-_]+)[ ]*\([ ]*([^ \t\r\n}]+)\)/i', array( &$this, 'checkFuncAllowed'), $stmt);

        //echo '<h1>'.$stmt.'</h1>';
        switch($matches[1]){
            case 'if':
                return '<?php if( '.$stmt.' ): ?>';
            case 'elseif':
                return '<?php elseif( '.$stmt.' ): ?>';
        }
    }

    private function convertFor($matches) {
        $expr = str_replace('<?php echo ', '', $matches[1]);
        $expr = str_replace('; ?>', '', $expr);

        //for: i from 0 to 10
		if (preg_match('/([a-z0-9\-_]+?) from ([^ \t\r\n\(\)}]+) to ([^ \t\r\n\(\)}]+)( step ([^ \t\r\n\(\)}]+))?/i', $expr)){
			$expr = preg_replace_callback('/([a-z0-9\-_]+?) from ([^ \t\r\n\(\)}]+) to ([^ \t\r\n\(\)}]+)( step ([^ \t\r\n\(\)}]+))?/i', array( &$this, 'buildRangeForLoop'), $expr);
		}
		// for: 'myArray as key=>val'
		else if (preg_match('/([a-z0-9\-_]+?) as ([a-z0-9\-_]+)[ ]?=>[ ]?([a-z0-9\-_]+)/i', $expr)) {
			$expr = preg_replace_callback('/([a-z0-9\-_]+?) as ([a-z0-9\-_]+)[ ]?=>[ ]?([a-z0-9\-_]+)/i', array( &$this, 'buildKeyValForLoop'), $expr);
		}
		// for: 'myArray as val'
		else if (preg_match('/([a-z0-9\-_]+?) as ([a-z0-9\-_]+)/i', $expr)) {
			$expr = preg_replace_callback('/([a-z0-9\-_]+?) as ([a-z0-9\-_]+)/i', array( &$this, 'buildValForLoop'), $expr);
		}
        return $expr;
    }

    private function buildRangeForLoop($matches) {
        $stepBy = isset($matches[5]) ? $matches[5] : 1;
        return '<?php foreach(range(' . $matches[2] . ', ' . $matches[3] . ', ' . $stepBy . ') as $data[\'' . $matches[1] . '\']): ?>';
    }

	private function buildKeyValForLoop($matches) {
		return '<?php foreach($data[\''.$matches[1].'\'] as $'.$matches[2].'=>$'.$matches[3].'): ?>';
	}

	private function buildValForLoop($matches) {
		return '<?php foreach($data[\''.$matches[1].'\'] as $'.$matches[2].'): ?>';
	}

    private function convertLoop($matches){
        $looplevel = sizeof(explode('\' ', $matches[0]));
        if(strpos($matches[0], "' ")!==FALSE){
            $strValue = str_repeat("' value", $looplevel-1);
            $loopStr = "<!-- loop {$matches[1]}$strValue.";
            if( strpos($matches[0], $loopStr)===0){
                $loopStr = substr($matches[0], strlen($loopStr));
                $loopStr = str_replace(' -->', '', $loopStr);
                $param = explode('.', $loopStr);
                $varBck ='';
                foreach($param as $pa){
                    if(strpos($pa, '@')===0){
                        $varBck .= '->' . substr($pa, 1);
                    }else{
                        $varBck .= "['$pa']";
                    }
                }
                $thislvl = $looplevel-1;
                $loopname = "\$v$thislvl$varBck";
            }else{
                $loopname = ($looplevel<2)? '$data[\''.$matches[1].'\']' : '$v'. ($looplevel-1);
            }
        }
        else if(strpos($matches[1], '.@')!==FALSE){
            $varname = str_replace('.@', '->', $matches[1]);
            $varname = explode('->', $varname);
            $firstname = $varname[0];
            array_splice($varname, 0, 1);
            $loopname =  '$data[\''.$firstname.'\']->' . implode('->', $varname) ;
        }
        else if(strpos($matches[1], '.')!==FALSE){
            $varname = explode('.',$matches[1]);
            $firstname = $varname[0];
            array_splice($varname, 0, 1);
            $loopname =  '$data[\''.$firstname .'\'][\''. implode("']['", $varname) .'\']';
        }
        else{
            $loopname = ($looplevel<2)? '$data[\''.$matches[1].'\']' : '$v'. ($looplevel-1);
        }
        return '<?php foreach('.$loopname.' as $k'.$looplevel.'=>$v'.$looplevel.'): ?>';
    }

    private function convertInclude($matches){
        $file = $matches[1];

        /*include file is a Variable <!-- include "{{file}}" -->,
         *modify the converted string <?php echo $data['file']; ?> to $data['file']; and return it to be written to file
         * <!-- include "<?php echo $data['file']; ?>" --> after convert to var */
        $includeVarPos = strpos($file, '<?php echo $data');
        if($includeVarPos===0){
            $file = str_replace('<?php echo ', '', $file);
            $file = str_replace('; ?>', '', $file);
            $dynamicFilename = '<?php include "{'.$file.'}.php"; ?>';

            //get the real template file name from $data passed in by users
            $file = $this->data[str_replace('\']', '', str_replace('$data[\'', '', $file) )];
        }

        //if first char is '/' then load the files in view root 'view' folder, <!-- '/admin/index' --> view/admin/index.html
        if(substr($file, 0,1)=='/'){
            $file = substr($file, 1);
            $cfilename = str_replace('\\', '/', Doo::conf()->SITE_PATH) . Doo::conf()->PROTECTED_FOLDER . "viewc/$file.php";
            $vfilename = str_replace('\\', '/', Doo::conf()->SITE_PATH) . Doo::conf()->PROTECTED_FOLDER . "view/$file.html";
        }
        else{
            $folders = explode('/', $this->mainRenderFolder);
            $file = implode('/', array_splice($folders, 0, -1)).'/'.$file;
            $cfilename = str_replace('\\', '/', Doo::conf()->SITE_PATH) . Doo::conf()->PROTECTED_FOLDER . "viewc/$file.php";
            $vfilename = str_replace('\\', '/', Doo::conf()->SITE_PATH) . Doo::conf()->PROTECTED_FOLDER . "view/$file.html";
        }

        if(!file_exists($vfilename)){
            echo "<span style=\"color:#ff0000\">Include view file <strong>$file.html</strong> not found</span>";
            exit;
        }else{
            if(file_exists($cfilename)){
                if(filemtime($vfilename)>filemtime($cfilename)){
                    $this->compile($file, $vfilename, $cfilename);
                }
            }else{
                $this->compile($file, $vfilename, $cfilename);
            }
        }

        if(isset ($dynamicFilename) )
            return $dynamicFilename;
            
        return '<?php include Doo::conf()->SITE_PATH .  Doo::conf()->PROTECTED_FOLDER . "viewc/'.$file.'.php"; ?>';
    }

    private function convertSet($matches) {

        $expr = str_replace('<?php echo ', '', $matches[2]);
        $expr = str_replace('; ?>', '', $expr);
        $expr = preg_replace_callback('/([a-z0-9\-_]+)[ ]*\([ ]*([^ \t\r\n}]+)\)/i', array( &$this, 'checkFuncAllowed'), $expr);
        
        return '<?php $data[\'' . $matches[1] . '\'] = ' . $expr . '; ?>';
    }

    private function extractObjectVariables($str) {

        $varname = '';
        $args = '';

        if(strpos($str, '.@')!==FALSE){
            $properties = explode('.@', $str);
            
            if(strpos($properties[0], "' ")!==FALSE){
                $looplevel = sizeof(explode('\' ', $properties[0]));

                //if ' key found that it's a key $k1
                if(strpos($properties[0],"' key")!==FALSE || strpos($properties[0],"' k")!==FALSE){
                    $varname = '$k' . ($looplevel-1);
                }else{
                    $varname = '$v' . ($looplevel-1);

                    //remove the variable part with the ' key or  ' value
                    array_splice($properties, 0, 1);

                    //join it up as array $v1['attachment']['pdf']   from  {{upper(msgdetails' value.attachment.pdf)}}
                    $varname .= "->". implode("->", $properties);
                }
            }else{
                $objname = $properties[0];
                array_splice($properties, 0, 1);
                $varname .= "\$data['$objname']->". implode("->", $properties);
            }

        } else if(strpos($str, '.')!==FALSE){
            $properties = explode('.', $str);
            if(strpos($properties[0], "' ")!==FALSE){
                $looplevel = sizeof(explode('\' ', $properties[0]));

                //if ' key found that it's a key $k1
                if(strpos($properties[0],"' key")!==FALSE || strpos($properties[0],"' k")!==FALSE){
                    $varname = '$k' . ($looplevel-1);
                }else{
                    $varname = '$v' . ($looplevel-1);

                    //remove the variable part with the ' key or  ' value
                    array_splice($properties, 0, 1);

                    //join it up as array $v1['attachment']['pdf']   from  {{upper(msgdetails' value.attachment.pdf)}}
                    $varname .= "['". implode("']['", $properties) ."']";
                }
            }else{
                $varname .= "\$data['". implode("']['", $properties) ."']";
            }
        } else {
            //if the function found used with a key or value in a loop, then use $k1,$k2 or $v1,$v2 instead of $data
            if(strpos($str, "' ")!==FALSE){
                $looplevel = sizeof(explode('\' ', $str));

                //if ' key found that it's a key $k1
                if(strpos($str,"' key")!==FALSE || strpos($str,"' k")!==FALSE){
                    $varname = '$k' . ($looplevel-1);
                }else{
                    $varname = '$v' . ($looplevel-1);
                }
            }else{
                $varname = "\$data['".$str."']";
            }

        }

        $varname = str_replace("\$data[''", "'", $varname);
        $varname = str_replace("'']", "'", $varname);

        return $varname;
    }

	private function convertNonDataVarKey($matches) {

		$varname = '';
        //if more than 1 dots, eg. users.total.pdf
        if(strpos($matches[2], '@')!==FALSE){
            $varname = str_replace('@', '->', $matches[2]);
            $varname = str_replace('.', '', $varname);
        }
        else if(strpos($matches[2], '.')!==FALSE){
            $properties = explode('.', $matches[2]);
            $varname .= "['". implode("']['", $properties) ."']";
        }
        //only 1 dot, users.john
        else{
            $varname = "['".$matches[2]."']";
        }
        return "<?php echo \${$matches[1]}{$varname}; ?>";

	}
    
    private function convertVarKey($matches){
        $varname = '';
        //if more than 1 dots, eg. users.total.pdf
        if(strpos($matches[2], '@')!==FALSE){
            $varname = str_replace('@', '->', $matches[2]);
            $varname = str_replace('.', '', $varname);
        }
        else if(strpos($matches[2], '.')!==FALSE){
            $properties = explode('.', $matches[2]);
            $varname .= "['". implode("']['", $properties) ."']";
        }
        //only 1 dot, users.john
        else{
            $varname = "['".$matches[2]."']";
        }
        return "<?php echo \$data['{$matches[1]}']$varname; ?>";
    }

    private function convertVarLoop($matches){
        $looplevel = sizeof(explode('\' ', $matches[0]));
        
        //if ' key found that it's a key $k1
        if(strpos($matches[0],"' key")!==FALSE || strpos($matches[0],"' k")!==FALSE)
            $varname = 'k' . ($looplevel-1);
        else{
            $varname = 'v' . ($looplevel-1);
            // This lets us use $data['key'] values as element indexes
            $matches[2] = str_replace('<?php echo ', '', $matches[2]);
            $matches[2] = str_replace('; ?>', '', $matches[2]);
            //remove the first variable if the ' is found, we dunwan the loop name
            if(strpos($matches[2], "' ")!==FALSE){
                $matches[2] = explode("' ", $matches[2]);
                array_splice($matches[2], 0, 1);
                $matches[2] = "' ".implode("' ", $matches[2] );
            }

            //users' value.uname  becomes  $v1['uname']
            //users' value.posts.latest  becomes  $v1['posts']['latest']
            //users' value.@uname  becomes  $v1->uname
            //users' value.@posts.@latest  becomes  $v1->posts->latest
            if(strpos($matches[2], '.@')!==FALSE){
                $varname .= str_replace('.@', '->', $matches[2]);
                $varname = str_replace("' value",'', $varname);
                $varname = str_replace("' v",'', $varname);
            }
            else if(strpos($matches[2], '.')!==FALSE){
                $properties = explode('.', $matches[2]);
                if(sizeof($properties)===2)
                    $varname .= "['".$properties[1]."']";
                else{
                    array_splice($properties, 0, 1);
                    $varname .= "['". implode("']['", $properties) ."']";
                }
            }
        }
        return '<?php echo $'.$varname.'; ?>';
    }

}
