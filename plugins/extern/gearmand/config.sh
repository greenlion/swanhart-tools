export PATH=../build_tools/:$PATH
./configure --with-mysql=no --with-drizzle=no --disable-libdrizzle --with-openssl=/tmp $*
