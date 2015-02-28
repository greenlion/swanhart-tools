<?php
$srcRoot = ".";
$buildRoot = ".";
$outfile = "rewriteengine.phar";
$from = "";

$phar = new Phar("$buildRoot/$outfile", 
  FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
  $outfile);

$files=array('parallel.php', 'rewriteengine.php', 'util.php');
foreach($files as $file) $phar[$file] = file_get_contents($file);
$phar->buildFromDirectory('parser','/[.]php$/');

$phar->setStub($phar->createDefaultStub("parallel.php"));
