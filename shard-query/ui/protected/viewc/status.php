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
    <body data-spy="scroll" data-target=".subnav" data-offset="50">
        <div class="navbar navbar-fixed-top">
            <div class="navbar-inner">
                <div class="container"> 
                	<a class="brand">Shard Query</a>
                    <div class="nav-collapse">
                        <ul class="nav">
						    <li> <a href="schemas<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Sharded Schemas</a>
							
					        </li>
                            <li> <a href="query<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Query</a>

                            </li>
                            <li class="active"> <a href="#">Status</a>

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
                	<h1>Status</h1>
                <p class="lead">Shard and Gearman Servers & Worker Status</p>
  				<div class="subnav">
                    <ul class="nav nav-pills">
                     	<li class="active">
                            <a href="#shards">Shards</a>
                        </li>
                        <li>
                            <a href="#gearman-servers">Gearman Servers</a>
                        </li>
                        <li>
                            <a href="#gearman-functions">Gearman Functions</a>
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
<?php	echo "<select id='status-schemata-select' style='height:40px;margin-left:-85px;margin-top:10px'>";
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
			<section id="shards">
				<div class="page-header">
					<h1>Shards
					</h1>
				</div>
				<table class="table table-striped">
			
<?php
		//print table if we have shards
		if(count($this->data['shards']) > 0){
			echo '<thead>';
					echo '<tr>';
						echo '<th>Shard Name</th>';
						echo '<th>Type</th>';
						echo '<th>Username</th>';
						echo '<th>Enabled</th>';
						echo '<th>Availability</th>';
					echo '</tr>';
					echo '</thead>';
				echo '<tbody>';
		}

		//print table
		foreach($this->data['shards'] as $s){

			echo "<tr>";
			echo "<td>" . $s->shard_name . "</td>";
			echo "<td>" . $s->shard_rdbms. "</td>";
			echo "<td>" . $s->username . "</td>";
			echo "<td>" . ($s->enabled == 1 ? 'Yes' : 'No') . "</td>";
		
			$pdo_dsn = util::build_PDO_DSN($s);	
			try{
 				$pdo = new PDO($pdo_dsn, $s->username, $s->password);
				echo '<td><button class="btn btn-success" href="#">OK!</button></td>';
  			}catch(PDOException $e){
				echo '<td><button class="btn btn-danger" rel="popover" data-content="' . $e->getMessage() . '" data-original-title="Message"  href="#">Failed To Connect</button></td>';
        		}
			echo '<td><button class="btn btn-primary" shard-id=' . $s->id . ' action="edit-shard" type="button">Edit</button></td>';
			echo '<td><button class="btn btn-danger" shard-id=' . $s->id . ' action="delete-shard" type="button">Remove</button></td>';
			echo "</tr>\n";
		}

		if(count($this->data['shards']) > 0)
			echo '</tbody>';
?>
				</table>
				<button class="btn btn-primary" action="add-shard" type="button">Add Shard</button>	
			</section>	
			<section id="gearman-servers">
		  		<div class="page-header">
					<h1>Gearman Servers
					</h1>
		    	</div>
				<table class="table table-striped">

<?php
		//print table if we have servers
		if(count($this->data['gearman-job-servers']) > 0){
			echo '<thead>';
					echo '<tr>';
						echo '<th>Hostname</th>';
						echo '<th>Port</th>';
						echo '<th>Enabled</th>';
						echo '<th>Function</th>';
						echo '<th>In Queue</th>';
						echo '<th>Jobs Running</th>';
						echo '<th>Capable Workers</th>';
					echo '</tr>';
					echo '</thead>';
				echo '<tbody>';
		}

		//print table
		foreach($this->data['gearman-job-servers'] as $g){

			echo '<tr>';
			//get gearmand status
			$res = $this->gearman_status($g);

			if($res['success']){
				
				$function_count = count($res['data']);
				echo "<td rowspan=$function_count>" . $g->hostname . "</td>";
				echo "<td rowspan=$function_count>" . $g->port . "</td>";
				echo "<td rowspan=$function_count>" . ($g->enabled == 1 ? 'Yes' : 'No') . "</td>";
				$count = 0;
				foreach($res['data'] as $key => $func){

					echo "<td>" . $key . "</td>";
					echo "<td" . ($func['in_queue'] > 0 ? ' style="background-color:orange;">' : '>') . $func['in_queue'] . "</td>";
					echo "<td>" . $func['jobs_running'] . "</td>";
					echo "<td" . ($func['capable_workers'] == 0 ? ' style="background-color:red;">' : '>') . $func['capable_workers'] . "</td>";
					
					
					if($count == 0){
						echo "<td rowspan=$function_count>". '<button class="btn btn-primary" gearman-id=' . $g->id . ' action="edit-gearman" type="button">Edit</button></td>';
						echo "<td rowspan=$function_count>". '<button class="btn btn-danger" gearman-id=' . $g->id . ' action="delete-gearman" type="button">Remove</button></td>'; 
						$count++;
					}
					echo "</tr>\n";
				}
			}else{
				echo "<td>" . $g->hostname . "</td>";
				echo "<td>" . $g->port . "</td>";
				echo "<td>" . ($g->enabled == 1 ? 'Yes' : 'No') . "</td>";
				echo "<td><div class=\"alert alert-error\">" . $res['data'] . "</div></td>";
				echo "<td></td>";
				echo "<td></td>";
				echo "<td></td>";
				echo '<td><button class="btn btn-primary" gearman-id=' . $g->id . ' action="edit-gearman" type="button">Edit</button></td>';
				echo '<td><button class="btn btn-danger" gearman-id=' . $g->id . ' action="delete-gearman" type="button">Remove</button></td>'; 
				echo "</tr>\n";
			}		
		}

		if(count($this->data['gearman-job-servers']) > 0)
			echo '</tbody>';
?>
					</table>
				<button class="btn btn-primary" action="add-gearman" type="button">Add Gearman Job Server</button>
			</section>	
			<section id="gearman-functions">
				<div class="page-header">
		            <h1>Gearman Functions
		            </h1>
			  	</div>
				<table class="table table-striped">
			
<?php
		//print table if we have functions
		if(count($this->data['gearman-functions']) > 0){
			echo '<thead>';
					echo '<tr>';
						echo '<th>Functions</th>';
						echo '<th>Worker Count</th>';
						echo '<th>Enabled</th>';
					echo '</tr>';
					echo '</thead>';
				echo '<tbody>';
		}

		//print table
		foreach($this->data['gearman-functions'] as $f){

			echo "<tr>";
			echo "<td>" . $this->data['gearman_function_names_map'][$f->function_name_id] . "</td>";
			echo "<td>" . $f->worker_count . "</td>";	
			echo "<td>" . ($f->enabled == 1 ? 'Yes' : 'No') . "</td>";	
			echo '<td><button class="btn btn-primary" function-id=' . $f->id . ' action="edit-function" type="button">Edit</button></td>';
			echo '<td><button class="btn btn-danger" function-id=' . $f->id . ' action="delete-function" type="button">Delete</button></td>';
			echo "</tr>\n";
		}

		if(count($this->data['gearman-functions']) > 0)
			echo '</tbody>';
?>

				</table>
				<button class="btn btn-primary" action="add-function" type="button">Add Gearman Function</button>
			</section>
			<br><br>
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

		<!-- shard edit modal -->
		<div class="modal hide fade" id="edit-shard-modal">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
				<h3>Shard Config</h3>
			</div>
			<div class="modal-body">
			    <form class="form-horizontal">
			        <fieldset>
			            <div class="control-group">
			                <label class="control-label" for="edit-shard-name-text">Shard Name</label>
			                <div class="controls">
			                    <input type="text" class="input-xlarge span2" id="edit-shard-name-text">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="edit-shard-host-text">Host</label>
			                <div class="controls">
			                    <input type="text" class="input-xlarge span" id="edit-shard-host-text">
			                </div>
			            </div>
						<div class="control-group">
				                <label class="control-label" for="edit-shard-port-text">Port</label>
				                <div class="controls">
				                    <input type="text" class="input-small" id="edit-shard-port-text">
				                </div>
				            </div>
			            <div class="control-group">
			                <label class="control-label" for="edit-shard-rdbms-select">RDBMS</label>
			                <div class="controls">
			                    <select id="edit-shard-rdbms-select"class="input-small">
			                        <option>mysql</option>
			                        <option>pdo-mysql</option>
			                        <option>pdo-pgsql</option>
			                    </select>
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="edit-coord-shard-checkbox">Coord Shard</label>
			                <div class="controls">
			                    <input type="checkbox" id="edit-coord-shard-checkbox">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="edit-accept-new-rows-checkbox">Accept New Rows</label>
			                <div class="controls">
			                    <input type="checkbox" id="edit-accept-new-rows-checkbox">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="edit-enabled-checkbox">Enabled</label>
			                <div class="controls">
			                    <input type="checkbox" id="edit-enabled-checkbox">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="edit-username-text">Username</label>
			                <div class="controls">
			                    <input type="text" class="input-xlarge span2" id="edit-username-text">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="edit-password-text">Password</label>
			                <div class="controls">
			                    <input type="password" class="input-xlarge span2" id="edit-password-text">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="edit-database-text">Database</label>
			                <div class="controls">
			                    <input type="text" class="input-xlarge span2" id="edit-database-text">
			                </div>
			            </div>
			        </fieldset>
			    </form>

			</div>
			<div class="modal-footer">  
				<div id="edit-shard-alert" class="alert alert-error"></div>
				<a href="#" class="btn" data-dismiss="modal">Close</a>
				<a href="#" id="edit-shard-modal-save" class="btn btn-primary">Save changes</a>
			</div>
		</div>
		<!-- /shard edit modal -->

		<!-- shard add modal -->
		<div class="modal hide fade" id="add-shard-modal">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
				<h3>Add Shard</h3>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="add-shard-form">
					<fieldset>
			            <div class="control-group">
			                <label class="control-label" for="add-shard-name-text">Shard Name</label>
			                <div class="controls">
			                    <input type="text" class="input-xlarge span2" id="add-shard-name-text">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="add-shard-host-text">Host</label>
			                <div class="controls">
			                    <input type="text" class="input-xlarge span" id="add-shard-host-text">
			                </div>
			            </div>
						<div class="control-group">
				                <label class="control-label" for="add-shard-port-text">Port</label>
				                <div class="controls">
				                    <input type="text" class="input-small" id="add-shard-port-text">
				                </div>
				            </div>
			            <div class="control-group">
			                <label class="control-label" for="add-shard-rdbms-select">RDBMS</label>
			                <div class="controls">
			                    <select id="add-shard-rdbms-select"class="input-small">
			                        <option>mysql</option>
			                        <option>pdo-mysql</option>
			                        <option>pdo-pgsql</option>
			                    </select>
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="add-coord-shard-checkbox">Coord Shard</label>
			                <div class="controls">
			                    <input type="checkbox" id="add-coord-shard-checkbox">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="add-accept-new-rows-checkbox">Accept New Rows</label>
			                <div class="controls">
			                    <input type="checkbox" id="add-accept-new-rows-checkbox">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="add-enabled-checkbox">Enabled</label>
			                <div class="controls">
			                    <input type="checkbox" id="add-enabled-checkbox" checked>
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="add-username-text">Username</label>
			                <div class="controls">
			                    <input type="text" class="input-xlarge span2" id="add-username-text">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="add-password-text">Password</label>
			                <div class="controls">
			                    <input type="password" class="input-xlarge span2" id="add-password-text">
			                </div>
			            </div>
			            <div class="control-group">
			                <label class="control-label" for="add-database-text">Database</label>
			                <div class="controls">
			                    <input type="text" class="input-xlarge span2" id="add-database-text">
			                </div>
			            </div>
					</fieldset>
				</form>
			</div>
			<div class="modal-footer">
				<div id="add-shard-alert" class="alert alert-error"></div>
				<a href="#" class="btn" data-dismiss="modal">Close</a>
				<a href="#" id="add-shard-modal-save" class="btn btn-primary">Add</a>
			</div>
		</div>
		<!-- /shard add modal -->

		<!-- edit gearman server  modal -->
		<div class="modal hide" id="edit-gearman-modal">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">x</button>
				<h3>Edit Gearman Job Server</h3>
			</div>
			<div class="modal-body">
				<div class="tabbable"> <!-- Only required for left/right tabs -->
					<ul id="edit-gearman-tab" class="nav nav-tabs">
						<li class="active"><a href="#gearman-edit-local-tab" data-toggle="tab">Local</a></li>
						<li><a href="#gearman-edit-remote-tab" data-toggle="tab">Remote</a></li>
					</ul>
					<div class="tab-content">
						<div class="tab-pane active" id="gearman-edit-local-tab">
							<form class="form-horizontal" id="edit-gearman-form">
								<fieldset>
									<div class="control-group">
										<label class="control-label" for="edit-gearman-hostname-local-text">Hostname</label>
										<div class="controls">
											<input type="text" class="input-xlarge span2" id="edit-gearman-hostname-local-text" value="localhost" disabled>
										</div>
									</div>
									<div class="control-group">
										<label class="control-label" for="edit-gearman-port-local-text">Port</label>
										<div class="controls">
											<input type="text" class="input-xlarge span1" id="edit-gearman-port-local-text" value="7000" disabled>
										</div>
									</div>
						            <div class="control-group">
						                <label class="control-label" for="edit-gearman-enabled-local-checkbox">Enabled</label>
						                <div class="controls">
						                    <input type="checkbox" checked="true" id="edit-gearman-enabled-local-checkbox"> <span class="help-block">Local instances are automatically managed</span>
						                </div>
						            </div>

								</fieldset>
							</form>
						</div>
						<div class="tab-pane" id="gearman-edit-remote-tab">
							<form class="form-horizontal" id="edit-gearman-form">
								<fieldset>
									<div class="control-group">
										<label class="control-label" for="edit-gearman-hostname-remote-text">Hostname</label>
										<div class="controls">
											<input type="text" class="input-xlarge span2" id="edit-gearman-hostname-remote-text" value="remotehost">
										</div>
									</div>
									<div class="control-group">
										<label class="control-label" for="edit-gearman-port-remote-text">Port</label>
										<div class="controls">
											<input type="text" class="input-xlarge span1" id="edit-gearman-port-remote-text" value="7000">
										</div>
									</div>
						            <div class="control-group">
						                <label class="control-label" for="edit-gearman-enabled-remote-checkbox">Enabled</label>
						                <div class="controls">
						                    <input type="checkbox" checked="true" id="edit-gearman-enabled-remote-checkbox">
						                </div>
						            </div>
								</fieldset>
							</form>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<div id="edit-gearman-alert" class="alert alert-error"></div>
				<a href="#" class="btn" data-dismiss="modal">Close</a>
				<a href="#" id="edit-gearman-modal-save" class="btn btn-primary">Save</a>
			</div>
		</div>

		<!-- add gearman server  modal -->
		<div class="modal hide" id="add-gearman-modal">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">x</button>
				<h3>Add Gearman Job Server</h3>
			</div>
			<div class="modal-body">
				<div class="tabbable"> <!-- Only required for left/right tabs -->
					<ul class="nav nav-tabs">
						<li class="active"><a href="#gearman-add-local-tab" data-toggle="tab">Local</a></li>
						<li><a href="#gearman-add-remote-tab" data-toggle="tab">Remote</a></li>
					</ul>
					<div class="tab-content">
						<div class="tab-pane active" id="gearman-add-local-tab">
							<form class="form-horizontal" id="add-gearman-form">
								<fieldset>
									<div class="control-group">
										<label class="control-label" for="add-gearman-hostname-local-text">Hostname</label>
										<div class="controls">
											<input type="text" class="input-xlarge span2" id="add-gearman-hostname-local-text" value="localhost" disabled>
										</div>
									</div>
									<div class="control-group">
										<label class="control-label" for="add-gearman-port-local-text">Port</label>
										<div class="controls">
											<input type="text" class="input-xlarge span1" id="add-gearman-port-local-text" value="7000">	
										</div>
									</div>
						            <div class="control-group">
						                <label class="control-label" for="add-gearman-enabled-local-checkbox">Enabled</label>
						                <div class="controls">
						                    <input type="checkbox" checked="true" id="add-gearman-enabled-local-checkbox"> <span class="help-block">Local instances are automatically managed</span>
						                </div>
						            </div>

								</fieldset>
							</form>
						</div>
						<div class="tab-pane" id="gearman-add-remote-tab">
							<form class="form-horizontal" id="add-gearman-form">
								<fieldset>
									<div class="control-group">
										<label class="control-label" for="add-gearman-hostname-remote-text">Hostname</label>
										<div class="controls">
											<input type="text" class="input-xlarge span2" id="add-gearman-hostname-remote-text" value="remotehost">
										</div>
									</div>
									<div class="control-group">
										<label class="control-label" for="add-gearman-port-remote-text">Port</label>
										<div class="controls">
											<input type="text" class="input-xlarge span1" id="add-gearman-port-remote-text" value="7000">
										</div>
									</div>
						            <div class="control-group">
						                <label class="control-label" for="add-gearman-enabled-remote-checkbox">Enabled</label>
						                <div class="controls">
						                    <input type="checkbox" checked="true" id="add-gearman-enabled-remote-checkbox">
						                </div>
						            </div>
								</fieldset>
							</form>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<div id="add-gearman-alert" class="alert alert-error"></div>
				<a href="#" class="btn" data-dismiss="modal">Close</a>
				<a href="#" id="add-gearman-modal-save" class="btn btn-primary">Add</a>
			</div>
		</div>

		<!-- edit gearman function  modal -->
		<div class="modal hide" id="edit-function-modal">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">x</button>
				<h3>Edit Gearman Function</h3>
			</div>
			<div class="modal-body">
				<form class="form-horizontal">
					<fieldset>
						<div class="control-group">
							<label class="control-label" for="edit-function-function-select">Function</label>
							<div class="controls">
							   <select id="edit-function-function-select"class="input-large">
<?php
							foreach($this->data['gearman_function_names'] as $n){
									echo '<option value=' . $n->id . '>' . $n->function_name .  '</option>';
							}
?>
			                    </select>
							</div>
						</div>
						<div class="control-group">
							<label class="control-label" for="edit-function-worker_count-text">Worker Count</label>
							<div class="controls">
								<input type="text" class="input-xlarge span1" id="edit-function-worker_count-text" value="10">
							</div>
						</div>
						<div class="control-group">
							<label class="control-label" for="edit-function-enabled-checkbox">Enabled</label>
							<div class="controls">
	 							<input type="checkbox" id="edit-function-enabled-checkbox"> 
							</div>
						</div>
					</fieldset>
				</form>
				</div>
			<div class="modal-footer">
				<div id="edit-function-alert" class="alert alert-error"></div>
				<a href="#" class="btn" data-dismiss="modal">Close</a>
				<a href="#" id="edit-function-modal-save" class="btn btn-primary">Save</a>
			</div>
		</div>

		<!-- add gearman function modal -->
		<div class="modal hide" id="add-function-modal">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">x</button>
				<h3>Add Gearman Function</h3>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="add-function-form">
					<fieldset>
						<div class="control-group">
							<label class="control-label" for="add-function-function-select">Function</label>
							<div class="controls">
							   <select id="add-function-function-select"class="input-large">
<?php
							foreach($this->data['gearman_function_names'] as $n){
									echo '<option value=' . $n->id . '>' . $n->function_name .  '</option>';
							}
?>
			                  </select>
							</div>
						</div>
						<div class="control-group">
							<label class="control-label" for="add-function-worker_count-text">Worker Count</label>
							<div class="controls">
								<input type="text" class="input-xlarge span1" id="add-function-worker_count-text" value="10">
							</div>
						</div>
						<div class="control-group">
							<label class="control-label" for="add-function-enabled-checkbox">Enabled</label>
							<div class="controls">
	 							<input type="checkbox" checked="true" id="add-function-enabled-checkbox"> 
							</div>
						</div>
					</fieldset>
				</form>
			</div>
			<div class="modal-footer">
				<div id="add-function-alert" class="alert alert-error"></div>
				<a href="#" class="btn" data-dismiss="modal">Close</a>
				<a href="#" id="add-function-modal-save" class="btn btn-primary">Add</a>
			</div>
		</div>
		<!-- scripts -->
		<script>var schema_id = <?php echo $data['schema_id']; ?></script>
        <script src="global/js/jquery-1.7.2.min.js"></script>
        <script src="global/js/bootstrap/bootstrap.min.js"></script>
        <script src="global/js/status.js"></script>
    </body>
</html>
