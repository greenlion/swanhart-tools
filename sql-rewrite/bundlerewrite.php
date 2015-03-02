<?php
$srcRoot = ".";
$buildRoot = ".";
$outfile = "rewriteengine.phar";
$b64 = "rewrite.cpp";

echo "Generating phar\n";
$phar = new Phar("$buildRoot/$outfile", 
  FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
  $outfile);

$phar->buildFromDirectory('.','/[.]php$/');

$phar->setStub($phar->createDefaultStub("parser/parallel.php"));
$phar = file_get_contents($outfile);
require($outfile);

$data = base64_encode($phar);
$out = "extern const char rwenginephar[];\nconst char rwenginephar[] =\n\"$data\";";
file_put_contents($b64, $out);
