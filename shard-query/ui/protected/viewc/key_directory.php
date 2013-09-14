<!DOCTYPE html>
<html lang="en">
    
    <head>
        <meta charset="utf-8">
        <title><?php echo $this->data['title']; ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="author" content="">
        <link href="global/css/bootstrap/bootstrap.min.css" rel="stylesheet">
        <link href="global/css/app.css" rel="stylesheet">
        <link rel="shortcut icon" href="global/img/favicon.ico">
    </head>
    
    <body data-spy="scroll" data-target=".subnav">
        <div class="navbar navbar-fixed-top">
            <div class="navbar-inner">
                <div class="container"> <a class="brand">Shard Query</a>

                    <div class="nav-collapse">
                        <ul class="nav">
                            <li> <a href="schemas<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Sharded Schemas</a>

                            </li>
                            <li> <a href="query<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Query</a>

                            </li>
                            <li> <a href="status<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Status</a>

                            </li>
                            <li class="active"> <a href="#">Key Directory</a>

                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <header class="jumbotron subhead" id="overview">
                	<h1>Key Directory</h1>
                <p class="lead">Configuration and Maintenance</p>
  				<div class="subnav">
                    <ul class="nav nav-pills">
                     	<li class="active">
                            <a href="#columns">Column Sequences</a>
                        </li>
                        <li>
                            <a href="#list-key-mapping">List Key Mapping</a>
                        </li>
                        <li>
                            <a href="#range-key-mapping">Range Key Mapping</a>
                        </li>
                    </ul>
                </div>        	
            </header>
    		<section>
    			<div class="row">
    			  	<div class="span6">
    					<h2>Select Sharded Schema:</h2>
    				</div>
    			  	<div class="span3">
<?php	echo "<select id='key-directory-schemata-select' style='height:40px;margin-left:-85px;margin-top:10px'>";
		//print schemas
		foreach($this->data['schemata'] as $s){	 
         	if($this->data['schema_id'] == $s->id)
				echo "<option selected='selected' value='" . $s->id . "'>" . $s->schema_name . "</option>";
			else
    			echo "<option value='" . $s->id . "'>" . $s->schema_name . "</option>";
		}
        echo "</select>";   
?>   	
    				</div>
    			</div>
      		</section>
    		<section id="columns">
    			<div class="page-header">
    		        <h1>Column Sequence</h1>
                </div>
    			<table class="table table-striped">
<?php
        //print table if we have cols
        if(count($this->data['column_sequences']) > 0){
            echo '<thead>';
                    echo '<tr>';
                        echo '<th>Sequence Name</th>';
                        echo '<th>Sequence Type</th>';
                        echo '<th>Next Value</th>';
                        echo '<th>Data Type</th>';
                    echo '</tr>';
                    echo '</thead>';
                echo '<tbody>';
        }

		//print table
		foreach($this->data['column_sequences'] as $cs){
			echo "<tr>";
			echo "<td>" . $cs->sequence_name . "</td>";
			echo "<td>" . $cs->sequence_type. "</td>";
			echo "<td>" . $cs->next_value . "</td>";
			echo "<td>" . $cs->datatype . "</td>";
			echo '<td><button class="btn btn-danger" column-id=' . $cs->id . ' action="delete-column" type="button">Remove</button></td>';
			echo "</tr>\n";
		}

       if(count($this->data['column_sequences']) > 0)
            echo '</tbody>';
?>
                </table>
                <button class="btn btn-primary" action="add-column" type="button" <?php  echo count($this->data['column_sequences']) == 0 ? '' : 'disabled' ?>>Add Column</button>
    		</section>	
    		<section id="list-key-mapping">
          		<div class="page-header">
                    <h1>List Key Mapping</h1>
                </div>
                <table class="table table-striped">
<?php
        //print table if we have cols
        if(count($this->data['list_key_mappings']) > 0){
            echo '<thead>';
                    echo '<tr>';
                        echo '<th>Column Sequence Name</th>';
                        echo '<th>Key Value</th>';
                        echo '<th>Shard</th>';
                    echo '</tr>';
                    echo '</thead>';
                echo '<tbody>';
        }

		//print table
		foreach($this->data['list_key_mappings'] as $km){
			echo "<tr>";
			echo "<td>" . $this->data['columns'][$km->column_id] . "</td>";

            if(isset($km->key_value))
                echo "<td>" . $km->key_value . "</td>";
			else
                echo "<td>" . $km->key_string_value . "</td>";

			echo "<td>" . $this->data['shards'][$km->shard_id] . "</td>";
			echo '<td><button class="btn btn-primary" map-id=' . $km->id . ' action="edit-list-key-map" type="button" disabled>Move</button></td>';
			echo '<td><button class="btn btn-danger" map-id=' . $km->id . ' action="delete-list-key-map" type="button"  disabled>Remove</button></td>';
			echo "</tr>\n";
		}

        if(count($this->data['list_key_mappings']) > 0)
            echo '</tbody>';
?>
                </table>
                <ul class="pager">
                  <li id="pager-list-prev" <?php  echo ($this->data['page_num'] == 1 ? "class='disabled'" : ''); ?>><a href="#list-key-mapping">Previous</a></li>
                  <li id="pager-list-next" <?php  echo (count($this->data['list_key_mappings']) < 20 ? "class='disabled'" : ''); ?>><a href="#list-key-mapping">Next</a></li>
                </ul>
				<button class="btn btn-primary" action="add-list-map" type="button">Add Map</button>
				<button class="btn btn-info disabled" action="gen-list-key-distrib" type="button">Generate Distribution</button>
    		</section>
    		<section id="range-key-mapping">
    	  		<div class="page-header">
    	        	<h1>Range Key Mapping</h1>
    	        </div>
                <table class="table table-striped">
<?php

        //print table if we have cols
        if(count($this->data['range_key_mappings']) > 0){
            echo '<thead>';
                    echo '<tr>';
                        echo '<th>Column Sequence Name</th>';
                        echo '<th>Key Value Min</th>';
                        echo '<th>Key Value Max</th>';
                        echo '<th>Shard</th>';
                    echo '</tr>';
                    echo '</thead>';
                echo '<tbody>';
        }

        //print table
        foreach($this->data['range_key_mappings'] as $km){
            echo "<tr>";
            echo "<td>" . $this->data['columns'][$km->column_id] . "</td>";
            echo "<td>" . $km->key_value_min . "</td>";
            echo "<td>" . $km->key_value_max . "</td>";
            echo "<td>" . $this->data['shards'][$km->shard_id] . "</td>";
            echo '<td><button class="btn btn-primary" map-id=' . $km->id . ' action="edit-range-key-map" type="button" disabled>Move</button></td>';
            echo '<td><button class="btn btn-danger" map-id=' . $km->id . ' action="delete-range-key-map" type="button" disabled>Remove</button></td>';
            echo "</tr>\n";
        }

         if(count($this->data['range_key_mappings']) > 0)
            echo '</tbody>';
?>
                </table>
                <button class="btn btn-primary" action="add-range-map" type="button" disabled>Add Map</button>
                <button class="btn btn-info" action="gen-range-key-distrib" type="button" disabled>Generate Distribution</button>
    		</section>
            <br /><br />
        </div>
        <!-- /container -->

        <!-- confirmation delete modal -->
        <div class="modal hide" id="confirmation-delete-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">x</button>
                <h3>Confirmation</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete?</p>
            </div>
            <div class="modal-footer">
                <div id="confirmation-delete-alert" class="alert alert-error"></div>
                <a href="#" class="btn" data-dismiss="modal">Close</a>
                <a id="continue-delete-button" href="#" class="btn btn-primary">Continue</a>
            </div>
        </div>

        <!-- add column sequence  modal -->
        <div class="modal hide" id="add-column-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">x</button>
                <h3>Add Column Sequence</h3>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="control-group">
                            <label class="control-label" for="add-column-name-text">Sequence Name</label>
                            <div class="controls">
                                <input type="text" class="input-xlarge span2" id="add-column-name-text" value="Column0">
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label" for="add-column-type-select">Type</label>
                            <div class="controls">
                               <select id="add-column-type-select"class="input-large">
                                    <option>shard_column</option>
                                    <option>sequence</option>
                                </select>
                            </div>
                        </div>
                         <div class="control-group">
                            <label class="control-label" for="add-column-data-type-select">Data Type</label>
                            <div class="controls">
                               <select id="add-column-data-type-select"class="input-large">
                                    <option>string</option>
                                    <option>integer</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer">
                <div id="add-column-alert" class="alert alert-error"></div>
                <a href="#" class="btn" data-dismiss="modal">Close</a>
                <a href="#" id="add-column-modal-save" class="btn btn-primary">Add</a>
            </div>
        </div>

        <!-- add list map modal -->
        <div class="modal hide" id="add-list-map-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">x</button>
                <h3>Add List Map</h3>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="control-group">
                            <label class="control-label" for="add-list-map-column-select">Column Sequence Name</label>
                            <div class="controls">
                                  <select id="add-list-map-column-select"class="input-large">
<?php
                                foreach($this->data['column_sequences'] as $s)
                                    echo '<option value=' . $s->id . ' .>' . $s->sequence_name . '</option>';
?>
                                </select>
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label" for="add-list-map-key-value-text">Key Value</label>
                            <div class="controls">
                                <input type="text" class="input-xlarge span1" id="add-list-map-key-value-text">
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label" for="add-list-map-shard-select">Shard</label>
                            <div class="controls">
                               <select id="add-list-map-shard-select"class="input-large">
<?php
                                foreach($this->data['shards'] as $id=>$shard_name)
                                    echo '<option value=' . $id . ' .>' . $shard_name . '</option>';
?>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                </form>
                </div>
            <div class="modal-footer">
                <div id="add-list-map-alert" class="alert alert-error"></div>
                <a href="#" class="btn" data-dismiss="modal">Close</a>
                <a href="#" id="add-list-map-modal-save" class="btn btn-primary">Add</a>
            </div>
        </div>
        
        <!-- add range map modal -->
        <div class="modal hide" id="add-range-map-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">x</button>
                <h3>Edit Range Map</h3>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="control-group">
                            <label class="control-label" for="add-range-map-column-name-text">Column Sequence Name</label>
                               <div class="controls">
                                  <select id="add-range-map-column-select"class="input-large">
<?php
                                foreach($this->data['column_sequences'] as $s)
                                    echo '<option value=' . $s->id . ' .>' . $s->sequence_name . '</option>';
?>
                                </select>
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label" for="add-range-map-min-value-text">Min Value</label>
                            <div class="controls">
                                <input type="text" class="input-xlarge span1" id="add-range-map-key-value-text">
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label" for="add-range-map-max-value-text">Max Value</label>
                            <div class="controls">
                                <input type="text" class="input-xlarge span1" id="add-range-map-max-value-text">
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label" for="add-range-map-shard-select">Shard</label>
                            <div class="controls">
                               <select id="add-range-map-shard-select"class="input-large">
<?php
                                foreach($this->data['shards'] as $id=>$shard_name)
                                    echo '<option value=' . $id . ' .>' . $shard_name . '</option>';
?>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                </form>
                </div>
            <div class="modal-footer">
                <div id="add-range-map-alert" class="alert alert-error"></div>
                <a href="#" class="btn" data-dismiss="modal">Close</a>
                <a href="#" id="add-range-map-modal-save" class="btn btn-primary">Save</a>
            </div>
        </div>

        <!-- scripts -->
        <script>
            var schema_id = <?php echo $data['schema_id']; ?>;
            var page_num = <?php echo $data['page_num']; ?>;
        </script>
        <script src="global/js/jquery-1.7.2.min.js"></script>
        <script src="global/js/bootstrap/bootstrap.min.js"></script>
        <script src="global/js/key_directory.js"></script>
	</body>
</html>
