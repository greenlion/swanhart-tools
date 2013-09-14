<?php
    class DBShard_Range_MapController extends DBRESTController
    {
        function __construct(){
            $this->model = 'ShardRangeMap';
        }
        //override
		public function create(){}   
    }
?>
