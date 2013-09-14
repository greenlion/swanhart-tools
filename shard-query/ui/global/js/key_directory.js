$(function() {
    $(document).ready(function() {
        
		$("#key-directory-schemata-select").change(function() {
			var schema_id = $("#key-directory-schemata-select :selected").val();
		 	window.location = "key-directory?schema_id=" + schema_id;
        });	

        var id;
        var table;

        //column delete click handler
        $('td > button[action="delete-column"]').click(function() {
            $('#confirmation-delete-alert').hide();
            id = $(this).attr('column-id');
            table = 'column_sequences';
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
       
        //add column click handler
        $('button[action="add-column"]').click(function() {
             //hide alert
            $("#add-column-alert").hide();

            //clear
            $('#add-column-form').each(function() {
                this.reset();
            });

            //show
            $('#add-column-modal').modal('show');
        });

        //column add
        $("#add-column-modal-save").click(function() {

            var column_sequence = {};
            column_sequence.schema_id = schema_id;
            column_sequence.sequence_type = $("#add-column-type-select").val();
            column_sequence.sequence_name = $("#add-column-name-text").val();
            column_sequence.next_value =  1;
            column_sequence.datatype =  $("#add-column-data-type-select").val();

            $.ajax({
                type: "POST",
                url: 'db/column_sequences',
                data: column_sequence,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                         //show alert
                        $("#add-columm-alert").empty();
                        $("#add-columm-alert").append(response.message);
                        $("#add-columm-alert").show();
                    }
                }
            });
        });
        
        //pagers
        $('#pager-list-prev').click(function(e){

            if(!$(this).hasClass('disabled'))
                window.location = "key-directory?schema_id=" + schema_id + "&page_num=" + (page_num - 1) + '#list-key-mapping';
        });

        $('#pager-list-next').click(function(e){

            if(!$(this).hasClass('disabled'))
                window.location = "key-directory?schema_id=" + schema_id + "&page_num=" + (page_num + 1) + '#list-key-mapping';
        });

        //list map delete click handler
        $('td > button[action="delete-list-key-map"]').click(function() {
            $('#confirmation-delete-alert').hide();
            id = $(this).attr('map-id');
            table = 'shard_map';
            $('#confirmation-delete-modal').modal('show');
        });

        //add list map click handler
        $('button[action="add-list-map"]').click(function() {
             //hide alert
            $("#add-list-map-alert").hide();

            //clear
            $('#add-list-map-form').each(function() {
                this.reset();
            });

            //show
            $('#add-list-map-modal').modal('show');
        });

        //column list map
        $("#add-list-map-modal-save").click(function() {

            var list_key = {};
            list_key.column_id = $("#add-list-map-column-select").val();
            list_key.key_value = $("#add-list-map-key-value-text").val();
            list_key.shard_id = $("#add-list-map-shard-select").val();

            $.ajax({
                type: "POST",
                url: 'db/shard_map',
                data: list_key,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                         //show alert
                        $("#add-list-map-alert").empty();
                        $("#add-list-map-alert").append(response.message);
                        $("#add-list-map-alert").show();
                    }
                }
            });
        });
    
        //range map delete click handler
        $('td > button[action="delete-range-key-map"]').click(function() {
            $('#confirmation-delete-alert').hide();
            id = $(this).attr('map-id');
            table = 'shard_range_map';
            $('#confirmation-delete-modal').modal('show');
        });

        //add range map click handler
        $('button[action="add-range-map"]').click(function() {
             //hide alert
            $("#add-range-map-alert").hide();

            //clear
            $('#add-range-map-form').each(function() {
                this.reset();
            });

            //show
            $('#add-range-map-modal').modal('show');
        });

        //column range map
        $("#add-range-map-modal-save").click(function() {

            var range_key = {};
            range_key.column_id = $("#add-range-map-column-select").val();
            range_key.key_min_value = $("#add-range-map-key-min_value-text").val();
            range_key.key_max_value = $("#add-range-map-key-max-value-text").val();
            range_key.shard_id = $("#add-range-map-shard-select").val();

            $.ajax({
                type: "POST",
                url: 'db/shard_range_map',
                data: range_key,
                contentType: 'json',
                dataType: 'json',
                success: function(response) {
                    if (response.success)
                        window.location.reload();
                    else{
                         //show alert
                        $("#add-range-map-alert").empty();
                        $("#add-range-map-alert").append(response.message);
                        $("#add-range-map-alert").show();
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
