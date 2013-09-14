<?php
/**
 * DooAcl class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * Provides Access Control List feature to the application.
 *
 * <p>DooAcl performs authorization checks for the specified resource and action. It checks against the rules defined in acl.conf.php.</p>
 * <p>Only when the user is allowed by one of the rules, will he be able to access the action.
 * If the user role cannot be found in both deny and allow list, he will not be able to access the action/resource</p>
 *
 * <p>Rules has to be defined in this way:</p>
 * <code>
 * # Allow member to access all actions in Sns and Blog resource.
 * $acl['member']['allow'] = array(
 *             'SnsController'=>'*',
 *             'BlogController'=>'*',
 *          );
 *
 * # Allow anonymous visitors for Blog index only.
 * $acl['anonymous']['allow'] = array(
 *             'BlogController'=>'index',
 *          );
 *
 * # Deny member from banUser, showVipHome, etc.
 * $acl['member']['deny'] = array(
 *             'SnsController'=>array('banUser', 'showVipHome'),
 *             'BlogController' =>array('deleteComment', 'writePost')
 *          );
 *
 * # Admin can access all except Sns showVipHome
 * $acl['admin']['allow'] = '*';
 * $acl['admin']['deny'] = array(
 *             'SnsController'=>array('showVipHome')
 *          );
 *
 * # If member is denied, reroute to the following routes.
 * $acl['member']['failRoute'] = array(
 *             //if not found this will be used
 *             '_default'=>'/error/member',
 *
 *             //if denied from sns banUser
 *             'SnsController/banUser'=>'/error/member/sns/notAdmin',
 *
 *             'SnsController/showVipHome'=>'/error/member/sns/notVip',
 *             'BlogController'=>'/error/member/blog/notAdmin'
 *          );
 * </code>
 *
 * <p>You have to assign the rules to DooAcl in bootstrap.</p>
 * <code>
 * # set rules
 * Doo::acl()->rules = $acl;
 *
 * # The default route to be reroute to when resource is denied. If not set, 404 error will be displayed.
 * Doo::acl()->defaultFailedRoute = '/error';
 * </code>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooAcl.php 1000 2009-08-23 20:46:49
 * @package doo.auth
 * @since 1.1
 */
class DooAcl {
	/**
	 * Rules settings for the application ACL. Defined in acl.conf.php
	 * @var array
	 */
	public $rules;

	/**
	 * Default route to be reroute to if no custome fail route is defined for a certain rule.
	 * @var string|array
	 */
	public $defaultFailedRoute = array('/error-default/failed-route/please-set-in-route', 404);

	/**
	 * Check if the user Role is in the allowed rule
	 *
	 * @param string $role Role of a user, usually retrieve from user's login session
	 * @param string $resource Resource name (use Controller class name)
	 * @param string $action Action name (use Method name)
	 * @return bool
	 */
	protected function hasAllowed($role, $resource, $action='') {
		if ($action=='') {
			return isset($this->rules[$role]['allow'][$resource]);
		} else {
			if(isset($this->rules[$role]['allow'][$resource])) {
				$actionlist = $this->rules[$role]['allow'][$resource];
				if ($actionlist==='*')
					return true;
				else
					return in_array($action, $actionlist);
			} else {
				if( isset($this->rules[$role]['allow']) && is_array($this->rules[$role]['allow']) && isset($this->rules[$role]['allow'][0]) ){
					return ($this->rules[$role]['allow'][0] == '*');
				}
				return false;
			}
		}
	}

	/**
	 * Check if the user Role is allowed to access the resource or action list or both.
	 *
	 * <code>
	 * //Check if member is allowed for BlogController->post
	 * Doo::acl()->isAllowed('member', 'BlogController', 'post' );
	 *
	 * //Check if member is allowed for BlogController
	 * Doo::acl()->isAllowed('member', 'BlogController');
	 * </code>
	 *
	 * @param string $role Role of a user, usually retrieve from user's login session
	 * @param string $resource Resource name (use Controller class name)
	 * @param string $action Action name (use Method name)
	 * @return bool
	 */
	public function isAllowed($role, $resource, $action='') {
		if (!$this->hasDenied($role, $resource, $action)) {
			if ($this->hasAllowed($role, $resource, $action)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the user Role is denied from the resource or action list or both.
	 *
	 * @param string $role Role of a user, usually retrieve from user's login session
	 * @param string $resource Resource name (use Controller class name)
	 * @param string $action Action name (use Method name)
	 * @return bool
	 */
	protected function hasDenied($role, $resource, $action='') {
		if ($action=='') {
			return isset($this->rules[$role]['deny'][$resource]);
		} else {
			if( isset($this->rules[$role]['deny']) && $this->rules[$role]['deny']=='*'){
				$this->rules[$role]['deny'] = array('*');
			}

			if (isset($this->rules[$role]['deny'][$resource])) {
				$actionlist = $this->rules[$role]['deny'][$resource];

				if($actionlist==='*')
					return true;
				else
					return in_array($action, $actionlist);
			} else {
				return false;
			}
		}
	}

	/**
	 * Check if the user Role is denied from the resource or action list or both.
	 *
	 * <code>
	 * //Check if member is denied from BlogController->post
	 * Doo::acl()->isDenied('member', 'BlogController', 'post' );
	 *
	 * //Check if member is denied from BlogController
	 * Doo::acl()->isDenied('member', 'BlogController');
	 * </code>
	 *
	 * @param string $role Role of a user, usually retrieve from user's login session
	 * @param string $resource Resource name (use Controller class name)
	 * @param string $action Action name (use Method name)
	 * @return bool
	 */
	public function isDenied($role, $resource, $action='') {
		if ($this->hasDenied($role, $resource, $action)) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the user's role is able to access the resource/action.
	 *
	 * @param string $role Role of a user, usually retrieve from user's login session
	 * @param string $resource Resource name (use Controller class name)
	 * @param string $action Action name (use Method name)
	 * @return array|string Returns the fail route if user cannot access the resource.
	 */
	public function process($role, $resource, $action='') {
		if ($this->isDenied($role, $resource, $action) ) {
			//echo 'In deny list';

			if (isset($this->rules[$role]['failRoute'])) {
				$route = $this->rules[$role]['failRoute'];

				if (is_string($route)) {
					return array($route, 'internal');
				} else {
					if (isset($route[$resource])) {
						return (is_string($route[$resource]))? array($route[$resource], 'internal') : $route[$resource] ;
					} elseif (isset( $route[$resource.'/'.$action] )) {
						$rs = $route[$resource.'/'.$action];
						return (is_string($rs))? array($rs, 'internal') : $rs;
					} elseif (isset( $route['_default'] )) {
						return (is_string($route['_default']))? array($route['_default'], 'internal') : $route['_default'];
					}
				}
			}
			return $this->defaultFailedRoute;

		} else if($this->isAllowed($role, $resource, $action)==false) {
			//echo 'Not in allow list<br>';

			if (isset($this->rules[$role]['failRoute'])) {
				$route = $this->rules[$role]['failRoute'];

				if (is_string($route)) {
					return array($route, 'internal');
				} else {
					if (isset($route[$resource])) {
						return (is_string($route[$resource]))? array($route[$resource], 'internal') : $route[$resource] ;
					} elseif (isset( $route[$resource.'/'.$action] )) {
						$rs = $route[$resource.'/'.$action];
						return (is_string($rs))? array($rs, 'internal') : $rs;
					} elseif (isset( $route['_default'] )) {
						return (is_string($route['_default']))? array($route['_default'], 'internal') : $route['_default'];
					}
				}
			}
			return $this->defaultFailedRoute;
		}
	}
}
