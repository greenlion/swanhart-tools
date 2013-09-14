$(function() {
  $(document).ready(function() {
        //configure popover
        $(".btn-danger").popover();
        
        //redirect based on selected schmata
		$("#status-schemata-select").change(function() {
			var schema_id = $("#status-schemata-select :selected").val();
		 	window.location = "status?schema_id=" + schema_id;
		 });

        var id;
        var table;
        //shard edit click handler
        $('td > button[action="edit-shard"]').click(function() {
            //hide alert
            $("#edit-shard-alert").hide();

            //get shard-id
            shard_id = $(this).attr('shard-id');
            $.ajax({
                type: "GET",
                url: 'db/shards/' + shard_id,
                dataType: "json",
                success: function(response) {
                    if (response.success) {

						schema_id = response.data.schema_id;
                        $("#edit-shard-name-text").val(response.data.shard_name);
                        $("#edit-shard-host-text").val(response.data.host);
						$("#edit-shard-port-text").val(response.data.port);
                        $("#edit-shard-rdbms-select").val(response.data.shard_rdbms);
						$("#edit-coord-shard-checkbox").prop('checked', (response.data.coord_shard == 1 ? true: false));
                        $("#edit-accepts-new-rows-checkbox").prop('checked', (response.data.accepts_new_rows == 1 ? true: false));
                        $("#edit-enabled-checkbox").prop('checked', (response.data.enabled == 1 ? true: false));
                        $("#edit-username-text").val(response.data.username);
                        $("#edit-password-text").val(response.data.password);
                        $("#edit-database-text").val(response.data.db);
                        id = response.data.id;
                        $('#edit-shard-modal').modal('show');
                    } else {
                        //show alert
                        $("#edit-shard-alert").empty();
                        $("#edit-shard-alert").append(response.message);
                        $("#edit-shard-alert").show();
                    }
                }
            });
        });
        //shard edit save
        $("#edit-shard-modal-save").click(function() {

            var shard = {};
            shard.id = id;
			shard.schema_id = schema_id;
            shard.shard_name = $("#edit-shard-name-text").val();
            shard.host = $("#edit-shard-host-text").val();
			shard.port = $("#edit-shard-port-text").val();
			shard.shard_rdbms = $("#edit-shard-rdbms-select :selected").text();
			shard.coord_shard = $("#edit-coord-shard-checkbox").is(':checked') ? 1: 0;
			shard.accepts_new_rows = $("#edit-accepts-new-rows-checkbox").is(':checked') ? 1: 0;
            shard.enabled = $("#edit-enabled-checkbox").is(':checked') ? 1: 0;
            shard.username =  $("#edit-username-text").val();
            shard.password =  $("#edit-password-text").val();
 			shard.db =   $("#edit-database-text").val();

            $.ajax({
                type: "PUT",
                url: 'db/shards/' + id,
                data: shard,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                    	window.location.reload();
                    else{
                        //show alert
                        $("#edit-shard-alert").empty();
                        $("#edit-shard-alert").append(response.message);
                        $("#edit-shard-alert").show();
                    }
                }
            });
        });

        //shard delete click handler
        $('td > button[action="delete-shard"]').click(function() {
            $('#confirmation-delete-alert').hide();
            id = $(this).attr('shard-id');
            table = 'shards';
            $('#confirmation-delete-modal').modal('show');
        });

        //delete confirmation confirm
        $("#continue-delete-button").click(function() {
            //send DELETE
            $.ajax({
                type: 'DELETE',
                url: 'db/' + table + '/' + id,
                dataType: "json",
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                        //show alert
                        $("#confirmation-delete-alert").empty();
                        $("#confirmation-delete-alert").append(response.message);
                        $("#confirmation-delete-alert").show();
                    }
                }
            });
        });

        //add shard click handler
        $('button[action="add-shard"]').click(function() {
             //hide alert
            $("#add-shard-alert").hide();

            //clear
            $('#add-shard-form').each(function() {
                this.reset();
            });

            //show
            $('#add-shard-modal').modal('show');
        });

        //shard add
        $("#add-shard-modal-save").click(function() {

            var shard = {};
            shard.id = id;
            shard.schema_id = schema_id;
            shard.shard_name = $("#add-shard-name-text").val();
            shard.host = $("#add-shard-host-text").val();
            shard.port = $("#add-shard-port-text").val();
            shard.shard_rdbms = $("#add-shard-rdbms-select :selected").text();
            shard.coord_shard = $("#add-coord-shard-checkbox").is(':checked') ? 1: 0;
            shard.accepts_new_rows = $("#add-accepts-new-rows-checkbox").is(':checked') ? 1: 0;
            shard.enabled = $("#add-enabled-checkbox").is(':checked') ? 1: 0;
            shard.username =  $("#add-username-text").val();
            shard.password =  $("#add-password-text").val();
            shard.db =   $("#add-database-text").val();

            $.ajax({
                type: "POST",
                url: 'db/shards',
                data: shard,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                         //show alert
                        $("#add-shard-alert").empty();
                        $("#add-shard-alert").append(response.message);
                        $("#add-shard-alert").show();
                    }
                }
            });
        });

        //gearman edit click handler
        $('td > button[action="edit-gearman"]').click(function() {
            //hide alert
            $("#edit-gearman-alert").hide();

            //get gearman-id
            gearman_id = $(this).attr('gearman-id');
            $.ajax({
                type: "GET",
                url: 'db/gearman_job_servers/' + gearman_id,
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        if(response.data.local  == 1){
                            $("#edit-gearman-hostname-local-text").val(response.data.hostname);
                            $("#edit-gearman-port-local-text").val(response.data.port);                
                            $("#edit-gearman-enabled-local-checkbox").prop('checked', (response.data.enabled == 1 ? true: false));
                            $('#edit-gearman-tab a[href="#gearman-edit-local-tab"]').tab('show'); 
                        }else{
                            $("#edit-gearman-hostname-remote-text").val(response.data.hostname);
                            $("#edit-gearman-port-remote-text").val(response.data.port);                
                            $("#edit-gearman-enabled-remote-checkbox").prop('checked', (response.data.enabled == 1 ? true: false));
                            $('#edit-gearman-tab a[href="#gearman-edit-remote-tab"]').tab('show'); 
                        }

                        id = response.data.id;
                        $('#edit-gearman-modal').modal('show');
                    } else {
                        //show alert
                        $("#edit-gearman-alert").empty();
                        $("#edit-gearman-alert").append(response.message);
                        $("#edit-gearman-alert").show();
                    }
                }
            });
        });

        //gearman edit save
        $("#edit-gearman-modal-save").click(function() {

            var gearman = {};
            gearman.schema_id = schema_id;
            if($('#gearman-edit-local-tab').hasClass('active')){
                gearman.hostname = $("#edit-gearman-hostname-local-text").val();
                gearman.port = $("#edit-gearman-port-local-text").val();
                gearman.enabled =  $("#edit-gearman-enabled-local-checkbox").is(':checked') ? 1: 0;
                gearman.local = 1;
            }else{
                gearman.hostname = $("#edit-gearman-hostname-remote-text").val();
                gearman.port = $("#edit-gearman-port-remote-text").val();
                gearman.enabled =  $("#edit-gearman-enabled-remote-checkbox").is(':checked') ? 1: 0;
                gearman.local = 0;
            }

            $.ajax({
                type: "PUT",
                url: 'db/gearman_job_servers/' + id,
                data: gearman,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                        //show alert
                        $("#edit-gearman-alert").empty();
                        $("#edit-gearman-alert").append(response.message);
                        $("#edit-gearman-alert").show();
                    }
                }
            });
        });

        //delete gearman click handler
        $('td > button[action="delete-gearman"]').click(function() {
             $('#confirmation-delete-alert').hide();
            id = $(this).attr('gearman-id');
            table = 'gearman_job_servers';
            $('#confirmation-delete-modal').modal('show');
        });

        //add gearman server click handler
        $('button[action="add-gearman"]').click(function() {
            //hide alert
            $("#add-gearman-alert").hide();

            //clear
            $('#add-gearman-form').each(function() {
                this.reset();
            });
            $('#add-gearman-modal').modal('show');

        });

        //add gearmand server
        $("#add-gearman-modal-save").click(function() {

            var gearman = {};
            gearman.schema_id = schema_id;
            if($('#gearman-add-local-tab').hasClass('active')){

                gearman.hostname = $("#add-gearman-hostname-local-text").val();
                gearman.port = $("#add-gearman-port-local-text").val();
                gearman.enabled =  $("#add-gearman-enabled-local-checkbox").is(':checked') ? 1: 0;
                gearman.local = 1;
            }else{
                gearman.hostname = $("#add-gearman-hostname-remote-text").val();
                gearman.port = $("#add-gearman-port-remote-text").val();
                gearman.enabled =  $("#add-gearman-enabled-remote-checkbox").is(':checked') ? 1: 0;
                gearman.local = 0;
            }

            //Send POST
            $.ajax({
                type: "POST",
                url: 'db/gearman_job_servers',
                data: gearman,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                        //show alert
                        $("#add-gearman-alert").empty();
                        $("#add-gearman-alert").append(response.message);
                        $("#add-gearman-alert").show();
                    }
                }
            });
        });

        //function edit click handler
        $('td > button[action="edit-function"]').click(function() {
             //hide alert
            $("#edit-function-alert").hide();

            //get function_id
            function_id = $(this).attr('function-id');
            $.ajax({
                type: "GET",
                url: 'db/gearman_functions/' + function_id,
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        //build form
                        schema_id = response.data.schema_id;
                        $("#edit-function-function-select").val(response.data.function_name_id);
                        $("#edit-function-worker_count-text").val(response.data.worker_count);
                        $("#edit-function-enabled-checkbox").prop('checked', (response.data.enabled == 1 ? true: false));
                        id = response.data.id;
                        $('#edit-function-modal').modal('show');
                    } else {
                         //show alert
                        $("#edit-gearman-alert").empty();
                        $("#edit-gearman-alert").append(response.message);
                        $("#edit-gearman-alert").show();
                    }
                }
            });
        });

        //function edit save
        $("#edit-function-modal-save").click(function() {

            //build obj
            var gearman_function = {};
            gearman_function.id = id;
            gearman_function.schema_id = schema_id;
            gearman_function.function_name_id = $("#edit-function-function-select").val();
            gearman_function.worker_count = $("#edit-function-worker_count-text").val();
            gearman_function.enabled = $('#edit-function-enabled-checkbox').is(':checked') ? 1: 0;

            $.ajax({
                type: "PUT",
                url: 'db/gearman_functions/' + id,
                data: gearman_function,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                        //show alert
                        $("#edit-function-alert").empty();
                        $("#edit-function-alert").append(response.message);
                        $("#edit-function-alert").show();
                    }
                }
            });
        });

        //add function click handler
        $('button[action="add-function"]').click(function() {
             //hide alert
            $("#add-function-alert").hide();

            //clear
            $('#add-function-form').each(function() {
                this.reset();
            });
            $('#add-function-modal').modal('show');

        });

        //add function server
        $("#add-function-modal-save").click(function() {

            //build obj
            var gearman_function = {};
            gearman_function.schema_id = schema_id;
            gearman_function.function_name_id = $("#add-function-function-select").val();
            gearman_function.worker_count = $("#add-function-worker_count-text").val();
            gearman_function.enabled = $('#add-function-enabled-checkbox').is(':checked') ? 1: 0;

            $.ajax({
                type: "POST",
                url: 'db/gearman_functions',
                data: gearman_function,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                        //show alert
                        $("#add-function-alert").empty();
                        $("#add-function-alert").append(response.message);
                        $("#add-function-alert").show();
                    }
                }
            });
        });

        //function delete click handler
        $('td > button[action="delete-function"]').click(function() {
            $('#confirmation-delete-alert').hide();
            id = $(this).attr('function-id');
            table = 'gearman_functions';
            $('#confirmation-delete-modal').modal('show');
        });
    });
});

$(document).scroll(function() {
    // If has not activated (has no attribute "data-top"
    if (!$('.subnav').attr('data-top')) {
        // If already fixed, then do nothing
        if ($('.subnav').hasClass('subnav-fixed')) return;
        // Remember top position
        var offset = $('.subnav').offset();
        $('.subnav').attr('data-top', offset.top);
    }

    if ($('.subnav').attr('data-top') - $('.subnav').outerHeight() <= $(this).scrollTop())
    $('.subnav').addClass('subnav-fixed');
    else
    $('.subnav').removeClass('subnav-fixed');
});
