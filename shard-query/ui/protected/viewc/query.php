<!DOCTYPE html>
<html lang="en">  
    <head>
        <meta charset="utf-8">
        <title> <?php echo $this->data['title']; ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="author" content="">
        <link href="global/css/bootstrap/bootstrap.min.css" rel="stylesheet">
        <link href="global/css/codemirror.css" rel="stylesheet">
        <link href="global/css/app.css" rel="stylesheet">
        <link rel="shortcut icon" href="global/img/favicon.ico">
        <style>.CodeMirror {border: 2px inset #dee; background: white}</style>
    </head>
    <body data-spy="scroll" data-target=".subnav" data-offset="50">
        <div class="navbar navbar-fixed-top">
            <div class="navbar-inner">
                <div class="container"> <a class="brand">Shard Query</a>

                    <div class="nav-collapse">
                        <ul class="nav">
                            <li> <a href="schemas<?php if(isset($_GET['schema_id'])) echo "?schema_id=" . $this->data['schema_id']; ?>">Sharded Schemas</a>

                            </li>
                            <li class="active"> <a href="#">Query</a>

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
                	<h1>Query</h1>
                <p class="lead">Submit Query to Shard Query Engine</p>
                <div class="subnav">
                    <ul class="nav nav-pills">
                        <li class="active">
                            <a id="nav-submit" href="#submit">Submit Query</a>
                        </li>
                        <li>
                            <a id="nav-results" href="#results">Results</a>
                        </li>
                    </ul>
                </div>
            </header>
			<section>
                <div class="row">
    			 	<div class="span6">
    					<h2>Select Sharded Schema:</h2>
    				</div>
    				<div class="span4">
<?php	echo "<select id='query-schemata-select' style='height:40px;margin-left:-85px;margin-top:10px'>";
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
            <section id="submit"> 
                <div class="page-header">
                    <h1>SQL Select Query:
                    </h1>
                </div>
                <div class="row">
                    <div class="span12">
                        <div class="row">
                            <div class="span4">
                                <h3>Coordination Shard</h3>
                            </div>
                            <div class ="span3">
                                <select id='coord-shard-select' style="margin-left:-55px;margin-top:15px;">
                                    <option></option>
<?php  
                //print schemas
                foreach($this->data['coord_shards'] as $s){  
                    echo "<option value='" . $s->shard_name . "'>" . $s->shard_name . "</option>";
                }     
?>      
                                </select>
                            </div>
                        </div>
                        <form class="form-horizontal well">
                            <textarea id="sql-textarea"></textarea>
                        </form>   
                        <div class="row">
                            <div class="span2"> 
                                <input id="submit-button" class="btn btn-primary btn-large" type="button" value="Submit" style="margin-left:7px;">
                            </div>
                            <div class="span9"> 
                                <div class="progress progress-striped active"  style="margin-top:15px;margin-left:-30px;">
                                  <div id="progress-bar" class="bar" style="width: 0%;"></div>
                                </div>
                            </div>  
                             <div class="span1">
                                  <span id="perc-badge"class="badge badge-info hide">0.00%</span>
                             </div>
                            <div class="span1">
                                <div id="img-ajax-loader" class="hide">
                                    <img src="global/img/ajax-loader.gif" alt="Loading..." style="margin-top:7px;margin-left:12px;">  
                                </div>
                            </div>
                        </div> 
                        <button id="explain-button" class="btn btn-info">Explain Query</button>
                        <br />  
                        <div id="query-alert" class="alert hide"/>
                    </div>
                </div>
            </section>
            <section id="results">
                <div id="div-query-result" class="hide">
                    <div class="row">
                        <div class="span10">
                              <h1>Query Results</h1>
                        </div>
                    </div>
                    <table id="table-query-result" class="table table-striped">
                        <thead id="table-head-query-result"></thead>
                        <tbody id="table-body-query-result"></tbody>
                    </table>
                </div>
            </section>
        </div>
        <!-- /container -->
        <!-- explain modal-->
        <div class="modal hide fade" id="explain-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3>Select Explain</h3>
            </div>
            <div class="modal-body">
                <div id="explain-modal-body" class="alert alert-info"/>    
            </div>
            <div class="modal-footer">
                <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
            </div>
        </div>
        <!-- scripts -->
        <script>var schema_id = <?php echo $data['schema_id']; ?></script>
        <script src="global/js/jquery-1.7.2.min.js"></script>
        <script src="global/js/bootstrap/bootstrap.min.js"></script>
        <script src="global/js/codemirror/codemirror.js"></script>
        <script src="global/js/codemirror/mysql.js"></script>
        <script src="global/js/query.js"></script>
    </body>
</html>
