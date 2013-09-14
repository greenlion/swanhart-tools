<?php

class DBRESTController extends DooController
{
    public function beforeRun($resource, $action)
    {
        //Init
        $this->res = new response();

        //set content type
        $this->contentType = 'json';
    }
    public function afterRun($routeResult)
    {
        //Check for success Result
        if ($routeResult == null || $routeResult == 200) {
            //Display Results
            if (isset($this->contentType))
                $this->setContentType($this->contentType);
            
            echo $this->res->to_json();
        }
    }
    
    //GET
    public function view()
    {
        try {
            //init model
            $m = new $this->model();
            
            //set id
            $m->id = isset($this->params['id']) ? $this->params['id'] : $m->id;
            
            //select one record
            $this->res->data    = $m->getOne(array(
                'asArray' => true
            ));
            $this->res->success = true;
            
        }
        catch (Exception $e) {
            $this->res->success = false;
            $this->res->message .= "Unable to View ";
            $this->res->data = $e->getMessage();
            Doo::logger()->err("DB View Error! " . $e->getMessage(), 'db');
        }
    }
    
    //POST
    public function create()
    {
        try {
            //init model
            Doo::db()->beginTransaction();
	       $this->initPutVars();
            $m = new $this->model($this->puts);
            
            //validate
            if ($error = $m->validate()) {
                $this->res->success = false;
                $this->res->message .= "Model Validation failed! ";
                $this->res->data = $error;
                return;
            }
            
            //insert
            $new_id = $m->insert();
            
            //save
            $m->id = $new_id;
            
            //commit
            Doo::db()->commit();
            $this->res->success = true;
            $this->res->data    =  $m->id;
        }
        catch (Exception $e) {
            Doo::db()->rollBack();
            $this->res->success = false;
            $this->res->message .= "Unable to Create! ";
            $this->res->data = $e->getMessage();
            Doo::logger()->err("DB Create Error! " . $e->getMessage(), 'db');
        }
    }
    
    //PUT
    public function update()
    {

        try {
            //init model
            Doo::db()->beginTransaction();
            $m     = new $this->model($this->puts);
            $m->id = isset($this->params['id']) ? $this->params['id'] : $m->id;
            
            //validate
            if ($error = $m->validate()) {
                $this->res->success = false;
                $this->res->message .= "Model Validation failed! ";
                $this->res->data = $error;
                return;
            }
            
            //update db
            $id = $m->update(array('setnulls' => true));
            
            //commit
            Doo::db()->commit();
            $this->res->success = true;
        }
        catch (Exception $e) {
            Doo::db()->rollBack();
            $this->res->success = false;
            $this->res->message .= "Unable to Update! ";
            $this->res->data = $e->getMessage();
            Doo::logger()->err("DB Update Error! " . $e->getMessage(), 'db');
        }
        
    }
    
    //DELETE
    public function destroy()
    {
        try {
            //init model
            Doo::db()->beginTransaction();
            $m = new $this->model();
            
            //set id
            $m->id = $this->params['id'];
            
            //delete
            $m->delete();
            
            //commit
            Doo::db()->commit();
            $this->res->success = true;
            $this->res->data    = $id;
        }
        catch (Exception $e) {
            Doo::db()->rollBack();
            $this->res->success = false;
            $this->res->message .= "Unable to Delete! ";
            $this->res->data = mySQLExceptions::parse($e->getMessage());
            Doo::logger()->err("DB Delete Error! " . $e->getMessage(), 'db');
        }
    }
}






