<?php
    class DBSchemataController extends DBRESTController
    {
        function __construct(){
            $this->model = 'Schemata';
        }

         //override
		public function destory(){

			$s = new Schemata();
			$s->id = $this->params['id'];
			$s = $s->getOne();

			//cannot delete the default!
			if($s->default == 1){
				$this->res->message = "Cannot Delete the Default Schemata!";
				$this->res->success = false;
				return;
			}

			parent::destory();
		}   
        
    }
?>
