$(function () {
    $(document).ready(function () {

        //schema configure click handler
        $('td > button[action="config-schema"]').click(function () {
            schema_id = $(this).attr('schema-id');
            window.location = "schemas?schema_id=" + schema_id + "#config";
        });

        var id;
        var is_default_schema;
        //schema edit click handler
        $('td > button[action="edit-schema"]').click(function () {
             //hide alert
            $("#edit-schema-alert").hide();

            $.ajax({
                type: "GET",
                url: 'db/schemata/' + $(this).attr('schema-id'),
                dataType: "json",
                success: function (response) {
                    if (response.success) {
                        id = response.data.id;
                        is_default_schema = response.data.is_default_schema;
                        $("#edit-schema-name-text").val(response.data.schema_name);
                        $('#edit-schema-modal').modal('show');
                      
                    } else {
                        //show alert
                        $("#edit-schema-alert").empty();
                        $("#edit-schema-alert").append(response.message);
                        $("#edit-schema-alert").show();
                    }
                }
            });
        });
        //schema edit save
        $("#edit-schema-modal-save").click(function() {

            var schema = {};
            schema.id = id;
            schema.is_default_schema = is_default_schema;
            schema.schema_name = $("#edit-schema-name-text").val();

            $.ajax({
                type: "PUT",
                url: 'db/schemata/' + id,
                data: schema,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                        //show alert
                        $("#edit-schema-alert").empty();
                        $("#edit-schema-alert").append(response.message);
                        $("#edit-schema-alert").show();
                    }
                }
            });
        });

        //schema delete click handler
        $('td > button[action="delete-schema"]').click(function() {
            $('#confirmation-delete-alert').hide();
            id = $(this).attr('schema-id');
            table = 'schemata';
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
                        console.log(response.message);
                        //show alert
                        $("#confirmation-delete-alert").empty();
                        $("#confirmation-delete-alert").append(response.message);
                        $("#confirmation-delete-alert").show();
                    }
                }
            });
        });

       //add shard click handler
        $('button[action="add-schema"]').click(function() {
             //hide alert
            $("#add-schema-alert").hide();

            //clear
            $('#add-schema-form').each(function() {
                this.reset();
            });

            //show
            $('#add-schema-modal').modal('show');
        });

        //schema add
        $("#add-schema-modal-save").click(function() {
            var schema = {};
           
            schema.schema_name = $("#add-schema-name-text").val();
            schema.is_default_schema = 0;

            $.ajax({
                type: "POST",
                url: 'db/schemata',
                data: schema,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                         //show alert
                        $("#add-schema-alert").empty();
                        $("#add-schema-alert").append(response.message);
                        $("#add-schema-alert").show();
                    }
                }
            });
        });


        //config edit click handler
        $('td > button[action="edit-config"]').click(function () {
             //hide alert
            $("#edit-config-alert").hide();

            //get schema_id
            schema_config_id = $(this).attr('schema-config-id');
            $.ajax({
                type: "GET",
                url: 'db/schemata_config/' + schema_config_id,
                dataType: "json",
                success: function (response) {
                    if (response.success) {
                        id = response.data.id;
                        schema_id = response.data.schema_id;
                        $("#edit-config-key-select").val(response.data.var_name);
                        $("#edit-config-value-text").val(response.data.var_value);
                        $('#edit-config-modal').modal('show');
                      
                    } else {
                        //show alert
                        $("#edit-config-alert").empty();
                        $("#edit-config-alert").append(response.message);
                        $("#edit-config-alert").show();
                    }
                }
            });
        });

        //config edit save
        $("#edit-config-modal-save").click(function() {

            var config = {};
            config.id = id;
            config.schema_id = schema_id;
            config.var_name = $("#edit-config-key-select :selected").text();
            config.var_value = $("#edit-config-value-text").val();

            $.ajax({
                type: "PUT",
                url: 'db/schemata_config/' + id,
                data: config,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                        //show alert
                        $("#edit-config-alert").empty();
                        $("#edit-config-alert").append(response.message);
                        $("#edit-config-alert").show();
                    }
                }
            });
        });

        //config delete click handler
        $('td > button[action="delete-config"]').click(function() {
            $('#confirmation-config-alert').hide();
            id = $(this).attr('schema-config-id');
            table = 'schemata_config';
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
       
       //add config click handler
        $('button[action="add-config"]').click(function() {
             //hide alert
            $("#add-config-alert").hide();

            //clear
            $('#add-config-form').each(function() {
                this.reset();
            });

            //show
            $('#add-config-modal').modal('show');
        });

        //config add
        $("#add-config-modal-save").click(function() {
            
            var config = {};
            config.schema_id = schema_id;
            config.var_name = $("#add-config-key-select :selected").text();
            config.var_value = $("#add-config-value-text").val();

            $.ajax({
                type: "POST",
                url: 'db/schemata_config',
                data: config,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                         //show alert
                        $("#add-config-alert").empty();
                        $("#add-config-alert").append(response.message);
                        $("#add-config-alert").show();
                    }
                }
            });
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
