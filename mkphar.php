<?php
/* This utility will package up the WarpSQL PHP assets
   into a single PHAR library.  It defines several
   entry points into various PHP functions to create
   an interface to the C WarpSQL code.  It also defines
   an auto-loader so that 
*/
function add_paths(&$phar, &$paths) {
	foreach($paths as $path) {
		if(!trim($path)) continue;
		echo "adding $path ...\n";
		$phar[$path] = file_get_contents($path);
	}
}

function main() {
 
	$phar = new Phar("WarpSQL.phar", 
		FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, "WarpSQL.phar") 
	        or die('Could not create parser.phar file\n');
	
	#add the SQL parser
	$x = `find PHPSQLParser -name \\*.php -type f` or die('Could not use find utility to iterate files\n');
	add_paths($phar, $x=explode("\n", $x));
	
	$phar["WarpSQL.stub.php"] = file_get_contents("WarpSQL.stub.php");
	$phar->setStub($phar->createDefaultStub("WarpSQL.stub.php"));
	echo "done.\n";


main();
exit(0);
