<?php
$srcRoot = ".";
$buildRoot = ".";
$outfile = "rewriteengine.phar";
$uu = "rewrite.cpp";

echo "Generating phar\n";
$phar = new Phar("$buildRoot/$outfile", 
  FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
  $outfile);

$files=array('parallel.php', 'rewriteengine.php', 'util.php');
foreach($files as $file) $phar[$file] = file_get_contents($file);
$phar->buildFromDirectory('parser','/[.]php$/');

$phar->setStub($phar->createDefaultStub("parallel.php"));
$phar = trim(file_get_contents($outfile));
$data = trim(base64_encode($phar));
$data = explode("\n", $data);
$out = "";
foreach($data as $line) {
  $out .= base64_encode($line);
}

$out = "const char rwenginephar[] =\n\"$out\";";
file_put_contents($uu, trim($out));
