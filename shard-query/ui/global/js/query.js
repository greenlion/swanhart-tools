$(function () {

    $(document).ready(function () {

        var check = null;
        var job_id = null;
        var schema_id = null;

        //redirect based on selected schmata
        $("#query-schemata-select").change(function() {
            var schema_id = $("#query-schemata-select :selected").val();
            window.location = "query?schema_id=" + schema_id;
         });

        //init codemirror
        var editor = CodeMirror.fromTextArea(document.getElementById("sql-textarea"), {
            mode: "text/x-mysql",
            tabMode: "indent",
            lineNumbers: true,
        });

        //explain query
        $("#explain-button").click(function () {
        
            var post = {};
            post.sql = editor.getValue();
            post.async = true;
            var schema_id = $("#query-schemata-select option:selected").val();

            //send POST to API
            $.ajax({
                url: 'query/explain/' + schema_id,
                dataType: "json",
                data: post,
                type: "POST",
                success: function (response) {
                     if (response.success) {
                        $('#explain-modal-body').empty();
                        $('#explain-modal-body').append(response.message);
                        $('#explain-modal').modal('show');
                    }
                }
            });
        });

        //submit query
        $("#submit-button").click(function () {
            var post = {};
            post.sql = editor.getValue();
            post.async = true;
            post.coord_name = $("#coord-shard-select option:selected").val();
            schema_id = $("#query-schemata-select option:selected").val();

            //send POST to API
            $.ajax({
                url: 'query/submit/' + schema_id,
                dataType: "json",
                data: post,
                type: "POST",
                success: function (response) {

                    if (response.success) {
                        job_id = response.data;

                       //start animated progress bar
                        if (check == null) {
                            check = setInterval(function () {
                                update_async_progress(job_id);
                                }, 1000);
                        }

                        //show ajax loader
                        $('#img-ajax-loader').show();
                        //show perc badge
                        $('#perc-badge').show();
                        //disable submit
                        $("#submit-button").attr("disabled", '');
                        //hide errors
                        $("#query-alert").hide();          

                    }else{
                        //show alert
                        $("#query-alert").addClass('alert-error');
                        $("#query-alert").empty();
                        $("#query-alert").append(response.message);
                        $("#query-alert").show();
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                     //show alert
                    $("#query-alert").addClass('alert-error');
                    $("#query-alert").empty();
                    $("#query-alert").append("Error Submitting Query. Please contact administrator!");
                    $("#query-alert").show();               
                }
            });

            //hide results
            $("#div-query-result").addClass("hide");

            //focus on submit
            $('#nav-submit')[0].click();
        });

        function update_async_progress(){

            //send GET to API
            $.ajax({
                url: 'query/async_completion_perc/'  + schema_id + '/' + job_id,
                dataType: "json",
                type: "GET",
                success: function (response) {

                    if (response.success) {
                        job_status = response.message;

                        if(job_status == 'error'){
                            //stop refreash
                            clearInterval(check);
                            check = null;
                           
                            //show error
                            $("#query-alert").addClass('alert-error');
                            $("#query-alert").empty();
                            $("#query-alert").append(response.message);
                            $("#query-alert").show();

                            //disable ajax loader
                            $('#img-ajax-loader').hide();

                            //enable submit
                            $("#submit-button").removeAttr("disabled"); 
                        }

                        $("#progress-bar").css('width', response.data + '%');
                        $("#perc-badge").html(response.data + '%');

                        if(response.data>= 100){
                            clearInterval(check);
                            check = null;
                            fetch_results();
                        }
                    }            
                }
            });
        }

        var fetch_attempts = 0;
        function fetch_results(){
            //check fetch retry limit
            fetch_attempts +=1;
            if(fetch_attempts > 10){
                
                //show alert
                $("#edit-shard-alert").empty();
                $("#edit-shard-alert").append(response.message);
                $("#edit-shard-alert").show();
                fetch_attempts = 0;
                return;
            }

            //send GET to API
            $.ajax({
                url: 'query/fetch/' + schema_id + '/' + job_id,
                dataType: "json",
                type: "GET",
                success: function (response) {
                    
                    if (response.success) {
                        print_results(response.data);       
                    }else{
                        //retry
                        fetch_results();
                    }
                }
            });
        }

        function print_results(data){

            //empty table
            $("#table-body-query-result").empty();
            //empty header
            $("#table-head-query-result").empty();

            //show results
            if (data != null && data.length > 0) {

                var row = "";
                //build header based on first row
                for (var k in data[0]) {
                    row += "<th>";
                    row += k;
                    row += "</th>";
                }
               
                //add header
                $("#table-head-query-result").append(row);                            

                //iterate data rows
                $.each(data, function (key, value) {

                    var row = "<tr>";
                    for (var k in value) {
                        row += "<td>";
                        row += value[k];
                        row += "</td>";
                    }
                    row += "</tr>";
                
                    //add row to table
                    $("#table-body-query-result").last().append(row);
                });
            }

            //enable
            $("#submit-button").removeAttr("disabled"); 
            $("#div-query-result").removeClass("hide");

            //disable ajax loader
            $('#img-ajax-loader').hide();

            //focus on results
            $('#nav-results')[0].click();

            //update scroll
            $('[data-spy="scroll"]').each(function () {
              var spy = $(this).scrollspy('refresh');
            });
        }
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
