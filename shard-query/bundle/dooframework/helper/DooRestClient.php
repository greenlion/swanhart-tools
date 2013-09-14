<?php
/**
 * DooRestClient class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * A REST client to make requests to 3rd party RESTful web services such as Twitter.
 *
 * <p>You can send GET, POST, PUT and DELETE requests with DooRestClient.
 * Method chaining is also supported by this class.</p>
 * Example usage:
 * <code>
 * //Example usage in a Doo Controller
 * //import the class and create the REST client object
 * $client = $this->load()->helper('DooRestClient', true);
 * $client->connect_to("http://twitter.com/direct_messages.xml")
 *        ->auth('twituser', 'password', true)
 *        ->get()
 *
 * if($client->isSuccess()){
 *      echo "<pre>Received content-type: {$client->resultContentType()}<br/>";
 *      print_r( $client->result() );
 * }else{
 *      echo "<pre>Request unsuccessful, error {$client->resultCode()} return";
 * }
 * </code>
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooRestClient.php 1000 2009-07-7 18:27:22
 * @package doo.helper
 * @since 1.0
 */
class DooRestClient {
    protected $server_url;
    protected $curl_opt;
    protected $auth_user;
    protected $auth_pwd;
    protected $args;
    protected $result;
    protected $header_code_received;
    protected $content_type_received;

    //for sending Accept header, this is to be used with requests to REST server which is built with Doo Framework
    const HTML = 'text/html';
    const XML = 'application/xml';
    const JSON = 'application/json';
    const JS = 'application/javascript';
    const CSS = 'text/css';
    const RSS = 'application/rss+xml';
    const YAML = 'text/yaml';
    const ATOM = 'application/atom+xml';
    const PDF = 'application/pdf';
    const TEXT = 'text/plain';
    const PNG = 'image/png';
    const JPG = 'image/jpeg';
    const GIF = 'image/gif';
    const CSV = 'text/csv';

    function  __construct($server_url=NULL) {
        if($server_url!=NULL)
            $this->server_url = $server_url;
        $this->curl_opt = array();
        $this->curl_opt['RETURNTRANSFER'] = true;
        $this->curl_opt['HEADER'] = false;
        $this->curl_opt['FRESH_CONNECT'] = true;
    }

    /**
     * Get/set the REST server URL
     * @param string $server_url
     * @return mixed
     */
    public function connect_to($server_url=NULL){
        if($server_url==NULL)
            return $this->server_url;
        $this->server_url = $server_url;
        return $this;
    }

    /**
     * Check if a given URL exist.
     *
     * The url exists if the return HTTP code is 200
     * @param string $url Url of the page
     * @return boolean True if exists (200)
     */
    public static function checkUrlExist($url){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true); // set to HEAD request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // don't output the response
        curl_exec($ch);
        $valid = curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200;
        curl_close($ch);
        return $valid;
    }

    /**
     * Send request to a URL and returns the HEAD request HTTP code.
     *
     * @param string $url Url of the page
     * @return int returns the HTTP code
     */
    public static function retrieveHeaderCode($url){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true); // set to HEAD request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // don't output the response
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }

    /**
     * Get/set the connection timeout duration (seconds)
     * @param int $sec Timeout duration in seconds
     * @return mixed
     */
    public function timeout($sec=NULL){
        if($sec===NULL)
            return $this->curl_opt['CONNECTTIMEOUT'];
        else
            $this->curl_opt['CONNECTTIMEOUT'] = $sec;
        return $this;
    }

    /**
     * Get/set data for the REST request.
     *
     * The data can either be a string of params <b>id=19&year=2009&filter=true</b>
     * or an assoc array <b>array('id'=>19, 'year'=>2009, 'filter'=>true)</b>
     *
     * <p>The data is returned when no data value is passed into the method.</p>
     *
     * @param string|array $data
     * @return mixed
     */
    public function data($data=NULL){
        if($data==NULL)
            return $this->args;

        if(is_string($data)){
            $this->args = $data;
        }
        else{
            $datastr = '';
            foreach($data as $k=>$v){
                $datastr .= "$k=$v&";
            }
            $this->args = $datastr;
        }
        return $this;
    }

    /**
     * Get/Set options for executing the REST connection.
     *
     * This method prepares options for Curl to work.
     * Instead of setting CURLOPT_URL = value, you should use URL = value while setting various options
     *
     * The options are returned when no option array is passed into the method.
     *
     * <p>See http://www.php.net/manual/en/function.curl-setopt.php for the list of Curl options.</p>
     * Option keys are case insensitive. Example option input:
     * <code>
     * $client = new DooRestClient('http://somewebsite.com/api/rest');
     * $client->options(array(
     *                      'returnTransfer'=>true,
     *                      'header'=>true,
     *                      'SSL_VERIFYPEER'=>false,
     *                      'timeout'=>10
     *                  ));
     *
     * $client->execute('get');
     * //or $client->get();
     * </code>
     * @param array $optArr
     * @return mixed
     */
    public function options($optArr=NULL){
        if($optArr==NULL)
            return $this->curl_opt;
        $this->curl_opt = array_merge($this->curl_opt, $optArr);
        return $this;
    }

    /**
     * Get/set authentication details for the RESTful call
     *
     * Authentication can be done with HTTP Basic or Digest. HTTP Auth Digest will be used by default.
     * If no values are passed into the method,
     * the auth details with be returned in an array consist of Username and Password.
     *
     * <p>If you are implementing your own RESTful api, you can handle authentication with DooDigestAuth::http_auth()
     * or setup authentication in your routes.</p>
     *
     * @param string $username Username
     * @param string $password Password
     * @param bool $basic to switch between HTTP Basic or Digest authentication
     * @return mixed
     */
    public function auth($username=NULL, $password=NULL, $basic=FALSE){
        if($username===NULL && $password===NULL)
            return array($this->auth_user, $this->auth_pwd);

        $this->auth_user = $username;
        $this->auth_pwd = $password;

        $this->curl_opt['HTTPAUTH'] = ($basic)?CURLAUTH_BASIC : CURLAUTH_DIGEST;

        return $this;
    }

    /**
     * Get/set desired accept type.
     * <p>This should be used if the REST server analyze Accept header to parse what format
     * you're seeking for the result content. eg. json, xml, rss, atom.
     * DooRestClient provides a list of common used format to be used in your code.</p>
     * Example to retrieve result in JSON:
     * <code>
     * $client = new DooRestClient;
     * $client->connect_to("http://twitter.com/direct_messages")
     *        ->auth('username', 'password', true)
     *        ->accept(DooRestClient::JSON)
     *        ->get();
     * </code>
     * @param string $type
     * @return mixed
     */
    public function accept($type=NULL){
        if($type==NULL)
            if(isset($this->curl_opt['HTTPHEADER']) && $this->curl_opt['HTTPHEADER'][0])
                return str_replace('Accept: ', '', $this->curl_opt['HTTPHEADER'][0]);
            else
                return;

        $this->curl_opt['HTTPHEADER'] = array("Accept: $type");
        return $this;
    }

    /**
     * Get/set desired content type to be post to the server
     * <p>This should be used if the REST server analyze Content-Type header to parse what format
     * you're posting to the API. eg. json, xml, rss, atom.
     * DooRestClient provides a list of common used format to be used in your code.</p>
     * Example to retrieve result in JSON:
     * <code>
     * $client = new DooRestClient;
     * $client->connect_to("http://twitter.com/post_status")
     *        ->auth('username', 'password', true)
     *        ->setContentType(DooRestClient::JSON)
     *        ->post();
     * </code>
     * @param string $type
     * @return mixed
     */
    public function setContentType($type=NULL){
        if($type==NULL)
            if(isset($this->curl_opt['HTTPHEADER']) && $this->curl_opt['HTTPHEADER'][0])
                return str_replace('Content-Type: ', '', $this->curl_opt['HTTPHEADER'][0]);
            else
                return;

        $this->curl_opt['HTTPHEADER'] = array("Content-Type: $type");
        return $this;
    }

    /**
     * Execute the RESTful request through either GET, POST, PUT or DELETE request method
     * @param string $method Method string is case insensitive.
     */
    public function execute($method){
        $method = strtolower($method);
        if($method=='get')
            $this->get();
        elseif($method=='post')
            $this->post();
        elseif($method=='put')
            $this->post();
        elseif($method=='delete')
            $this->post();
    }

    /**
     * Execute the request with HTTP GET request method
     * @return DooRestClient
     */
    public function get(){
        if($this->args!=NULL)
            $serverurl = $this->server_url .'?'. $this->args ;
        else
            $serverurl = $this->server_url;

        $ch = curl_init( $serverurl );

        $arr = array();
        foreach($this->curl_opt as $k=>$v){
            $arr[ constant('CURLOPT_'.strtoupper($k))] = $v;
        }

        //set HTTP auth username and password is found
        if(isset($this->auth_user) || isset($this->auth_pwd))
            $arr[CURLOPT_USERPWD] = $this->auth_user .':'. $this->auth_pwd;

        //set GET method
        $arr[CURLOPT_HTTPGET] = true;

        curl_setopt_array($ch, $arr);

        $this->result = curl_exec($ch);
        $this->header_code_received = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->content_type_received = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);
        return $this;
    }

    /**
     * Execute the request with HTTP POST request method
     * @return DooRestClient
     */
    public function post(){
        $ch = curl_init( $this->server_url );

        $arr = array();
        foreach($this->curl_opt as $k=>$v){
            $arr[ constant('CURLOPT_'.strtoupper($k))] = $v;
        }

        //set HTTP auth username and password is found
        if(isset($this->auth_user) || isset($this->auth_pwd))
            $arr[CURLOPT_USERPWD] = $this->auth_user .':'. $this->auth_pwd;

        //set POST method and fields
        $arr[CURLOPT_POST] = true;
        $arr[CURLOPT_POSTFIELDS] = $this->args;

        curl_setopt_array($ch, $arr);

        $this->result = curl_exec($ch);
        $this->header_code_received = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->content_type_received = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);
        return $this;
    }

    /**
     * Execute the request with HTTP PUT request method
     * @return DooRestClient
     */
    public function put(){
        $ch = curl_init( $this->server_url );

        $arr = array();
        foreach($this->curl_opt as $k=>$v){
            $arr[ constant('CURLOPT_'.strtoupper($k))] = $v;
        }

        //set HTTP auth username and password is found
        if(isset($this->auth_user) || isset($this->auth_pwd))
            $arr[CURLOPT_USERPWD] = $this->auth_user .':'. $this->auth_pwd;

        //set PUT method and fields
        $arr[CURLOPT_CUSTOMREQUEST] = 'PUT';
        $arr[CURLOPT_POSTFIELDS] = $this->args;

        curl_setopt_array($ch, $arr);

        $this->result = curl_exec($ch);
        $this->header_code_received = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->content_type_received = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);
        return $this;
    }

    /**
     * Execute the request with HTTP DELETE request method
     * @return DooRestClient
     */
    public function delete(){
        $ch = curl_init( $this->server_url );

        $arr = array();
        foreach($this->curl_opt as $k=>$v){
            $arr[ constant('CURLOPT_'.strtoupper($k))] = $v;
        }

        //set HTTP auth username and password is found
        if(isset($this->auth_user) || isset($this->auth_pwd))
            $arr[CURLOPT_USERPWD] = $this->auth_user .':'. $this->auth_pwd;

        //set DELETE method, delete methods don't have fields,ids should be set in server url
        $arr[CURLOPT_CUSTOMREQUEST] = 'DELETE';

        curl_setopt_array($ch, $arr);

        $this->result = curl_exec($ch);
        $this->header_code_received = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->content_type_received = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);
        return $this;
    }

    //-------------- Result handlers --------------

    /**
     * Get result of the executed request
     * @return string
     */
    public function result(){
        return $this->result;
    }

    /**
     * Determined if it's a successful request
     * @return bool
     */
    public function isSuccess(){
        return ($this->header_code_received>=200 && $this->header_code_received<300);
    }

    /**
     * Get result's HTTP status code of the executed request
     * @return int
     */
    public function resultCode(){
        return $this->header_code_received;
    }


    /**
     * Get result's content type of the executed request
     * @return int
     */
    public function resultContentType(){
        return $this->content_type_received;
    }

    /**
     * Convert the REST result to XML object
     *
     * Returns a SimpleXMLElement object by default which consumed less memory than DOMDocument.
     * However if you need the result to be DOMDocument which is more flexible and powerful in modifying XML,
     * just passed in True to the function.
     *
     * @param bool $domObject convert result in to DOMDOcument if True
     * @return SimpleXMLElement|DOMDocument
     */
    public function xml_result($domObject=FALSE){
        if($domObject){
            $d = new DOMDocument('1.0','UTF-8');
            $d->loadXML($this->result);
            return $d;
        }else
            return simplexml_load_string($this->result);
    }

    /**
     * Convert the REST result to JSON object
     * @param bool $toArray convert result into assoc array if True.
     * @return object
     */
    public function json_result($toArray=FALSE){
        return json_decode($this->result,$toArray);
    }

}
