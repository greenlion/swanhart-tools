<?php
/**
 * DooUrlBuilder class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * A helper class that helps generate URL from routes.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooUrlBuilder.php 1000 2009-08-4 11:17:22
 * @package doo.helper
 * @since 1.1
 */
class DooUrlBuilder {

    /**
     * Build URL based on a route Id.
     * @param string $id Id of the route
     * @param array $param Parameter values to build the route URL
     * @param bool $addAppUrl Add the APP_URL to the url
     * @return string URL of a route
     */
    public static function url($id, $param=null, $addAppUrl=false){
        $route = Doo::app()->route;
        $routename = null;
        foreach($route as $req=>$r){
            foreach($r as $rname=>$value){
                if(isset($value['id']) && $value['id']==$id){
                    $routename = $rname;
                    break;
                }
            }
        }

        if($addAppUrl)
            $routename = Doo::conf()->APP_URL . substr($routename,1);

        if($param!=null){
            foreach($param as $k=>$v){
                $routename = str_replace(':' . $k, $v, $routename);
            }
        }

        return $routename;
    }

    /**
     * Build URL based on a route Controller and Method.
     * @param string $controller Name of the Controller
     * @param string $method Name of the Action method
     * @param array $param Parameter values to build the route URL
     * @param bool $addAppUrl Add the APP_URL to the url
     * @return string URL of a route
     */
    public static function url2($controller, $method, $param=null, $addAppUrl=false){
        $route = Doo::app()->route;
        $routename = null;
        foreach($route as $req=>$r){
            foreach($r as $rname=>$value){
                if($value[0]==$controller && $value[1]==$method){
                    $routename = $rname;
                    break;
                }
            }
        }
        if($addAppUrl)
            $routename = Doo::conf()->APP_URL . substr($routename,1);

        if($param!=null){
            foreach($param as $k=>$v){
                $routename = str_replace(':' . $k, $v, $routename);
            }
        }

        return $routename;
    }
}
