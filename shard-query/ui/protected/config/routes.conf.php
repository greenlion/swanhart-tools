<?php
/**
 * Define your URI routes here.
 *
 * $route[Request Method][Uri] = array( Controller class, action method, other options, etc. )
 *
 * RESTful api support, *=any request method, GET PUT POST DELETE
 * POST 	Create
 * GET      Read
 * PUT      Update, Create
 * DELETE 	Delete
 *
 * Use lowercase for Request Method
 *
 * If you have your controller file name different from its class name, eg. home.php HomeController
 * $route['*']['/'] = array('home', 'index', 'className'=>'HomeController');
 * 
 * If you need to reverse generate URL based on route ID with DooUrlBuilder in template view, please defined the id along with the routes
 * $route['*']['/'] = array('HomeController', 'index', 'id'=>'home');
 *
 * If you need dynamic routes on root domain, such as http://facebook.com/username
 * Use the key 'root':  $route['*']['root']['/:username'] = array('UserController', 'showProfile');
 *
 * If you need to catch unlimited parameters at the end of the url, eg. http://localhost/paramA/paramB/param1/param2/param.../.../..
 * Use the key 'catchall': $route['*']['catchall']['/:first'] = array('TestController', 'showAllParams');
 * 
 * If you have placed your controllers in a sub folder, eg. /protected/admin/EditStuffController.php
 * $route['*']['/'] = array('admin/EditStuffController', 'action');
 * 
 * If you want a module to be publicly accessed (without using Doo::app()->getModule() ) , use [module name] ,   eg. /protected/module/forum/PostController.php
 * $route['*']['/'] = array('[forum]PostController', 'action');
 * 
 * If you create subfolders in a module,  eg. /protected/module/forum/post/ListController.php, the module here is forum, subfolder is post
 * $route['*']['/'] = array('[forum]post/PostController', 'action');
 *
 * Aliasing give you an option to access the action method/controller through a different URL. This is useful when you need a different url than the controller class name.
 * For instance, you have a ClientController::new() . By default, you can access via http://localhost/client/new
 * 
 * $route['autoroute_alias']['/customer'] = 'ClientController';
 * $route['autoroute_alias']['/company/client'] = 'ClientController';
 * 
 * With the definition above, it allows user to access the same controller::method with the following URLs:
 * http://localhost/company/client/new
 *
 * To define alias for a Controller inside a module, you may use an array:
 * $route['autoroute_alias']['/customer'] = array('controller'=>'ClientController', 'module'=>'example');
 * $route['autoroute_alias']['/company/client'] = array('controller'=>'ClientController', 'module'=>'example');
 *
 * Auto routes can be accessed via URL pattern: http://domain.com/controller/method
 * If you have a camel case method listAllUser(), it can be accessed via http://domain.com/controller/listAllUser or http://domain.com/controller/list-all-user
 * In any case you want to control auto route to be accessed ONLY via dashed URL (list-all-user)
 *
 * $route['autoroute_force_dash'] = true;	//setting this to false or not defining it will keep auto routes accessible with the 2 URLs.
 *
 */

//User Required for Web Applicahtion

$user = unserialize(file_get_contents("protected/config/user.inc"));

//Desktop Web Site
$route['get']['/'] = array('redirect', 'query');
$route['get']['/schemas'] = array('DesktopController', 'schemas', 'auth' => $user, 'authFail'=>'Unauthorized!');
$route['get']['/query'] = array('DesktopController', 'query',  'auth' => $user, 'authFail'=>'Unauthorized!');
$route['get']['/status'] = array('DesktopController', 'status',  'auth' => $user, 'authFail'=>'Unauthorized!');
$route['get']['/key-directory'] = array('DesktopController', 'key_directory',  'auth' => $user, 'authFail'=>'Unauthorized!');

//Shard Query Operations
$route['post']['/query/submit/:schema_id'] = array('QueryRESTController', 'submit', 'match'=> array('schema_id'=>'/^\d+$/'));
$route['post']['/query/explain/:schema_id'] = array('QueryRESTController', 'explain', 'match'=> array('schema_id'=>'/^\d+$/'));
$route['get']['/query/async_completion_perc/:schema_id/:job_id'] = array('QueryRESTController', 'async_completion_perc', 'match'=> array('job_id'=>'/^\d+$/', 'schema_id'=>'/^\d+$/'));
$route['get']['/query/fetch/:schema_id/:job_id'] = array('QueryRESTController', 'fetch', 'match'=> array('job_id'=>'/^\d+$/', 'schema_id'=>'/^\d+$/'));

//Schema Explorer
$route['get']['/schema/tables/:db'] = array('SchemaRESTController', 'tables');
$route['get']['/schema/fields/:db/:table'] = array('SchemaRESTController', 'fields');

//Util 
$route['get']['/util/remove_agg_tmp_tables/:schema_id'] = array('UtilRESTController', 'remove_agg_tmp_tables');

//CLI Gearman Worker
$route['cli']['worker'] = array('GearmanWorkerCLIController', 'worker');
$route['cli']['stop_workers'] = array('GearmanWorkerCLIController', 'stop_workers');
$route['cli']['check_workers'] = array('GearmanWorkerCLIController', 'check_workers');

//CLI Gearman Job Server
$route['cli']['stop_job_servers'] = array('GearmanJobServerCLIController', 'stop_job_servers');
$route['cli']['check_job_servers'] = array('GearmanJobServerCLIController', 'check_job_servers');

$route['*']['/error'] = array('ErrorController', 'index');

//------------------- DB REST Controllers ------------
include('db.routes.conf.php');
