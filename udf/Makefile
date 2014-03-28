all:
	gcc -o udf_bcmath.so number.c bcmath.c `/usr/local/mysql/bin/mysql_config --cflags`  -shared -fPIC -DSTDC_HEADERS -I h

install:
	cp *.so `/usr/local/mysql/bin/mysql_config --plugindir`
