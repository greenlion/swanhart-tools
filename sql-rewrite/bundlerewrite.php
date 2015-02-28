<?php
$srcRoot = ".";
$buildRoot = ".";
$outfile = "rewriteengine.phar";
$outhdr = "rewriteengine.h";
$from = "";

echo "Generating phar\n";
$phar = new Phar("$buildRoot/$outfile", 
  FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
  $outfile);

$files=array('parallel.php', 'rewriteengine.php', 'util.php');
foreach($files as $file) $phar[$file] = file_get_contents($file);
$phar->buildFromDirectory('parser','/[.]php$/');

$phar->setStub($phar->createDefaultStub("parallel.php"));

unset($phar);
$phar = file_get_contents($outfile);
echo "Generating header\n";

echo "Compressing phar file\n";
$phar = gzcompress($phar);

$out = "const unsigned char RWENGINE[] = {";
$data = "";
for($i=0;$i<strlen($phar);++$i) {
  if($data) $data .= ',';
  $c = $phar[$i];
  $data .= ord($c);
}
$len=strlen($data);

$out .= $data . "};\n";

$out .= "#define RWENGINE_SZ = $len;\n";

echo "Wrote " . file_put_contents($outhdr, $out) . " bytes to header file\n";
