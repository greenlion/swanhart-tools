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
                            <li class="active"> <a href="#">Sharded Schemas</a>

                            </li>
                            <li> <a href="query<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Query</a>

                            </li>
                            <li> <a href="status<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Status</a>

                            </li>
                            <li> <a href="key-directory<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Key Directory</a>

                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <header class="jumbotron subhead" id="overview">
                	<h1>Sharded Schemas</h1>

                <p class="lead">Configure Schemas</p>
                <div class="subnav">
                    <ul class="nav nav-pills">
                        <li class="active">
							<a href="#schema">Schemas</a>
                        </li>
                        <li>
							<a href="#config">Configure</a>
                        </li>
                    </ul>
                </div>
            </header>
            <section id="schema">
                <div class="page-header">
					<h1>Schemas
					</h1>
				</div>
                    <table class="table table-striped">
<?php                       
                //print table if we have cols
                if(count($this->data['schemata']) > 0){
                    echo '<thead>';
                            echo '<tr>';
                                echo '<th>Name</th>';
                            echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                }

                //print table
                $this->data['selected_schema'] = "";
                foreach($this->data['schemata'] as $s){
                    if($this->data['schema_id'] == $s->id){
                        $this->data['selected_schema'] = $s->schema_name;
                        echo '<tr class="info">';
                    }else{
                        echo '<tr>';
                    }
					echo "<td>" . $s->schema_name . "</td>"; 
					echo '<td><button class="btn btn-primary" schema-id=' . $s->id . ' action="edit-schema"  type="button">Edit</button></td>';
                    echo '<td><button class="btn btn-info" schema-id=' . $s->id . ' action="config-schema"  type="button">Config</button></td>'; 
					echo '<td> <button class="btn btn-danger" schema-id=' . $s->id . ' action="delete-schema"  type="button">Remove</button></td>'; 
					echo "</tr>\n";
				 } 

                if(count($this->data['schemata']) > 0)
                     echo '</tbody>';
?>
                    </table>
                    <button class="btn btn-primary" action="add-schema" type="button">Add Schemas</button>
            </section>
            <section id="config">
                <div class="page-header">
                    <h1>Configure - <?php echo $this->data['selected_schema']; ?>
                    </h1>
                </div>
                <table class="table table-striped">
<?php

            //print table if we have cols
            if(count($this->data['config']) > 0){
                echo '<thead>';
                        echo '<tr>';
                            echo '<th>Key</th>';
                            echo '<th>Value</th>';
                        echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
            }              

            //print table 
			foreach($this->data['config'] as $c){
				echo "<tr>";
				echo "<td>" . $c->var_name . "</td>";
				echo "<td>" . $c->var_value . "</td>";
                echo '<td><button class="btn btn-primary" schema-config-id=' . $c->id . ' action="edit-config"  type="button">Edit</button></td>';
                echo '<td><button class="btn btn-danger" schema-config-id=' . $c->id . ' action="delete-config"  type="button">Remove</button></td>';
				echo "</tr>";
			}
            if(count($this->data['config']) > 0)
                echo '</tbody>';
?>                    	
                </table>
                <button class="btn btn-primary" action="add-config" type="button">Add Config</button>
            </section>
			<br /><br />
        </div>
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
        <!-- schema edit modal -->
        <div class="modal hide fade" id="edit-schema-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
                	<h3>Edit Schema</h3>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="control-group">
                            <label class="control-label" for="edit-schema-name-text">Schema Name</label>
                            <div class="controls">
                                <input type="text" class="input-xlarge span2" id="edit-schema-name-text">
                            </div>
                        </div>
                    </fieldset>
                </form>
                </div>
                <div class="modal-footer">	
                    <div id="edit-schema-alert" class="alert alert-error"></div>
                    <a href="#" class="btn" data-dismiss="modal">Close</a>
                    <a href="#" id="edit-schema-modal-save" class="btn btn-primary">Save changes</a>
                </div>
            </div>
		</div>
        <!-- schema add modal -->
        <div class="modal hide fade" id="add-schema-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
                    <h3>Add Schema</h3>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="control-group">
                            <label class="control-label" for="add-schema-name-text">Schema Name</label>
                            <div class="controls">
                                <input type="text" class="input-xlarge span2" id="add-schema-name-text">
                            </div>
                        </div>
                    </fieldset>
                </form>
                </div>
                <div class="modal-footer">  
                    <div id="add-schema-alert" class="alert alert-error"></div>
                    <a href="#" class="btn" data-dismiss="modal">Close</a>
                    <a href="#" id="add-schema-modal-save" class="btn btn-primary">Add</a>
                </div>
            </div>
        </div>
        <!-- config edit modal -->
        <div class="modal hide fade" id="edit-config-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
                    <h3>Edit Config</h3>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="control-group">
                            <label class="control-label" for="edit-config-key-select">Key Name</label>
                            <div class="controls">
                                <select id="edit-config-key-select"class="input-large">
<?php
                            foreach($this->data['config_items'] as $n){
                                    echo '<option>' . $n->name . '</option>';
                            }
?>
                                </select>
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label" for="edit-config-value-text">Value</label>
                            <div class="controls">
                                <input type="text" class="input-xlarge span2" id="edit-config-value-text">
                            </div>
                        </div>
                    </fieldset>
                </form>
                </div>
                <div class="modal-footer">  
                    <div id="edit-config-alert" class="alert alert-error"></div>
                    <a href="#" class="btn" data-dismiss="modal">Close</a>
                    <a href="#" id="edit-config-modal-save" class="btn btn-primary">Save changes</a>
                </div>
            </div>
        </div>
         <!-- config add modal -->
        <div class="modal hide fade" id="add-config-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
                    <h3>Add Config</h3>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="control-group">
                            <label class="control-label" for="add-config-key-select">Key Name</label>
                            <div class="controls">
                                <select id="add-config-key-select"class="input-large">
<?php
                            foreach($this->data['config_items'] as $n){
                                    echo '<option>' . $n->name . '</option>';
                            }
?>
                                </select>
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label" for="add-config-value-text">Value</label>
                            <div class="controls">
                                <input type="text" class="input-xlarge span2" id="add-config-value-text">
                            </div>
                        </div>
                    </fieldset>
                </form>
                </div>
                <div class="modal-footer">  
                    <div id="add-config-alert" class="alert alert-error"></div>
                    <a href="#" class="btn" data-dismiss="modal">Close</a>
                    <a href="#" id="add-config-modal-save" class="btn btn-primary">Add</a>
                </div>
            </div>
        </div>
        <!-- scripts -->
        <script>var schema_id = <?php echo $data['schema_id']; ?></script>
        <script src="global/js/jquery-1.7.2.min.js"></script>
        <script src="global/js/bootstrap/bootstrap.min.js"></script>
        <script src="global/js/schemas.js"></script>
    </body>
</html>