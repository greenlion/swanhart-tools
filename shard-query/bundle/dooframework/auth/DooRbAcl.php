<?php
/**
* DooRbAcl class file.
*
* @author aligo <aligo_x@163.com>
* @link http://www.doophp.com/
* @copyright Copyright &copy; 2009 Leng Sheng Hong
* @license http://www.doophp.com/license
*
*/
Doo::loadCore('auth/DooAcl');

class DooRbAcl extends DooAcl{

    /**
     * Check if the user Roles is allowed to access the resource or action list or both.
     *
     * <code>
     * //Check if members are allowed for BlogController->post
     * Doo::acl()->isAllowed(array('member','admin'), 'BlogController', 'post' );
     *
     * //Check if members are allowed for BlogController
     * Doo::acl()->isAllowed(array('anonymous','member'), 'BlogController');
     * </code>
     *
     * @param array $role Roles of a user, usually retrieve from user's login session
     * @param string $resource Resource name (use Controller class name)
     * @param string $action Action name (use Method name)
     * @return bool
     */
    public function isAllowed($roles, $resource, $action='') {
       $allowed=false;
        foreach($roles as $role){
           if ($this->hasDenied($role, $resource, $action)) {
              return false;
           }

           if ($this->hasAllowed($role, $resource, $action)) {
              $allowed=true;
           }
        }

      return $allowed;
    }

    /**
     * Check if the user Roles is denied from the resource or action list or both.
     *
     * <code>
     * //Check if members are denied from BlogController->post
     * Doo::acl()->isDenied(array('member','admin'), 'BlogController', 'post' );
     *
     * //Check if members are denied from BlogController
     * Doo::acl()->isDenied(array('anonymous','member'), 'BlogController');
     * </code>
     *
     * @param string $role Role of a user, usually retrieve from user's login session
     * @param string $resource Resource name (use Controller class name)
     * @param string $action Action name (use Method name)
     * @return bool
     */
    public function isDenied($roles, $resource, $action='') {
       $denied=true;
        foreach($roles as $role){
           if ($this->hasDenied($role, $resource, $action)) {
              return true;
           }

           if ($this->hasAllowed($role, $resource, $action)) {
              $denied=false;
           }
        }

        return $denied;
    }

    /**
     * Check if the user's role is able to access the resource/action.
     *
     * @param mixed $roles Roles of a user, usually retrieve from user's login session
     * @param string $resource Resource name (use Controller class name)
     * @param string $action Action name (use Method name)
     * @return array|string Returns the fail route if user cannot access the resource.
     */
    public function process($roles, $resource, $action=''){

      if (!is_array($roles)){
         $roles = explode(',', $roles);
      }

      $denied = false;
      $allowed = false;
      foreach($roles as $role) {
         $denied = $denied || $this->hasDenied($role, $resource, $action);
         $allowed = $allowed || $this->hasAllowed($role, $resource, $action);
      }

      if( $denied ){
            //echo 'In deny list';
            if (isset($this->rules[$role]['failRoute'])) {
                $route = $this->rules[$role]['failRoute'];

                if (is_string($route)){
                    return array($route, 'internal');
                } else {
                    if (isset($route[$resource])) {
                        return (is_string($route[$resource])) ? array($route[$resource], 'internal') : $route[$resource] ;
                    } else if (isset( $route[$resource.'/'.$action] )) {
                        $rs = $route[$resource.'/'.$action];
                        return (is_string($rs))? array($rs, 'internal') : $rs;
                    } else if (isset( $route['_default'] )) {
                        return (is_string($route['_default'])) ? array($route['_default'], 'internal') : $route['_default'];
                    }
                }
            }
			return $this->defaultFailedRoute;
        }
		else if ($allowed==false) {
            //echo 'Not in allow list<br>';
            if(isset($this->rules[$role]['failRoute'])){
                $route = $this->rules[$role]['failRoute'];

                if(is_string($route)){
                        return array($route, 'internal');
                } else{
                    if( isset($route[$resource])) {
                        return (is_string($route[$resource])) ? array($route[$resource], 'internal') : $route[$resource] ;
                    } else if (isset( $route[$resource.'/'.$action] )) {
                        $rs = $route[$resource.'/'.$action];
                        return (is_string($rs))? array($rs, 'internal') : $rs;
                    } else if (isset( $route['_default'] )) {
                        return (is_string($route['_default'])) ? array($route['_default'], 'internal') : $route['_default'];
                    }
                }
            }
			return $this->defaultFailedRoute;
        }
    }
}