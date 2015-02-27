
const char init_php_script[]=


/* This function hooks rewrite related PHP output to the general log*/ 
void php_log_output(const char *str){
	general_log_print(current_thd, COM_QUERY, "%s", str);
}

/*
bool php_rewrite_engine(php *p, char* sql, THD *thd, php_array *out_plan) {
  std::string s = "set_include_path('";
  s += std::string(php_scripts_dir);
  s += "');";
  php_ret retval = p->eval_string("%s",s.c_str());
  retval =  p->eval_string("%s", init_php_script);
  if(SUCCESS != retval) printf("%s", "Could not eval PHP!\n");

//  php_array settings;
//   // php_array process_info;
//   // print_r(EXEC_RULES($sql, $table_info, $process_info,$SETTINGS,'RewriteParallelRule'));
     return false;
}
*/
    php php_ctx(true);
    php_ctx.set_message_function(php_log_output);
    php_ctx.set_output_function(php_log_output);
    php_ctx.set_error_function(php_log_output);
    php_array *plan;
    bool ok =  php_rewrite_engine(&php_ctx,sql,thd, plan); 
    if(!ok) {
      return_value= dispatch_command(command, thd, sql, (uint) (packet_length-1));
    } else {
      /* HANDLE REWRITE HERE */
      php_ctx.call_void("print_r", "s", sql);
      php_ctx.call_void("print_r", "a", plan);
      return_value= dispatch_command(command, thd, sql, (uint) (packet_length-1));
    }

