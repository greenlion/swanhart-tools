#include <stdlib.h>
#include <string.h>
#include <mysql.h>
#include <config.h>
#include <number.h>

#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <stdio.h>
#include <unistd.h>
#include <errno.h>

void out_of_memory() {
}

void rt_error() {
}

void rt_warn() {
}

void rt_notice() {
}

my_bool bccomp_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
  int i;
  long scale;
  if(args->arg_count != 3) {
    strcpy(message,"This function requires at least three arguments. (scale, arg, arg)"); 
    return 1;
  }

  if (args->arg_type[0] != INT_RESULT) { 
    strcpy(message, "First argument must be an INTEGER which represents the scale.  The scale is the number of places past the decimal point.  Use 0 for integers.");
    return 1;
  } else {
    scale = (long)*(args->args[0]);
  }
    
  initid->max_length = 1024 * 1024;
  for(i=1;i<args->arg_count;i++) {
    if(args->arg_type[i] != STRING_RESULT) {
      strcpy(message, "Please use strings for all arguments except the first.  Please cast columns to a char of appropriate size.");
      return 1;
    }
  }
  bc_init_numbers();

  return 0;
}

long long bccomp(UDF_INIT *initid, UDF_ARGS *args, char *is_null, char *error) {
  bc_num tmpnum1;
  bc_num tmpnum2;
  char *strval;
  char *str;
  char *p;
  if(args->args[1] == NULL || args->args[2] == NULL) {
    *is_null=1;
    return 0;
  } 
  long long retval;
  int i;

  bc_init_num(&tmpnum1);
  bc_init_num(&tmpnum2);

  str = strndup(args->args[1], args->lengths[1]);

  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum1, str, 0);
  } else {
    bc_str2num(&tmpnum1, str, strlen(p+1));
  }
  free(str);

  str = strndup(args->args[2], args->lengths[2]);

  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum2, str, 0);
  } else {
    bc_str2num(&tmpnum2, str, strlen(p+1));
  }
  free(str);

  retval = bc_compare(tmpnum1,tmpnum2);

  bc_free_num(&tmpnum1);
  bc_free_num(&tmpnum2);

  return retval;
}

my_bool bcadd_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
  int i;
  long scale;
  if(args->arg_count != 3) {
    strcpy(message,"This function requires at least three arguments. (scale, arg, arg)"); 
    return 1;
  }

  if (args->arg_type[0] != INT_RESULT) { 
    strcpy(message, "First argument must be an INTEGER which represents the scale.  The scale is the number of places past the decimal point.  Use 0 for integers.");
    return 1;
  } else {
    scale = (long)*(args->args[0]);
  }
    
  initid->max_length = 1024 * 1024;
  for(i=1;i<args->arg_count;i++) {
    if(args->arg_type[i] != STRING_RESULT) {
      strcpy(message, "Please use strings for all arguments except the first.  Please cast columns to a char of appropriate size.");
      return 1;
    }
  }
  bc_init_numbers();

  return 0;
}

void bcadd_deinit(UDF_INIT *initid) { 
  bc_deinit_numbers();
}
void bcsub_deinit(UDF_INIT *initid) { 
  bc_deinit_numbers();
}
void bcmul_deinit(UDF_INIT *initid) { 
  bc_deinit_numbers();
}
void bcdiv_deinit(UDF_INIT *initid) { 
  bc_deinit_numbers();
}
void bccomp_deinit(UDF_INIT *initid) { 
  bc_deinit_numbers();
}
void bcpow_deinit(UDF_INIT *initid) { 
  bc_deinit_numbers();
}
void bcsqrt_deinit(UDF_INIT *initid) { 
  bc_deinit_numbers();
}

char *bcadd(UDF_INIT *initid, UDF_ARGS *args, char* result, unsigned long *length, char *is_null, char *error) {
  bc_num tmpnum1;
  bc_num tmpnum2;
  bc_num resultnum;
  char *strval;
  char *str;
  char *p;
  int i;
  if(args->args[1] == NULL || args->args[2] == NULL) {
    *is_null=1;
    return result;
  } 

  bc_init_num(&tmpnum1);
  bc_init_num(&tmpnum2);
  bc_init_num(&resultnum);


  str = strndup(args->args[1], args->lengths[1]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum1, str, 0);
  } else {
    bc_str2num(&tmpnum1, str, strlen(p+1));
  }
  free(str);

  str = strndup(args->args[2], args->lengths[2]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum2, str, 0);
  } else {
    bc_str2num(&tmpnum2, str, strlen(p+1));
  }
  free(str);

  bc_add(tmpnum1,tmpnum2,&resultnum,(long)*(args->args[0])); 

  strval = bc_num2str(resultnum);
  *length = strlen(strval);
  if(*length <= 766) {
    memset(result,0,766);
    strncpy(result,strval,*length);
    free(strval);
    strval  = result;
  }

  bc_free_num(&tmpnum1);
  bc_free_num(&tmpnum2);
  bc_free_num(&resultnum);

  return strval;
}


my_bool bcpow_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
  int i;
  long scale;
  if(args->arg_count != 3) {
    strcpy(message,"This function requires at least three arguments. (scale, arg, arg)"); 
    return 1;
  }

  if (args->arg_type[0] != INT_RESULT) { 
    strcpy(message, "First argument must be an INTEGER which represents the scale.  The scale is the number of places past the decimal point.  Use 0 for integers.");
    return 1;
  } else {
    scale = (long)*(args->args[0]);
  }
    
  initid->max_length = 1024 * 1024;
  for(i=1;i<args->arg_count;i++) {
    if(args->arg_type[i] != STRING_RESULT) {
      strcpy(message, "Please use strings for all arguments except the first.  Please cast columns to a char of appropriate size.");
      return 1;
    }
  }
  bc_init_numbers();

  return 0;
}

char *bcpow(UDF_INIT *initid, UDF_ARGS *args, char* result, unsigned long *length, char *is_null, char *error) {
  bc_num tmpnum1;
  bc_num tmpnum2;
  bc_num resultnum;
  char tempstr[255];
  char *strval;
  char *str;
  char *p;
  int i;
  if(args->args[1] == NULL || args->args[2] == NULL) {
    *is_null=1;
    return result;
  } 

  bc_init_num(&tmpnum1);
  bc_init_num(&tmpnum2);
  bc_init_num(&resultnum);

  str = strndup(args->args[1], args->lengths[1]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum1, str, 0);
  } else {
    bc_str2num(&tmpnum1, str, strlen(p+1));
  }
  free(str); 

  str = strndup(args->args[2], args->lengths[2]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum2, str, 0);
  } else {
    bc_str2num(&tmpnum2, str, strlen(p+1));
  }
  free(str); 

  bc_raise(tmpnum1,tmpnum2,&resultnum,(long)*(args->args[0])); 

  strval = bc_num2str(resultnum);
  *length = strlen(strval);
  if(*length <= 766) {
    memset(result,0,766);
    strncpy(result,strval,*length);
    free(strval);
    strval  = result;
  }

  bc_free_num(&tmpnum1);
  bc_free_num(&tmpnum2);
  bc_free_num(&resultnum);

  return strval;
}


my_bool bcdiv_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
  int i;
  long scale;
  if(args->arg_count != 3) {
    strcpy(message,"This function requires at least three arguments. (scale, arg, arg)"); 
    return 1;
  }

  if (args->arg_type[0] != INT_RESULT) { 
    strcpy(message, "First argument must be an INTEGER which represents the scale.  The scale is the number of places past the decimal point.  Use 0 for integers.");
    return 1;
  } else {
    scale = (long)*(args->args[0]);
  }

  initid->max_length = 1024 * 1024;
  for(i=1;i<args->arg_count;i++) {
    if(args->arg_type[i] != STRING_RESULT) {
      strcpy(message, "Please use strings for all arguments except the first.  Please cast columns to a char of appropriate size.");
      return 1;
    }
  }
  bc_init_numbers();

  return 0;
}

char *bcdiv(UDF_INIT *initid, UDF_ARGS *args, char* result, unsigned long *length, char *is_null, char *error) {
  bc_num tmpnum1;
  bc_num tmpnum2;
  bc_num resultnum;
  char *strval;
  char *str;
  char *p;
  int i;
  if(args->args[1] == NULL || args->args[2] == NULL) {
    *is_null=1;
    return result;
  } 

  bc_init_num(&tmpnum1);
  bc_init_num(&tmpnum2);
  bc_init_num(&resultnum);

  str = strndup(args->args[1], args->lengths[1]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum1, str, 0);
  } else {
    bc_str2num(&tmpnum1, str, strlen(p+1));
  }
  free(str);

  str = strndup(args->args[2], args->lengths[2]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum2, str, 0);
  } else {
    bc_str2num(&tmpnum2, str, strlen(p+1));
  }

  if(*(str+1) == '0') {
    // return NULL on division by zero
    *is_null = 1;
    memset(result, 0, 766);
    free(str);
    return result;
  }
  free(str);

  bc_divide(tmpnum1,tmpnum2,&resultnum,(long)*(args->args[0])); 

  strval = bc_num2str(resultnum);
  *length = strlen(strval);
  if(*length <= 766) {
    memset(result,0,766);
    strncpy(result,strval,*length);
    free(strval);
    strval  = result;
  }

  bc_free_num(&tmpnum1);
  bc_free_num(&tmpnum2);
  bc_free_num(&resultnum);

  return strval;
}

my_bool bcmul_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
  int i;
  long scale;
  if(args->arg_count != 3) {
    strcpy(message,"This function requires at least three arguments. (scale, arg, arg)"); 
    return 1;
  }

  if (args->arg_type[0] != INT_RESULT) { 
    strcpy(message, "First argument must be an INTEGER which represents the scale.  The scale is the number of places past the decimal point.  Use 0 for integers.");
    return 1;
  } else {
    scale = (long)*(args->args[0]);
  }
    
  initid->max_length = 1024 * 1024;
  for(i=1;i<args->arg_count;i++) {
    if(args->arg_type[i] != STRING_RESULT) {
      strcpy(message, "Please use strings for all arguments except the first.  Please cast columns to a char of appropriate size.");
      return 1;
    }
  }
  bc_init_numbers();

  return 0;
}

char *bcmul(UDF_INIT *initid, UDF_ARGS *args, char* result, unsigned long *length, char *is_null, char *error) {
  bc_num tmpnum1;
  bc_num tmpnum2;
  bc_num resultnum;
  char *strval;
  char *str;
  char *p;
  int i;
  if(args->args[1] == NULL || args->args[2] == NULL) {
    printf("HERE X\n");
    *is_null=1;
    return result;
  } 

  bc_init_num(&tmpnum1);
  bc_init_num(&tmpnum2);
  bc_init_num(&resultnum);

  str = strndup(args->args[1], args->lengths[1]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum1, str, 0);
  } else {
    bc_str2num(&tmpnum1, str, strlen(p+1));
  }
  free(str);

  str = strndup(args->args[2], args->lengths[2]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum2, str, 0);
  } else {
    bc_str2num(&tmpnum2, str, strlen(p+1));
  }
  free(str);

  bc_multiply(tmpnum1,tmpnum2,&resultnum,(long)*(args->args[0])); 

  strval = bc_num2str(resultnum);
  *length = strlen(strval);

  if(*length <= 766) {
    memset(result,0,766);
    strncpy(result,strval,*length);
    free(strval);
    strval  = result;
  }

  bc_free_num(&tmpnum1);
  bc_free_num(&tmpnum2);
  bc_free_num(&resultnum);

  return strval;
}
my_bool bcsub_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
  int i;
  long scale;
  if(args->arg_count != 3) {
    strcpy(message,"This function requires at least three arguments. (scale, arg, arg)"); 
    return 1;
  }

  if (args->arg_type[0] != INT_RESULT) { 
    strcpy(message, "First argument must be an INTEGER which represents the scale.  The scale is the number of places past the decimal point.  Use 0 for integers.");
    return 1;
  } else {
    scale = (long)*(args->args[0]);
  }
    
  initid->max_length = 1024 * 1024;
  for(i=1;i<args->arg_count;i++) {
    if(args->arg_type[i] != STRING_RESULT) {
      strcpy(message, "Please use strings for all arguments except the first.  Please cast columns to a char of appropriate size.");
      return 1;
    }
  }
  bc_init_numbers();

  return 0;
}

char *bcsub(UDF_INIT *initid, UDF_ARGS *args, char* result, unsigned long *length, char *is_null, char *error) {
  bc_num tmpnum1;
  bc_num tmpnum2;
  bc_num resultnum;
  char *strval;
  char *str;
  char *p;
  int i;
  if(args->args[1] == NULL || args->args[2] == NULL) {
    *is_null=1;
    return result;
  } 

  bc_init_num(&tmpnum1);
  bc_init_num(&tmpnum2);
  bc_init_num(&resultnum);

  str = strndup(args->args[1], args->lengths[1]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum1, str, 0);
  } else {
    bc_str2num(&tmpnum1, str, strlen(p+1));
  }
  free(str);

  str = strndup(args->args[2], args->lengths[2]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&tmpnum2, str, 0);
  } else {
    bc_str2num(&tmpnum2, str, strlen(p+1));
  }
  free(str);

  bc_sub(tmpnum1,tmpnum2,&resultnum,(long)*(args->args[0])); 

  strval = bc_num2str(resultnum);
  *length = strlen(strval);
  if(*length <= 766) {
    memset(result,0,766);
    strncpy(result,strval,*length);
    free(strval);
    strval  = result;
  }

  bc_free_num(&tmpnum1);
  bc_free_num(&tmpnum2);
  bc_free_num(&resultnum);

  return strval;
}

my_bool bcsum_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
  
  int i;
  long scale;
  bc_init_numbers();
  bc_num num;
  initid->maybe_null = 1;
  initid->max_length = 1024 * 1024;
  if(args->arg_count < 2) {
    strcpy(message,"This function requires at least two arguments.  bcsum(scale, arg, ...)");
    return 1;
  }
  if (args->arg_type[0] != INT_RESULT) { 
    strcpy(message, "First argument must be an INTEGER which represents the scale.  The scale is the number of places past the decimal point.  Use 0 for integers.");
    return 1;
  } else {
    scale = (long)*(args->args[0]);
  }

  for(i=1;i<args->arg_count;i++) {
    if(args->arg_type[i] != STRING_RESULT) {
      strcpy(message, "Please use strings for all arguments except the first.  Please cast columns to a char of appropriate size.");
      return 1;
    }
  }

  initid->ptr = NULL;
  return 0;

} 

void bcsum_deinit(UDF_INIT *initid) {
  if(initid->ptr != NULL) { 
    bc_free_num((bc_num*)(initid->ptr));
    free(initid->ptr);
  }
  bc_deinit_numbers();
}

void bcsum_clear(UDF_INIT *initid, char *is_null, char *error) {
  if(initid->ptr == NULL) {
    *is_null = 1; 
  } else {
    bc_free_num((bc_num*)(initid->ptr));
    free(initid->ptr);
    initid->ptr = NULL;
  }
}

void bcsum_add(UDF_INIT *initid, UDF_ARGS *args, char *is_null, char *error) {
  bc_num tmpnum1;
  bc_num *tmpptr;
  bc_num dest;
  int i;
  long scale;

  if(args->args[1] == NULL || args->args[2] == NULL) {
    *is_null=1;
    return;
  } 

  scale = atoi(args->args[0]);
  
  for(i=1;i<args->arg_count;i++) {
    if(strcmp(args->args[i], "") == 0) { 
      continue;
    } else {
      bc_init_num(&tmpnum1);
      bc_str2num(&tmpnum1, args->args[i], scale);
      if(initid->ptr == NULL) {
        /* allocate memory for storage*/
        tmpptr = (bc_num*)malloc(sizeof(bc_num));
        *tmpptr = bc_new_num (1,0); 
        initid->ptr = (char*)tmpptr;
      } else {
        tmpptr = (bc_num*)(initid->ptr);
      }
    }
    bc_add(tmpnum1,*tmpptr, (bc_num*)(initid->ptr), scale);
  }

  bc_free_num(&tmpnum1);
}


char *bcsum(UDF_INIT *initid, UDF_ARGS *args, char* result, unsigned long *length, char *is_null, char *error) {
  char *strval;
  if(initid->ptr != NULL) {
    strval = bc_num2str((bc_num)*((bc_num*)(initid->ptr)));
  } else {
    *is_null=1;
    return result;  
  }

  *length = strlen(strval);
  if(*length == 0) {
    *is_null=1; 
    return result;
  }

  if(*length > 766) {
    // use our pointer
    return(strval);
  } else {
    // use the preallocated space
    memset(result,0,766);
    strncpy(result, strval,*length);
    free(strval);
    return result;
  }
}

void bcsum_reset(UDF_INIT *initid, UDF_ARGS *args, char *is_null, char *error)
{
  (void) is_null;

  bcsum_clear(initid, is_null, error);
  bcsum_add(initid, args, is_null, error);
}

my_bool bcsqrt_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
  int i;
  long scale;
  if(args->arg_count != 2) {
    strcpy(message,"This function requires two arguments. (scale, arg)"); 
    return 1;
  }

  if (args->arg_type[0] != INT_RESULT) { 
    strcpy(message, "First argument must be an INTEGER which represents the scale.  The scale is the number of places past the decimal point.  Use 0 for integers.");
    return 1;
  } else {
    scale = (long)*(args->args[0]);
  }
    
  initid->max_length = 1024 * 1024;
  for(i=1;i<args->arg_count;i++) {
    if(args->arg_type[i] != STRING_RESULT) {
      strcpy(message, "Please use strings for all arguments except the first.  Please cast columns to a char of appropriate size.");
      return 1;
    }
  }
  bc_init_numbers();

  return 0;
}

char *bcsqrt(UDF_INIT *initid, UDF_ARGS *args, char* rslt, unsigned long *length, char *is_null, char *error) {
  bc_num result;
  char *strval;
  char *str;
  char *p;
  int i;
  int need_free = 0;
  if(args->args[1] == NULL || args->args[2] == NULL) {
    *is_null=1;
    return rslt;
  } 
  bc_init_num(&result);

  str = strndup(args->args[1], args->lengths[1]);
  if (!(p = strchr(str, '.'))) {
    bc_str2num(&result, str, 0);
  } else {
    bc_str2num(&result, str, strlen(p+1));
  }
  free(str);

  if (bc_sqrt(&result, *(args->args[0])) == 0) {
    *is_null = 1; 
    return rslt;
  }

  strval = bc_num2str(result);
  *length = strlen(strval);
  if(*length <= 766) {
    memset(rslt,0,766);
    strncpy(rslt,strval,*length);
    free(strval);
    strval  = rslt;
  }

  bc_free_num(&result);

  return strval;
}
