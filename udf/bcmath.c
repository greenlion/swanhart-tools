#include <stdlib.h>
#include <string.h>
#include <mysql.h>
#include <config.h>
#include <number.h>
/* 
Convert to bc_num detecting scale */
/*static void strtonum(const bc_num* num, const char* str) {
	char *p;

	if (!(p = strchr(str, '.'))) {
		bc_str2num(num, str, 0);
		return;
	}

	bc_str2num(num, str, strlen(p+1));
}*/

void out_of_memory() {
}

void rt_error() {
}

void rt_warn() {
}

void rt_notice() {
}


my_bool bcsum_init(UDF_INIT *initid, UDF_ARGS *args, char* message) {
	
	int i;
	long long scale;
	char *result;
	bc_init_numbers();
	bc_num num;
	initid->ptr = NULL;
	initid->maybe_null = 1;
	initid->max_length = 1024 * 1024;
	if(args->arg_count < 2) {
		strcpy(message,"This function requires at least two arguments.  bc_sum(scale, arg, ...");
		return 1;
	}
	if (args->arg_type[0] != INT_RESULT) { 
		strcpy(message, "First argument must be an INTEGER which represents the scale of the operation!");
		return 1;
	} else {
 		scale = (long)*(args->args[0]);
	}

	for(i=0;i<args->arg_count;++i) {
		if(args->arg_type[i] != STRING_RESULT) {
			args->arg_type[i] = STRING_RESULT; // make MySQL convert the arg to string for us
		}
	}

	bc_init_num(&num);
	initid->ptr = (char *)&num;
	return 0;

} 

void bcsum_deinit(UDF_INIT *initid) {
	// if(initid->ptr != NULL) bc_free_num((bc_num*)(initid->ptr));
}

void bcsum_clear(UDF_INIT *initid, char *is_null, char *error) {
	if(initid->ptr == NULL) {
		*is_null = 1;	
	} else {
		bc_free_num((bc_num*)(initid->ptr));
		initid->ptr = NULL;
	}
}

void bcsum_add(UDF_INIT *initid, UDF_ARGS *args, char *is_null, char *error) {
	bc_num tmpnum1;
	bc_num *tmpptr;
        bc_num dest;
	int i;
	long scale;


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


char *bcsum(UDF_INIT *initid, UDF_ARGS *args, char* result, unsigned long length, char *is_null, char *error) {
	char *strval;
	if(initid->ptr != NULL) {
		strval = bc_num2str((bc_num)*((bc_num*)(initid->ptr)));
	} else {
		*is_null=1;
		return result;	
	}

	length = strlen(strval);
	if(length == 0) {
		*is_null=1; 
		return result;
	}

	if(length > 766) {
		// use our pointer
		return(strval);
	} else {
		// use the preallocated space
		memset(result,0,766);
		strcpy(result, strval);
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

/*
PHP_FUNCTION(bcsub)
{
	char *left, *right;
	int left_len, right_len;
	long scale_param = 0;
	bc_num first, second, result;
	int scale = BCG(bc_precision), argc = ZEND_NUM_ARGS();

	if (zend_parse_parameters(argc, "ss|l", &left, &left_len, &right, &right_len, &scale_param) == FAILURE) {
		return;
	}
	
	if (argc == 3) {
		scale = (int) ((int)scale_param < 0) ? 0 : scale_param;
	}

	bc_init_num(&first);
	bc_init_num(&second);
	bc_init_num(&result);
	bc_str2num(&first, left);
	bc_str2num(&second, right);
	bc_sub (first, second, &result, scale);

	if (result->n_scale > scale) {
		result->n_scale = scale;
	}

	Z_STRVAL_P(return_value) = bc_num2str(result);
	Z_STRLEN_P(return_value) = strlen(Z_STRVAL_P(return_value));
	Z_TYPE_P(return_value) = IS_STRING;
	bc_free_num(&first);
	bc_free_num(&second);
	bc_free_num(&result);
	return;
}
PHP_FUNCTION(bcmul)
{
	char *left, *right;
	int left_len, right_len;
	long scale_param = 0;
	bc_num first, second, result;
	int scale = BCG(bc_precision), argc = ZEND_NUM_ARGS();

	if (zend_parse_parameters(argc, "ss|l", &left, &left_len, &right, &right_len, &scale_param) == FAILURE) {
		return;
	}
	
	if (argc == 3) {
		scale = (int) ((int)scale_param < 0) ? 0 : scale_param;
	}
	
	bc_init_num(&first);
	bc_init_num(&second);
	bc_init_num(&result);
	strtonum(&first, left);
	strtonum(&second, right);
	bc_multiply (first, second, &result, scale);

	if (result->n_scale > scale) {
		result->n_scale = scale;
	}

	Z_STRVAL_P(return_value) = bc_num2str(result);
	Z_STRLEN_P(return_value) = strlen(Z_STRVAL_P(return_value));
	Z_TYPE_P(return_value) = IS_STRING;
	bc_free_num(&first);
	bc_free_num(&second);
	bc_free_num(&result);
	return;
}
PHP_FUNCTION(bcdiv)
{
	char *left, *right;
	int left_len, right_len;
	long scale_param = 0;
	bc_num first, second, result;
	int scale = BCG(bc_precision), argc = ZEND_NUM_ARGS();

	if (zend_parse_parameters(argc, "ss|l", &left, &left_len, &right, &right_len, &scale_param) == FAILURE) {
		return;
	}
	
	if (argc == 3) {
		scale = (int) ((int)scale_param < 0) ? 0 : scale_param;
	}
	
	bc_init_num(&first);
	bc_init_num(&second);
	bc_init_num(&result);
	strtonum(&first, left);
	strtonum(&second, right);

	switch (bc_divide(first, second, &result, scale)) {
		case 0: // OK 
			if (result->n_scale > scale) {
				result->n_scale = scale;
			}
			Z_STRVAL_P(return_value) = bc_num2str(result);
			Z_STRLEN_P(return_value) = strlen(Z_STRVAL_P(return_value));
			Z_TYPE_P(return_value) = IS_STRING;
			break;
		case -1: // division by zero 
			php_error_docref(NULL, E_WARNING, "Division by zero");
			break;
	}

	bc_free_num(&first);
	bc_free_num(&second);
	bc_free_num(&result);
	return;
}
PHP_FUNCTION(bcmod)
{
	char *left, *right;
	int left_len, right_len;
	bc_num first, second, result;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "ss", &left, &left_len, &right, &right_len) == FAILURE) {
		return;
	}
	
	bc_init_num(&first);
	bc_init_num(&second);
	bc_init_num(&result);
	bc_str2num(&first, left, 0);
	bc_str2num(&second, right, 0);
	
	switch (bc_modulo(first, second, &result, 0)) {
		case 0:
			Z_STRVAL_P(return_value) = bc_num2str(result);
			Z_STRLEN_P(return_value) = strlen(Z_STRVAL_P(return_value));
			Z_TYPE_P(return_value) = IS_STRING;
			break;
		case -1:
			php_error_docref(NULL, E_WARNING, "Division by zero");
			break;
	}
	
	bc_free_num(&first);
	bc_free_num(&second);
	bc_free_num(&result);
	return;
}
PHP_FUNCTION(bcpowmod)
{
	char *left, *right, *modulous;
	int left_len, right_len, modulous_len;
	bc_num first, second, mod, result;
	long scale = BCG(bc_precision);
	int scale_int;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "sss|l", &left, &left_len, &right, &right_len, &modulous, &modulous_len, &scale) == FAILURE) {
		return;
	}

	bc_init_num(&first);
	bc_init_num(&second);
	bc_init_num(&mod);
	bc_init_num(&result);
	strtonum(&first, left);
	strtonum(&second, right);
	strtonum(&mod, modulous);

	scale_int = (int) ((int)scale < 0) ? 0 : scale;

	if (bc_raisemod(first, second, mod, &result, scale_int) != -1) {
		if (result->n_scale > scale) {
			result->n_scale = scale;
		}
		Z_STRVAL_P(return_value) = bc_num2str(result);
		Z_STRLEN_P(return_value) = strlen(Z_STRVAL_P(return_value));
		Z_TYPE_P(return_value) = IS_STRING;
	} else {
		RETVAL_FALSE;
	}
	
	bc_free_num(&first);
	bc_free_num(&second);
	bc_free_num(&mod);
	bc_free_num(&result);
	return;
}
PHP_FUNCTION(bcpow)
{
	char *left, *right;
	int left_len, right_len;
	long scale_param = 0;
	bc_num first, second, result;
	int scale = BCG(bc_precision), argc = ZEND_NUM_ARGS();

	if (zend_parse_parameters(argc, "ss|l", &left, &left_len, &right, &right_len, &scale_param) == FAILURE) {
		return;
	}
	
	if (argc == 3) {
		scale = (int) ((int)scale_param < 0) ? 0 : scale_param;
	}

	bc_init_num(&first);
	bc_init_num(&second);
	bc_init_num(&result);
	strtonum(&first, left);
	strtonum(&second, right);
	bc_raise (first, second, &result, scale);

	if (result->n_scale > scale) {
		result->n_scale = scale;
	}

	Z_STRVAL_P(return_value) = bc_num2str(result);
	Z_STRLEN_P(return_value) = strlen(Z_STRVAL_P(return_value));
	Z_TYPE_P(return_value) = IS_STRING;
	bc_free_num(&first);
	bc_free_num(&second);
	bc_free_num(&result);
	return;
}
PHP_FUNCTION(bcsqrt)
{
	char *left;
	int left_len;
	long scale_param = 0;
	bc_num result;
	int scale = BCG(bc_precision), argc = ZEND_NUM_ARGS();

	if (zend_parse_parameters(argc, "s|l", &left, &left_len, &scale_param) == FAILURE) {
		return;
	}
	
	if (argc == 2) {
		scale = (int) ((int)scale_param < 0) ? 0 : scale_param;
	}

	bc_init_num(&result);
	strtonum(&result, left);
	
	if (bc_sqrt (&result, scale) != 0) {
		if (result->n_scale > scale) {
			result->n_scale = scale;
		}
		Z_STRVAL_P(return_value) = bc_num2str(result);
		Z_STRLEN_P(return_value) = strlen(Z_STRVAL_P(return_value));
		Z_TYPE_P(return_value) = IS_STRING;
	} else {
		php_error_docref(NULL, E_WARNING, "Square root of negative number");
	}

	bc_free_num(&result);
	return;
}
PHP_FUNCTION(bccomp)
{
	char *left, *right;
	int left_len, right_len;
	long scale_param = 0;
	bc_num first, second;
	int scale = BCG(bc_precision), argc = ZEND_NUM_ARGS();

	if (zend_parse_parameters(argc, "ss|l", &left, &left_len, &right, &right_len, &scale_param) == FAILURE) {
		return;
	}
	
	if (argc == 3) {
		scale = (int) ((int)scale_param < 0) ? 0 : scale_param;
	}

	bc_init_num(&first);
	bc_init_num(&second);

	bc_str2num(&first, left, scale);
	bc_str2num(&second, right, scale);
	Z_LVAL_P(return_value) = bc_compare(first, second);
	Z_TYPE_P(return_value) = IS_LONG;

	bc_free_num(&first);
	bc_free_num(&second);
	return;
}

PHP_FUNCTION(bcscale)
{
	long new_scale;
	
	if (zend_parse_parameters(ZEND_NUM_ARGS(), "l", &new_scale) == FAILURE) {
		return;
	}

	BCG(bc_precision) = ((int)new_scale < 0) ? 0 : new_scale;

	RETURN_TRUE;
}

#endif
*/
/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
